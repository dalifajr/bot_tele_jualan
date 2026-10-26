import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';
import '../models/device_session.dart';
import '../config/constants.dart';

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
  late final WebViewController _webViewController;
  bool _isWebViewReady = false;

  @override
  void initState() {
    super.initState();
    _initWebViewController();
  }

  void _initWebViewController() {
    final cleanHost = widget.host.replaceAll(RegExp(r'^(https?|wss?)://'), '').split('/').first.split(':').first;
    final isDomain = cleanHost.contains('.') && !RegExp(r'^\d+\.\d+\.\d+\.\d+$').hasMatch(cleanHost);
    final isSecure = isDomain || widget.port == 443;
    final wsScheme = isSecure ? 'wss://' : 'ws://';
    final hostAndPort = isSecure ? cleanHost : '$cleanHost:${widget.port}';
    final wsUrl = '$wsScheme$hostAndPort/ws/controller?device_id=${widget.device.deviceId}&token=${widget.token}';
    final jmuxerUrl = isSecure ? 'https://$cleanHost/static_remote/jmuxer.min.js' : 'http://$cleanHost:${widget.port}/static_remote/jmuxer.min.js';

    final htmlContent = '''
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; user-select: none; -webkit-user-select: none; }
  body, html { width: 100%; height: 100%; overflow: hidden; background: #000; touch-action: none; }
  #container { position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
  video { width: 100%; height: 100%; object-fit: contain; pointer-events: none; background: #000; }
  #touchArea { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; touch-action: none; }
  #statusOverlay { position: absolute; top: 12px; left: 12px; z-index: 20; color: #06B6D4; font-family: monospace; font-size: 11px; background: rgba(0,0,0,0.6); padding: 4px 8px; border-radius: 6px; pointer-events: none; }
</style>
<script src="https://cdn.jsdelivr.net/npm/jmuxer@2.0.5/dist/jmuxer.min.js"></script>
</head>
<body>
<div id="container">
  <video id="player" autoplay muted playsinline></video>
  <div id="touchArea"></div>
  <div id="statusOverlay">Menghubungkan...</div>
</div>
<script>
  let jmuxer = null;
  const player = document.getElementById('player');
  const touchArea = document.getElementById('touchArea');
  const statusEl = document.getElementById('statusOverlay');

  function initMuxer() {
    if (typeof JMuxer !== 'undefined' && !jmuxer) {
      try {
        jmuxer = new JMuxer({
          node: 'player',
          mode: 'video',
          flushingTime: 10,
          clearBuffer: true,
          fps: 30,
          debug: false
        });
        statusEl.textContent = 'Decoder Siap';
      } catch(e) {
        statusEl.textContent = 'Muxer Err';
      }
    }
  }

  // Load fallback jika CDN lambat
  if (typeof JMuxer === 'undefined') {
    const s = document.createElement('script');
    s.src = "$jmuxerUrl";
    s.onload = initMuxer;
    document.head.appendChild(s);
  } else {
    initMuxer();
  }

  let ws = null;
  let framesReceived = 0;

  function connectWs() {
    ws = new WebSocket('$wsUrl');
    ws.binaryType = 'arraybuffer';

    ws.onopen = function() {
      statusEl.textContent = 'Streaming Aktif';
      statusEl.style.color = '#10B981';
      sendPayload({ type: 'start_stream' });

      setInterval(() => {
        if (ws && ws.readyState === WebSocket.OPEN) {
          sendPayload({ type: 'ping' });
        }
      }, 5000);
    };

    ws.onmessage = function(event) {
      if (event.data instanceof ArrayBuffer) {
        framesReceived++;
        if (!jmuxer) initMuxer();
        if (jmuxer) {
          jmuxer.feed({ video: new Uint8Array(event.data) });
          if (player.paused) {
            player.play().catch(() => {});
          }
        }
      } else if (typeof event.data === 'string') {
        try {
          if (window.FlutterBridge) {
            window.FlutterBridge.postMessage(event.data);
          }
        } catch(e) {}
      }
    };

    ws.onclose = function() {
      statusEl.textContent = 'Terputus';
      statusEl.style.color = '#EF4444';
      setTimeout(connectWs, 3000);
    };
  }

  connectWs();

  window.sendPayload = function(obj) {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify(obj));
    }
  };

  function sendTouch(action, e) {
    const rect = touchArea.getBoundingClientRect();
    const touch = (e.touches && e.touches.length > 0) ? e.touches[0] : e;
    const clientX = touch.clientX;
    const clientY = touch.clientY;
    const x = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
    const y = Math.max(0, Math.min(1, (clientY - rect.top) / rect.height));
    sendPayload({ type: 'touch', action: action, x: x, y: y });
  }

  touchArea.addEventListener('pointerdown', (e) => {
    if (player.paused) player.play().catch(() => {});
    e.preventDefault();
    sendTouch('down', e);
  });
  touchArea.addEventListener('pointermove', (e) => { e.preventDefault(); sendTouch('move', e); });
  touchArea.addEventListener('pointerup', (e) => { e.preventDefault(); sendTouch('up', e); });
  touchArea.addEventListener('pointercancel', (e) => { e.preventDefault(); sendTouch('up', e); });
</script>
</body>
</html>
''';

    _webViewController = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.black);

    if (_webViewController.platform is AndroidWebViewController) {
      (_webViewController.platform as AndroidWebViewController)
          .setMediaPlaybackRequiresUserGesture(false);
    }

    _webViewController
      ..addJavaScriptChannel('FlutterBridge', onMessageReceived: (JavaScriptMessage msg) {})
      ..loadHtmlString(htmlContent, baseUrl: 'https://$cleanHost');

    setState(() {
      _isWebViewReady = true;
    });
  }

  void _sendKey(int keyCode) {
    _webViewController.runJavaScript("window.sendPayload({ type: 'key', action: 'click', code: $keyCode });");
  }

  void _sendSwipe(int startX, int startY, int endX, int endY) {
    _webViewController.runJavaScript("window.sendPayload({ type: 'swipe', startX: $startX, startY: $startY, endX: $endX, endY: $endY, duration: 250 });");
  }

  void _sendShell(String cmd) {
    final sanitized = cmd.replaceAll('\\', '\\\\').replaceAll('"', '\\"');
    _webViewController.runJavaScript('window.sendPayload({ type: "shell", command: "$sanitized" });');
  }

  void _showTextInputDialog() {
    final textController = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF111622),
        title: const Text('Input Teks ke HP Target', style: TextStyle(color: Colors.white, fontSize: 16)),
        content: TextField(
          controller: textController,
          autofocus: true,
          style: const TextStyle(color: Colors.white),
          decoration: const InputDecoration(
            hintText: 'Tuliskan teks untuk diketik di HP...',
            hintStyle: TextStyle(color: Colors.white30),
            enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFF06B6D4))),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal', style: TextStyle(color: Colors.white54)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF06B6D4)),
            onPressed: () {
              final text = textController.text;
              if (text.isNotEmpty) {
                final sanitized = text.replaceAll(' ', '%s');
                _sendShell('input text "$sanitized"');
              }
              Navigator.pop(ctx);
            },
            child: const Text('Kirim', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
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
              '${widget.device.screenWidth}x${widget.device.screenHeight} px • Latensi 0ms Kernel',
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
            onPressed: () => _sendKey(AppConstants.keyPower),
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
                      child: _isWebViewReady
                          ? WebViewWidget(controller: _webViewController)
                          : const Center(
                              child: CircularProgressIndicator(color: Color(0xFF06B6D4)),
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
                    onPressed: () => _sendKey(AppConstants.keyBack),
                  ),
                  IconButton(
                    icon: const Icon(Icons.radio_button_unchecked, color: Colors.white70, size: 22),
                    tooltip: 'Home',
                    onPressed: () => _sendKey(AppConstants.keyHome),
                  ),
                  IconButton(
                    icon: const Icon(Icons.crop_square, color: Colors.white70, size: 22),
                    tooltip: 'Recents',
                    onPressed: () => _sendKey(AppConstants.keyRecents),
                  ),
                  IconButton(
                    icon: const Icon(Icons.volume_down, color: Colors.white70, size: 20),
                    tooltip: 'Volume Down',
                    onPressed: () => _sendKey(AppConstants.keyVolumeDown),
                  ),
                  IconButton(
                    icon: const Icon(Icons.volume_up, color: Colors.white70, size: 20),
                    tooltip: 'Volume Up',
                    onPressed: () => _sendKey(AppConstants.keyVolumeUp),
                  ),
                  IconButton(
                    icon: const Icon(Icons.notifications_active_outlined, color: Colors.white70, size: 20),
                    tooltip: 'Tarik Notifikasi',
                    onPressed: () {
                      final w = widget.device.screenWidth;
                      final h = widget.device.screenHeight;
                      _sendSwipe((w / 2).round(), 10, (w / 2).round(), (h * 0.65).round());
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
}
