import 'package:flutter/material.dart';

import '../services/api.dart';

class ActivationRequestScreen extends StatefulWidget {
  const ActivationRequestScreen({
    super.key,
    required this.customerId,
    required this.customerName,
    this.activationRequestId,
  });

  final int customerId;
  final String customerName;
  final int? activationRequestId;

  @override
  State<ActivationRequestScreen> createState() =>
      _ActivationRequestScreenState();
}

class _ActivationRequestScreenState extends State<ActivationRequestScreen> {
  bool _loading = true;
  bool _saving = false;
  String? _error;
  List<Map<String, dynamic>> _stock = [];
  final Map<int, int?> _selectedByCat = {};
  final _notesCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.inventoryTypes();
      if (!mounted) return;
      final stock = ((data['my_stock'] as List?) ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      setState(() {
        _stock = stock;
        for (final cat in stock) {
          final id = (cat['id'] as num).toInt();
          _selectedByCat[id] = null;
        }
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString().replaceFirst('Exception: ', '');
        _loading = false;
      });
    }
  }

  Future<void> _submit() async {
    final ids = _selectedByCat.values.whereType<int>().toList();
    if (ids.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select at least one device')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      final payload = <String, dynamic>{
        'inventory_item_ids': ids,
        'notes':
            _notesCtrl.text.trim().isEmpty ? null : _notesCtrl.text.trim(),
      };
      if (widget.activationRequestId != null) {
        payload['activation_request_id'] = widget.activationRequestId;
      }
      final res = await ApiClient.instance.submitActivation(
        widget.customerId,
        payload,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${res['message'] ?? 'Submitted'}')),
      );
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
      setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    const brand = Color(0xFF0F6CBD);
    return Scaffold(
      backgroundColor: const Color(0xFFF3F2F1),
      appBar: AppBar(
        title: const Text('Activation request'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF201F1E),
        elevation: 0,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(
                      widget.customerName,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'Select devices from your stock (per category)',
                      style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                    ),
                    const SizedBox(height: 16),
                    if (_stock.isEmpty)
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFFBEB),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFFDE68A)),
                        ),
                        child: const Text(
                          'No inventory on your ID. Assign stock in Inventory first.',
                          style: TextStyle(
                            fontSize: 13,
                            color: Color(0xFF92400E),
                          ),
                        ),
                      )
                    else
                      ..._stock.map((cat) {
                        final catId = (cat['id'] as num).toInt();
                        final items = ((cat['items'] as List?) ?? [])
                            .map((e) => Map<String, dynamic>.from(e as Map))
                            .toList();
                        return Container(
                          margin: const EdgeInsets.only(bottom: 10),
                          padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFEDEBE9)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${cat['name'] ?? 'Category'}',
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF64748B),
                                ),
                              ),
                              const SizedBox(height: 6),
                              DropdownButtonFormField<int?>(
                                value: _selectedByCat[catId],
                                isExpanded: true,
                                decoration: InputDecoration(
                                  filled: true,
                                  fillColor: const Color(0xFFF8FAFC),
                                  contentPadding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 10,
                                  ),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(10),
                                    borderSide: const BorderSide(
                                      color: Color(0xFFEDEBE9),
                                    ),
                                  ),
                                  enabledBorder: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(10),
                                    borderSide: const BorderSide(
                                      color: Color(0xFFEDEBE9),
                                    ),
                                  ),
                                ),
                                hint: Text('Select ${cat['name'] ?? 'item'}…'),
                                items: [
                                  const DropdownMenuItem<int?>(
                                    value: null,
                                    child: Text('— None —'),
                                  ),
                                  ...items.map((item) {
                                    final id = (item['id'] as num).toInt();
                                    return DropdownMenuItem<int?>(
                                      value: id,
                                      child: Text(
                                        '${item['label'] ?? item['name'] ?? ''}',
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    );
                                  }),
                                ],
                                onChanged: (v) =>
                                    setState(() => _selectedByCat[catId] = v),
                              ),
                            ],
                          ),
                        );
                      }),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _notesCtrl,
                      minLines: 2,
                      maxLines: 4,
                      decoration: InputDecoration(
                        hintText: 'Notes (optional)',
                        filled: true,
                        fillColor: Colors.white,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFFEDEBE9)),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFFEDEBE9)),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: (_saving || _stock.isEmpty) ? null : _submit,
                      style: FilledButton.styleFrom(
                        backgroundColor: brand,
                        minimumSize: const Size.fromHeight(48),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: _saving
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Submit request'),
                    ),
                  ],
                ),
    );
  }
}
