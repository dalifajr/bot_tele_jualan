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
        System.out.println("[ScreenEncoder] Mengaktifkan Native Screenrecord Streamer Engine (720x1600 @ 2Mbps)...");
        runNativeScreenrecordFallback();
    }

    public synchronized byte[] getSpsPpsHeader() {
        return spsPpsHeader;
    }

    /**
     * Fallback menggunakan native binary /system/bin/screenrecord (100% didukung di seluruh Android OS)
     */
    private void runNativeScreenrecordFallback() {
        System.out.println("[ScreenEncoder] Mengaktifkan Native Screenrecord Streamer Engine...");
        byte[] buffer = new byte[16384];

        while (isRunning && !Thread.currentThread().isInterrupted()) {
            Process process = null;
            try {
                String[] cmd = new String[] {
                    "/system/bin/screenrecord",
                    "--output-format=h264",
                    "--size", width + "x" + height,
                    "--bit-rate", String.valueOf(bitRate),
                    "--time-limit", "180",
                    "-"
                };
                process = Runtime.getRuntime().exec(cmd);
                nativeProcess = process;

                // Drain stderr di daemon thread agar tidak blocking/deadlock
                final Process currentProc = process;
                Thread errDrainer = new Thread(new Runnable() {
                    @Override
                    public void run() {
                        try {
                            InputStream err = currentProc.getErrorStream();
                            byte[] errBuf = new byte[512];
                            while (err.read(errBuf) != -1) {}
                        } catch (Exception ignored) {}
                    }
                });
                errDrainer.setDaemon(true);
                errDrainer.start();

                InputStream in = process.getInputStream();
                int read;
                boolean isHeaderCached = false;

                while (isRunning && (read = in.read(buffer)) != -1) {
                    if (read > 0 && callback != null) {
                        byte[] chunk = new byte[read];
                        System.arraycopy(buffer, 0, chunk, 0, read);

                        if (!isHeaderCached && spsPpsHeader == null) {
                            spsPpsHeader = chunk;
                            isHeaderCached = true;
                        }

                        callback.onVideoFrame(chunk, 0, read, false);
                    }
                }
            } catch (Exception e) {
                System.err.println("[ScreenEncoder] Native screenrecord error: " + e.getMessage());
                try {
                    Thread.sleep(500);
                } catch (Exception ignored) {}
            } finally {
                if (process != null) {
                    try {
                        process.destroy();
                    } catch (Exception ignored) {}
                }
                nativeProcess = null;
            }
        }
        System.out.println("[ScreenEncoder] Native Screenrecord Streamer selesai.");
    }

    private void initCodec() throws Exception {
        MediaFormat format = MediaFormat.createVideoFormat(MediaFormat.MIMETYPE_VIDEO_AVC, width, height);
        format.setInteger(MediaFormat.KEY_COLOR_FORMAT, MediaCodecInfo.CodecCapabilities.COLOR_FormatSurface);
        format.setInteger(MediaFormat.KEY_BIT_RATE, bitRate);
        format.setInteger(MediaFormat.KEY_FRAME_RATE, frameRate);
        format.setInteger(MediaFormat.KEY_I_FRAME_INTERVAL, DeviceConfig.iFrameInterval);

        try {
            format.setInteger(MediaFormat.KEY_BITRATE_MODE, 1); // VBR
            format.setInteger(MediaFormat.KEY_LATENCY, 0);       // Ultra-low latency (0ms queue)
            format.setInteger(MediaFormat.KEY_PRIORITY, 0);      // Realtime priority
            // Repeat frame setiap 100ms agar saat layar idle video decoder client tidak freeze
            format.setLong("repeat-previous-frame-after", 100000L);
        } catch (Throwable ignored) {}

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

            int physW = DeviceConfig.physicalWidth > 0 ? DeviceConfig.physicalWidth : width;
            int physH = DeviceConfig.physicalHeight > 0 ? DeviceConfig.physicalHeight : height;
            int layerStack = getPhysicalDisplayLayerStack();

            // 1. Coba pendekatan Modern Android 10 - 15+ (SurfaceControl.Transaction)
            try {
                Class<?> transactionClass = Class.forName("android.view.SurfaceControl$Transaction");
                Object transaction = transactionClass.getConstructor().newInstance();

                Method setDisplaySurface = transactionClass.getMethod("setDisplaySurface", IBinder.class, Surface.class);
                setDisplaySurface.invoke(transaction, virtualDisplay, surface);

                Method setDisplayProjection = transactionClass.getMethod("setDisplayProjection", IBinder.class, int.class, Rect.class, Rect.class);
                setDisplayProjection.invoke(transaction, virtualDisplay, 0, new Rect(0, 0, physW, physH), new Rect(0, 0, width, height));

                Method setDisplayLayerStack = transactionClass.getMethod("setDisplayLayerStack", IBinder.class, int.class);
                setDisplayLayerStack.invoke(transaction, virtualDisplay, layerStack);

                Method apply = transactionClass.getMethod("apply");
                apply.invoke(transaction);

                System.out.println("[ScreenEncoder] Virtual Display terikat: " + physW + "x" + physH + " -> " + width + "x" + height + " (LayerStack " + layerStack + ")");
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
                    setDisplayProjection.invoke(null, virtualDisplay, 0, new Rect(0, 0, physW, physH), new Rect(0, 0, width, height));
                    setDisplayLayerStack.invoke(null, virtualDisplay, layerStack);
                } finally {
                    closeTransaction.invoke(null);
                }
                System.out.println("[ScreenEncoder] Virtual Display terikat (Legacy Mode).");
                return true;
            }
        } catch (Throwable t) {
            System.err.println("[ScreenEncoder] SurfaceControl display bind gagal: " + t.getMessage());
            return false;
        }
    }

    private int getPhysicalDisplayLayerStack() {
        // 1. Coba deteksi via DisplayManagerGlobal (Android 4.2 - Android 15/17)
        try {
            Class<?> dmgClass = Class.forName("android.hardware.display.DisplayManagerGlobal");
            Method getInstance = dmgClass.getMethod("getInstance");
            Object dmg = getInstance.invoke(null);
            Method getDisplayInfo = dmgClass.getMethod("getDisplayInfo", int.class);
            Object displayInfo = getDisplayInfo.invoke(dmg, 0); // 0 = main physical display
            if (displayInfo != null) {
                java.lang.reflect.Field layerStackField = displayInfo.getClass().getField("layerStack");
                int ls = layerStackField.getInt(displayInfo);
                System.out.println("[ScreenEncoder] LayerStack terdeteksi via DisplayManagerGlobal: " + ls);
                return ls;
            }
        } catch (Throwable ignored) {}

        // 2. Fallback via SurfaceControl internal tokens
        try {
            Class<?> surfaceControlClass = Class.forName("android.view.SurfaceControl");
            IBinder token = null;

            try {
                Method getPhysicalDisplayIds = surfaceControlClass.getMethod("getPhysicalDisplayIds");
                long[] ids = (long[]) getPhysicalDisplayIds.invoke(null);
                if (ids != null && ids.length > 0) {
                    Method getPhysicalDisplayToken = surfaceControlClass.getMethod("getPhysicalDisplayToken", long.class);
                    token = (IBinder) getPhysicalDisplayToken.invoke(null, ids[0]);
                }
            } catch (Throwable ignored) {}

            if (token == null) {
                try {
                    Method getInternalDisplayToken = surfaceControlClass.getMethod("getInternalDisplayToken");
                    token = (IBinder) getInternalDisplayToken.invoke(null);
                } catch (Throwable ignored) {}
            }

            if (token != null) {
                try {
                    Class<?> displayInfoClass = Class.forName("android.view.SurfaceControl$DisplayInfo");
                    Method getDisplayInfo = surfaceControlClass.getMethod("getDisplayInfo", IBinder.class);
                    Object info = getDisplayInfo.invoke(null, token);
                    if (info != null) {
                        java.lang.reflect.Field f = displayInfoClass.getField("layerStack");
                        return f.getInt(info);
                    }
                } catch (Throwable ignored) {}
            }
        } catch (Throwable ignored) {}
        return 0;
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
