import 'package:flutter/material.dart';
import '../services/websocket_service.dart';

class TerminalBottomSheet extends StatefulWidget {
  final ControllerWebSocketService wsService;

  const TerminalBottomSheet({super.key, required this.wsService});

  @override
  State<TerminalBottomSheet> createState() => _TerminalBottomSheetState();
}

class _TerminalBottomSheetState extends State<TerminalBottomSheet> {
  final List<String> _logs = ['[Android Root Shell UID: 0 Terhubung]'];
  final TextEditingController _inputController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    widget.wsService.shellOutputStream.listen((line) {
      if (mounted) {
        setState(() {
          _logs.add(line);
        });
        _scrollToBottom();
      }
    });
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _sendCommand(String cmd) {
    final trimmed = cmd.trim();
    if (trimmed.isEmpty) return;
    setState(() {
      _logs.add('root@android:# $trimmed');
    });
    widget.wsService.sendShell(trimmed);
    _inputController.clear();
    _scrollToBottom();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.75,
      decoration: const BoxDecoration(
        color: Color(0xFF090D14),
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        boxShadow: [
          BoxShadow(color: Colors.black87, blurRadius: 20, spreadRadius: 5)
        ],
      ),
      child: Column(
        children: [
          // Header Bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: const BoxDecoration(
              color: Color(0xFF111622),
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Row(
                  children: [
                    Icon(Icons.terminal, color: Color(0xFF06B6D4), size: 20),
                    SizedBox(width: 8),
                    Text(
                      'Root Interactive Shell',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
                IconButton(
                  icon: const Icon(Icons.clear_all, color: Colors.grey),
                  onPressed: () {
                    setState(() {
                      _logs.clear();
                      _logs.add('[Terminal dibersihkan]');
                    });
                  },
                  tooltip: 'Clear Logs',
                )
              ],
            ),
          ),

          // Shortcut Pills
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              children: [
                _buildPresetButton('ps -A'),
                _buildPresetButton('df -h'),
                _buildPresetButton('dumpsys battery'),
                _buildPresetButton('top -n 1 -m 5'),
                _buildPresetButton('logcat -d -t 20'),
              ],
            ),
          ),

          // Terminal Output
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.all(12),
              itemCount: _logs.length,
              itemBuilder: (context, index) {
                final text = _logs[index];
                final isCmd = text.startsWith('root@android:#');
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Text(
                    text,
                    style: TextStyle(
                      fontFamily: 'monospace',
                      fontSize: 12,
                      color: isCmd ? const Color(0xFF06B6D4) : const Color(0xFFE2E8F0),
                    ),
                  ),
                );
              },
            ),
          ),

          // Input Form
          Container(
            padding: const EdgeInsets.all(8),
            color: const Color(0xFF111622),
            child: Row(
              children: [
                const Text(
                  '# ',
                  style: TextStyle(
                    color: Color(0xFFF43F5E),
                    fontWeight: FontWeight.bold,
                    fontFamily: 'monospace',
                  ),
                ),
                Expanded(
                  child: TextField(
                    controller: _inputController,
                    style: const TextStyle(
                      color: Colors.white,
                      fontFamily: 'monospace',
                      fontSize: 13,
                    ),
                    decoration: const InputDecoration(
                      hintText: 'Ketik perintah shell root...',
                      hintStyle: TextStyle(color: Colors.white30),
                      border: InputBorder.none,
                      isDense: true,
                    ),
                    onSubmitted: _sendCommand,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.send, color: Color(0xFF06B6D4)),
                  onPressed: () => _sendCommand(_inputController.text),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPresetButton(String cmd) {
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: ActionChip(
        label: Text(
          cmd,
          style: const TextStyle(
            fontSize: 11,
            fontFamily: 'monospace',
            color: Color(0xFF06B6D4),
          ),
        ),
        backgroundColor: const Color(0xFF182030),
        onPressed: () => _sendCommand(cmd),
      ),
    );
  }

  @override
  void dispose() {
    _inputController.dispose();
    _scrollController.dispose();
    super.dispose();
  }
}
