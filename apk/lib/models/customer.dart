class Customer {
  final int id;
  final String name;
  final String? email;
  final String? phone;
  final String? company;
  final String? city;
  final String? source;
  final String? status;
  final String? statusTone;
  final String? notes;
  final String pipeline;
  final int? assignedTo;
  final String? assigneeName;
  final bool followUp;
  final String? followUpAt;
  final bool followUpHasTime;
  final String? followUpLabel;
  final Map<String, dynamic> dateSummary;
  final String? lastContactAt;
  final String? createdAt;
  final String? createdLabel;
  final Map<String, dynamic> meta;
  final int? operationCopyId;
  final int? sourceCustomerId;
  final String? sourceCustomerName;

  Customer({
    required this.id,
    required this.name,
    this.email,
    this.phone,
    this.company,
    this.city,
    this.source,
    this.status,
    this.statusTone,
    this.notes,
    this.pipeline = 'crm',
    this.assignedTo,
    this.assigneeName,
    this.followUp = false,
    this.followUpAt,
    this.followUpHasTime = false,
    this.followUpLabel,
    this.dateSummary = const {},
    this.lastContactAt,
    this.createdAt,
    this.createdLabel,
    this.meta = const {},
    this.operationCopyId,
    this.sourceCustomerId,
    this.sourceCustomerName,
  });

  bool get isOperations => pipeline == 'operations';

  dynamic getMetaValue(String key) {
    if (meta.containsKey(key)) return meta[key];
    switch (key) {
      case 'name':
        return name;
      case 'email':
        return email;
      case 'phone':
        return phone;
      case 'company':
        return company;
      case 'city':
        return city;
      case 'source':
        return source;
      case 'status':
        return status;
      case 'notes':
        return notes;
      default:
        return null;
    }
  }

  factory Customer.fromJson(Map<String, dynamic> json) {
    return Customer(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? 'Unnamed',
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      company: json['company']?.toString(),
      city: json['city']?.toString(),
      source: json['source']?.toString(),
      status: json['status']?.toString(),
      statusTone: json['status_tone']?.toString(),
      notes: json['notes']?.toString(),
      pipeline: json['pipeline']?.toString() ?? 'crm',
      assignedTo: json['assigned_to'] as int?,
      assigneeName: json['assignee_name']?.toString(),
      followUp: json['follow_up'] == true,
      followUpAt: json['follow_up_at']?.toString(),
      followUpHasTime: json['follow_up_has_time'] == true,
      followUpLabel: json['follow_up_label']?.toString(),
      dateSummary: (json['date_summary'] is Map)
          ? Map<String, dynamic>.from(json['date_summary'] as Map)
          : {},
      lastContactAt: json['last_contact_at']?.toString(),
      createdAt: json['created_at']?.toString(),
      createdLabel: json['created_label']?.toString(),
      meta: (json['meta'] is Map)
          ? Map<String, dynamic>.from(json['meta'] as Map)
          : {},
      operationCopyId: json['operation_copy_id'] as int?,
      sourceCustomerId: json['source_customer_id'] as int?,
      sourceCustomerName: json['source_customer_name']?.toString(),
    );
  }
}

