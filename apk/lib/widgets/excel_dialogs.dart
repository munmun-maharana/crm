import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class ExcelDialog extends StatefulWidget {
  final List<String> statusOptions;

  const ExcelDialog({super.key, this.statusOptions = const []});

  @override
  State<ExcelDialog> createState() => _ExcelDialogState();
}

class _ExcelDialogState extends State<ExcelDialog> {
  DateTime? _fromDate;
  DateTime? _toDate;
  String? _selectedStatus;
  bool _exporting = false;

  Future<void> _pickFromDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _fromDate ?? DateTime.now().subtract(const Duration(days: 30)),
      firstDate: DateTime(2020),
      lastDate: DateTime(2030),
    );
    if (picked != null) setState(() => _fromDate = picked);
  }

  Future<void> _pickToDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _toDate ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2030),
    );
    if (picked != null) setState(() => _toDate = picked);
  }

  Future<void> _downloadDemo() async {
    final url = '$kApiBaseUrl/upload/demo?api_token=${ApiService.instance.token}';
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _exportExcel() async {
    setState(() => _exporting = true);
    final query = <String, String>{
      'api_token': ApiService.instance.token ?? '',
      if (_fromDate != null) 'from_date': DateFormat('yyyy-MM-dd').format(_fromDate!),
      if (_toDate != null) 'to_date': DateFormat('yyyy-MM-dd').format(_toDate!),
      if (_selectedStatus != null && _selectedStatus!.isNotEmpty) 'status': _selectedStatus!,
    };
    final uri = Uri.parse('$kApiBaseUrl/upload/export').replace(queryParameters: query);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
    if (mounted) {
      setState(() => _exporting = false);
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      backgroundColor: Colors.white,
      child: Container(
        width: 480,
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: AppTheme.emeraldSoft,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppTheme.emeraldBorder),
                  ),
                  child: const Icon(Icons.table_view, color: AppTheme.emeraldText, size: 20),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Excel Export & Template', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      Text('Download customer data in Excel format', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.of(context).pop(),
                  icon: const Icon(Icons.close, size: 20),
                ),
              ],
            ),
            const Divider(height: 24),

            // Demo Format Download Box
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppTheme.border),
              ),
              child: Row(
                children: [
                  const Icon(Icons.download_for_offline, color: AppTheme.primary, size: 28),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Need Excel format template?', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        Text('Download standard import template with all fields', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                      ],
                    ),
                  ),
                  OutlinedButton(
                    onPressed: _downloadDemo,
                    style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6)),
                    child: const Text('Download', style: TextStyle(fontSize: 12)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            const Text('EXPORT CUSTOMER LEADS', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.textMuted)),
            const SizedBox(height: 10),

            Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: _pickFromDate,
                    child: InputDecorator(
                      decoration: const InputDecoration(labelText: 'From Date'),
                      child: Text(
                        _fromDate != null ? DateFormat('dd MMM yyyy').format(_fromDate!) : 'Select Date',
                        style: TextStyle(color: _fromDate != null ? AppTheme.textMain : AppTheme.textMuted, fontSize: 13),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: InkWell(
                    onTap: _pickToDate,
                    child: InputDecorator(
                      decoration: const InputDecoration(labelText: 'To Date'),
                      child: Text(
                        _toDate != null ? DateFormat('dd MMM yyyy').format(_toDate!) : 'Select Date',
                        style: TextStyle(color: _toDate != null ? AppTheme.textMain : AppTheme.textMuted, fontSize: 13),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),

            DropdownButtonFormField<String>(
              value: _selectedStatus,
              decoration: const InputDecoration(labelText: 'Filter by Status (Optional)'),
              items: [
                const DropdownMenuItem(value: null, child: Text('All Statuses')),
                ...widget.statusOptions.map((s) => DropdownMenuItem(value: s, child: Text(s))),
              ],
              onChanged: (v) => setState(() => _selectedStatus = v),
            ),
            const SizedBox(height: 20),

            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Close'),
                ),
                const SizedBox(width: 10),
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF047857)),
                  onPressed: _exporting ? null : _exportExcel,
                  icon: const Icon(Icons.file_download, size: 18),
                  label: Text(_exporting ? 'Exporting...' : 'Export to Excel'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

