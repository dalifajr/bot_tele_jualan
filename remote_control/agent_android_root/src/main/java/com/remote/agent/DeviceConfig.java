package com.remote.agent;

/**
 * Konfigurasi Operasional Agent Android Root
 */
public class DeviceConfig {
    // Alamat WebSocket Relay Server (Default domain produksi Helm: belajaridn.id)
    public static String serverWsUrl = "wss://belajaridn.id/ws/device";
    
    // Kredensial & Identitas Perangkat
    public static String deviceId = "ANDROID_TARGET_01";
    public static String deviceToken = "SECRET_REMOTE_TOKEN_2026";
    public static String deviceName = "Android Rooted Device";
    
    // Resolusi Fisik Asli & Target Stream
    public static int physicalWidth = 1080;
    public static int physicalHeight = 2400;
    public static int targetWidth = 720;
    public static int targetHeight = 1600;
    public static int frameRate = 30;
    public static int bitRate = 2000000; // 2.0 Mbps untuk kejernihan maksimal
    public static int iFrameInterval = 1; // Keyframe setiap 1 detik untuk inisiasi instan

    // Status Kontrol
    public static volatile boolean isStreaming = false;
    public static volatile boolean isRunning = true;
}
