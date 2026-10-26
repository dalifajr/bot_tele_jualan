package com.remote.agent;

import javax.net.ssl.SSLSocketFactory;
import java.io.*;
import java.net.Socket;
import java.net.URI;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.util.Base64;

/**
 * AgentWebSocketClient: Client WebSocket Mandiri (RFC 6455) Tanpa Dependency Eksternal
 * Mendukung WS/WSS, Text Control Message, dan Binary Streaming Video Frame.
 */
public class AgentWebSocketClient {

    public interface MessageListener {
        void onConnected();
        void onTextMessage(String text);
        void onDisconnected(String reason);
    }

    private final URI serverUri;
    private final MessageListener listener;
    private Socket socket;
    private OutputStream out;
    private InputStream in;
    private volatile boolean isConnected = false;
    private Thread readThread;

    public AgentWebSocketClient(String url, MessageListener listener) throws Exception {
        this.serverUri = new URI(url);
        this.listener = listener;
    }

    public synchronized void connect() throws Exception {
        String host = serverUri.getHost();
        int port = serverUri.getPort();
        boolean isSecure = "wss".equalsIgnoreCase(serverUri.getScheme());

        if (port == -1) {
            port = isSecure ? 443 : 80;
        }

        if (isSecure) {
            socket = SSLSocketFactory.getDefault().createSocket(host, port);
        } else {
            socket = new Socket(host, port);
        }

        socket.setTcpNoDelay(true); // Prioritaskan latensi ultra-rendah
        socket.setKeepAlive(true);

        out = socket.getOutputStream();
        in = new BufferedInputStream(socket.getInputStream());

        doHandshake(host, port);
        isConnected = true;

        if (listener != null) {
            listener.onConnected();
        }

        startReaderThread();
    }

    private void doHandshake(String host, int port) throws Exception {
        byte[] nonce = new byte[16];
        new SecureRandom().nextBytes(nonce);
        String secKey;
        try {
            secKey = Base64.getEncoder().encodeToString(nonce);
        } catch (Throwable t) {
            secKey = "dGhlIHNhbXBsZSBub25jZQ==";
        }

        String path = serverUri.getPath();
        if (path == null || path.isEmpty()) path = "/";
        if (serverUri.getQuery() != null) path += "?" + serverUri.getQuery();

        String request = "GET " + path + " HTTP/1.1\r\n" +
                "Host: " + host + (port != 80 && port != 443 ? ":" + port : "") + "\r\n" +
                "Upgrade: websocket\r\n" +
                "Connection: Upgrade\r\n" +
                "Sec-WebSocket-Key: " + secKey + "\r\n" +
                "Sec-WebSocket-Version: 13\r\n" +
                "X-Device-Id: " + DeviceConfig.deviceId + "\r\n" +
                "X-Device-Token: " + DeviceConfig.deviceToken + "\r\n" +
                "\r\n";

        out.write(request.getBytes(StandardCharsets.UTF_8));
        out.flush();

        // Baca response status HTTP
        ByteArrayOutputStream lineBuffer = new ByteArrayOutputStream();
        int b;
        boolean headerEnded = false;
        String statusLine = null;

        while ((b = in.read()) != -1) {
            lineBuffer.write(b);
            byte[] bytes = lineBuffer.toByteArray();
            int len = bytes.length;
            if (len >= 4 && bytes[len - 4] == '\r' && bytes[len - 3] == '\n' && bytes[len - 2] == '\r' && bytes[len - 1] == '\n') {
                headerEnded = true;
                break;
            }
        }

        if (!headerEnded) {
            throw new IOException("Handshake gagal: Server menutup koneksi sebelum header selesai.");
        }

        String fullHeader = lineBuffer.toString(StandardCharsets.UTF_8.name());
        if (!fullHeader.contains("101 Switching Protocols")) {
            throw new IOException("Handshake ditolak server: " + fullHeader.split("\r\n")[0]);
        }
    }

    private void startReaderThread() {
        readThread = new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    while (isConnected && !socket.isClosed()) {
                    int b1 = in.read();
                    if (b1 == -1) break;

                    boolean fin = (b1 & 0x80) != 0;
                    int opcode = b1 & 0x0F;

                    int b2 = in.read();
                    if (b2 == -1) break;

                    boolean masked = (b2 & 0x80) != 0;
                    long payloadLength = b2 & 0x7F;

                    if (payloadLength == 126) {
                        payloadLength = ((in.read() & 0xFF) << 8) | (in.read() & 0xFF);
                    } else if (payloadLength == 127) {
                        payloadLength = 0;
                        for (int i = 0; i < 8; i++) {
                            payloadLength = (payloadLength << 8) | (in.read() & 0xFF);
                        }
                    }

                    byte[] mask = null;
                    if (masked) {
                        mask = new byte[4];
                        for (int i = 0; i < 4; i++) mask[i] = (byte) in.read();
                    }

                    byte[] payload = new byte[(int) payloadLength];
                    int readTotal = 0;
                    while (readTotal < payloadLength) {
                        int read = in.read(payload, readTotal, (int) payloadLength - readTotal);
                        if (read == -1) break;
                        readTotal += read;
                    }

                    if (masked && mask != null) {
                        for (int i = 0; i < payload.length; i++) {
                            payload[i] = (byte) (payload[i] ^ mask[i % 4]);
                        }
                    }

                    if (opcode == 0x01) { // Text Message
                        String text = new String(payload, StandardCharsets.UTF_8);
                        if (listener != null) listener.onTextMessage(text);
                    } else if (opcode == 0x08) { // Close
                        break;
                    } else if (opcode == 0x09) { // Ping -> Respond Pong
                        sendFrame(0x0A, payload, 0, payload.length);
                    }
                }
            } catch (Exception e) {
                // Koneksi terputus
            } finally {
                disconnect("Koneksi socket terputus");
            }
        }}, "AgentWebSocketReader");
        readThread.start();
    }

    /**
     * Mengirim Pesan Teks (JSON Control / Telemetry)
     */
    public synchronized void sendText(String text) {
        if (!isConnected || out == null) return;
        byte[] data = text.getBytes(StandardCharsets.UTF_8);
        try {
            sendFrame(0x01, data, 0, data.length);
        } catch (Exception e) {
            disconnect(e.getMessage());
        }
    }

    /**
     * Mengirim Frame Biner Video H.264
     */
    public synchronized void sendBinary(byte[] data, int offset, int length) {
        if (!isConnected || out == null) return;
        try {
            sendFrame(0x02, data, offset, length);
        } catch (Exception e) {
            disconnect(e.getMessage());
        }
    }

    private void sendFrame(int opcode, byte[] data, int offset, int length) throws IOException {
        // Client wajib menerapkan MASKING pada frame keluar sesuai RFC 6455
        byte[] mask = new byte[4];
        new SecureRandom().nextBytes(mask);

        out.write(0x80 | (opcode & 0x0F)); // FIN = 1

        if (length <= 125) {
            out.write(0x80 | length); // Mask = 1
        } else if (length <= 65535) {
            out.write(0x80 | 126);
            out.write((length >> 8) & 0xFF);
            out.write(length & 0xFF);
        } else {
            out.write(0x80 | 127);
            for (int i = 7; i >= 0; i--) {
                out.write((int) ((length >> (8 * i)) & 0xFF));
            }
        }

        out.write(mask);

        byte[] maskedData = new byte[length];
        for (int i = 0; i < length; i++) {
            maskedData[i] = (byte) (data[offset + i] ^ mask[i % 4]);
        }
        out.write(maskedData);
        out.flush();
    }

    public synchronized void disconnect(String reason) {
        if (!isConnected) return;
        isConnected = false;
        try {
            if (socket != null) socket.close();
        } catch (Exception ignored) {}
        if (listener != null) {
            listener.onDisconnected(reason);
        }
    }

    public boolean isConnected() {
        return isConnected;
    }
}
