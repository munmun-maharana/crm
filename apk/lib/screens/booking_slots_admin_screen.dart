import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/booking_slot.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class BookingSlotsAdminScreen extends StatefulWidget {
  const BookingSlotsAdminScreen({super.key});

  @override
  State<BookingSlotsAdminScreen> createState() => _BookingSlotsAdminScreenState();
}

class _BookingSlotsAdminScreenState extends State<BookingSlotsAdminScreen> {
  DateTime _currentDate = DateTime.now();
  bool _loading = false;
  String? _error;
  List<BookingSlot> _slots = [];

  String get _ymd => DateFormat('yyyy-MM-dd').format(_currentDate);
  String get _formattedDate => DateFormat('dd MMM yyyy (E)').format(_currentDate);

  @override
  void initState() {
    super.initState();
    _fetchSlots();
  }

  Future<void> _fetchSlots() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await ApiService.instance.settingsBookingSlots(slotDate: _ymd);
      final rawSlots = (res['slots'] as List?) ?? [];
      _slots = rawSlots
          .whereType<Map>()
          .map((m) => BookingSlot.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      setState(() => _loading = false);
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString().replaceAll('Exception: ', '');
          _loading = false;
        });
      }
    }
  }

  void _shiftDate(int days) {
    setState(() => _currentDate = _currentDate.add(Duration(days: days)));
    _fetchSlots();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _currentDate,
      firstDate: DateTime(2020),
      lastDate: DateTime(2030),
    );
    if (picked != null) {
      setState(() => _currentDate = picked);
      _fetchSlots();
    }
  }

  Future<void> _openAddSlotsDialog() async {
    final startController = TextEditingController(text: '09:00');
    final endController = TextEditingController(text: '10:00');
    bool isRange = false;
    DateTime startDate = _currentDate;
    DateTime endDate = _currentDate;
    bool submitting = false;
    String? dialogError;

    final added = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: const Text('Add Booking Slot(s)'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (dialogError != null) ...[
                      Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                      const SizedBox(height: 8),
                    ],
                    Row(
                      children: [
                        ChoiceChip(
                          label: const Text('Single Date'),
                          selected: !isRange,
                          onSelected: (_) => setDialogState(() => isRange = false),
                        ),
                        const SizedBox(width: 8),
                        ChoiceChip(
                          label: const Text('Date Range'),
                          selected: isRange,
                          onSelected: (_) => setDialogState(() => isRange = true),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    if (!isRange) ...[
                      Text('Date: ${DateFormat('dd MMM yyyy').format(startDate)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                    ] else ...[
                      Text('Start: ${DateFormat('dd MMM yyyy').format(startDate)}'),
                      Text('End: ${DateFormat('dd MMM yyyy').format(endDate)}'),
                    ],
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: startController,
                            decoration: const InputDecoration(labelText: 'Start Time (HH:mm)'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextField(
                            controller: endController,
                            decoration: const InputDecoration(labelText: 'End Time (HH:mm)'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
                ElevatedButton(
                  onPressed: submitting
                      ? null
                      : () async {
                          setDialogState(() {
                            submitting = true;
                            dialogError = null;
                          });
                          try {
                            await ApiService.instance.settingsBookingSlotStore({
                              'mode': isRange ? 'range' : 'single',
                              'start_date': DateFormat('yyyy-MM-dd').format(startDate),
                              if (isRange) 'end_date': DateFormat('yyyy-MM-dd').format(endDate),
                              'slots': [
                                {
                                  'start_time': startController.text.trim(),
                                  'end_time': endController.text.trim(),
                                }
                              ],
                            });
                            if (ctx.mounted) Navigator.of(ctx).pop(true);
                          } catch (e) {
                            setDialogState(() {
                              submitting = false;
                              dialogError = e.toString().replaceAll('Exception: ', '');
                            });
                          }
                        },
                  child: const Text('Create Slot'),
                ),
              ],
            );
          },
        );
      },
    );

    if (added == true) {
      _fetchSlots();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Booking slot created!'), backgroundColor: Colors.green),
        );
      }
    }
  }

  Future<void> _openEditSlotDialog(BookingSlot slot) async {
    final startController = TextEditingController(text: slot.startTime);
    final endController = TextEditingController(text: slot.endTime);
    final nameController = TextEditingController(text: slot.customerName ?? '');
    final notesController = TextEditingController(text: slot.notes ?? '');
    String status = slot.status;
    bool submitting = false;
    String? dialogError;

    final updated = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: const Text('Edit Booking Slot'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (dialogError != null) ...[
                      Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                      const SizedBox(height: 8),
                    ],
                    Row(
                      children: [
                        Expanded(
                          child: TextField(controller: startController, decoration: const InputDecoration(labelText: 'Start Time')),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextField(controller: endController, decoration: const InputDecoration(labelText: 'End Time')),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      value: status,
                      decoration: const InputDecoration(labelText: 'Status'),
                      items: const [
                        DropdownMenuItem(value: 'available', child: Text('Available')),
                        DropdownMenuItem(value: 'booked', child: Text('Booked')),
                        DropdownMenuItem(value: 'success', child: Text('Success')),
                        DropdownMenuItem(value: 'cancelled', child: Text('Cancelled')),
                      ],
                      onChanged: (v) => setDialogState(() => status = v ?? 'available'),
                    ),
                    const SizedBox(height: 10),
                    TextField(controller: nameController, decoration: const InputDecoration(labelText: 'Customer Name')),
                    const SizedBox(height: 10),
                    TextField(controller: notesController, decoration: const InputDecoration(labelText: 'Notes')),
                  ],
                ),
              ),
              actions: [
                TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
                ElevatedButton(
                  onPressed: submitting
                      ? null
                      : () async {
                          setDialogState(() {
                            submitting = true;
                            dialogError = null;
                          });
                          try {
                            await ApiService.instance.settingsBookingSlotUpdate(slot.id, {
                              'start_time': startController.text.trim(),
                              'end_time': endController.text.trim(),
                              'status': status,
                              'customer_name': nameController.text.trim(),
                              'notes': notesController.text.trim(),
                            });
                            if (ctx.mounted) Navigator.of(ctx).pop(true);
                          } catch (e) {
                            setDialogState(() {
                              submitting = false;
                              dialogError = e.toString().replaceAll('Exception: ', '');
                            });
                          }
                        },
                  child: const Text('Save'),
                ),
              ],
            );
          },
        );
      },
    );

    if (updated == true) {
      _fetchSlots();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Slot updated!'), backgroundColor: Colors.green),
        );
      }
    }
  }

  Future<void> _deleteSlot(BookingSlot slot) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Slot?'),
        content: Text('Delete slot ${slot.label}?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirm != true) return;

    try {
      await ApiService.instance.settingsBookingSlotDestroy(slot.id);
      _fetchSlots();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Slot deleted.')));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _clearAllOnDate() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Clear All Slots on Date?'),
        content: Text('Delete all scheduled slots on $_formattedDate?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Clear All'),
          ),
        ],
      ),
    );
    if (confirm != true) return;

    try {
      await ApiService.instance.settingsBookingSlotDestroyDate(_ymd);
      _fetchSlots();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('All slots on date cleared.')));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: const Text('Booking Slots Admin'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            tooltip: 'Add Slots',
            onPressed: _openAddSlotsDialog,
          ),
          IconButton(
            icon: const Icon(Icons.delete_sweep, color: Colors.red),
            tooltip: 'Clear All on Date',
            onPressed: _slots.isEmpty ? null : _clearAllOnDate,
          ),
        ],
      ),
      body: Column(
        children: [
          // Date Selector Header
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            color: Colors.white,
            child: Row(
              children: [
                IconButton(icon: const Icon(Icons.chevron_left), onPressed: () => _shiftDate(-1)),
                Expanded(
                  child: InkWell(
                    onTap: _pickDate,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.calendar_month, size: 18, color: AppTheme.primary),
                        const SizedBox(width: 8),
                        Text(_formattedDate, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      ],
                    ),
                  ),
                ),
                IconButton(icon: const Icon(Icons.chevron_right), onPressed: () => _shiftDate(1)),
              ],
            ),
          ),
          const Divider(height: 1),

          // Slots List
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(child: Text(_error!, style: const TextStyle(color: Colors.red)))
                    : _slots.isEmpty
                        ? Center(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Text('No slots scheduled for this date.', style: TextStyle(color: AppTheme.textMuted)),
                                const SizedBox(height: 12),
                                ElevatedButton.icon(
                                  onPressed: _openAddSlotsDialog,
                                  icon: const Icon(Icons.add),
                                  label: const Text('Add First Slot'),
                                ),
                              ],
                            ),
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.all(12),
                            itemCount: _slots.length,
                            separatorBuilder: (_, __) => const SizedBox(height: 8),
                            itemBuilder: (context, index) {
                              final slot = _slots[index];
                              return Card(
                                child: ListTile(
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                                  leading: Container(
                                    width: 10,
                                    height: 38,
                                    decoration: BoxDecoration(
                                      color: slot.isAvailable ? Colors.green : (slot.isSuccess ? Colors.teal : Colors.orange),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                  ),
                                  title: Text(slot.label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                  subtitle: Text(
                                    slot.isAvailable ? '• Available' : 'Customer: ${slot.customerName ?? "Booked"}',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: slot.isAvailable ? Colors.green.shade800 : AppTheme.textMuted,
                                      fontWeight: slot.isAvailable ? FontWeight.bold : FontWeight.normal,
                                    ),
                                  ),
                                  trailing: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      IconButton(
                                        icon: const Icon(Icons.edit, size: 18),
                                        onPressed: () => _openEditSlotDialog(slot),
                                      ),
                                      IconButton(
                                        icon: const Icon(Icons.delete, size: 18, color: Colors.red),
                                        onPressed: () => _deleteSlot(slot),
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
}
