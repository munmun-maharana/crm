import 'package:flutter/material.dart';

import '../services/api.dart';
import 'login_screen.dart';

class CrmHistoryScreen extends StatefulWidget {
  const CrmHistoryScreen({
    super.key,
    required this.customerId,
    required this.customerName,
    this.initialActivities,
    this.pipeline = 'crm',
  });

  final int customerId;
  final String customerName;
  final List<dynamic>? initialActivities;
  final String pipeline;

  @override
  State<CrmHistoryScreen> createState() => _CrmHistoryScreenState();
}

class _CrmHistoryScreenState extends State<CrmHistoryScreen> {
  late List<dynamic> _activities;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _activities = List<dynamic>.from(widget.initialActivities ?? const []);
    if (_activities.isEmpty) {
      _load();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.crmShow(
        widget.customerId,
        pipeline: widget.pipeline,
      );
      if (!mounted) return;
      setState(() {
        _activities = (data['activities'] as List?) ?? [];
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF3F2F1),
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Chat history', style: TextStyle(fontSize: 16)),
            Text(
              widget.customerName,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w500,
                color: Color(0xFF64748B),
              ),
            ),
          ],
        ),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF201F1E),
        elevation: 0,
      ),
      body: _loading && _activities.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _activities.isEmpty
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
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _activities.isEmpty
                      ? ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: const [
                            SizedBox(height: 120),
                            Center(
                              child: Text(
                                'No timeline yet',
                                style: TextStyle(color: Color(0xFF94A3B8)),
                              ),
                            ),
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _activities.length,
                          itemBuilder: (context, i) {
                            final a = Map<String, dynamic>.from(
                              _activities[i] as Map,
                            );
                            return _HistoryTile(activity: a);
                          },
                        ),
                ),
    );
  }
}

class _HistoryTile extends StatelessWidget {
  const _HistoryTile({required this.activity});

  final Map<String, dynamic> activity;

  @override
  Widget build(BuildContext context) {
    final meta = activity['meta'];
    final metaMap = meta is Map ? Map<String, dynamic>.from(meta) : null;

    return Container(
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
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  '${activity['type'] ?? 'note'}'.toUpperCase(),
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F6CBD),
                  ),
                ),
              ),
              if (metaMap?['status_to'] != null) ...[
                const SizedBox(width: 6),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEE2E2),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    '${metaMap!['status_to']}'.toUpperCase(),
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFFB91C1C),
                    ),
                  ),
                ),
              ],
              const Spacer(),
              Text(
                '${activity['created_at'] ?? ''}',
                style: const TextStyle(
                  fontSize: 11,
                  color: Color(0xFF94A3B8),
                ),
              ),
            ],
          ),
          if ((activity['title'] ?? '').toString().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              '${activity['title']}',
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ],
          if ((activity['body'] ?? '').toString().isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              '${activity['body']}',
              style: const TextStyle(
                fontSize: 13,
                color: Color(0xFF475569),
                height: 1.35,
              ),
            ),
          ],
          if ((activity['user_name'] ?? '').toString().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              'by ${activity['user_name']}',
              style: const TextStyle(
                fontSize: 11,
                color: Color(0xFF94A3B8),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
