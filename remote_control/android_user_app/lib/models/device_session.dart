class DeviceSession {
  final String deviceId;
  final String deviceName;
  final int screenWidth;
  final int screenHeight;
  final bool isStreaming;
  final int controllersCount;
  final String connectedAt;
  final int uptimeSeconds;

  DeviceSession({
    required this.deviceId,
    required this.deviceName,
    required this.screenWidth,
    required this.screenHeight,
    required this.isStreaming,
    required this.controllersCount,
    required this.connectedAt,
    required this.uptimeSeconds,
  });

  factory DeviceSession.fromJson(Map<String, dynamic> json) {
    return DeviceSession(
      deviceId: json['device_id'] ?? '',
      deviceName: json['device_name'] ?? 'Android Device',
      screenWidth: json['screen_width'] ?? 720,
      screenHeight: json['screen_height'] ?? 1280,
      isStreaming: json['is_streaming'] ?? false,
      controllersCount: json['controllers_count'] ?? 0,
      connectedAt: json['connected_at'] ?? '',
      uptimeSeconds: json['uptime_seconds'] ?? 0,
    );
  }
}
