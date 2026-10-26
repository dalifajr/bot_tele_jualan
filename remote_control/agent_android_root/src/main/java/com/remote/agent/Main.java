package com.remote.agent;

import android.view.MotionEvent;

import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Main: Entry Point Daemon Agen Android Root
 * Dijalankan melalui `app_process` dengan UID 0 (root).
 */
public class Main {

    private static AgentWebSocketClient wsClient;
    private static ScreenEncoder screenEncoder;
    private static InputDispatcher inputDispatcher;

    public static void main(String[] args) {
        System.out.println("=================================================");
        System.out.println("   ANDROID REMOTE CONTROL DAEMON (ROOT LEVEL)    ");
        System.out.println("=================================================");

        parseArguments(args);
        detectDisplayResolution();

        inputDispatcher = new InputDispatcher();

        // Siklus hidup daemon utama (Auto-reconnect loop)
        while (DeviceConfig.isRunning) {
            try {
                System.out.println("[Agent] Menghubungkan ke Relay Server: " + DeviceConfig.serverWsUrl + "...");
                
                wsClient = new AgentWebSocketClient(DeviceConfig.serverWsUrl, new AgentWebSocketClient.MessageListener() {
                    @Override
                    public void onConnected() {
                        System.out.println("[Agent] Berhasil terhubung ke Relay Server!");
                        sendRegistrationPayload();
                    }

                    @Override
                    public void onTextMessage(String text) {
                        handleIncomingControlMessage(text);
                    }

                    @Override
                    public void onDisconnected(String reason) {
                        System.out.println("[Agent] Terputus dari server: " + reason);
                        stopStreaming();
                    }
                });

                wsClient.connect();

                // Tetap hidup selama socket terkoneksi
                while (wsClient.isConnected()) {
                    Thread.sleep(1000);
                }

            } catch (Exception e) {
                System.err.println("[Agent] Gagal terhubung: " + e.getMessage());
            }

            // Tunggu 5 detik sebelum mencoba menghubungkan ulang (Backoff retry)
            try {
                System.out.println("[Agent] Mencoba menghubungkan ulang dalam 5 detik...");
                Thread.sleep(5000);
            } catch (InterruptedException ignored) {}
        }
    }

    /**
     * Memproses argumen command line jika ada parameter kustom saat dijalankan
     */
    private static void parseArguments(String[] args) {
        for (int i = 0; i < args.length; i++) {
            if ("--url".equals(args[i]) && i + 1 < args.length) {
                DeviceConfig.serverWsUrl = args[++i];
            } else if ("--id".equals(args[i]) && i + 1 < args.length) {
                DeviceConfig.deviceId = args[++i];
            } else if ("--token".equals(args[i]) && i + 1 < args.length) {
                DeviceConfig.deviceToken = args[++i];
            } else if ("--width".equals(args[i]) && i + 1 < args.length) {
                DeviceConfig.targetWidth = Integer.parseInt(args[++i]);
            } else if ("--height".equals(args[i]) && i + 1 < args.length) {
                DeviceConfig.targetHeight = Integer.parseInt(args[++i]);
            }
        }
    }

    /**
     * Mendeteksi resolusi fisik layar HP via shell `wm size`
     */
    private static void detectDisplayResolution() {
        try {
            String sizeOutput = ShellExecutor.executeCommandSync("wm size");
            Pattern pattern = Pattern.compile("Physical size:\\s*(\\d+)x(\\d+)");
            Matcher matcher = pattern.matcher(sizeOutput);
            if (matcher.find()) {
                int physicalW = Integer.parseInt(matcher.group(1));
                int physicalH = Integer.parseInt(matcher.group(2));

                // Pertahankan rasio aspek untuk target streaming
                if (physicalW > 0 && physicalH > 0) {
                    float ratio = (float) physicalH / (float) physicalW;
                    DeviceConfig.targetWidth = 720;
                    DeviceConfig.targetHeight = (int) (720 * ratio);
                    // Pastikan genap untuk encoder H.264
                    if (DeviceConfig.targetHeight % 2 != 0) DeviceConfig.targetHeight++;
                    System.out.println("[Agent] Deteksi Layar Fisik: " + physicalW + "x" + physicalH + " -> Stream Target: " + DeviceConfig.targetWidth + "x" + DeviceConfig.targetHeight);
                }
            }
        } catch (Exception e) {
            System.out.println("[Agent] Menggunakan resolusi default: " + DeviceConfig.targetWidth + "x" + DeviceConfig.targetHeight);
        }
    }

    /**
     * Mengirim payload registrasi identitas perangkat ke Relay Server
     */
    private static void sendRegistrationPayload() {
        String model = ShellExecutor.executeCommandSync("getprop ro.product.model");
        String androidVer = ShellExecutor.executeCommandSync("getprop ro.build.version.release");

        String registerJson = "{"
                + "\"type\":\"register\","
                + "\"device_id\":\"" + DeviceConfig.deviceId + "\","
                + "\"device_token\":\"" + DeviceConfig.deviceToken + "\","
                + "\"device_name\":\"" + model + " (Android " + androidVer + ")\","
                + "\"screen_width\":" + DeviceConfig.targetWidth + ","
                + "\"screen_height\":" + DeviceConfig.targetHeight
                + "}";

        wsClient.sendText(registerJson);
    }

    /**
     * Mengurai dan mengeksekusi pesan kontrol dari Controller (Web Admin / Android User)
     */
    private static void handleIncomingControlMessage(String json) {
        try {
            if (json.contains("\"type\":\"start_stream\"")) {
                startStreaming();
            } else if (json.contains("\"type\":\"stop_stream\"")) {
                stopStreaming();
            } else if (json.contains("\"type\":\"touch\"")) {
                parseAndInjectTouch(json);
            } else if (json.contains("\"type\":\"key\"")) {
                parseAndInjectKey(json);
            } else if (json.contains("\"type\":\"swipe\"")) {
                parseAndInjectSwipe(json);
            } else if (json.contains("\"type\":\"shell\"")) {
                parseAndExecuteShell(json);
            }
        } catch (Exception e) {
            System.err.println("[Agent] Gagal memproses perintah: " + e.getMessage());
        }
    }

    private static synchronized void startStreaming() {
        if (DeviceConfig.isStreaming) return;
        DeviceConfig.isStreaming = true;

        screenEncoder = new ScreenEncoder(
                DeviceConfig.targetWidth,
                DeviceConfig.targetHeight,
                DeviceConfig.bitRate,
                DeviceConfig.frameRate,
                (data, offset, length, isKeyFrame) -> {
                    if (wsClient != null && wsClient.isConnected()) {
                        wsClient.sendBinary(data, offset, length);
                    }
                }
        );
        screenEncoder.start();
        System.out.println("[Agent] Sesi streaming video DIAKTIFKAN oleh Controller.");
    }

    private static synchronized void stopStreaming() {
        if (!DeviceConfig.isStreaming) return;
        DeviceConfig.isStreaming = false;
        if (screenEncoder != null) {
            screenEncoder.stop();
            screenEncoder = null;
        }
        System.out.println("[Agent] Sesi streaming video DINONAKTIFKAN (Sleep Mode).");
    }

    private static void parseAndInjectTouch(String json) {
        String actionStr = extractJsonValue(json, "action");
        float x = Float.parseFloat(extractJsonValue(json, "x"));
        float y = Float.parseFloat(extractJsonValue(json, "y"));

        int action = MotionEvent.ACTION_MOVE;
        if ("down".equalsIgnoreCase(actionStr)) action = MotionEvent.ACTION_DOWN;
        else if ("up".equalsIgnoreCase(actionStr)) action = MotionEvent.ACTION_UP;

        inputDispatcher.injectTouch(action, x, y);
    }

    private static void parseAndInjectKey(String json) {
        int keyCode = Integer.parseInt(extractJsonValue(json, "code"));
        inputDispatcher.injectKey(keyCode);
    }

    private static void parseAndInjectSwipe(String json) {
        float startX = Float.parseFloat(extractJsonValue(json, "startX"));
        float startY = Float.parseFloat(extractJsonValue(json, "startY"));
        float endX = Float.parseFloat(extractJsonValue(json, "endX"));
        float endY = Float.parseFloat(extractJsonValue(json, "endY"));
        int duration = Integer.parseInt(extractJsonValue(json, "duration"));

        inputDispatcher.injectSwipe(startX, startY, endX, endY, duration);
    }

    private static void parseAndExecuteShell(String json) {
        String command = extractJsonValue(json, "command");
        ShellExecutor.executeCommandAsync(command, (outputLine, isError) -> {
            if (wsClient != null && wsClient.isConnected()) {
                String response = "{\"type\":\"shell_output\",\"line\":\"" + escapeJson(outputLine) + "\",\"is_error\":" + isError + "}";
                wsClient.sendText(response);
            }
        });
    }

    /**
     * Parser JSON ringan mandiri tanpa library eksternal
     */
    private static String extractJsonValue(String json, String key) {
        Pattern pattern = Pattern.compile("\"" + key + "\"\\s*:\\s*\"?([^,\"}]+)\"?");
        Matcher matcher = pattern.matcher(json);
        if (matcher.find()) {
            return matcher.group(1).trim();
        }
        return "0";
    }

    private static String escapeJson(String raw) {
        if (raw == null) return "";
        return raw.replace("\\", "\\\\").replace("\"", "\\\"").replace("\n", "\\n").replace("\r", "\\r");
    }
}
