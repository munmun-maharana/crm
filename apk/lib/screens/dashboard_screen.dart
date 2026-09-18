import 'package:flutter/material.dart';

import '../services/api.dart';
import 'login_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _data;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.dashboard();
      if (!mounted) return;
      setState(() {
        _data = data;
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
    const brand = Color(0xFF0F6CBD);
    final showCrm = ApiClient.instance.canAccessCrm && _data?['show_crm'] == true;
    final showStock = ApiClient.instance.canAccessInventory;
    final crm = _data?['crm'] as Map<String, dynamic>?;
    final summary = _data?['stock_summary'] as Map<String, dynamic>? ?? {};
    final byType = (_data?['stock_by_type'] as List?) ?? [];

    Widget body;
    if (_loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (_error != null) {
      body = Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    } else {
      body = RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (showCrm && crm != null) ...[
              const _SectionTitle('CRM'),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: _StatCard(
                      label: 'Total leads',
                      value: '${crm['total'] ?? 0}',
                      color: brand,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _StatCard(
                      label: 'Today',
                      value: '${crm['today'] ?? 0}',
                      color: const Color(0xFF0F766E),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _StatCard(
                      label: 'Today F/ups',
                      value: '${crm['follow_up'] ?? 0}',
                      color: const Color(0xFFB45309),
                    ),
                  ),
                ],
              ),
              if (showStock) const SizedBox(height: 20),
            ],
            if (showStock) ...[
              const _SectionTitle('My stock'),
              const SizedBox(height: 8),
              _StockDonut(
                total: (summary['total'] as num?)?.toInt() ?? 0,
                inStock: (summary['in_stock'] as num?)?.toInt() ?? 0,
                assigned: (summary['assigned'] as num?)?.toInt() ?? 0,
                damaged: (summary['damaged'] as num?)?.toInt() ?? 0,
                repair: (summary['repair'] as num?)?.toInt() ?? 0,
              ),
              if (byType.isNotEmpty) ...[
                const SizedBox(height: 20),
                const _SectionTitle('By type'),
                const SizedBox(height: 8),
                _ByTypeChart(
                  rows: byType
                      .map((raw) => Map<String, dynamic>.from(raw as Map))
                      .toList(),
                ),
              ],
            ],
            if (!showCrm && !showStock)
              const Padding(
                padding: EdgeInsets.only(top: 40),
                child: Center(
                  child: Text(
                    'No dashboard modules enabled for your access.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Color(0xFF94A3B8)),
                  ),
                ),
              ),
          ],
        ),
      );
    }

    if (widget.embedded) return body;

    return Scaffold(
      backgroundColor: const Color(0xFFF3F2F1),
      body: body,
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: const TextStyle(
        fontSize: 11,
        letterSpacing: 1.1,
        fontWeight: FontWeight.w800,
        color: Color(0xFF64748B),
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
              color: color.withValues(alpha: 0.8),
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w800,
              color: Color(0xFF201F1E),
            ),
          ),
        ],
      ),
    );
  }
}

class _StockDonut extends StatelessWidget {
  const _StockDonut({
    required this.total,
    required this.inStock,
    required this.assigned,
    required this.damaged,
    required this.repair,
  });

  final int total;
  final int inStock;
  final int assigned;
  final int damaged;
  final int repair;

  static const _colors = {
    'In stock': Color(0xFF047857),
    'Assigned': Color(0xFF0F6CBD),
    'Damaged': Color(0xFFB45309),
    'Repair': Color(0xFF7C3AED),
  };

  @override
  Widget build(BuildContext context) {
    final slices = <MapEntry<String, int>>[
      MapEntry('In stock', inStock),
      MapEntry('Assigned', assigned),
      MapEntry('Damaged', damaged),
      MapEntry('Repair', repair),
    ];
    final sum = slices.fold<int>(0, (a, e) => a + e.value);
    final paintSlices = slices
        .where((e) => e.value > 0)
        .map(
          (e) => _DonutSlice(
            value: e.value.toDouble(),
            color: _colors[e.key]!,
          ),
        )
        .toList();

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFEDEBE9)),
      ),
      child: Row(
        children: [
          SizedBox(
            width: 120,
            height: 120,
            child: CustomPaint(
              painter: _DonutPainter(
                slices: paintSlices.isEmpty
                    ? [
                        const _DonutSlice(
                          value: 1,
                          color: Color(0xFFE2E8F0),
                        ),
                      ]
                    : paintSlices,
              ),
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      '$total',
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF201F1E),
                        height: 1,
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'TOTAL',
                      style: TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF94A3B8),
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final e in slices)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: _colors[e.key],
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            e.key,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF475569),
                            ),
                          ),
                        ),
                        Text(
                          '${e.value}',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF201F1E),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          sum > 0
                              ? '${((e.value / sum) * 100).round()}%'
                              : '0%',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF94A3B8),
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DonutSlice {
  const _DonutSlice({required this.value, required this.color});
  final double value;
  final Color color;
}

class _DonutPainter extends CustomPainter {
  _DonutPainter({required this.slices});

  final List<_DonutSlice> slices;

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = size.shortestSide / 2;
    final rect = Rect.fromCircle(center: center, radius: radius);
    final total = slices.fold<double>(0, (a, s) => a + s.value);
    if (total <= 0) return;

    var start = -1.57079632679; // -pi/2
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = radius * 0.32
      ..strokeCap = StrokeCap.butt;

    for (final slice in slices) {
      final sweep = (slice.value / total) * 6.28318530718;
      paint.color = slice.color;
      canvas.drawArc(rect.deflate(paint.strokeWidth / 2), start, sweep, false, paint);
      start += sweep;
    }
  }

  @override
  bool shouldRepaint(covariant _DonutPainter oldDelegate) =>
      oldDelegate.slices != slices;
}

class _ByTypeChart extends StatelessWidget {
  const _ByTypeChart({required this.rows});

  final List<Map<String, dynamic>> rows;

  @override
  Widget build(BuildContext context) {
    final maxVal = rows
        .map((r) => ((r['total'] as num?) ?? 0).toDouble())
        .fold<double>(0, (a, b) => a > b ? a : b);
    final scale = maxVal <= 0 ? 1.0 : maxVal;

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFEDEBE9)),
      ),
      child: Column(
        children: [
          Row(
            children: const [
              _LegendDot(color: Color(0xFF0F6CBD), label: 'Total'),
              SizedBox(width: 14),
              _LegendDot(color: Color(0xFF047857), label: 'In stock'),
            ],
          ),
          const SizedBox(height: 16),
          SizedBox(
            height: 160,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                for (final row in rows) ...[
                  Expanded(
                    child: _TypeBars(
                      name: '${row['name'] ?? ''}',
                      total: ((row['total'] as num?) ?? 0).toDouble(),
                      inStock: ((row['in_stock'] as num?) ?? 0).toDouble(),
                      scale: scale,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot({required this.color, required this.label});
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: Color(0xFF64748B),
          ),
        ),
      ],
    );
  }
}

class _TypeBars extends StatelessWidget {
  const _TypeBars({
    required this.name,
    required this.total,
    required this.inStock,
    required this.scale,
  });

  final String name;
  final double total;
  final double inStock;
  final double scale;

  @override
  Widget build(BuildContext context) {
    const barMax = 110.0;
    final totalH = (total / scale) * barMax;
    final inH = (inStock / scale) * barMax;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(
                  children: [
                    Text(
                      '${total.toInt()}',
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF0F6CBD),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Container(
                      height: totalH.clamp(4, barMax),
                      decoration: BoxDecoration(
                        color: const Color(0xFF0F6CBD),
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 4),
              Expanded(
                child: Column(
                  children: [
                    Text(
                      '${inStock.toInt()}',
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF047857),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Container(
                      height: inH.clamp(4, barMax),
                      decoration: BoxDecoration(
                        color: const Color(0xFF047857),
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            name,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: Color(0xFF475569),
              height: 1.15,
            ),
          ),
        ],
      ),
    );
  }
}
