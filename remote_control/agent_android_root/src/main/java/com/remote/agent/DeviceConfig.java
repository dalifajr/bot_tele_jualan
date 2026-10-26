package com.remote.agent;

/**
 * Konfigurasi Operasional Agent Android Root
 */
public class DeviceConfig {
    // Alamat WebSocket Relay Server (Disesuaikan dengan domain/IP server VPS)
    public static String serverWsUrl = "ws://127.0.0.1:8080/ws/device";
    
    // Kredensial & Identitas Perangkat
    public static String deviceId = "ANDROID_TARGET_01";
    public static String deviceToken = "SECRET_REMOTE_TOKEN_2026";
    public static String deviceName = "Android Rooted Device";
    
    // Pengaturan Video Streaming (Default: 720p 30 FPS untuk hemat bandwidth & baterai)
    public static int targetWidth = 720;
    public static int targetHeight = 1280;
    public static int frameRate = 30;
    public static int bitRate = 1800000; // 1.8 Mbps
    public static int iFrameInterval = 2; // Keyframe setiap 2 detik

    // Status Kontrol
    public static volatile boolean isStreaming = false;
    public static volatile boolean isRunning = true;
}
