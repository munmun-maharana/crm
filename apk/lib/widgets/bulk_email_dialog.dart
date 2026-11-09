import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/customer.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class BulkEmailDialog extends StatefulWidget {
  final List<Customer> customers;

  const BulkEmailDialog({super.key, required this.customers});

  @override
  State<BulkEmailDialog> createState() => _BulkEmailDialogState();
}

class _BulkEmailDialogState extends State<BulkEmailDialog> {
  final _recipientController = TextEditingController();
  bool _loading = false;
  bool _copied = false;
  Map<String, dynamic>? _apiData;

  @override
  void initState() {
    super.initState();
    _fetchBulkEmailData();
  }

  @override
  void dispose() {
    _recipientController.dispose();
    super.dispose();
  }

  Future<void> _fetchBulkEmailData() async {
    setState(() => _loading = true);
    try {
      final ids = widget.customers.map((c) => c.id).toList();
      final res = await ApiService.instance.customerBulkEmail(ids);
      _apiData = res;
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  String _generatePlainTextTable() {
    final buffer = StringBuffer();
    buffer.writeln('CUSTOMER LEADS REPORT');
    buffer.writeln('----------------------------------------------------');
    for (final c in widget.customers) {
      buffer.writeln('Name: ${c.name}');
      buffer.writeln('Phone: ${c.phone ?? "—"}');
      buffer.writeln('City: ${c.city ?? "—"}');
      buffer.writeln('Status: ${c.status ?? "—"}');
      buffer.writeln('Vehicle: ${c.getMetaValue("model") ?? "—"}');
      buffer.writeln('Service: ${c.getMetaValue("service_details") ?? "—"}');
      buffer.writeln('Price: ₹${c.getMetaValue("seal_price") ?? "—"}');
      buffer.writeln('Booking: ${c.followUpAt ?? "—"}');
      buffer.writeln('----------------------------------------------------');
    }
    return buffer.toString();
  }

  void _copyTable() {
    Clipboard.setData(ClipboardData(text: _generatePlainTextTable()));
    setState(() => _copied = true);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Table copied to clipboard! Paste directly into Gmail.'), backgroundColor: Colors.green),
    );
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) setState(() => _copied = false);
    });
  }

  Future<void> _openGmailWeb() async {
    _copyTable();
    String? gmailUrl = _apiData?['gmail_url']?.toString();
    final recipient = _recipientController.text.trim();

    if (gmailUrl != null && gmailUrl.isNotEmpty) {
      if (recipient.isNotEmpty) {
        gmailUrl = gmailUrl.replaceAll(RegExp(r'(&to=)[^&]*'), '\$1${Uri.encodeComponent(recipient)}');
      }
      final uri = Uri.parse(gmailUrl);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } else {
      // Fallback mailto
      final mailto = Uri.parse('mailto:$recipient?subject=${Uri.encodeComponent("Customer Leads (${widget.customers.length})")}&body=${Uri.encodeComponent(_generatePlainTextTable())}');
      if (await canLaunchUrl(mailto)) {
        await launchUrl(mailto);
      }
    }
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      backgroundColor: Colors.white,
      clipBehavior: Clip.antiAlias,
      child: Container(
        width: 600,
        constraints: const BoxConstraints(maxHeight: 700),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Header
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
              decoration: const BoxDecoration(
                color: Color(0xFFF8FAFC),
                border: Border(bottom: BorderSide(color: AppTheme.border)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: AppTheme.roseSoft,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppTheme.roseBorder),
                    ),
                    child: const Icon(Icons.mail, color: AppTheme.roseText, size: 20),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Send Customer Leads via Gmail',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        Text(
                          '${widget.customers.length} customers selected • Excel-style cyan table',
                          style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close, size: 20),
                  ),
                ],
              ),
            ),

            // Recipient Input
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('RECIPIENT EMAIL (OPTIONAL)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.textMuted)),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _recipientController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      hintText: 'Enter recipient email (e.g. boss@gmail.com)...',
                      prefixIcon: Icon(Icons.alternate_email, size: 18),
                    ),
                  ),
                ],
              ),
            ),

            // Cyan Table Preview
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFF8FE3EB).withValues(alpha: 0.35),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: const Color(0xFF8FE3EB)),
                          ),
                          child: const Text(
                            'Cyan Header (#8fe3eb) & Borders',
                            style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0F766E)),
                          ),
                        ),
                        TextButton.icon(
                          onPressed: _copyTable,
                          icon: Icon(_copied ? Icons.check : Icons.copy, size: 16, color: _copied ? Colors.green : AppTheme.primary),
                          label: Text(_copied ? 'Copied!' : 'Copy Table', style: TextStyle(fontSize: 12, color: _copied ? Colors.green : AppTheme.primary)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),

                    Expanded(
                      child: Container(
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.black54),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: _loading
                            ? const Center(child: CircularProgressIndicator())
                            : SingleChildScrollView(
                                scrollDirection: Axis.horizontal,
                                child: SingleChildScrollView(
                                  child: DataTable(
                                    headingRowColor: WidgetStateProperty.all(const Color(0xFF8FE3EB)),
                                    border: TableBorder.all(color: Colors.black26),
                                    columns: const [
                                      DataColumn(label: Text('Name', style: TextStyle(fontWeight: FontWeight.bold))),
                                      DataColumn(label: Text('Phone', style: TextStyle(fontWeight: FontWeight.bold))),
                                      DataColumn(label: Text('City', style: TextStyle(fontWeight: FontWeight.bold))),
                                      DataColumn(label: Text('Status', style: TextStyle(fontWeight: FontWeight.bold))),
                                      DataColumn(label: Text('Booking Date', style: TextStyle(fontWeight: FontWeight.bold))),
                                    ],
                                    rows: widget.customers.map((c) {
                                      return DataRow(
                                        cells: [
                                          DataCell(Text(c.name, style: const TextStyle(fontWeight: FontWeight.w600))),
                                          DataCell(Text(c.phone ?? '—')),
                                          DataCell(Text(c.city ?? '—')),
                                          DataCell(Text(c.status ?? '—')),
                                          DataCell(Text(c.followUpAt ?? '—')),
                                        ],
                                      );
                                    }).toList(),
                                  ),
                                ),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Instructions Banner
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              color: AppTheme.amberSoft,
              child: const Row(
                children: [
                  Icon(Icons.info_outline, color: AppTheme.amberText, size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Opening Gmail will automatically copy the table so you can paste it directly.',
                      style: TextStyle(color: AppTheme.amberText, fontSize: 12),
                    ),
                  ),
                ],
              ),
            ),

            // Footer
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
              decoration: const BoxDecoration(
                color: Color(0xFFF8FAFC),
                border: Border(top: BorderSide(color: AppTheme.border)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  OutlinedButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Close'),
                  ),
                  const SizedBox(width: 10),
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF1E293B)),
                    onPressed: _openGmailWeb,
                    icon: const Icon(Icons.open_in_new, size: 16),
                    label: const Text('Open in Gmail Web'),
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
