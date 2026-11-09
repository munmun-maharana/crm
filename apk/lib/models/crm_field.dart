class CrmField {
  final int id;
  final String pipeline;
  final String key;
  final String label;
  final String type;
  final List<String> options;
  final bool required;
  final bool active;
  final bool inForm;
  final bool inTable;
  final bool inExcel;
  final bool inUpdate;
  final bool autoSelect;
  final bool isSystem;
  final bool isCore;
  final String? mapsFrom;
  final int sortOrder;
  final dynamic value;

  CrmField({
    required this.id,
    required this.pipeline,
    required this.key,
    required this.label,
    required this.type,
    this.options = const [],
    this.required = false,
    this.active = true,
    this.inForm = true,
    this.inTable = false,
    this.inExcel = false,
    this.inUpdate = false,
    this.autoSelect = false,
    this.isSystem = false,
    this.isCore = false,
    this.mapsFrom,
    this.sortOrder = 0,
    this.value,
  });

  factory CrmField.fromJson(Map<String, dynamic> json) {
    return CrmField(
      id: json['id'] as int? ?? 0,
      pipeline: json['pipeline'] as String? ?? 'crm',
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
      type: json['type'] as String? ?? 'text',
      options: (json['options'] as List?)?.map((e) => e.toString()).toList() ?? [],
      required: json['required'] == true,
      active: json['active'] != false,
      inForm: json['in_form'] == true,
      inTable: json['in_table'] == true,
      inExcel: json['in_excel'] == true,
      inUpdate: json['in_update'] == true,
      autoSelect: json['auto_select'] == true,
      isSystem: json['is_system'] == true,
      isCore: json['is_core'] == true,
      mapsFrom: json['maps_from']?.toString(),
      sortOrder: json['sort_order'] as int? ?? 0,
      value: json['value'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'pipeline': pipeline,
      'key': key,
      'label': label,
      'type': type,
      'options': options,
      'required': required,
      'active': active,
      'in_form': inForm,
      'in_table': inTable,
      'in_excel': inExcel,
      'in_update': inUpdate,
      'auto_select': autoSelect,
      'is_system': isSystem,
      'maps_from': mapsFrom,
      'sort_order': sortOrder,
    };
  }
}

