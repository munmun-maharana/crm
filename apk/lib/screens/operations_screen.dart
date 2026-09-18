import 'package:flutter/material.dart';

import '../services/api.dart';
import '../widgets/search_filter_bar.dart';
import 'crm_detail_screen.dart';
import 'login_screen.dart';

const _statusFilters = [
  FilterOption(value: 'new', label: 'New'),
  FilterOption(value: 'interested', label: 'Interested'),
  FilterOption(value: 'contacted', label: 'Contacted'),
  FilterOption(value: 'converted', label: 'Converted'),
  FilterOption(value: 'lost', label: 'Lost'),
  FilterOption(value: 'request', label: 'Request'),
];

class OperationsScreen extends StatefulWidget {
  const OperationsScreen({super.key});

  @override
  State<OperationsScreen> createState() => _OperationsScreenState();
}

class _OperationsScreenState extends State<OperationsScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _summary = {};
  List<dynamic> _rows = [];
  final _searchCtrl = TextEditingController();
  String _status = '';
  String _filter = '';

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

  bool get _hasFilter => _status.isNotEmpty || _filter.isNotEmpty;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.operations(
        q: _searchCtrl.text,
        status: _status,
        filter: _filter,
      );
      if (!mounted) return;
      setState(() {
        _summary = Map<String, dynamic>.from(data['summary'] as Map? ?? {});
        _rows = (data['customers'] as List?) ?? [];
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

  Future<void> _openFilter() async {
    final res = await showListFilterSheet(
      context: context,
      title: 'Team Ops filters',
      statusOptions: _statusFilters,
      currentStatus: _status,
      currentFilter: _filter,
      showFollowUp: true,
    );
    if (res == null || !mounted) return;
    setState(() {
      _status = res['status'] ?? '';
      _filter = res['filter'] ?? '';
    });
    await _load();
  }

  Future<void> _openDetail(int id) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CrmDetailScreen(
          customerId: id,
          pipeline: 'operations',
        ),
      ),
    );
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _rows.isEmpty && _error == null && _summary.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _rows.isEmpty && _summary.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!),
            const SizedBox(height: 12),
            FilledButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
          children: [
            SearchFilterBar(
              controller: _searchCtrl,
              hint: 'Search name, phone, company…',
              hasActiveFilter: _hasFilter,
              onSubmitted: (_) => _load(),
              onClear: _load,
              onFilterTap: _openFilter,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _StatCard(
                    label: 'Total',
                    value: '${_summary['total'] ?? 0}',
                    color: const Color(0xFF0F6CBD),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _StatCard(
                    label: 'Today',
                    value: '${_summary['today'] ?? 0}',
                    color: const Color(0xFF0F766E),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _StatCard(
                    label: 'Today F/ups',
                    value: '${_summary['follow_up'] ?? 0}',
                    color: const Color(0xFFB45309),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _StatCard(
                    label: 'New',
                    value: '${_summary['new'] ?? 0}',
                    color: const Color(0xFF6D28D9),
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
            else if (_rows.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 48),
                child: Center(
                  child: Text(
                    'No operation records',
                    style: TextStyle(color: Color(0xFF94A3B8)),
                  ),
                ),
              )
            else
              ..._rows.map((raw) {
                final row = Map<String, dynamic>.from(raw as Map);
                final id = (row['id'] as num).toInt();
                return Material(
                  color: Colors.transparent,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: () => _openDetail(id),
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
                                  '${row['name'] ?? '—'}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                    fontSize: 14,
                                  ),
                                ),
                              ),
                              if ((row['status'] ?? '').toString().isNotEmpty)
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 8,
                                    vertical: 4,
                                  ),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFDEECF9),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    '${row['status']}',
                                    style: const TextStyle(
                                      color: Color(0xFF0F6CBD),
                                      fontWeight: FontWeight.w700,
                                      fontSize: 11,
                                    ),
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
                              if ((row['company'] ?? '').toString().isNotEmpty)
                                row['company'],
                              if ((row['phone'] ?? '').toString().isNotEmpty)
                                row['phone'],
                              if ((row['city'] ?? '').toString().isNotEmpty)
                                row['city'],
                            ].join(' · '),
                            style: const TextStyle(
                              color: Color(0xFF64748B),
                              fontSize: 12,
                            ),
                          ),
                          if ((row['follow_up'] ?? '').toString().isNotEmpty) ...[
                            const SizedBox(height: 6),
                            Text(
                              'Follow-up: ${row['follow_up']}',
                              style: const TextStyle(
                                color: Color(0xFFB45309),
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFEDEBE9)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w800,
              color: color.withValues(alpha: 0.85),
              letterSpacing: 0.4,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: Color(0xFF201F1E),
            ),
          ),
        ],
      ),
    );
  }
}
