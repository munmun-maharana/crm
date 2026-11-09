class ActivityImage {
  final String name;
  final String path;
  final String url;

  ActivityImage({required this.name, required this.path, required this.url});

  factory ActivityImage.fromJson(Map<String, dynamic> json) {
    return ActivityImage(
      name: json['name']?.toString() ?? '',
      path: json['path']?.toString() ?? '',
      url: json['url']?.toString() ?? '',
    );
  }
}

class CustomerActivity {
  final int id;
  final String type;
  final String title;
  final String body;
  final String userName;
  final String createdAt;
  final String? ago;
  final Map<String, dynamic> meta;

  CustomerActivity({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.userName,
    required this.createdAt,
    this.ago,
    this.meta = const {},
  });

  List<ActivityImage> get images {
    final imgs = meta['images'];
    if (imgs is List) {
      return imgs
          .whereType<Map>()
          .map((m) => ActivityImage.fromJson(Map<String, dynamic>.from(m)))
          .toList();
    }
    return [];
  }

  Map<String, dynamic>? get fieldChanges {
    final fc = meta['field_changes'];
    if (fc is Map) {
      return Map<String, dynamic>.from(fc);
    }
    return null;
  }

  factory CustomerActivity.fromJson(Map<String, dynamic> json) {
    return CustomerActivity(
      id: json['id'] as int? ?? 0,
      type: json['type'] as String? ?? 'note',
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      userName: json['user_name'] as String? ?? 'System',
      createdAt: json['created_at'] as String? ?? '',
      ago: json['ago'] as String?,
      meta: (json['meta'] is Map)
          ? Map<String, dynamic>.from(json['meta'] as Map)
          : {},
    );
  }
}

