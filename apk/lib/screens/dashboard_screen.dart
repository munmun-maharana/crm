import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/customer.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import 'customer_detail_screen.dart';

class DashboardScreen extends StatefulWidget {
  final Function(String? filter, String? status)? onNavigateToCrm;

  const DashboardScreen({super.key, this.onNavigateToCrm});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _statusCards = [];
  Map<String, dynamic> _charts = {};
  List<Customer> _recentLeads = [];
  String? _whatsappPhone;
  String _chartPeriod = 'week'; // 'week', 'month', 'year', 'falloff'

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await ApiService.instance.dashboard();
      if (mounted) {
        setState(() {
          _statusCards = (res['status_cards'] as List?)
                  ?.whereType<Map>()
                  .map((m) => Map<String, dynamic>.from(m))
                  .toList() ??
              [];
          _charts = (res['charts'] is Map)
              ? Map<String, dynamic>.from(res['charts'] as Map)
              : {};
          final rawRecent = (res['recent'] as List?) ?? [];
          _recentLeads = rawRecent
              .whereType<Map>()
              .map((m) => Customer.fromJson(Map<String, dynamic>.from(m)))
              .toList();
          _whatsappPhone = res['whatsapp_target_phone']?.toString();
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString().replaceAll('Exception: ', '');
          _loading = false;
        });
      }
    }
  }

  Future<void> _openWhatsApp() async {
    final phone = _whatsappPhone;
    if (phone == null || phone.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('WhatsApp phone number not configured in Settings.')),
      );
      return;
    }
    final uri = Uri.parse('https://wa.me/$phone');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: _loadDashboard,
                icon: const Icon(Icons.refresh),
                label: const Text('Try Again'),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadDashboard,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Quick WhatsApp bar
          if (_whatsappPhone != null && _whatsappPhone!.isNotEmpty) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFDCFCE7),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFF86EFAC)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.chat, color: Color(0xFF15803D), size: 20),
                  const SizedBox(width: 10),
                  const Expanded(
                    child: Text(
                      'Official WhatsApp Support & Alerts',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF166534)),
                    ),
                  ),
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF16A34A),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    ),
                    onPressed: _openWhatsApp,
                    child: const Text('Open WhatsApp', style: TextStyle(fontSize: 12)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
          ],

          // Status Cards Grid
          const Text(
            'PIPELINE OVERVIEW',
            style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 0.8, color: AppTheme.textMuted),
          ),
          const SizedBox(height: 8),

          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: _statusCards.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              childAspectRatio: 1.8,
            ),
            itemBuilder: (context, index) {
              final card = _statusCards[index];
              final label = card['label']?.toString() ?? '';
              final value = card['value']?.toString() ?? '0';
              final filter = card['filter']?.toString();
              final status = card['status']?.toString();

              Color bg = AppTheme.statusBg(status ?? label);
              Color border = AppTheme.statusBorder(status ?? label);
              Color textCol = AppTheme.statusText(status ?? label);

              if (filter == 'follow_up_today') {
                bg = const Color(0xFFFFEDD5);
                border = const Color(0xFFFED7AA);
                textCol = const Color(0xFFC2410C);
              }

              return InkWell(
                onTap: () {
                  widget.onNavigateToCrm?.call(filter, status);
                },
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: bg,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: border),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        label,
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.bold,
                          color: textCol.withValues(alpha: 0.9),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        value,
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: textCol,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
          const SizedBox(height: 20),

          // Analytics / Trends Chart Card
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Lead Performance',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                      ),
                      DropdownButton<String>(
                        value: _chartPeriod,
                        underline: const SizedBox(),
                        items: const [
                          DropdownMenuItem(value: 'week', child: Text('7 Days', style: TextStyle(fontSize: 12))),
                          DropdownMenuItem(value: 'month', child: Text('12 Months', style: TextStyle(fontSize: 12))),
                          DropdownMenuItem(value: 'year', child: Text('5 Years', style: TextStyle(fontSize: 12))),
                          DropdownMenuItem(value: 'falloff', child: Text('Distribution', style: TextStyle(fontSize: 12))),
                        ],
                        onChanged: (v) {
                          if (v != null) setState(() => _chartPeriod = v);
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _buildChartPreview(),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Recent Leads List
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'RECENT LEADS',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 0.8, color: AppTheme.textMuted),
              ),
              TextButton(
                onPressed: () => widget.onNavigateToCrm?.call(null, null),
                child: const Text('View All →', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 8),

          if (_recentLeads.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Center(
                  child: Text('No leads found.', style: TextStyle(color: AppTheme.textMuted)),
                ),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _recentLeads.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final lead = _recentLeads[index];
                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                    leading: CircleAvatar(
                      backgroundColor: AppTheme.primarySoft,
                      child: Text(
                        lead.name.isNotEmpty ? lead.name[0].toUpperCase() : '?',
                        style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary),
                      ),
                    ),
                    title: Text(
                      lead.name,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                    subtitle: Text(
                      '${lead.phone ?? "No phone"} • ${lead.city ?? "No city"}',
                      style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                    ),
                    trailing: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppTheme.statusBg(lead.status),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: AppTheme.statusBorder(lead.status)),
                          ),
                          child: Text(
                            (lead.status ?? 'New').toUpperCase(),
                            style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.statusText(lead.status)),
                          ),
                        ),
                        if (lead.followUpAt != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            lead.followUpLabel ?? '',
                            style: const TextStyle(fontSize: 10, color: AppTheme.amberText, fontWeight: FontWeight.bold),
                          ),
                        ],
                      ],
                    ),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => CustomerDetailScreen(customerId: lead.id),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
        ],
      ),
    );
  }

  Widget _buildChartPreview() {
    final Map<String, dynamic> chartData = _charts[_chartPeriod] is Map
        ? Map<String, dynamic>.from(_charts[_chartPeriod] as Map)
        : {};

    final labels = (chartData['labels'] as List?)?.map((e) => e.toString()).toList() ?? [];
    final data = (chartData['data'] as List?)?.map((e) => (e as num).toInt()).toList() ?? [];

    if (labels.isEmpty || data.isEmpty) {
      return const SizedBox(
        height: 120,
        child: Center(child: Text('No chart data available', style: TextStyle(color: AppTheme.textMuted))),
      );
    }

    final maxVal = data.fold<int>(1, (prev, elem) => elem > prev ? elem : prev);

    return SizedBox(
      height: 160,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: List.generate(labels.length, (i) {
          final val = data[i];
          final heightFactor = (val / maxVal).clamp(0.05, 1.0);
          return Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Text(val.toString(), style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 4),
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    height: 100 * heightFactor,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.bottomCenter,
                        end: Alignment.topCenter,
                        colors: [
                          AppTheme.primary,
                          AppTheme.primary.withValues(alpha: 0.7),
                        ],
                      ),
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    labels[i],
                    style: const TextStyle(fontSize: 10, color: AppTheme.textMuted),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          );
        }),
      ),
    );
  }
}
