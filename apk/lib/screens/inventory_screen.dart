import 'package:flutter/material.dart';

import '../services/api.dart';
import '../widgets/search_filter_bar.dart';
import 'inventory_history_screen.dart';
import 'login_screen.dart';

const _inventoryStatusFilters = [
  FilterOption(value: 'in_stock', label: 'In stock'),
  FilterOption(value: 'assigned', label: 'Assigned'),
  FilterOption(value: 'damaged', label: 'Damaged'),
  FilterOption(value: 'repair', label: 'Repair'),
];

class InventoryScreen extends StatefulWidget {
  const InventoryScreen({super.key});

  @override
  State<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends State<InventoryScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _summary = {};
  List<Map<String, dynamic>> _types = [];
  List<dynamic> _items = [];
  int? _typeId;
  final _searchCtrl = TextEditingController();
  String _status = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load([int? typeId]) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.inventory(
        typeId: typeId ?? _typeId,
        q: _searchCtrl.text,
        status: _status,
      );
      if (!mounted) return;
      final types = ((data['types'] as List?) ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      final selected = data['type_id'];
      setState(() {
        _types = types;
        _typeId = selected == null ? null : (selected as num).toInt();
        _summary = Map<String, dynamic>.from(data['summary'] as Map? ?? {});
        _items = (data['items'] as List?) ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final msg = e.toString().replaceFirst('Exception: ', '');
      if (msg.contains('Session expired')) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const LoginScreen()),
        );
        return;
      }
      setState(() {
        _error = msg;
        _loading = false;
      });
    }
  }

  Future<void> _selectType(int id) async {
    if (_typeId == id) return;
    setState(() => _typeId = id);
    await _load(id);
  }

  Future<void> _openFilter() async {
    final res = await showListFilterSheet(
      context: context,
      title: 'Inventory filters',
      statusOptions: _inventoryStatusFilters,
      currentStatus: _status,
      showFollowUp: false,
    );
    if (res == null || !mounted) return;
    setState(() => _status = res['status'] ?? '');
    await _load(_typeId);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _types.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _types.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!),
            const SizedBox(height: 12),
            FilledButton(onPressed: () => _load(), child: const Text('Retry')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _load(_typeId),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          SearchFilterBar(
            controller: _searchCtrl,
            hint: 'Search name or code…',
            hasActiveFilter: _status.isNotEmpty,
            onSubmitted: (_) => _load(_typeId),
            onClear: () => _load(_typeId),
            onFilterTap: _openFilter,
          ),
          const SizedBox(height: 12),
          if (_types.isNotEmpty) ...[
            _TypeTabs(
              types: _types,
              selectedId: _typeId,
              onSelect: _selectType,
            ),
            const SizedBox(height: 12),
          ],
          Row(
            children: [
              Expanded(
                child: _MiniStat(
                  label: 'Total',
                  value: '${_summary['total'] ?? 0}',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniStat(
                  label: 'In stock',
                  value: '${_summary['in_stock'] ?? 0}',
                  accent: const Color(0xFF047857),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (_loading)
            const Padding(
              padding: EdgeInsets.only(top: 40),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_items.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 40),
              child: Center(
                child: Text(
                  'No items found',
                  style: TextStyle(color: Color(0xFF94A3B8)),
                ),
              ),
            )
          else
            ..._items.map((raw) {
              final item = Map<String, dynamic>.from(raw as Map);
              return _ItemCard(
                item: item,
                onTap: () {
                  final id = (item['id'] as num).toInt();
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => InventoryHistoryScreen(
                        itemId: id,
                        itemName: '${item['name'] ?? 'Item'}',
                      ),
                    ),
                  );
                },
              );
            }),
        ],
      ),
    );
  }
}

class _TypeTabs extends StatelessWidget {
  const _TypeTabs({
    required this.types,
    required this.selectedId,
    required this.onSelect,
  });

  final List<Map<String, dynamic>> types;
  final int? selectedId;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    const brand = Color(0xFF0F6CBD);
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFEDEBE9)),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (final t in types)
              Padding(
                padding: const EdgeInsets.only(right: 2),
                child: InkWell(
                  onTap: () => onSelect((t['id'] as num).toInt()),
                  borderRadius: BorderRadius.circular(8),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: selectedId == (t['id'] as num).toInt()
                          ? const Color(0xFFDEECF9)
                          : Colors.transparent,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${t['name']}'.toUpperCase(),
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.3,
                            color: selectedId == (t['id'] as num).toInt()
                                ? brand
                                : const Color(0xFF64748B),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          '${t['total'] ?? 0}',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            color: selectedId == (t['id'] as num).toInt()
                                ? brand.withValues(alpha: 0.7)
                                : const Color(0xFF94A3B8),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ItemCard extends StatelessWidget {
  const _ItemCard({required this.item, required this.onTap});

  final Map<String, dynamic> item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFEDEBE9)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${item['name'] ?? '—'}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                      ),
                    ),
                  ),
                  Text(
                    'x${item['quantity'] ?? 0}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(width: 4),
                  const Icon(
                    Icons.chevron_right,
                    size: 18,
                    color: Color(0xFF94A3B8),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                [
                  if ('${item['type'] ?? ''}'.isNotEmpty) item['type'],
                  if ('${item['code'] ?? ''}'.isNotEmpty) item['code'],
                ].join(' · '),
                style: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
              ),
              const SizedBox(height: 6),
              Text(
                '${item['status_label'] ?? item['status'] ?? ''}',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF0F6CBD),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({
    required this.label,
    required this.value,
    this.accent = const Color(0xFF201F1E),
  });

  final String label;
  final String value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFEDEBE9)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              color: accent.withValues(alpha: 0.75),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}
