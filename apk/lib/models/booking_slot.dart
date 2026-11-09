class BookingSlot {
  final int id;
  final String slotDate;
  final String startTime;
  final String endTime;
  final String label;
  final String status;
  final String statusLabel;
  final String? customerName;
  final int? customerId;
  final String? notes;

  BookingSlot({
    required this.id,
    required this.slotDate,
    required this.startTime,
    required this.endTime,
    required this.label,
    required this.status,
    this.statusLabel = '',
    this.customerName,
    this.customerId,
    this.notes,
  });

  bool get isAvailable => status == 'available';
  bool get isBooked => status == 'booked';
  bool get isSuccess => status == 'success';
  bool get isCancelled => status == 'cancelled';

  factory BookingSlot.fromJson(Map<String, dynamic> json) {
    return BookingSlot(
      id: json['id'] as int? ?? 0,
      slotDate: json['slot_date']?.toString() ?? '',
      startTime: json['start_time']?.toString() ?? '',
      endTime: json['end_time']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      status: json['status']?.toString() ?? 'available',
      statusLabel: json['status_label']?.toString() ?? '',
      customerName: json['customer_name']?.toString(),
      customerId: json['customer_id'] as int?,
      notes: json['notes']?.toString(),
    );
  }
}

