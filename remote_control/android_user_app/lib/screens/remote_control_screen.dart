import 'package:flutter/material.dart';
import '../models/device_session.dart';
import '../services/websocket_service.dart';
import '../config/constants.dart';
import '../widgets/terminal_sheet.dart';

class RemoteControlScreen extends StatefulWidget {
  final DeviceSession device;
  final String host;
  final int port;
  final String token;

  const RemoteControlScreen({
    super.key,
    required this.device,
    required this.host,
    required this.port,
    required this.token,
  });

  @override
  State<RemoteControlScreen> createState() => _RemoteControlScreenState();
}

class _RemoteControlScreenState extends State<RemoteControlScreen> {
  late ControllerWebSocketService _wsService;
  final GlobalKey _viewportKey = GlobalKey();
  Offset? _touchFeedbackPos;

  @override
  void initState() {
    super.initState();
    _wsService = ControllerWebSocketService();
    _connectWebSocket();
  }

  Future<void> _connectWebSocket() async {
    try {
      await _wsService.connect(
        host: widget.host,
        port: widget.port,
        deviceId: widget.device.deviceId,
        token: widget.token,
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal terhubung ke relay: $e')),
        );
      }
    }
  }

  void _handlePointerEvent(PointerEvent event, String action) {
    final RenderBox? renderBox = _viewportKey.currentContext?.findRenderObject() as RenderBox?;
    if (renderBox == null) return;

    final localPosition = renderBox.globalToLocal(event.position);
    final size = renderBox.size;

    if (localPosition.dx < 0 || localPosition.dx > size.width ||
        localPosition.dy < 0 || localPosition.dy > size.height) {
      return;
    }

    final normX = (localPosition.dx / size.width).clamp(0.0, 1.0);
    final normY = (localPosition.dy / size.height).clamp(0.0, 1.0);

    final targetX = (normX * widget.device.screenWidth).round();
    final targetY = (normY * widget.device.screenHeight).round();

    if (action == 'down') {
      setState(() {
        _touchFeedbackPos = localPosition;
      });
      Future.delayed(const Duration(milliseconds: 300), () {
        if (mounted) {
          setState(() {
            _touchFeedbackPos = null;
          });
        }
      });
    }

    _wsService.sendTouch(action: action, x: targetX, y: targetY);
  }

  void _openTerminal() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => TerminalBottomSheet(wsService: _wsService),
    );
  }

  void _showTextInputDialog() {
    final textController = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF111622),
        title: const Text('Input Teks ke HP', style: TextStyle(color: Colors.white)),
        content: TextField(
          controller: textController,
          autofocus: true,
          style: const TextStyle(color: Colors.white),
          decoration: const InputDecoration(
            hintText: 'Teks yang dikirimkan...',
            hintStyle: TextStyle(color: Colors.white30),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF06B6D4)),
            onPressed: () {
              final text = textController.text;
              if (text.isNotEmpty) {
                final sanitized = text.replaceAll(' ', '%s');
                _wsService.sendShell('input text "$sanitized"');
              }
              Navigator.pop(ctx);
            },
            child: const Text('Kirim', style: TextStyle(color: Colors.black)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0D14),
      appBar: AppBar(
        backgroundColor: const Color(0xFF111622),
        elevation: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.device.deviceName,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
            ),
            Text(
              '${widget.device.screenWidth}x${widget.device.screenHeight} px',
              style: const TextStyle(fontSize: 11, color: Color(0xFF06B6D4)),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.text_fields),
            tooltip: 'Kirim Teks',
            onPressed: _showTextInputDialog,
          ),
          IconButton(
            icon: const Icon(Icons.power_settings_new, color: Color(0xFFF43F5E)),
            tooltip: 'Layar On/Off',
            onPressed: () => _wsService.sendKey(AppConstants.keyPower),
          ),
          IconButton(
            icon: const Icon(Icons.terminal, color: Color(0xFF06B6D4)),
            tooltip: 'Terminal Root',
            onPressed: _openTerminal,
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Center Screen Viewport
            Expanded(
              child: Center(
                child: AspectRatio(
                  aspectRatio: widget.device.screenWidth / widget.device.screenHeight,
                  child: Container(
                    key: _viewportKey,
                    margin: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.black,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFF1E293B), width: 2),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x3306B6D4),
                          blurRadius: 15,
                          spreadRadius: 1,
                        )
                      ],
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(14),
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          // Video Render Canvas Placeholder
                          Container(
                            color: const Color(0xFF070A10),
                            child: const Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.phonelink, color: Color(0xFF06B6D4), size: 40),
                                  SizedBox(height: 8),
                                  Text(
                                    'Sesi Remote Terhubung',
                                    style: TextStyle(color: Colors.white70, fontSize: 13),
                                  ),
                                  Text(
                                    'Sentuhan diteruskan 0ms ke kernel',
                                    style: TextStyle(color: Colors.white30, fontSize: 10),
                                  ),
                                ],
                              ),
                            ),
                          ),

                          // Touch Gesture Listener
                          Listener(
                            onPointerDown: (e) => _handlePointerEvent(e, 'down'),
                            onPointerMove: (e) => _handlePointerEvent(e, 'move'),
                            onPointerUp: (e) => _handlePointerEvent(e, 'up'),
                            behavior: HitTestBehavior.opaque,
                          ),

                          // Visual Ripple Touch Feedback
                          if (_touchFeedbackPos != null)
                            Positioned(
                              left: _touchFeedbackPos!.dx - 18,
                              top: _touchFeedbackPos!.dy - 18,
                              child: Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: const Color(0x6606B6D4),
                                  border: Border.all(color: const Color(0xFF06B6D4), width: 2),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),

            // Virtual Android Bottom Navigation Keys
            Container(
              height: 52,
              color: const Color(0xFF111622),
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  IconButton(
                    icon: const Icon(Icons.arrow_back_ios_new, color: Colors.white70, size: 20),
                    tooltip: 'Back',
                    onPressed: () => _wsService.sendKey(AppConstants.keyBack),
                  ),
                  IconButton(
                    icon: const Icon(Icons.radio_button_unchecked, color: Colors.white70, size: 22),
                    tooltip: 'Home',
                    onPressed: () => _wsService.sendKey(AppConstants.keyHome),
                  ),
                  IconButton(
                    icon: const Icon(Icons.crop_square, color: Colors.white70, size: 22),
                    tooltip: 'Recents',
                    onPressed: () => _wsService.sendKey(AppConstants.keyRecents),
                  ),
                  IconButton(
                    icon: const Icon(Icons.volume_down, color: Colors.white70, size: 20),
                    tooltip: 'Volume Down',
                    onPressed: () => _wsService.sendKey(AppConstants.keyVolumeDown),
                  ),
                  IconButton(
                    icon: const Icon(Icons.volume_up, color: Colors.white70, size: 20),
                    tooltip: 'Volume Up',
                    onPressed: () => _wsService.sendKey(AppConstants.keyVolumeUp),
                  ),
                  IconButton(
                    icon: const Icon(Icons.notifications_active_outlined, color: Colors.white70, size: 20),
                    tooltip: 'Tarik Notifikasi',
                    onPressed: () {
                      final w = widget.device.screenWidth;
                      final h = widget.device.screenHeight;
                      _wsService.sendSwipe(
                        startX: (w / 2).round(),
                        startY: 10,
                        endX: (w / 2).round(),
                        endY: (h * 0.65).round(),
                      );
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _wsService.dispose();
    super.dispose();
  }
}
