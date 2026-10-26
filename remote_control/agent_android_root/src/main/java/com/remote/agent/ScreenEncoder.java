package com.remote.agent;

import android.graphics.Rect;
import android.media.MediaCodec;
import android.media.MediaCodecInfo;
import android.media.MediaFormat;
import android.os.IBinder;
import android.view.Surface;

import java.io.OutputStream;
import java.lang.reflect.Method;
import java.nio.ByteBuffer;

/**
 * ScreenEncoder: Menangkap Layar Android dan Meng-encode ke H.264 Video Stream
 * Menggunakan Hardware Acceleration (GPU) via MediaCodec & SurfaceControl.
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
        releaseResources();
    }

    @Override
    public void run() {
        try {
            initCodec();
            bindDisplayToSurface(inputSurface);

            MediaCodec.BufferInfo bufferInfo = new MediaCodec.BufferInfo();
            System.out.println("[ScreenEncoder] Hardware H.264 Streaming aktif (" + width + "x" + height + " @ " + frameRate + "fps)");

            while (isRunning && !Thread.currentThread().isInterrupted()) {
                int outputIndex = codec.dequeueOutputBuffer(bufferInfo, 10000); // 10ms timeout

                if (outputIndex >= 0) {
                    ByteBuffer outputBuffer;
                    try {
                        outputBuffer = codec.getOutputBuffer(outputIndex);
                    } catch (NoSuchMethodError e) {
                        // Dukungan versi Android lama
                        outputBuffer = codec.getOutputBuffers()[outputIndex];
                    }

                    if (outputBuffer != null && bufferInfo.size > 0) {
                        outputBuffer.position(bufferInfo.offset);
                        outputBuffer.limit(bufferInfo.offset + bufferInfo.size);

                        byte[] outData = new byte[bufferInfo.size];
                        outputBuffer.get(outData);

                        boolean isKeyFrame = (bufferInfo.flags & MediaCodec.BUFFER_FLAG_KEY_FRAME) != 0;
                        if (callback != null) {
                            callback.onVideoFrame(outData, 0, outData.length, isKeyFrame);
                        }
                    }

                    codec.releaseOutputBuffer(outputIndex, false);
                } else if (outputIndex == MediaCodec.INFO_OUTPUT_FORMAT_CHANGED) {
                    MediaFormat newFormat = codec.getOutputFormat();
                    System.out.println("[ScreenEncoder] Output format berubah: " + newFormat);
                }
            }
        } catch (Exception e) {
            System.err.println("[ScreenEncoder] Terjadi error pada siklus encoding: " + e.getMessage());
        } finally {
            releaseResources();
        }
    }

    private void initCodec() throws Exception {
        MediaFormat format = MediaFormat.createVideoFormat(MediaFormat.MIMETYPE_VIDEO_AVC, width, height);
        format.setInteger(MediaFormat.KEY_COLOR_FORMAT, MediaCodecInfo.CodecCapabilities.COLOR_FormatSurface);
        format.setInteger(MediaFormat.KEY_BIT_RATE, bitRate);
        format.setInteger(MediaFormat.KEY_FRAME_RATE, frameRate);
        format.setInteger(MediaFormat.KEY_I_FRAME_INTERVAL, DeviceConfig.iFrameInterval);

        // Pengaturan prioritas latensi rendah
        try {
            format.setInteger(MediaFormat.KEY_BITRATE_MODE, 1); // VBR
            format.setInteger(MediaFormat.KEY_LATENCY, 0);       // Realtime ultra low latency
        } catch (Exception ignored) {}

        codec = MediaCodec.createEncoderByType(MediaFormat.MIMETYPE_VIDEO_AVC);
        codec.configure(format, null, null, MediaCodec.CONFIGURE_FLAG_ENCODE);
        inputSurface = codec.createInputSurface();
        codec.start();
    }

    /**
     * Memproyeksikan layar Android ke Surface MediaCodec via SurfaceControl root.
     */
    private void bindDisplayToSurface(Surface surface) {
        try {
            Class<?> surfaceControlClass = Class.forName("android.view.SurfaceControl");
            Method createDisplay = surfaceControlClass.getMethod("createDisplay", String.class, boolean.class);
            virtualDisplay = (IBinder) createDisplay.invoke(null, "remote_control_display", false);

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

            System.out.println("[ScreenEncoder] Virtual Display berhasil dikaitkan ke SurfaceControl.");
        } catch (Throwable t) {
            System.err.println("[ScreenEncoder] Warning: SurfaceControl reflection display bind gagal: " + t.getMessage());
        }
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
