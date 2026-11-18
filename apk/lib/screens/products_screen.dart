import 'package:flutter/material.dart';
import '../models/crm_field.dart';
import '../models/product.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  final _searchController = TextEditingController();

  bool _loading = false;
  String? _error;
  List<Product> _products = [];
  Map<String, dynamic> _summary = {'total': 0, 'active': 0, 'inactive': 0};
  List<String> _categories = [];
  List<String> _categoryOptions = [];
  List<String> _unitOptions = [];
  List<String> _statusOptions = [];
  List<CrmField> _formFields = [];

  String _searchQuery = '';
  String? _selectedCategory;
  String? _selectedStatus;

  int _currentPage = 1;
  int _lastPage = 1;
  int _totalCount = 0;

  @override
  void initState() {
    super.initState();
    _fetchProducts();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchProducts({int page = 1}) async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await ApiService.instance.products(
        q: _searchQuery,
        status: _selectedStatus,
        category: _selectedCategory,
        page: page,
      );

      final rawList = (res['products'] as List?) ?? [];
      final items = rawList
          .whereType<Map>()
          .map((m) => Product.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      final pagination = (res['pagination'] is Map) ? res['pagination'] as Map : {};
      final summary = (res['summary'] is Map) ? res['summary'] as Map : {};

      final rawCategories = (res['categories'] as List?)?.map((e) => e.toString()).toList() ?? [];
      final rawCatOptions = (res['category_options'] as List?)?.map((e) => e.toString()).toList() ?? [];
      final rawUnitOptions = (res['unit_options'] as List?)?.map((e) => e.toString()).toList() ?? [];
      final rawStatusOptions = (res['status_options'] as List?)?.map((e) => e.toString()).toList() ?? [];
      final rawFields = (res['form_fields'] as List?) ?? [];

      if (mounted) {
        setState(() {
          _products = items;
          _summary = Map<String, dynamic>.from(summary);
          _categories = rawCategories;
          _categoryOptions = rawCatOptions.isNotEmpty ? rawCatOptions : ['GPS Tracker', 'Dashcam', 'Fuel Sensor', 'Speed Governor', 'Accessories', 'Subscription', 'Service'];
          _unitOptions = rawUnitOptions.isNotEmpty ? rawUnitOptions : ['pcs', 'unit', 'box', 'set', 'year', 'month'];
          _statusOptions = rawStatusOptions.isNotEmpty ? rawStatusOptions : ['Active', 'Inactive'];
          _formFields = rawFields.whereType<Map>().map((m) => CrmField.fromJson(Map<String, dynamic>.from(m))).toList();
          _currentPage = pagination['current_page'] as int? ?? 1;
          _lastPage = pagination['last_page'] as int? ?? 1;
          _totalCount = pagination['total'] as int? ?? items.length;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString().replaceAll('Exception: ', '');
          _loading = false;
        });
      }
    }
  }

  Future<void> _toggleProductActive(Product product) async {
    try {
      final res = await ApiService.instance.productToggleActive(product.id);
      final newActive = res['is_active'] == true;
      final newStatus = res['status']?.toString() ?? (newActive ? 'Active' : 'Inactive');

      setState(() {
        final idx = _products.indexWhere((p) => p.id == product.id);
        if (idx != -1) {
          final old = _products[idx];
          _products[idx] = Product(
            id: old.id,
            name: old.name,
            code: old.code,
            price: old.price,
            formattedPrice: old.formattedPrice,
            category: old.category,
            unit: old.unit,
            status: newStatus,
            isActive: newActive,
            description: old.description,
            meta: old.meta,
            createdAt: old.createdAt,
          );
        }
        if (newActive) {
          _summary['active'] = (_summary['active'] as int? ?? 0) + 1;
          _summary['inactive'] = ((_summary['inactive'] as int? ?? 1) - 1).clamp(0, 99999);
        } else {
          _summary['active'] = ((_summary['active'] as int? ?? 1) - 1).clamp(0, 99999);
          _summary['inactive'] = (_summary['inactive'] as int? ?? 0) + 1;
        }
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${product.name} is now $newStatus'),
            duration: const Duration(seconds: 2),
            backgroundColor: newActive ? Colors.green : Colors.grey[800],
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update status: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _deleteProduct(Product product) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Delete Product?'),
        content: Text('Are you sure you want to delete "${product.name}"? This action cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    try {
      await ApiService.instance.productDestroy(product.id);
      _fetchProducts(page: _currentPage);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Product deleted successfully'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Delete failed: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  void _openProductDialog([Product? existing]) {
    final isEdit = existing != null;
    final nameCtrl = TextEditingController(text: existing?.name ?? '');
    final codeCtrl = TextEditingController(text: existing?.code ?? '');
    final priceCtrl = TextEditingController(text: existing != null ? existing.price.toString() : '');
    final descCtrl = TextEditingController(text: existing?.description ?? '');

    String category = existing?.category ?? (_categoryOptions.isNotEmpty ? _categoryOptions.first : 'GPS Tracker');
    String unit = existing?.unit ?? (_unitOptions.isNotEmpty ? _unitOptions.first : 'pcs');
    String status = existing?.status ?? 'Active';

    final Map<String, TextEditingController> customControllers = {};
    for (final f in _formFields) {
      if (['name', 'code', 'price', 'category', 'unit', 'status', 'description'].contains(f.key)) {
        continue;
      }
      final curVal = existing?.meta[f.key]?.toString() ?? '';
      customControllers[f.key] = TextEditingController(text: curVal);
    }

    bool saving = false;
    String? dialogError;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppTheme.primarySoft,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.inventory_2, color: AppTheme.primary, size: 20),
                  ),
                  const SizedBox(width: 10),
                  Text(isEdit ? 'Edit Product' : 'Add New Product',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
                ],
              ),
              content: SizedBox(
                width: 480,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (dialogError != null) ...[
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: Colors.red.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
                          ),
                          child: Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                        ),
                        const SizedBox(height: 12),
                      ],
                      TextField(
                        controller: nameCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Product Name *',
                          hintText: 'e.g. GPS Tracker GT06N',
                          prefixIcon: Icon(Icons.label_outline, size: 18),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: codeCtrl,
                              decoration: const InputDecoration(
                                labelText: 'Product Code / SKU',
                                hintText: 'e.g. GPS-01',
                                prefixIcon: Icon(Icons.tag, size: 18),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: TextField(
                              controller: priceCtrl,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              decoration: const InputDecoration(
                                labelText: 'Price (₹) *',
                                hintText: '0.00',
                                prefixIcon: Icon(Icons.currency_rupee, size: 18),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              value: _categoryOptions.contains(category) ? category : (_categoryOptions.isNotEmpty ? _categoryOptions.first : null),
                              decoration: const InputDecoration(labelText: 'Category', prefixIcon: Icon(Icons.category_outlined, size: 18)),
                              items: _categoryOptions.map((c) => DropdownMenuItem(value: c, child: Text(c, style: const TextStyle(fontSize: 13)))).toList(),
                              onChanged: (v) => setDialogState(() => category = v ?? 'GPS Tracker'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              value: _unitOptions.contains(unit) ? unit : (_unitOptions.isNotEmpty ? _unitOptions.first : null),
                              decoration: const InputDecoration(labelText: 'Unit', prefixIcon: Icon(Icons.straighten, size: 18)),
                              items: _unitOptions.map((u) => DropdownMenuItem(value: u, child: Text(u, style: const TextStyle(fontSize: 13)))).toList(),
                              onChanged: (v) => setDialogState(() => unit = v ?? 'pcs'),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        value: _statusOptions.contains(status) ? status : 'Active',
                        decoration: const InputDecoration(labelText: 'Status', prefixIcon: Icon(Icons.toggle_on_outlined, size: 18)),
                        items: _statusOptions.map((s) => DropdownMenuItem(value: s, child: Text(s))).toList(),
                        onChanged: (v) => setDialogState(() => status = v ?? 'Active'),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: descCtrl,
                        maxLines: 2,
                        decoration: const InputDecoration(
                          labelText: 'Description / Notes',
                          hintText: 'Specifications, warranty, features...',
                          prefixIcon: Icon(Icons.description_outlined, size: 18),
                        ),
                      ),
                      // Custom fields
                      if (customControllers.isNotEmpty) ...[
                        const SizedBox(height: 16),
                        const Divider(),
                        const Text('ADDITIONAL CUSTOM FIELDS',
                            style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.textMuted)),
                        const SizedBox(height: 8),
                        ...customControllers.entries.map((entry) {
                          final fld = _formFields.firstWhere((f) => f.key == entry.key,
                              orElse: () => CrmField(id: 0, pipeline: 'products', key: entry.key, label: entry.key, type: 'text'));
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: TextField(
                              controller: entry.value,
                              decoration: InputDecoration(labelText: fld.label),
                            ),
                          );
                        }),
                      ],
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: saving ? null : () => Navigator.of(ctx).pop(),
                  child: const Text('Cancel'),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  onPressed: saving
                      ? null
                      : () async {
                          final name = nameCtrl.text.trim();
                          if (name.isEmpty) {
                            setDialogState(() => dialogError = 'Product name is required.');
                            return;
                          }
                          final price = double.tryParse(priceCtrl.text.trim()) ?? 0.0;

                          setDialogState(() {
                            saving = true;
                            dialogError = null;
                          });

                          final payload = <String, dynamic>{
                            'name': name,
                            'code': codeCtrl.text.trim(),
                            'price': price,
                            'category': category,
                            'unit': unit,
                            'status': status,
                            'description': descCtrl.text.trim(),
                          };

                          for (final entry in customControllers.entries) {
                            payload[entry.key] = entry.value.text.trim();
                          }

                          try {
                            if (isEdit) {
                              await ApiService.instance.productUpdate(existing.id, payload);
                            } else {
                              await ApiService.instance.productStore(payload);
                            }
                            if (context.mounted) {
                              Navigator.of(ctx).pop();
                              _fetchProducts(page: isEdit ? _currentPage : 1);
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(isEdit ? 'Product updated successfully!' : 'Product added successfully!'),
                                  backgroundColor: Colors.green,
                                ),
                              );
                            }
                          } catch (e) {
                            setDialogState(() {
                              saving = false;
                              dialogError = e.toString().replaceAll('Exception: ', '');
                            });
                          }
                        },
                  child: saving
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : Text(isEdit ? 'Save Changes' : 'Add Product'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      body: RefreshIndicator(
        onRefresh: () => _fetchProducts(page: _currentPage),
        child: Column(
          children: [
            // Top Summary Bar
            _buildSummaryBar(),

            // Search and Filter Bar
            _buildFilterBar(),

            // Products List
            Expanded(
              child: _loading && _products.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                      ? Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(_error!, style: const TextStyle(color: Colors.red)),
                              const SizedBox(height: 10),
                              ElevatedButton(onPressed: () => _fetchProducts(), child: const Text('Retry')),
                            ],
                          ),
                        )
                      : _products.isEmpty
                          ? _buildEmptyState()
                          : ListView.separated(
                              padding: const EdgeInsets.fromLTRB(14, 8, 14, 80),
                              itemCount: _products.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 8),
                              itemBuilder: (context, index) {
                                final p = _products[index];
                                return _buildProductCard(p);
                              },
                            ),
            ),

            // Pagination controls
            if (_lastPage > 1)
              Container(
                color: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Page $_currentPage of $_lastPage ($_totalCount items)',
                      style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                    ),
                    Row(
                      children: [
                        IconButton(
                          icon: const Icon(Icons.chevron_left),
                          onPressed: _currentPage > 1 ? () => _fetchProducts(page: _currentPage - 1) : null,
                        ),
                        IconButton(
                          icon: const Icon(Icons.chevron_right),
                          onPressed: _currentPage < _lastPage ? () => _fetchProducts(page: _currentPage + 1) : null,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Add Product', style: TextStyle(fontWeight: FontWeight.bold)),
        onPressed: () => _openProductDialog(),
      ),
    );
  }

  Widget _buildSummaryBar() {
    final total = _summary['total'] ?? _totalCount;
    final active = _summary['active'] ?? 0;
    final inactive = _summary['inactive'] ?? 0;

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      child: Row(
        children: [
          Expanded(child: _metricPill('TOTAL PRODUCTS', '$total', Colors.blue, Icons.inventory_2_outlined)),
          const SizedBox(width: 8),
          Expanded(child: _metricPill('ACTIVE', '$active', Colors.green, Icons.check_circle_outline)),
          const SizedBox(width: 8),
          Expanded(child: _metricPill('INACTIVE', '$inactive', Colors.amber[800]!, Icons.pause_circle_outline)),
        ],
      ),
    );
  }

  Widget _metricPill(String title, String count, Color color, IconData icon) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(count, style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: color, height: 1)),
                const SizedBox(height: 2),
                Text(title, style: TextStyle(fontSize: 8.5, fontWeight: FontWeight.w700, color: color.withValues(alpha: 0.8), letterSpacing: 0.3), maxLines: 1),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterBar() {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Container(
                  height: 40,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppTheme.border),
                  ),
                  child: TextField(
                    controller: _searchController,
                    onSubmitted: (v) {
                      _searchQuery = v.trim();
                      _fetchProducts();
                    },
                    decoration: InputDecoration(
                      hintText: 'Search products by name, code...',
                      hintStyle: const TextStyle(fontSize: 13, color: AppTheme.textMuted),
                      prefixIcon: const Icon(Icons.search, size: 20, color: AppTheme.textMuted),
                      suffixIcon: _searchController.text.isNotEmpty
                          ? IconButton(
                              icon: const Icon(Icons.clear, size: 16),
                              onPressed: () {
                                _searchController.clear();
                                _searchQuery = '';
                                _fetchProducts();
                              },
                            )
                          : null,
                      border: InputBorder.none,
                      contentPadding: const EdgeInsets.symmetric(vertical: 10),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primary,
                  foregroundColor: Colors.white,
                  minimumSize: const Size(40, 40),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                onPressed: () {
                  _searchQuery = _searchController.text.trim();
                  _fetchProducts();
                },
                child: const Text('Search', style: TextStyle(fontSize: 13)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                // Status Filter
                ChoiceChip(
                  label: const Text('All Status', style: TextStyle(fontSize: 11)),
                  selected: _selectedStatus == null,
                  onSelected: (sel) {
                    if (sel) {
                      setState(() => _selectedStatus = null);
                      _fetchProducts();
                    }
                  },
                ),
                const SizedBox(width: 6),
                ChoiceChip(
                  label: const Text('Active', style: TextStyle(fontSize: 11)),
                  selected: _selectedStatus == 'Active',
                  selectedColor: Colors.green.withValues(alpha: 0.15),
                  onSelected: (sel) {
                    setState(() => _selectedStatus = sel ? 'Active' : null);
                    _fetchProducts();
                  },
                ),
                const SizedBox(width: 6),
                ChoiceChip(
                  label: const Text('Inactive', style: TextStyle(fontSize: 11)),
                  selected: _selectedStatus == 'Inactive',
                  selectedColor: Colors.amber.withValues(alpha: 0.15),
                  onSelected: (sel) {
                    setState(() => _selectedStatus = sel ? 'Inactive' : null);
                    _fetchProducts();
                  },
                ),
                if (_categories.isNotEmpty) ...[
                  const VerticalDivider(width: 16),
                  ChoiceChip(
                    label: const Text('All Categories', style: TextStyle(fontSize: 11)),
                    selected: _selectedCategory == null,
                    onSelected: (sel) {
                      if (sel) {
                        setState(() => _selectedCategory = null);
                        _fetchProducts();
                      }
                    },
                  ),
                  ..._categories.map((cat) {
                    return Padding(
                      padding: const EdgeInsets.only(left: 6),
                      child: ChoiceChip(
                        label: Text(cat, style: const TextStyle(fontSize: 11)),
                        selected: _selectedCategory == cat,
                        onSelected: (sel) {
                          setState(() => _selectedCategory = sel ? cat : null);
                          _fetchProducts();
                        },
                      ),
                    );
                  }),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProductCard(Product p) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: p.isActive ? AppTheme.border : Colors.grey.withValues(alpha: 0.3)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: p.isActive ? const Color(0xFFE0F2FE) : Colors.grey.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  Icons.inventory_2,
                  color: p.isActive ? const Color(0xFF0284C7) : Colors.grey[600],
                  size: 22,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      p.name,
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.bold,
                        color: p.isActive ? AppTheme.textMain : Colors.grey[600],
                      ),
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        if (p.code != null && p.code!.isNotEmpty) ...[
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(color: AppTheme.border),
                            ),
                            child: Text(
                              'SKU: ${p.code}',
                              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: AppTheme.textMuted),
                            ),
                          ),
                          const SizedBox(width: 6),
                        ],
                        if (p.category != null && p.category!.isNotEmpty) ...[
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEFF6FF),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(color: const Color(0xFFBFDBFE)),
                            ),
                            child: Text(
                              p.category!,
                              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF1D4ED8)),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
              // Price Tag
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    p.formattedPrice,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F766E),
                    ),
                  ),
                  if (p.unit != null && p.unit!.isNotEmpty)
                    Text(
                      'per ${p.unit}',
                      style: const TextStyle(fontSize: 10, color: AppTheme.textMuted),
                    ),
                ],
              ),
            ],
          ),
          if (p.description != null && p.description!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              p.description!,
              style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          const SizedBox(height: 10),
          const Divider(height: 1),
          const SizedBox(height: 8),
          Row(
            children: [
              // Active status switch
              InkWell(
                onTap: () => _toggleProductActive(p),
                borderRadius: BorderRadius.circular(6),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Switch(
                        value: p.isActive,
                        activeColor: Colors.green,
                        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        onChanged: (_) => _toggleProductActive(p),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        p.isActive ? 'Active' : 'Inactive',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: p.isActive ? Colors.green[700] : Colors.grey[600],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const Spacer(),
              IconButton(
                icon: const Icon(Icons.edit_outlined, size: 19, color: AppTheme.primary),
                tooltip: 'Edit Product',
                onPressed: () => _openProductDialog(p),
              ),
              IconButton(
                icon: const Icon(Icons.delete_outline, size: 19, color: Colors.red),
                tooltip: 'Delete Product',
                onPressed: () => _deleteProduct(p),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppTheme.primarySoft,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.inventory_2_outlined, size: 48, color: AppTheme.primary),
          ),
          const SizedBox(height: 16),
          const Text(
            'No Products Found',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textMain),
          ),
          const SizedBox(height: 6),
          const Text(
            'Add your first product to start managing inventory and tracking sales.',
            style: TextStyle(fontSize: 13, color: AppTheme.textMuted),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.primary,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () => _openProductDialog(),
            icon: const Icon(Icons.add),
            label: const Text('Add Product Now'),
          ),
        ],
      ),
    );
  }
}
