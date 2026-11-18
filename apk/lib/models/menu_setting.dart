class MenuSetting {
  final int id;
  final String key;
  final String label;
  final String defaultLabel;
  final String? icon;
  final String? route;
  final int sortOrder;
  final bool isActive;

  MenuSetting({
    required this.id,
    required this.key,
    required this.label,
    required this.defaultLabel,
    this.icon,
    this.route,
    this.sortOrder = 0,
    this.isActive = true,
  });

  bool get isCustomized => label.trim().toLowerCase() != defaultLabel.trim().toLowerCase();

  factory MenuSetting.fromJson(Map<String, dynamic> json) {
    return MenuSetting(
      id: json['id'] as int? ?? 0,
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
      defaultLabel: json['default_label'] as String? ?? '',
      icon: json['icon']?.toString(),
      route: json['route']?.toString(),
      sortOrder: json['sort_order'] as int? ?? 0,
      isActive: json['is_active'] != false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'key': key,
      'label': label,
      'default_label': defaultLabel,
      'icon': icon,
      'route': route,
      'sort_order': sortOrder,
      'is_active': isActive,
    };
  }
}
