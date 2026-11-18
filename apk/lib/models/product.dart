class Product {
  final int id;
  final String name;
  final String? code;
  final double price;
  final String formattedPrice;
  final String? category;
  final String? unit;
  final String status;
  final bool isActive;
  final String? description;
  final Map<String, dynamic> meta;
  final String? createdAt;

  Product({
    required this.id,
    required this.name,
    this.code,
    this.price = 0.0,
    this.formattedPrice = '₹0.00',
    this.category,
    this.unit,
    this.status = 'Active',
    this.isActive = true,
    this.description,
    this.meta = const {},
    this.createdAt,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    final rawPrice = json['price'];
    double p = 0.0;
    if (rawPrice is num) {
      p = rawPrice.toDouble();
    } else if (rawPrice != null) {
      p = double.tryParse(rawPrice.toString()) ?? 0.0;
    }

    final active = json['is_active'] == true ||
        (json['status'] != null && json['status'].toString().toLowerCase() == 'active');

    return Product(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      code: json['code']?.toString(),
      price: p,
      formattedPrice: json['formatted_price']?.toString() ?? '₹${p.toStringAsFixed(2)}',
      category: json['category']?.toString(),
      unit: json['unit']?.toString(),
      status: json['status']?.toString() ?? (active ? 'Active' : 'Inactive'),
      isActive: active,
      description: json['description']?.toString(),
      meta: (json['meta'] is Map) ? Map<String, dynamic>.from(json['meta'] as Map) : {},
      createdAt: json['created_at']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'code': code,
      'price': price,
      'category': category,
      'unit': unit,
      'status': status,
      'is_active': isActive,
      'description': description,
      'meta': meta,
    };
  }
}
