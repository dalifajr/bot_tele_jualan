package com.remote.agent;

import android.graphics.Rect;
import android.media.MediaCodec;
import android.media.MediaCodecInfo;
import android.media.MediaFormat;
import android.os.Build;
import android.os.IBinder;
import android.view.Surface;

import java.io.InputStream;
import java.lang.reflect.Method;
import java.nio.ByteBuffer;
import java.util.Arrays;

/**
 * ScreenEncoder: Menangkap Layar Android dan Meng-encode ke H.264 Video Stream
 * Mendukung Android 5 hingga Android 14/15/17 (Pixel 7) dengan Dual Engine:
 * 1. Hardware Accelerated MediaCodec via SurfaceControl / SurfaceControl$Transaction
 * 2. Native Screenrecord Fallback Streamer jika SurfaceControl di-block oleh vendor OS
 */
public class ScreenEncoder implements Runnable {

    public interface FrameCallback {
        void onVideoFrame(byte[] data, int offset, int length, boolean isKeyFrame);
    }

    private final int width;
    private final int height;
    private final int bitRate;
    private final int frameRate;
    private final FrameCallback callback;

    private MediaCodec codec;
    private Surface inputSurface;
    private IBinder virtualDisplay;
    private Thread workerThread;
    private volatile boolean isRunning = false;
    private Process nativeProcess = null;

    // Cache SPS (Sequence Parameter Set) & PPS (Picture Parameter Set)
    private byte[] spsPpsHeader = null;

    public ScreenEncoder(int width, int height, int bitRate, int frameRate, FrameCallback callback) {
        this.width = width;
        this.height = height;
        this.bitRate = bitRate;
        this.frameRate = frameRate;
        this.callback = callback;
    }

    public synchronized void start() {
        if (isRunning) return;
        isRunning = true;
        workerThread = new Thread(this, "ScreenEncoderThread");
        workerThread.start();
    }

    public synchronized void stop() {
        isRunning = false;
        if (workerThread != null) {
            workerThread.interrupt();
            workerThread = null;
        }
        if (nativeProcess != null) {
            try {
                nativeProcess.destroy();
            } catch (Exception ignored) {}
            nativeProcess = null;
        }
        releaseResources();
    }

    @Override
    public void run() {
        boolean hardwareSuccess = false;

        try {
            initCodec();
            hardwareSuccess = bindDisplayToSurface(inputSurface);

            if (hardwareSuccess) {
                System.out.println("[ScreenEncoder] Hardware H.264 Encoder Aktif (" + width + "x" + height + " @ " + frameRate + "fps)");
                MediaCodec.BufferInfo bufferInfo = new MediaCodec.BufferInfo();
                long framesProduced = 0;
                long startTime = System.currentTimeMillis();

                while (isRunning && !Thread.currentThread().isInterrupted()) {
                    int outputIndex = codec.dequeueOutputBuffer(bufferInfo, 15000); // 15ms timeout

                    if (outputIndex >= 0) {
                        ByteBuffer outputBuffer;
                        try {
                            outputBuffer = codec.getOutputBuffer(outputIndex);
                        } catch (NoSuchMethodError e) {
                            outputBuffer = codec.getOutputBuffers()[outputIndex];
                        }

                        if (outputBuffer != null && bufferInfo.size > 0) {
                            outputBuffer.position(bufferInfo.offset);
                            outputBuffer.limit(bufferInfo.offset + bufferInfo.size);

                            byte[] outData = new byte[bufferInfo.size];
                            outputBuffer.get(outData);

                            boolean isConfig = (bufferInfo.flags & MediaCodec.BUFFER_FLAG_CODEC_CONFIG) != 0;
                            boolean isKeyFrame = (bufferInfo.flags & MediaCodec.BUFFER_FLAG_KEY_FRAME) != 0;

                            if (isConfig) {
                                // Simpan SPS/PPS untuk dikirim ke controller baru
                                spsPpsHeader = Arrays.copyOf(outData, outData.length);
                                System.out.println("[ScreenEncoder] SPS/PPS Codec Config Header tersimpan (" + spsPpsHeader.length + " bytes)");
                                if (callback != null) {
                                    callback.onVideoFrame(outData, 0, outData.length, true);
                                }
                            } else {
                                framesProduced++;
                                if (isKeyFrame && spsPpsHeader != null && !containsSps(outData)) {
                                    // Gabungkan SPS/PPS di depan Keyframe agar decoder instan menginisiasi
                                    byte[] combined = new byte[spsPpsHeader.length + outData.length];
                                    System.arraycopy(spsPpsHeader, 0, combined, 0, spsPpsHeader.length);
                                    System.arraycopy(outData, 0, combined, spsPpsHeader.length, outData.length);
                                    if (callback != null) {
                                        callback.onVideoFrame(combined, 0, combined.length, true);
                                    }
                                } else {
                                    if (callback != null) {
                                        callback.onVideoFrame(outData, 0, outData.length, isKeyFrame);
                                    }
                                }
                            }
                        }

                        codec.releaseOutputBuffer(outputIndex, false);
                    } else if (outputIndex == MediaCodec.INFO_OUTPUT_FORMAT_CHANGED) {
                        MediaFormat newFormat = codec.getOutputFormat();
                        System.out.println("[ScreenEncoder] Output format berubah: " + newFormat);
                    }

                    // Failsafe: Jika dalam 3 detik tidak ada frame yang dihasilkan, beralih ke Native Streamer
                    if (framesProduced == 0 && (System.currentTimeMillis() - startTime) > 3000) {
                        System.err.println("[ScreenEncoder] Warning: Hardware encoder idle (0 frames). Beralih ke Native Screenrecord...");
                        break;
                    }
                }
            }
        } catch (Throwable t) {
            System.err.println("[ScreenEncoder] Hardware encoder mengalami kendala: " + t.getMessage());
        } finally {
            releaseResources();
        }

        // Jika hardware encoding tidak menghasilkan frame atau di-block Android 14/15, gunakan native fallback
        if (isRunning && !Thread.currentThread().isInterrupted()) {
            runNativeScreenrecordFallback();
        }
    }

    /**
     * Fallback menggunakan native binary /system/bin/screenrecord
     * 100% kompatibel di semua versi Android (Pixel 7 Android 14/17, Xiaomi, Samsung).
     */
    private void runNativeScreenrecordFallback() {
        System.out.println("[ScreenEncoder] Mengaktifkan Native Screenrecord Streamer...");
        try {
            String cmd = String.format("screenrecord --output-format=h264 --size %dx%d --bit-rate %d --time-limit 0 -", width, height, bitRate);
            nativeProcess = Runtime.getRuntime().exec(cmd);
            InputStream in = nativeProcess.getInputStream();
            byte[] buffer = new byte[16384];
            int read;

            while (isRunning && (read = in.read(buffer)) != -1) {
                if (read > 0 && callback != null) {
                    byte[] chunk = new byte[read];
                    System.arraycopy(buffer, 0, chunk, 0, read);
                    callback.onVideoFrame(chunk, 0, read, false);
                }
            }
        } catch (Exception e) {
            System.err.println("[ScreenEncoder] Native screenrecord error: " + e.getMessage());
        }
    }

    private void initCodec() throws Exception {
        MediaFormat format = MediaFormat.createVideoFormat(MediaFormat.MIMETYPE_VIDEO_AVC, width, height);
        format.setInteger(MediaFormat.KEY_COLOR_FORMAT, MediaCodecInfo.CodecCapabilities.COLOR_FormatSurface);
        format.setInteger(MediaFormat.KEY_BIT_RATE, bitRate);
        format.setInteger(MediaFormat.KEY_FRAME_RATE, frameRate);
        format.setInteger(MediaFormat.KEY_I_FRAME_INTERVAL, DeviceConfig.iFrameInterval);

        try {
            format.setInteger(MediaFormat.KEY_BITRATE_MODE, 1); // VBR
            format.setInteger(MediaFormat.KEY_LATENCY, 0);       // Ultra-low latency
        } catch (Exception ignored) {}

        codec = MediaCodec.createEncoderByType(MediaFormat.MIMETYPE_VIDEO_AVC);
        codec.configure(format, null, null, MediaCodec.CONFIGURE_FLAG_ENCODE);
        inputSurface = codec.createInputSurface();
        codec.start();
    }

    /**
     * Memproyeksikan layar Android ke Surface MediaCodec via SurfaceControl root.
     * Mendukung SurfaceControl.Transaction (Android 10 - 15+) dan openTransaction (Android 5 - 9).
     */
    private boolean bindDisplayToSurface(Surface surface) {
        try {
            Class<?> surfaceControlClass = Class.forName("android.view.SurfaceControl");
            Method createDisplay = surfaceControlClass.getMethod("createDisplay", String.class, boolean.class);
            virtualDisplay = (IBinder) createDisplay.invoke(null, "remote_control_display", false);

            if (virtualDisplay == null) {
                System.err.println("[ScreenEncoder] Gagal membuat virtualDisplay (null).");
                return false;
            }

            // 1. Coba pendekatan Modern Android 10 - 15+ (SurfaceControl.Transaction)
            try {
                Class<?> transactionClass = Class.forName("android.view.SurfaceControl$Transaction");
                Object transaction = transactionClass.getConstructor().newInstance();

                Method setDisplaySurface = transactionClass.getMethod("setDisplaySurface", IBinder.class, Surface.class);
                setDisplaySurface.invoke(transaction, virtualDisplay, surface);

                Method setDisplayProjection = transactionClass.getMethod("setDisplayProjection", IBinder.class, int.class, Rect.class, Rect.class);
                setDisplayProjection.invoke(transaction, virtualDisplay, 0, new Rect(0, 0, width, height), new Rect(0, 0, width, height));

                Method setDisplayLayerStack = transactionClass.getMethod("setDisplayLayerStack", IBinder.class, int.class);
                setDisplayLayerStack.invoke(transaction, virtualDisplay, 0);

                Method apply = transactionClass.getMethod("apply");
                apply.invoke(transaction);

                System.out.println("[ScreenEncoder] Virtual Display berhasil dikaitkan via SurfaceControl.Transaction (Android 10+).");
                return true;
            } catch (Throwable tModern) {
                // 2. Fallback ke pendekatan Legacy Android 5 - 9 (SurfaceControl.openTransaction)
                Method openTransaction = surfaceControlClass.getMethod("openTransaction");
                Method setDisplaySurface = surfaceControlClass.getMethod("setDisplaySurface", IBinder.class, Surface.class);
                Method setDisplayProjection = surfaceControlClass.getMethod("setDisplayProjection", IBinder.class, int.class, Rect.class, Rect.class);
                Method setDisplayLayerStack = surfaceControlClass.getMethod("setDisplayLayerStack", IBinder.class, int.class);
                Method closeTransaction = surfaceControlClass.getMethod("closeTransaction");

                openTransaction.invoke(null);
                try {
                    setDisplaySurface.invoke(null, virtualDisplay, surface);
                    setDisplayProjection.invoke(null, virtualDisplay, 0, new Rect(0, 0, width, height), new Rect(0, 0, width, height));
                    setDisplayLayerStack.invoke(null, virtualDisplay, 0);
                } finally {
                    closeTransaction.invoke(null);
                }
                System.out.println("[ScreenEncoder] Virtual Display berhasil dikaitkan via SurfaceControl.openTransaction (Legacy).");
                return true;
            }
        } catch (Throwable t) {
            System.err.println("[ScreenEncoder] SurfaceControl display bind gagal: " + t.getMessage());
            return false;
        }
    }

    private boolean containsSps(byte[] data) {
        if (data.length < 5) return false;
        for (int i = 0; i < data.length - 4; i++) {
            if (data[i] == 0 && data[i + 1] == 0 && data[i + 2] == 0 && data[i + 3] == 1) {
                int nalType = data[i + 4] & 0x1F;
                if (nalType == 7) return true; // NAL unit SPS
            }
        }
        return false;
    }

    private void releaseResources() {
        try {
            if (virtualDisplay != null) {
                Class<?> surfaceControlClass = Class.forName("android.view.SurfaceControl");
                Method destroyDisplay = surfaceControlClass.getMethod("destroyDisplay", IBinder.class);
                destroyDisplay.invoke(null, virtualDisplay);
                virtualDisplay = null;
            }
        } catch (Exception ignored) {}

        try {
            if (codec != null) {
                codec.stop();
                codec.release();
                codec = null;
            }
        } catch (Exception ignored) {}

        try {
            if (inputSurface != null) {
                inputSurface.release();
                inputSurface = null;
            }
        } catch (Exception ignored) {}
    }
}
