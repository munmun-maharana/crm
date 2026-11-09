import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/customer.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class ComposeDialog extends StatefulWidget {
  final Customer customer;
  final String initialChannel; // 'whatsapp', 'email', 'sms'

  const ComposeDialog({
    super.key,
    required this.customer,
    this.initialChannel = 'whatsapp',
  });

  @override
  State<ComposeDialog> createState() => _ComposeDialogState();
}

class _ComposeDialogState extends State<ComposeDialog> {
  late String _channel;
  final _subjectController = TextEditingController();
  final _messageController = TextEditingController();
  final _linkController = TextEditingController();
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _channel = widget.initialChannel;
    _applyTemplate('booking_confirmed');
  }

  @override
  void dispose() {
    _subjectController.dispose();
    _messageController.dispose();
    _linkController.dispose();
    super.dispose();
  }

  void _applyTemplate(String type) {
    final c = widget.customer;
    final name = c.name;
    final model = c.getMetaValue('model') ?? c.getMetaValue('car_model') ?? '';
    final service = c.getMetaValue('service_details') ?? c.getMetaValue('service') ?? 'Full Car Detailing';
    final price = c.getMetaValue('seal_price') ?? c.getMetaValue('sale_price') ?? c.getMetaValue('mrp') ?? '';
    final slotTime = c.getMetaValue('booking_slot_time') ?? c.getMetaValue('booking_time') ?? '';
    final date = c.followUpAt ?? '';

    if (type == 'booking_confirmed') {
      _subjectController.text = 'Booking Confirmed! - $name';
      _messageController.text = '''🎉 *Booking Confirmed!*

Dear $name,

Your vehicle cleaning booking has been confirmed!

📅 *Booking Date:* $date
⏰ *Time:* $slotTime
🚗 *Vehicle Details:* • $model ($service)
💰 *Total Amount:* ₹$price
💳 *Payment Status:* Pending

📍 *Location:* ${c.city ?? 'Bhubaneswar'}

Thank you for choosing our service! We look forward to serving you.
*For any queries or changes, please contact us.*''';
    } else {
      _subjectController.text = 'Follow-up - $name';
      _messageController.text = '''Hello $name,

This is a quick follow-up regarding your vehicle cleaning enquiry.

🚗 *Vehicle Details:* • $model ($service)
💰 *Estimated Amount:* ₹$price
📍 *Location:* ${c.city ?? 'Bhubaneswar'}

Please let us know your preferred date and time slot so we can confirm your booking.

*Thank you!*''';
    }
    setState(() {});
  }

  Future<void> _submit() async {
    final msg = _messageController.text.trim();
    if (msg.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please write a message.')),
      );
      return;
    }

    setState(() => _sending = true);
    try {
      final res = await ApiService.instance.customerCompose(
        widget.customer.id,
        channel: _channel,
        message: msg,
        subject: _channel == 'email' ? _subjectController.text.trim() : null,
        link: _linkController.text.trim().isNotEmpty ? _linkController.text.trim() : null,
      );

      final openUrl = res['open_url']?.toString();
      if (openUrl != null && openUrl.isNotEmpty) {
        final uri = Uri.parse(openUrl);
        if (await canLaunchUrl(uri)) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      }

      if (!mounted) return;
      Navigator.of(context).pop(true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Message recorded & ${_channel.toUpperCase()} opened!'),
          backgroundColor: Colors.green,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _sending = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceAll('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.customer;
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      backgroundColor: Colors.white,
      clipBehavior: Clip.antiAlias,
      child: Container(
        width: 520,
        constraints: const BoxConstraints(maxHeight: 680),
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
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _channel.toUpperCase(),
                          style: const TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 0.8,
                            color: AppTheme.primary,
                          ),
                        ),
                        Text(
                          'Send to ${c.name}',
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                            color: AppTheme.textMain,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        Text(
                          _channel == 'email' ? (c.email ?? 'No email') : (c.phone ?? 'No phone'),
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

            // Channel Selector Tabs
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 0),
              child: SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'whatsapp', label: Text('WhatsApp'), icon: Icon(Icons.chat, size: 16)),
                  ButtonSegment(value: 'email', label: Text('Email'), icon: Icon(Icons.email, size: 16)),
                  ButtonSegment(value: 'sms', label: Text('SMS'), icon: Icon(Icons.sms, size: 16)),
                ],
                selected: {_channel},
                onSelectionChanged: (set) {
                  setState(() => _channel = set.first);
                },
              ),
            ),

            // Form Body
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  if (_channel == 'email') ...[
                    const Text('SUBJECT', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.textMuted)),
                    const SizedBox(height: 6),
                    TextField(
                      controller: _subjectController,
                      decoration: const InputDecoration(hintText: 'Email Subject'),
                    ),
                    const SizedBox(height: 14),
                  ],

                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('MESSAGE', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.textMuted)),
                      Wrap(
                        spacing: 6,
                        children: [
                          ActionChip(
                            label: const Text('🎉 Confirmed', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                            backgroundColor: AppTheme.emeraldSoft,
                            side: const BorderSide(color: AppTheme.emeraldBorder),
                            padding: EdgeInsets.zero,
                            onPressed: () => _applyTemplate('booking_confirmed'),
                          ),
                          ActionChip(
                            label: const Text('⏰ Follow-up', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                            backgroundColor: AppTheme.amberSoft,
                            side: const BorderSide(color: AppTheme.amberBorder),
                            padding: EdgeInsets.zero,
                            onPressed: () => _applyTemplate('follow_up'),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _messageController,
                    maxLines: 8,
                    decoration: const InputDecoration(
                      hintText: 'Type your message...',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 14),

                  const Text('LINK (OPTIONAL)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.textMuted)),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _linkController,
                    decoration: const InputDecoration(hintText: 'https://...'),
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
                    onPressed: _sending ? null : () => Navigator.of(context).pop(),
                    child: const Text('Cancel'),
                  ),
                  const SizedBox(width: 10),
                  ElevatedButton.icon(
                    onPressed: _sending ? null : _submit,
                    icon: _sending
                        ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.send, size: 16),
                    label: Text(_sending ? 'Sending...' : 'Send via ${_channel.toUpperCase()}'),
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
