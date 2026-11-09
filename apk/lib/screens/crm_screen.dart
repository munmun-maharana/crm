import 'package:flutter/material.dart';
import '../models/customer.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../widgets/booking_slots_slider.dart';
import '../widgets/bulk_email_dialog.dart';
import '../widgets/excel_dialogs.dart';
import 'customer_detail_screen.dart';
import 'customer_form_screen.dart';

class CrmScreen extends StatefulWidget {
  final String pipeline; // 'crm' or 'operations'
  final String? initialFilter;
  final String? initialStatus;

  const CrmScreen({
    super.key,
    this.pipeline = 'crm',
    this.initialFilter,
    this.initialStatus,
  });

  @override
  State<CrmScreen> createState() => _CrmScreenState();
}

class _CrmScreenState extends State<CrmScreen> {
  final _searchController = TextEditingController();
  final Set<int> _selectedIds = {};

  bool _loading = false;
  String? _error;
  List<Customer> _customers = [];
  List<String> _statusOptions = [];
  List<Map<String, dynamic>> _agents = [];
  bool _showBookingSlots = false;

  String _searchQuery = '';
  String? _statusFilter;
  String? _specialFilter;
  int? _assignedToFilter;

  int _currentPage = 1;
  int _lastPage = 1;
  int _totalCount = 0;

  @override
  void initState() {
    super.initState();
    _specialFilter = widget.initialFilter;
    _statusFilter = widget.initialStatus;
    _fetchMeta();
    _fetchCustomers();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchMeta() async {
    try {
      final res = await ApiService.instance.customerMeta(pipeline: widget.pipeline);
      if (mounted) {
        setState(() {
          _statusOptions = (res['status_options'] as List?)?.map((e) => e.toString()).toList() ?? [];
          final rawAgents = (res['agents'] as List?) ?? [];
          _agents = rawAgents.whereType<Map>().map((m) => Map<String, dynamic>.from(m)).toList();
        });
      }
    } catch (_) {}
  }

  Future<void> _fetchCustomers({int page = 1}) async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await ApiService.instance.customers(
        pipeline: widget.pipeline,
        q: _searchQuery,
        status: _statusFilter,
        filter: _specialFilter,
        assignedTo: _assignedToFilter,
        page: page,
      );

      final rawList = (res['customers'] as List?) ?? [];
      final items = rawList
          .whereType<Map>()
          .map((m) => Customer.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      final pagination = (res['pagination'] is Map) ? res['pagination'] as Map : {};

      if (mounted) {
        setState(() {
          _currentPage = pagination['current_page'] as int? ?? 1;
          _lastPage = pagination['last_page'] as int? ?? 1;
          _totalCount = pagination['total'] as int? ?? items.length;
          _customers = items;
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

  void _onSearch(String val) {
    setState(() => _searchQuery = val.trim());
    _fetchCustomers(page: 1);
  }

  void _toggleSelect(int id) {
    setState(() {
      if (_selectedIds.contains(id)) {
        _selectedIds.remove(id);
      } else {
        _selectedIds.add(id);
      }
    });
  }

  Future<void> _bulkMap() async {
    if (_selectedIds.isEmpty) return;
    int? selectedAgentId;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: const Text('Bulk Map Customers'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Assign ${_selectedIds.length} customers to an agent:'),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    value: selectedAgentId,
                    decoration: const InputDecoration(labelText: 'Select Agent'),
                    items: _agents
                        .map((a) => DropdownMenuItem(value: a['id'] as int, child: Text(a['name']?.toString() ?? '')))
                        .toList(),
                    onChanged: (v) => setDialogState(() => selectedAgentId = v),
                  ),
                ],
              ),
              actions: [
                TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
                ElevatedButton(
                  onPressed: selectedAgentId != null ? () => Navigator.of(ctx).pop(true) : null,
                  child: const Text('Assign'),
                ),
              ],
            );
          },
        );
      },
    );

    if (confirmed == true && selectedAgentId != null) {
      try {
        await ApiService.instance.customerBulkMap(_selectedIds.toList(), selectedAgentId!);
        _selectedIds.clear();
        _fetchCustomers();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Customers mapped successfully!'), backgroundColor: Colors.green),
          );
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
        }
      }
    }
  }

  void _openBulkEmail() {
    final selected = _customers.where((c) => _selectedIds.contains(c.id)).toList();
    if (selected.isEmpty) return;
    showDialog(
      context: context,
      builder: (_) => BulkEmailDialog(customers: selected),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      body: Column(
        children: [
          // CRM Top Toolbar (Red border and light red background matching website)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: Column(
              children: [
                // Toolbar Container with Red border
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFE4E6), // Light red bg matching website
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFEF4444)), // Red border
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.search, size: 18, color: Color(0xFFE11D48)),
                      const SizedBox(width: 6),
                      Expanded(
                        child: TextField(
                          controller: _searchController,
                          decoration: const InputDecoration(
                            hintText: 'Search name, phone, email, city...',
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            filled: false,
                            contentPadding: EdgeInsets.zero,
                            isDense: true,
                          ),
                          onSubmitted: _onSearch,
                        ),
                      ),
                      if (_searchQuery.isNotEmpty)
                        IconButton(
                          icon: const Icon(Icons.close, size: 16),
                          visualDensity: VisualDensity.compact,
                          onPressed: () {
                            _searchController.clear();
                            _onSearch('');
                          },
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 6),

                // Secondary Filter Controls Bar
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      // Today Follow-up button matching website
                      ActionChip(
                        avatar: Icon(
                          Icons.calendar_today,
                          size: 14,
                          color: _specialFilter == 'follow_up_today' ? Colors.white : const Color(0xFFEA580C),
                        ),
                        label: Text(
                          'Today Follow-up',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: _specialFilter == 'follow_up_today' ? Colors.white : const Color(0xFF9A3412),
                          ),
                        ),
                        backgroundColor: _specialFilter == 'follow_up_today' ? const Color(0xFFEA580C) : const Color(0xFFFFEDD5),
                        side: const BorderSide(color: Color(0xFFFDBA74)),
                        onPressed: () {
                          setState(() {
                            _specialFilter = _specialFilter == 'follow_up_today' ? null : 'follow_up_today';
                          });
                          _fetchCustomers(page: 1);
                        },
                      ),
                      const SizedBox(width: 6),

                      // Status Filter Dropdown
                      Container(
                        height: 32,
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: AppTheme.border),
                        ),
                        child: DropdownButton<String>(
                          value: _statusFilter,
                          underline: const SizedBox(),
                          isDense: true,
                          hint: const Text('All Status', style: TextStyle(fontSize: 12)),
                          items: [
                            const DropdownMenuItem(value: null, child: Text('All Status', style: TextStyle(fontSize: 12))),
                            ..._statusOptions.map((s) => DropdownMenuItem(value: s, child: Text(s, style: const TextStyle(fontSize: 12)))),
                          ],
                          onChanged: (v) {
                            setState(() => _statusFilter = v);
                            _fetchCustomers(page: 1);
                          },
                        ),
                      ),
                      const SizedBox(width: 6),

                      // User filter (if admin or coordinator)
                      if (ApiService.instance.canSeeAll && _agents.isNotEmpty) ...[
                        Container(
                          height: 32,
                          padding: const EdgeInsets.symmetric(horizontal: 8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.border),
                          ),
                          child: DropdownButton<int>(
                            value: _assignedToFilter,
                            underline: const SizedBox(),
                            isDense: true,
                            hint: const Text('All Users', style: TextStyle(fontSize: 12)),
                            items: [
                              const DropdownMenuItem(value: null, child: Text('All Users', style: TextStyle(fontSize: 12))),
                              ..._agents.map((a) => DropdownMenuItem(value: a['id'] as int, child: Text(a['name']?.toString() ?? '', style: const TextStyle(fontSize: 12)))),
                            ],
                            onChanged: (v) {
                              setState(() => _assignedToFilter = v);
                              _fetchCustomers(page: 1);
                            },
                          ),
                        ),
                        const SizedBox(width: 6),
                      ],

                      // Booking Slots toggle button
                      ActionChip(
                        avatar: Icon(Icons.event, size: 14, color: _showBookingSlots ? Colors.white : AppTheme.primary),
                        label: Text(
                          'Booking Slots',
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: _showBookingSlots ? Colors.white : AppTheme.primary),
                        ),
                        backgroundColor: _showBookingSlots ? AppTheme.primary : AppTheme.primarySoft,
                        side: const BorderSide(color: Color(0xFFBAE6FD)),
                        onPressed: () => setState(() => _showBookingSlots = !_showBookingSlots),
                      ),
                      const SizedBox(width: 6),

                      // Excel Button
                      ActionChip(
                        avatar: const Icon(Icons.table_view, size: 14, color: Color(0xFF047857)),
                        label: const Text('Excel', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF047857))),
                        backgroundColor: AppTheme.emeraldSoft,
                        side: const BorderSide(color: AppTheme.emeraldBorder),
                        onPressed: () {
                          showDialog(
                            context: context,
                            builder: (_) => ExcelDialog(statusOptions: _statusOptions),
                          );
                        },
                      ),
                      const SizedBox(width: 6),

                      // + Add Customer Button
                      ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF0F6CBD),
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          minimumSize: Size.zero,
                        ),
                        onPressed: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => CustomerFormScreen(pipeline: widget.pipeline),
                            ),
                          ).then((created) {
                            if (created == true) _fetchCustomers();
                          });
                        },
                        icon: const Icon(Icons.add, size: 14),
                        label: const Text('Add', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Booking Slots Slider Widget
          if (_showBookingSlots)
            BookingSlotsSlider(
              onSlotBooked: () => _fetchCustomers(),
            ),

          // Selection summary bar (when items selected)
          if (_selectedIds.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              color: AppTheme.primarySoft,
              child: Row(
                children: [
                  Text(
                    '${_selectedIds.length} selected',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.primary),
                  ),
                  const Spacer(),
                  TextButton.icon(
                    onPressed: _openBulkEmail,
                    icon: const Icon(Icons.email, size: 16),
                    label: const Text('Bulk Gmail', style: TextStyle(fontSize: 12)),
                  ),
                  if (ApiService.instance.canBulkMap)
                    TextButton.icon(
                      onPressed: _bulkMap,
                      icon: const Icon(Icons.person_add, size: 16),
                      label: const Text('Map', style: TextStyle(fontSize: 12)),
                    ),
                  IconButton(
                    icon: const Icon(Icons.close, size: 18),
                    onPressed: () => setState(() => _selectedIds.clear()),
                  ),
                ],
              ),
            ),

          // Main List View
          Expanded(
            child: _loading && _customers.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, style: const TextStyle(color: Colors.red)),
                            const SizedBox(height: 8),
                            ElevatedButton(onPressed: () => _fetchCustomers(), child: const Text('Retry')),
                          ],
                        ),
                      )
                    : _customers.isEmpty
                        ? const Center(child: Text('No customers found.', style: TextStyle(color: AppTheme.textMuted)))
                        : RefreshIndicator(
                            onRefresh: () => _fetchCustomers(page: _currentPage),
                            child: ListView.separated(
                              padding: const EdgeInsets.all(12),
                              itemCount: _customers.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 8),
                              itemBuilder: (context, index) {
                                final c = _customers[index];
                                final isSelected = _selectedIds.contains(c.id);

                                return Card(
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                    side: BorderSide(
                                      color: isSelected ? AppTheme.primary : AppTheme.border,
                                      width: isSelected ? 1.5 : 1,
                                    ),
                                  ),
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(12),
                                    onTap: () {
                                      Navigator.of(context).push(
                                        MaterialPageRoute(
                                          builder: (_) => CustomerDetailScreen(customerId: c.id),
                                        ),
                                      ).then((_) => _fetchCustomers(page: _currentPage));
                                    },
                                    onLongPress: () => _toggleSelect(c.id),
                                    child: Padding(
                                      padding: const EdgeInsets.all(12),
                                      child: Row(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Checkbox(
                                            value: isSelected,
                                            onChanged: (_) => _toggleSelect(c.id),
                                            visualDensity: VisualDensity.compact,
                                          ),
                                          const SizedBox(width: 6),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Row(
                                                  children: [
                                                    Expanded(
                                                      child: Text(
                                                        c.name,
                                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                                        maxLines: 1,
                                                        overflow: TextOverflow.ellipsis,
                                                      ),
                                                    ),
                                                    Container(
                                                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                                      decoration: BoxDecoration(
                                                        color: AppTheme.statusBg(c.status),
                                                        borderRadius: BorderRadius.circular(6),
                                                        border: Border.all(color: AppTheme.statusBorder(c.status)),
                                                      ),
                                                      child: Text(
                                                        (c.status ?? 'New').toUpperCase(),
                                                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.statusText(c.status)),
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                                const SizedBox(height: 4),
                                                Text(
                                                  '${c.phone ?? "No phone"} • ${c.city ?? "No city"}${c.assigneeName != null ? " • ${c.assigneeName}" : ""}',
                                                  style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                                                  maxLines: 1,
                                                  overflow: TextOverflow.ellipsis,
                                                ),
                                                const SizedBox(height: 6),

                                                // Date summary badge
                                                _buildDateSummaryBadge(c),
                                              ],
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
          ),

          // Pagination Bar
          if (_lastPage > 1)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              color: Colors.white,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Page $_currentPage of $_lastPage ($_totalCount records)', style: const TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                  Row(
                    children: [
                      IconButton(
                        icon: const Icon(Icons.chevron_left),
                        onPressed: _currentPage > 1 ? () => _fetchCustomers(page: _currentPage - 1) : null,
                      ),
                      IconButton(
                        icon: const Icon(Icons.chevron_right),
                        onPressed: _currentPage < _lastPage ? () => _fetchCustomers(page: _currentPage + 1) : null,
                      ),
                    ],
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildDateSummaryBadge(Customer c) {
    final ds = c.dateSummary;
    final kind = ds['kind']?.toString();
    final date = ds['date']?.toString();
    final time = ds['time']?.toString();

    if (kind == 'follow_up' && date != null) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        decoration: BoxDecoration(
          color: AppTheme.amberSoft,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: AppTheme.amberBorder),
        ),
        child: Text(
          '⏰ FU: $date • $time',
          style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: AppTheme.amberText),
        ),
      );
    }

    if (kind == 'booked' && date != null) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        decoration: BoxDecoration(
          color: AppTheme.emeraldSoft,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: AppTheme.emeraldBorder),
        ),
        child: Text(
          '📅 Booked: $date • $time',
          style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: AppTheme.emeraldText),
        ),
      );
    }

    if (date != null) {
      return Text('Created: $date', style: const TextStyle(fontSize: 11, color: AppTheme.textMuted));
    }

    return const SizedBox();
  }
}
