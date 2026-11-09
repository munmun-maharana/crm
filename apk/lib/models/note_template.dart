class NoteTemplate {
  final int id;
  final String title;
  final String body;
  final int sortOrder;
  final bool isActive;

  NoteTemplate({
    required this.id,
    required this.title,
    required this.body,
    this.sortOrder = 0,
    this.isActive = true,
  });

  factory NoteTemplate.fromJson(Map<String, dynamic> json) {
    return NoteTemplate(
      id: json['id'] as int? ?? 0,
      title: json['title']?.toString() ?? '',
      body: json['body']?.toString() ?? '',
      sortOrder: json['sort_order'] as int? ?? 0,
      isActive: json['is_active'] != false,
    );
  }
}

