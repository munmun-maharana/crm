import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/booking_slot.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class BookingSlotsSlider extends StatefulWidget {
  final ValueChanged<String>? onDateChanged;
  final VoidCallback? onSlotBooked;

  const BookingSlotsSlider({
    super.key,
    this.onDateChanged,
    this.onSlotBooked,
  });

  @override
  State<BookingSlotsSlider> createState() => _BookingSlotsSliderState();
}

class _BookingSlotsSliderState extends State<BookingSlotsSlider> {
  DateTime _currentDate = DateTime.now();
  bool _loading = false;
  List<BookingSlot> _slots = [];
  Map<String, dynamic> _counts = {
    'total': 0,
    'available': 0,
    'booked': 0,
    'success': 0,
    'cancelled': 0,
  };

  @override
  void initState() {
    super.initState();
    _loadSlots();
  }

  String get _ymd => DateFormat('yyyy-MM-dd').format(_currentDate);
  String get _formattedDate => DateFormat('dd MMM yyyy (E)').format(_currentDate);

  Future<void> _loadSlots() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.instance.bookingSlots(date: _ymd);
      final rawSlots = (res['slots'] as List?) ?? [];
      _slots = rawSlots
          .whereType<Map>()
          .map((m) => BookingSlot.fromJson(Map<String, dynamic>.from(m)))
          .toList();
      if (res['counts'] is Map) {
        _counts = Map<String, dynamic>.from(res['counts'] as Map);
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  void _shiftDay(int delta) {
    setState(() {
      _currentDate = _currentDate.add(Duration(days: delta));
    });
    widget.onDateChanged?.call(_ymd);
    _loadSlots();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _currentDate,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) {
      setState(() => _currentDate = picked);
      widget.onDateChanged?.call(_ymd);
      _loadSlots();
    }
  }

  Future<void> _openBookingModal(BookingSlot slot) async {
    if (!slot.isAvailable) return;

    final nameController = TextEditingController();
    int step = 1;
    String? error;
    bool submitting = false;

    final booked = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return Dialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Container(
                width: 440,
                padding: const EdgeInsets.all(20),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Header
                    Row(
                      children: [
                        Container(
                          width: 36,
                          height: 36,
                          decoration: BoxDecoration(
                            color: AppTheme.primarySoft,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(Icons.event_available, color: AppTheme.primary, size: 20),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                step == 1 ? 'Book Time Slot' : 'Confirm Slot Booking',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                              ),
                              Text(
                                step == 1 ? 'Enter customer name to reserve' : 'Please review and confirm details',
                                style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.of(ctx).pop(false),
                          icon: const Icon(Icons.close, size: 20),
                        ),
                      ],
                    ),
                    const Divider(height: 24),

                    if (error != null) ...[
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: AppTheme.roseSoft,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: AppTheme.roseBorder),
                        ),
                        child: Text(error!, style: const TextStyle(color: AppTheme.roseText, fontSize: 12)),
                      ),
                      const SizedBox(height: 12),
                    ],

                    if (step == 1) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: AppTheme.border),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('SELECTED SLOT', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.textMuted)),
                                const SizedBox(height: 2),
                                Text(slot.label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                              ],
                            ),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                const Text('DATE', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.textMuted)),
                                const SizedBox(height: 2),
                                Text(_formattedDate, style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary, fontSize: 13)),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                      const Text('CUSTOMER NAME *', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.textMuted)),
                      const SizedBox(height: 6),
                      TextField(
                        controller: nameController,
                        autofocus: true,
                        decoration: const InputDecoration(hintText: 'Enter customer name...'),
                        onSubmitted: (val) {
                          if (val.trim().isEmpty) {
                            setDialogState(() => error = 'Please enter customer name');
                            return;
                          }
                          setDialogState(() {
                            error = null;
                            step = 2;
                          });
                        },
                      ),
                      const SizedBox(height: 20),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          OutlinedButton(
                            onPressed: () => Navigator.of(ctx).pop(false),
                            child: const Text('Cancel'),
                          ),
                          const SizedBox(width: 10),
                          ElevatedButton(
                            onPressed: () {
                              if (nameController.text.trim().isEmpty) {
                                setDialogState(() => error = 'Please enter customer name');
                                return;
                              }
                              setDialogState(() {
                                error = null;
                                step = 2;
                              });
                            },
                            child: const Text('Continue →'),
                          ),
                        ],
                      ),
                    ] else ...[
                      // Step 2 Confirm
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: AppTheme.emeraldSoft,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppTheme.emeraldBorder),
                        ),
                        child: Column(
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Customer Name', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                                Text(nameController.text.trim(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                              ],
                            ),
                            const Divider(height: 16),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Selected Date', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                                Text(_formattedDate, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                              ],
                            ),
                            const Divider(height: 16),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Booking Time', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(slot.label, style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.emeraldText, fontSize: 12)),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        'Once confirmed, this slot will be assigned to ${nameController.text.trim()} and marked as Booked.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                      ),
                      const SizedBox(height: 20),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          OutlinedButton(
                            onPressed: submitting ? null : () => setDialogState(() => step = 1),
                            child: const Text('← Back'),
                          ),
                          ElevatedButton.icon(
                            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF059669)),
                            onPressed: submitting
                                ? null
                                : () async {
                                    setDialogState(() {
                                      submitting = true;
                                      error = null;
                                    });
                                    try {
                                      await ApiService.instance.bookingSlotBook(
                                        slot.id,
                                        customerName: nameController.text.trim(),
                                      );
                                      if (ctx.mounted) Navigator.of(ctx).pop(true);
                                    } catch (e) {
                                      setDialogState(() {
                                        submitting = false;
                                        error = e.toString().replaceAll('Exception: ', '');
                                      });
                                    }
                                  },
                            icon: submitting
                                ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                : const Icon(Icons.check, size: 16),
                            label: Text(submitting ? 'Booking...' : '✓ Confirm Booking'),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            );
          },
        );
      },
    );

    if (booked == true) {
      _loadSlots();
      widget.onSlotBooked?.call();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Slot booked successfully!'), backgroundColor: Colors.green),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.border),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.03), blurRadius: 4, offset: const Offset(0, 2)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Bar: Date & Navigation
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.primarySoft,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.calendar_month, color: AppTheme.primary, size: 16),
                    const SizedBox(width: 6),
                    Text(
                      _formattedDate,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.primary),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              IconButton(
                icon: const Icon(Icons.chevron_left, size: 20),
                visualDensity: VisualDensity.compact,
                tooltip: 'Previous Day',
                onPressed: () => _shiftDay(-1),
              ),
              IconButton(
                icon: const Icon(Icons.today, size: 18),
                visualDensity: VisualDensity.compact,
                tooltip: 'Today',
                onPressed: () {
                  setState(() => _currentDate = DateTime.now());
                  widget.onDateChanged?.call(_ymd);
                  _loadSlots();
                },
              ),
              IconButton(
                icon: const Icon(Icons.edit_calendar, size: 18),
                visualDensity: VisualDensity.compact,
                tooltip: 'Pick Date',
                onPressed: _pickDate,
              ),
              IconButton(
                icon: const Icon(Icons.chevron_right, size: 20),
                visualDensity: VisualDensity.compact,
                tooltip: 'Next Day',
                onPressed: () => _shiftDay(1),
              ),
            ],
          ),
          const SizedBox(height: 8),

          // Count Badges
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                _countBadge('Total', _counts['total'] ?? 0, Colors.grey.shade100, Colors.grey.shade800),
                _countBadge('Available', _counts['available'] ?? 0, AppTheme.emeraldSoft, AppTheme.emeraldText),
                _countBadge('Booked', _counts['booked'] ?? 0, AppTheme.roseSoft, AppTheme.roseText),
                _countBadge('Success', _counts['success'] ?? 0, AppTheme.tealSoft, AppTheme.tealText),
                _countBadge('Cancelled', _counts['cancelled'] ?? 0, AppTheme.amberSoft, AppTheme.amberText),
              ],
            ),
          ),
          const SizedBox(height: 10),

          // Horizontal Slots List
          if (_loading)
            const SizedBox(
              height: 52,
              child: Center(child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))),
            )
          else if (_slots.isEmpty)
            Container(
              height: 48,
              alignment: Alignment.center,
              child: const Text('No slots scheduled for this date.', style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
            )
          else
            SizedBox(
              height: 54,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _slots.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final s = _slots[index];
                  Color bg;
                  Color borderCol;
                  Color textCol;
                  if (s.isAvailable) {
                    bg = AppTheme.emeraldSoft;
                    borderCol = AppTheme.emeraldBorder;
                    textCol = AppTheme.emeraldText;
                  } else if (s.isSuccess) {
                    bg = AppTheme.tealSoft;
                    borderCol = AppTheme.tealBorder;
                    textCol = AppTheme.tealText;
                  } else if (s.isBooked) {
                    bg = AppTheme.roseSoft;
                    borderCol = AppTheme.roseBorder;
                    textCol = AppTheme.roseText;
                  } else {
                    bg = Colors.grey.shade100;
                    borderCol = Colors.grey.shade300;
                    textCol = Colors.grey.shade600;
                  }

                  return InkWell(
                    onTap: s.isAvailable ? () => _openBookingModal(s) : null,
                    borderRadius: BorderRadius.circular(10),
                    child: Container(
                      width: 140,
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: bg,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: borderCol),
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  s.label,
                                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: textCol),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(
                            s.isAvailable
                                ? '• Available'
                                : (s.customerName ?? s.status.toUpperCase()),
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                              color: textCol.withValues(alpha: 0.85),
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }

  Widget _countBadge(String label, dynamic count, Color bg, Color text) {
    return Container(
      margin: const EdgeInsets.only(right: 6),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        '$label: $count',
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: text),
      ),
    );
  }
}

