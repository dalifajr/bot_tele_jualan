import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

void main() {
  runApp(const ListenerApp());
}

class ListenerApp extends StatelessWidget {
  const ListenerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Jualan Listener',
      theme: ThemeData(
        colorSchemeSeed: Colors.teal,
        useMaterial3: true,
      ),
      home: const ListenerHomePage(),
    );
  }
}

class ListenerHomePage extends StatefulWidget {
  const ListenerHomePage({super.key});

  @override
  State<ListenerHomePage> createState() => _ListenerHomePageState();
}

class _ListenerHomePageState extends State<ListenerHomePage> {
  static const MethodChannel _channel = MethodChannel('jualan_listener/native');

  final TextEditingController _endpointController = TextEditingController();
  final TextEditingController _secretController = TextEditingController();
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _amountController = TextEditingController(text: '50123');
  final TextEditingController _sourceAppController = TextEditingController(text: 'TEST_APP');
  final TextEditingController _referenceController = TextEditingController();
  final TextEditingController _rawTextController = TextEditingController(
    text: 'Pembayaran berhasil Rp50.123',
  );

  List<_InstalledApp> _apps = const [];
  Set<String> _selectedPackages = <String>{};
  bool _monitorAll = true;
  bool _listenerEnabled = false;
  int _pendingCount = 0;
  bool _isLoading = false;
  String _status = 'Siap';

  @override
  void initState() {
    super.initState();
    _loadAll();
  }

  Future<void> _loadAll() async {
    setState(() => _isLoading = true);
    try {
      final configRaw = await _channel.invokeMethod<Map<Object?, Object?>>('getConfig');
      final selectedRaw = await _channel.invokeMethod<List<Object?>>('getSelectedApps');
      final appsRaw = await _channel.invokeMethod<List<Object?>>('getInstalledApps');
      final listenerEnabledRaw = await _channel.invokeMethod<bool>('isListenerEnabled');
      final pendingRaw = await _channel.invokeMethod<int>('getPendingQueueCount');

      final config = configRaw ?? <Object?, Object?>{};
      _endpointController.text = (config['endpoint'] ?? '').toString();
      _secretController.text = (config['secret'] ?? '').toString();
      _monitorAll = config['monitorAll'] == true;

      _selectedPackages = (selectedRaw ?? const <Object?>[])
          .map((e) => e.toString())
          .toSet();

      _apps = (appsRaw ?? const <Object?>[])
          .map((raw) => _InstalledApp.fromMap((raw as Map).cast<Object?, Object?>()))
          .toList();

      _listenerEnabled = listenerEnabledRaw ?? false;
      _pendingCount = pendingRaw ?? 0;
      _status = 'Config dimuat';
    } on PlatformException catch (e) {
      _status = 'Gagal load: ${e.message}';
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _saveConfig() async {
    setState(() => _isLoading = true);
    try {
      await _channel.invokeMethod('setConfig', {
        'endpoint': _endpointController.text.trim(),
        'secret': _secretController.text.trim(),
        'monitorAll': _monitorAll,
      });
      _status = 'Konfigurasi tersimpan';
      await _loadQueueCount();
    } on PlatformException catch (e) {
      _status = 'Gagal simpan config: ${e.message}';
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _saveSelectedApps() async {
    try {
      await _channel.invokeMethod('setSelectedApps', {
        'packageNames': _selectedPackages.toList(),
      });
      setState(() {
        _status = 'Daftar aplikasi listener diperbarui';
      });
    } on PlatformException catch (e) {
      setState(() {
        _status = 'Gagal simpan daftar app: ${e.message}';
      });
    }
  }

  Future<void> _openSettings() async {
    await _channel.invokeMethod('openNotificationListenerSettings');
    await _refreshListenerStatus();
  }

  Future<void> _refreshListenerStatus() async {
    final enabled = await _channel.invokeMethod<bool>('isListenerEnabled');
    if (!mounted) return;
    setState(() {
      _listenerEnabled = enabled ?? false;
      _status = _listenerEnabled
          ? 'Notification listener aktif'
          : 'Notification listener belum aktif';
    });
  }

  Future<void> _testConnection() async {
    setState(() => _isLoading = true);
    try {
      final response = await _channel.invokeMethod<Map<Object?, Object?>>(
        'testConnectionNative',
        {
          'endpoint': _endpointController.text.trim(),
          'secret': _secretController.text.trim(),
        },
      );

      final ok = response?['ok'] == true;
      final code = response?['statusCode'];
      final body = response?['body'];
      final err = response?['error'];

      setState(() {
        _status = ok
            ? 'Test koneksi sukses (HTTP $code)'
            : 'Test koneksi gagal (HTTP $code): ${err ?? body}';
      });
    } on PlatformException catch (e) {
      setState(() {
        _status = 'Test koneksi error: ${e.message}';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _sendTestPaymentPayload() async {
    final amount = int.tryParse(_amountController.text.trim()) ?? 0;
    if (amount <= 0) {
      setState(() {
        _status = 'Amount test harus angka > 0';
      });
      return;
    }

    setState(() => _isLoading = true);
    try {
      await _channel.invokeMethod('enqueueTestPayload', {
        'amount': amount,
        'sourceApp': _sourceAppController.text.trim(),
        'reference': _referenceController.text.trim().isEmpty
            ? null
            : _referenceController.text.trim(),
        'rawText': _rawTextController.text.trim(),
      });
      await _channel.invokeMethod('enqueueFlush');
      await _loadQueueCount();
      setState(() {
        _status = 'Payload test dimasukkan ke queue dan dikirim';
      });
    } on PlatformException catch (e) {
      setState(() {
        _status = 'Gagal kirim payload test: ${e.message}';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _loadQueueCount() async {
    final count = await _channel.invokeMethod<int>('getPendingQueueCount');
    if (!mounted) return;
    setState(() {
      _pendingCount = count ?? 0;
    });
  }

  @override
  Widget build(BuildContext context) {
    final query = _searchController.text.trim().toLowerCase();
    final filteredApps = _apps
        .where((app) {
          if (query.isEmpty) return true;
          return app.label.toLowerCase().contains(query) ||
              app.packageName.toLowerCase().contains(query);
        })
        .toList();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Jualan Notification Listener'),
        actions: [
          IconButton(
            onPressed: _isLoading ? null : _loadAll,
            icon: const Icon(Icons.refresh),
          )
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _listenerEnabled
                              ? 'Listener Status: AKTIF'
                              : 'Listener Status: BELUM AKTIF',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: _listenerEnabled ? Colors.green : Colors.red,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text('Pending queue: $_pendingCount event'),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            FilledButton(
                              onPressed: _openSettings,
                              child: const Text('Aktifkan Listener'),
                            ),
                            OutlinedButton(
                              onPressed: _refreshListenerStatus,
                              child: const Text('Cek Status'),
                            ),
                            OutlinedButton(
                              onPressed: () async {
                                await _channel.invokeMethod('enqueueFlush');
                                await _loadQueueCount();
                                setState(() {
                                  _status = 'Flush queue dijalankan';
                                });
                              },
                              child: const Text('Flush Queue'),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Konfigurasi Endpoint',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _endpointController,
                          decoration: const InputDecoration(
                            labelText: 'Endpoint payment',
                            hintText: 'https://domain/listener/payment',
                          ),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _secretController,
                          decoration: const InputDecoration(labelText: 'Shared secret'),
                        ),
                        const SizedBox(height: 8),
                        SwitchListTile(
                          value: _monitorAll,
                          onChanged: (value) {
                            setState(() {
                              _monitorAll = value;
                            });
                          },
                          title: const Text('Dengarkan semua aplikasi'),
                          contentPadding: EdgeInsets.zero,
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            FilledButton(
                              onPressed: _saveConfig,
                              child: const Text('Simpan Config'),
                            ),
                            OutlinedButton(
                              onPressed: _testConnection,
                              child: const Text('Test Koneksi'),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Aplikasi Yang Didengarkan',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _searchController,
                          onChanged: (_) => setState(() {}),
                          decoration: const InputDecoration(
                            prefixIcon: Icon(Icons.search),
                            hintText: 'Cari nama/package aplikasi',
                          ),
                        ),
                        const SizedBox(height: 8),
                        if (_monitorAll)
                          const Text('Mode semua aplikasi aktif, pilihan per-app dinonaktifkan.'),
                        SizedBox(
                          height: 240,
                          child: ListView.builder(
                            itemCount: filteredApps.length,
                            itemBuilder: (context, index) {
                              final app = filteredApps[index];
                              final checked = _selectedPackages.contains(app.packageName);
                              return CheckboxListTile(
                                value: checked,
                                onChanged: _monitorAll
                                    ? null
                                    : (value) {
                                        setState(() {
                                          if (value == true) {
                                            _selectedPackages.add(app.packageName);
                                          } else {
                                            _selectedPackages.remove(app.packageName);
                                          }
                                        });
                                      },
                                title: Text(app.label),
                                subtitle: Text(app.packageName),
                                dense: true,
                                controlAffinity: ListTileControlAffinity.leading,
                              );
                            },
                          ),
                        ),
                        const SizedBox(height: 8),
                        OutlinedButton(
                          onPressed: _monitorAll ? null : _saveSelectedApps,
                          child: const Text('Simpan Pilihan Aplikasi'),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Test Kirim Payload Payment',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _amountController,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(labelText: 'Amount (Rp)'),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _sourceAppController,
                          decoration: const InputDecoration(labelText: 'Source app'),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _referenceController,
                          decoration: const InputDecoration(
                            labelText: 'Reference (opsional)',
                            hintText: 'PAY-ORD...',
                          ),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _rawTextController,
                          minLines: 2,
                          maxLines: 5,
                          decoration: const InputDecoration(labelText: 'Raw text notifikasi'),
                        ),
                        const SizedBox(height: 8),
                        FilledButton(
                          onPressed: _sendTestPaymentPayload,
                          child: const Text('Kirim Payload Test'),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text('Status: $_status'),
              ],
            ),
    );
  }
}

class _InstalledApp {
  final String packageName;
  final String label;

  const _InstalledApp({required this.packageName, required this.label});

  factory _InstalledApp.fromMap(Map<Object?, Object?> map) {
    return _InstalledApp(
      packageName: (map['packageName'] ?? '').toString(),
      label: (map['label'] ?? '').toString(),
    );
  }
}
