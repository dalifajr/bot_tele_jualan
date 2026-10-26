import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';
import 'package:web_socket_channel/web_socket_channel.dart';
import '../models/device_session.dart';

class ControllerWebSocketService {
  WebSocketChannel? _channel;
  bool _isConnected = false;
  
  final StreamController<String> _shellOutputController = StreamController<String>.broadcast();
  final StreamController<Uint8List> _videoFrameController = StreamController<Uint8List>.broadcast();
  final StreamController<bool> _connectionStateController = StreamController<bool>.broadcast();

  Stream<String> get shellOutputStream => _shellOutputController.stream;
  Stream<Uint8List> get videoFrameStream => _videoFrameController.stream;
  Stream<bool> get connectionStateStream => _connectionStateController.stream;

  bool get isConnected => _isConnected;

  Future<void> connect({
    required String host,
    required int port,
    required String deviceId,
    required String token,
  }) async {
    disconnect();

    final uri = Uri.parse('ws://$host:$port/ws/controller?device_id=$deviceId&token=$token');
    
    try {
      _channel = WebSocketChannel.connect(uri);
      _isConnected = true;
      _connectionStateController.add(true);

      _channel!.stream.listen(
        (message) {
          if (message is List<int>) {
            // Binary Video Frame (H.264 NAL Unit)
            _videoFrameController.add(Uint8List.fromList(message));
          } else if (message is String) {
            _handleTextMessage(message);
          }
        },
        onError: (error) {
          _isConnected = false;
          _connectionStateController.add(false);
          _shellOutputController.add('[Error WebSocket]: $error');
        },
        onDone: () {
          _isConnected = false;
          _connectionStateController.add(false);
          _shellOutputController.add('[WebSocket Ditutup oleh Server]');
        },
      );
    } catch (e) {
      _isConnected = false;
      _connectionStateController.add(false);
      rethrow;
    }
  }

  void _handleTextMessage(String rawJson) {
    try {
      final data = jsonDecode(rawJson) as Map<String, dynamic>;
      final type = data['type'];

      if (type == 'shell_output') {
        final line = data['line'] ?? '';
        _shellOutputController.add(line);
      } else if (type == 'device_disconnected') {
        _shellOutputController.add('[Peringatan]: ${data['message']}');
      }
    } catch (_) {
      _shellOutputController.add(rawJson);
    }
  }

  void sendTouch({
    required String action,
    required int x,
    required int y,
  }) {
    _sendJson({
      'type': 'touch',
      'action': action,
      'x': x,
      'y': y,
    });
  }

  void sendKey(int keyCode) {
    _sendJson({
      'type': 'key',
      'code': keyCode,
    });
  }

  void sendSwipe({
    required int startX,
    required int startY,
    required int endX,
    required int endY,
    int duration = 250,
  }) {
    _sendJson({
      'type': 'swipe',
      'startX': startX,
      'startY': startY,
      'endX': endX,
      'endY': endY,
      'duration': duration,
    });
  }

  void sendShell(String command) {
    _sendJson({
      'type': 'shell',
      'command': command,
    });
  }

  void _sendJson(Map<String, dynamic> map) {
    if (_isConnected && _channel != null) {
      _channel!.sink.add(jsonEncode(map));
    }
  }

  void disconnect() {
    if (_channel != null) {
      try {
        _channel!.sink.close();
      } catch (_) {}
      _channel = null;
    }
    _isConnected = false;
    _connectionStateController.add(false);
  }

  void dispose() {
    disconnect();
    _shellOutputController.close();
    _videoFrameController.close();
    _connectionStateController.close();
  }
}
