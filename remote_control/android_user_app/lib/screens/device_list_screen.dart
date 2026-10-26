import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../models/device_session.dart';
import '../config/constants.dart';
import 'remote_control_screen.dart';

class DeviceListScreen extends StatefulWidget {
  const DeviceListScreen({super.key});

  @override
  State<DeviceListScreen> createState() => _DeviceListScreenState();
}

class _DeviceListScreenState extends State<DeviceListScreen> {
  final TextEditingController _hostController = TextEditingController(text: AppConstants.defaultRelayHost);
  final TextEditingController _portController = TextEditingController(text: '443');
  final TextEditingController _tokenController = TextEditingController(text: AppConstants.defaultAdminKey);

  List<DeviceSession> _devices = [];
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _fetchDevices();
  }

  Future<void> _fetchDevices() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    String rawHost = _hostController.text.trim();
    // Bersihkan dari protocol atau path /ws/device jika user tidak sengaja memasukkannya
    rawHost = rawHost.replaceAll(RegExp(r'^(https?|wss?)://'), '');
    if (rawHost.contains('/')) rawHost = rawHost.split('/').first;
    if (rawHost.contains(':')) rawHost = rawHost.split(':').first;

    final port = int.tryParse(_portController.text.trim()) ?? 8099;
    final isDomain = rawHost.contains('.') && !RegExp(r'^\d+\.\d+\.\d+\.\d+$').hasMatch(rawHost);
    final isHttps = isDomain || port == 443 || _hostController.text.trim().startsWith('https://');
    
    final url = isHttps 
        ? Uri.parse('https://$rawHost/api/devices')
        : Uri.parse('http://$rawHost:$port/api/devices');

    try {
      final res = await http.get(url).timeout(const Duration(seconds: 5));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final list = (data['devices'] as List<dynamic>? ?? [])
            .map((item) => DeviceSession.fromJson(item))
            .toList();

        setState(() {
          _devices = list;
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = 'Server merespon dengan kode: ${res.statusCode}\nURL: $url';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Gagal menghubungi server relay ($url)\nDetail: $e';
        _isLoading = false;
      });
    }
  }

  void _navigateToControl(DeviceSession device) {
    String rawHost = _hostController.text.trim();
    rawHost = rawHost.replaceAll(RegExp(r'^(https?|wss?)://'), '');
    if (rawHost.contains('/')) rawHost = rawHost.split('/').first;
    if (rawHost.contains(':')) rawHost = rawHost.split(':').first;

    final port = int.tryParse(_portController.text.trim()) ?? 8099;
    final token = _tokenController.text.trim();

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (ctx) => RemoteControlScreen(
          device: device,
          host: rawHost,
          port: port,
          token: token,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0D14),
      appBar: AppBar(
        backgroundColor: const Color(0xFF111622),
        title: const Text(
          'CyberControl Remote',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Color(0xFF06B6D4)),
            onPressed: _fetchDevices,
          ),
        ],
      ),
      body: Column(
        children: [
          // Connection Config Card
          Container(
            padding: const EdgeInsets.all(16),
            margin: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFF111622),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFF1E293B)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Konfigurasi Relay Server',
                  style: TextStyle(
                    color: Color(0xFF06B6D4),
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      flex: 3,
                      child: TextField(
                        controller: _hostController,
                        style: const TextStyle(color: Colors.white, fontSize: 13),
                        decoration: const InputDecoration(
                          labelText: 'Host / Domain VPS',
                          hintText: 'belajaridn.id',
                          hintStyle: TextStyle(color: Colors.white30, fontSize: 11),
                          helperText: 'Cukup nama domain (tanpa /ws/...)',
                          helperStyle: TextStyle(color: Color(0xFF06B6D4), fontSize: 10),
                          labelStyle: TextStyle(color: Colors.white60, fontSize: 11),
                          isDense: true,
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 1,
                      child: TextField(
                        controller: _portController,
                        keyboardType: TextInputType.number,
                        style: const TextStyle(color: Colors.white, fontSize: 13),
                        decoration: const InputDecoration(
                          labelText: 'Port',
                          labelStyle: TextStyle(color: Colors.white60, fontSize: 11),
                          isDense: true,
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _tokenController,
                  obscureText: true,
                  style: const TextStyle(color: Colors.white, fontSize: 13),
                  decoration: const InputDecoration(
                    labelText: 'Admin Secret Key',
                    labelStyle: TextStyle(color: Colors.white60, fontSize: 11),
                    isDense: true,
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),

          // Header List
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Perangkat Aktif (${_devices.length})',
                  style: const TextStyle(
                    color: Colors.white70,
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
                if (_isLoading)
                  const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF06B6D4)),
                  ),
              ],
            ),
          ),

          // Devices List
          Expanded(
            child: _errorMessage != null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.cloud_off, color: Color(0xFFF43F5E), size: 40),
                          const SizedBox(height: 8),
                          Text(
                            _errorMessage!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Colors.white70, fontSize: 12),
                          ),
                          const SizedBox(height: 12),
                          ElevatedButton(
                            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF06B6D4)),
                            onPressed: _fetchDevices,
                            child: const Text('Coba Lagi', style: TextStyle(color: Colors.black)),
                          ),
                        ],
                      ),
                    ),
                  )
                : _devices.isEmpty
                    ? const Center(
                        child: Text(
                          'Tidak ada perangkat yang online.\nJalankan daemon root di HP target.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.white38, fontSize: 12),
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _devices.length,
                        itemBuilder: (context, index) {
                          final dev = _devices[index];
                          return Card(
                            color: const Color(0xFF111622),
                            margin: const EdgeInsets.only(bottom: 10),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                              side: const BorderSide(color: Color(0xFF1E293B)),
                            ),
                            child: ListTile(
                              leading: Container(
                                width: 40,
                                height: 40,
                                decoration: BoxDecoration(
                                  color: const Color(0xFF182030),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Icon(Icons.phone_android, color: Color(0xFF06B6D4)),
                              ),
                              title: Text(
                                dev.deviceName,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                              ),
                              subtitle: Text(
                                'ID: ${dev.deviceId} • ${dev.screenWidth}x${dev.screenHeight}',
                                style: const TextStyle(color: Colors.white38, fontSize: 11),
                              ),
                              trailing: ElevatedButton(
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF06B6D4),
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                ),
                                onPressed: () => _navigateToControl(dev),
                                child: const Text(
                                  'KONTROL',
                                  style: TextStyle(
                                    color: Colors.black,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 11,
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _hostController.dispose();
    _portController.dispose();
    _tokenController.dispose();
    super.dispose();
  }
}
