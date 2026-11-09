import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config.dart';
import '../models/crm_field.dart';
import '../models/customer.dart';
import '../models/customer_activity.dart';
import '../models/note_template.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../widgets/compose_dialog.dart';
import '../widgets/post_update_confirm_dialog.dart';
import 'customer_form_screen.dart';

class CustomerDetailScreen extends StatefulWidget {
  final int customerId;

  const CustomerDetailScreen({super.key, required this.customerId});

  @override
  State<CustomerDetailScreen> createState() => _CustomerDetailScreenState();
}

class _CustomerDetailScreenState extends State<CustomerDetailScreen> {
  bool _loading = true;
  String? _error;

  Customer? _customer;
  List<CrmField> _allFields = [];
  List<CrmField> _updateFields = [];
  List<String> _statusOptions = [];
  List<String> _washStatuses = [];
  List<NoteTemplate> _noteTemplates = [];
  List<CustomerActivity> _activities = [];
  Map<String, dynamic> _permissions = {};

  // Form state for Post Update
  String? _newStatus;
  final _titleController = TextEditingController();
  final _bodyController = TextEditingController();
  bool _followUp = false;
  DateTime? _followUpDate;
  bool _followUpTimeOn = false;
  TimeOfDay? _followUpTime;
  final Map<String, TextEditingController> _updateFieldControllers = {};
  bool _submittingUpdate = false;
  bool _showAllActivities = false;

  @override
  void initState() {
    super.initState();
    _fetchDetail();
  }

  @override
  void dispose() {
    _titleController.dispose();
    _bodyController.dispose();
    for (final c in _updateFieldControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _fetchDetail() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await ApiService.instance.customerShow(widget.customerId);
      if (!mounted) return;

      final custData = res['customer'] as Map<String, dynamic>;
      _customer = Customer.fromJson(custData);

      _allFields = ((res['fields'] as List?) ?? [])
          .whereType<Map>()
          .map((m) => CrmField.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      _updateFields = ((res['update_fields'] as List?) ?? [])
          .whereType<Map>()
          .map((m) => CrmField.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      // Init controllers for update fields
      for (final f in _updateFields) {
        final val = f.value?.toString() ?? '';
        _updateFieldControllers.putIfAbsent(f.key, () => TextEditingController(text: val));
        _updateFieldControllers[f.key]!.text = val;
      }

      _statusOptions = (res['status_options'] as List?)?.map((e) => e.toString()).toList() ?? [];
      _washStatuses = (res['wash_statuses'] as List?)?.map((e) => e.toString()).toList() ?? [];
      _noteTemplates = ((res['note_templates'] as List?) ?? [])
          .whereType<Map>()
          .map((m) => NoteTemplate.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      _activities = ((res['activities'] as List?) ?? [])
          .whereType<Map>()
          .map((m) => CustomerActivity.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      _permissions = (res['permissions'] is Map)
          ? Map<String, dynamic>.from(res['permissions'] as Map)
          : {};

      _newStatus = _customer?.status;
      _followUp = _customer?.followUp ?? false;
      if (_customer?.followUpAt != null) {
        try {
          _followUpDate = DateTime.parse(_customer!.followUpAt!);
          _followUpTime = TimeOfDay.fromDateTime(_followUpDate!);
          _followUpTimeOn = _customer!.followUpHasTime;
        } catch (_) {}
      }

      setState(() => _loading = false);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString().replaceAll('Exception: ', '');
        _loading = false;
      });
    }
  }

  Future<void> _makeCall() async {
    final phone = _customer?.phone;
    if (phone == null || phone.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Phone number missing')));
      return;
    }
    final clean = phone.replaceAll(RegExp(r'\D+'), '');
    final uri = Uri.parse('tel:$clean');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
      await ApiService.instance.customerAutoAction(widget.customerId, 'call');
      _fetchDetail();
    }
  }

  void _openComposer(String channel) {
    if (_customer == null) return;
    showDialog(
      context: context,
      builder: (_) => ComposeDialog(customer: _customer!, initialChannel: channel),
    ).then((sent) {
      if (sent == true) _fetchDetail();
    });
  }

  Future<void> _assignToOperations() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Assign to Operations?'),
        content: Text('Assign ${_customer?.name} to Operation Team?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Assign')),
        ],
      ),
    );
    if (confirm != true) return;

    try {
      await ApiService.instance.customerAssignOperations(widget.customerId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Assigned to Operation Team!'), backgroundColor: Colors.green),
      );
      _fetchDetail();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceAll('Exception: ', ''))),
      );
    }
  }

  Future<void> _deleteCustomer() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Customer?'),
        content: Text('Are you sure you want to permanently delete ${_customer?.name}?'),
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
      await ApiService.instance.customerDestroy(widget.customerId);
      if (!mounted) return;
      Navigator.of(context).pop(true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Customer deleted.'), backgroundColor: Colors.red),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceAll('Exception: ', ''))),
      );
    }
  }

  void _applyNoteTemplate(NoteTemplate template) {
    _titleController.text = template.title;
    _bodyController.text = template.body;
    setState(() {});
  }

  void _applyWashStatus(String wash) {
    setState(() {
      _newStatus = wash;
      _titleController.text = 'Wash: $wash';
    });
  }

  void _shiftFollowUpDate(int days) {
    final base = _followUpDate ?? DateTime.now();
    setState(() {
      _followUpDate = base.add(Duration(days: days));
      _followUp = true;
    });
  }

  void _shiftFollowUpTime(int minutes) {
    final now = _followUpTime ?? TimeOfDay.now();
    final totalMins = (now.hour * 60 + now.minute + minutes) % 1440;
    final nextTotal = totalMins < 0 ? totalMins + 1440 : totalMins;
    setState(() {
      _followUpTime = TimeOfDay(hour: nextTotal ~/ 60, minute: nextTotal % 60);
      _followUpTimeOn = true;
      _followUp = true;
    });
  }

  Future<void> _prepareAndConfirmPostUpdate() async {
    final c = _customer;
    if (c == null) return;

    final changes = <PostUpdateChangeItem>[];

    // Status change check
    final curStatus = c.status ?? '';
    final targetStatus = _newStatus ?? curStatus;
    if (targetStatus.toLowerCase() != curStatus.toLowerCase()) {
      changes.add(PostUpdateChangeItem(
        label: 'Status',
        from: curStatus.isNotEmpty ? curStatus : 'None',
        to: targetStatus,
        changed: true,
      ));
    } else if (targetStatus.isNotEmpty) {
      changes.add(PostUpdateChangeItem(label: 'Status', to: targetStatus, changed: false));
    }

    // Title / internal msg
    if (_titleController.text.trim().isNotEmpty) {
      changes.add(PostUpdateChangeItem(
        label: 'Internal Message / Title',
        to: _titleController.text.trim(),
        changed: true,
      ));
    }

    // Body / feedback
    if (_bodyController.text.trim().isNotEmpty) {
      changes.add(PostUpdateChangeItem(
        label: 'Note / Feedback',
        to: _bodyController.text.trim(),
        changed: true,
      ));
    }

    // Update fields diff
    final Map<String, String> updateFieldValues = {};
    for (final f in _updateFields) {
      final ctrl = _updateFieldControllers[f.key];
      if (ctrl == null) continue;
      final newVal = ctrl.text.trim();
      final oldVal = (f.value?.toString() ?? '').trim();
      updateFieldValues[f.key] = newVal;

      if (newVal != oldVal) {
        changes.add(PostUpdateChangeItem(
          label: f.label,
          from: oldVal.isNotEmpty ? oldVal : 'Empty',
          to: newVal.isNotEmpty ? newVal : 'Empty',
          changed: true,
        ));
      } else if (newVal.isNotEmpty) {
        changes.add(PostUpdateChangeItem(label: f.label, to: newVal, changed: false));
      }
    }

    // Follow up change
    final initFu = c.followUp;
    final initFuAt = c.followUpAt ?? '';
    final curFu = _followUp;
    String curFuSummary = 'Off';
    if (curFu && _followUpDate != null) {
      final dStr = DateFormat('yyyy-MM-dd').format(_followUpDate!);
      final tStr = _followUpTimeOn && _followUpTime != null
          ? ' ${DateFormat('HH:mm').format(DateTime(2020, 1, 1, _followUpTime!.hour, _followUpTime!.minute))}'
          : '';
      curFuSummary = '$dStr$tStr';
    }

    if (initFu != curFu || (curFu && curFuSummary != initFuAt)) {
      changes.add(PostUpdateChangeItem(
        label: 'Follow Up',
        from: initFu ? initFuAt : 'Off',
        to: curFuSummary,
        changed: true,
      ));
    }

    if (changes.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please write a note, change status, or set follow-up.')),
      );
      return;
    }

    // Show Confirm Post Update Modal Dialog
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => PostUpdateConfirmDialog(
        customerName: c.name,
        changes: changes,
      ),
    );

    if (confirmed != true) return;

    setState(() => _submittingUpdate = true);
    try {
      final payload = <String, dynamic>{
        'type': 'note',
        'status_change': targetStatus,
        'title': _titleController.text.trim(),
        'body': _bodyController.text.trim().isNotEmpty ? _bodyController.text.trim() : null,
        'follow_up': _followUp,
        if (_followUpDate != null) 'follow_up_date': DateFormat('yyyy-MM-dd').format(_followUpDate!),
        'follow_up_time_on': _followUpTimeOn,
        if (_followUpTime != null)
          'follow_up_time': DateFormat('HH:mm').format(DateTime(2020, 1, 1, _followUpTime!.hour, _followUpTime!.minute)),
        'update_fields': updateFieldValues,
      };

      await ApiService.instance.customerAddActivity(widget.customerId, payload);
      if (!mounted) return;
      _titleController.clear();
      _bodyController.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Update posted successfully!'), backgroundColor: Colors.green),
      );
      _fetchDetail();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceAll('Exception: ', ''))),
      );
    } finally {
      if (mounted) setState(() => _submittingUpdate = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Customer Details')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null || _customer == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Customer Details')),
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error ?? 'Customer not found', style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 12),
              ElevatedButton(onPressed: _fetchDetail, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }

    final c = _customer!;

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(c.name, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            Text(
              '${c.pipeline.toUpperCase()} • ${c.phone ?? "No phone"}',
              style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_outlined),
            tooltip: 'Edit Record',
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => CustomerFormScreen(
                    pipeline: c.pipeline,
                    customer: c,
                    formFields: _allFields.where((f) => f.inForm).toList(),
                  ),
                ),
              ).then((res) {
                if (res == true) _fetchDetail();
              });
            },
          ),
          IconButton(
            icon: const Icon(Icons.delete_outline, color: Colors.red),
            tooltip: 'Delete Record',
            onPressed: _deleteCustomer,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchDetail,
        child: ListView(
          padding: const EdgeInsets.all(12),
          children: [
            // Left Column Card replica: Compact Customer Profile
            _buildProfileCard(c),
            const SizedBox(height: 14),

            // Right Column Card replica: Post Update Section
            _buildPostUpdateCard(c),
            const SizedBox(height: 14),

            // Bottom Full-Width Section replica: Activities / Messages Timeline
            _buildActivityTimeline(c),
          ],
        ),
      ),
    );
  }

  Widget _buildProfileCard(Customer c) {
    return Container(
      decoration: BoxDecoration(
        color: AppTheme.skySoft,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.skyBorder),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Row
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      c.name,
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0C4A6E)),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Created: ${c.createdLabel ?? "—"} • ID #${c.id}',
                      style: const TextStyle(fontSize: 11.5, color: Color(0xFF0369A1)),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.statusBg(c.status),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppTheme.statusBorder(c.status)),
                ),
                child: Text(
                  (c.status ?? 'New').toUpperCase(),
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.statusText(c.status)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Quick Action Bar: Call, WhatsApp, Email, SMS, Assigned to OPS
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.9),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppTheme.skyBorder),
            ),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                _actionIconButton(Icons.call, 'Call', AppTheme.skyText, AppTheme.skySoft, _makeCall),
                _actionIconButton(Icons.chat, 'WhatsApp', const Color(0xFF16A34A), const Color(0xFFDCFCE7), () => _openComposer('whatsapp')),
                _actionIconButton(Icons.mail, 'Email', const Color(0xFFD97706), const Color(0xFFFEF3C7), () => _openComposer('email')),
                _actionIconButton(Icons.sms, 'SMS', const Color(0xFF7C3AED), const Color(0xFFEDE9FE), () => _openComposer('sms')),
                if (!c.isOperations) ...[
                  if (_permissions['can_assign_operations'] == true)
                    ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF059669),
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        minimumSize: Size.zero,
                      ),
                      onPressed: _assignToOperations,
                      icon: const Icon(Icons.arrow_forward, size: 14),
                      label: const Text('Assign to OPS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                    )
                  else if (_permissions['is_assigned_to_ops'] == true)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppTheme.emeraldSoft,
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: AppTheme.emeraldBorder),
                      ),
                      child: const Text('Assigned to OPS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.emeraldText)),
                    ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),

          // All Customer Fields Grid
          const Text(
            'CUSTOMER INFORMATION',
            style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0369A1), letterSpacing: 0.6),
          ),
          const SizedBox(height: 8),

          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: _allFields.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 8,
              mainAxisSpacing: 8,
              childAspectRatio: 2.2,
            ),
            itemBuilder: (context, index) {
              final f = _allFields[index];
              final rawVal = f.value?.toString() ?? '—';

              final isMapUrl = f.key.toLowerCase().contains('map') && rawVal.startsWith('http');

              return Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFBAE6FD)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      f.label.toUpperCase(),
                      style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Color(0xFF0284C7)),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    if (isMapUrl)
                      InkWell(
                        onTap: () async {
                          final uri = Uri.parse(rawVal);
                          if (await canLaunchUrl(uri)) launchUrl(uri, mode: LaunchMode.externalApplication);
                        },
                        child: const Row(
                          children: [
                            Icon(Icons.location_on, size: 12, color: AppTheme.primary),
                            SizedBox(width: 4),
                            Text('Open Map URL', style: TextStyle(fontSize: 11, color: AppTheme.primary, fontWeight: FontWeight.bold)),
                          ],
                        ),
                      )
                    else
                      Text(
                        rawVal.isEmpty ? '—' : rawVal,
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textMain),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                  ],
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _actionIconButton(IconData icon, String label, Color color, Color bg, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 16),
            const SizedBox(width: 4),
            Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }

  Widget _buildPostUpdateCard(Customer c) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.border),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'POST UPDATE',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, letterSpacing: 0.8, color: AppTheme.primary),
              ),
              if (_washStatuses.isNotEmpty) ...[
                Wrap(
                  spacing: 6,
                  children: _washStatuses.map((w) {
                    final isSel = _newStatus?.toLowerCase() == w.toLowerCase();
                    return ChoiceChip(
                      label: Text(w, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                      selected: isSel,
                      selectedColor: AppTheme.statusBg(w),
                      onSelected: (_) => _applyWashStatus(w),
                    );
                  }).toList(),
                ),
              ],
            ],
          ),
          const Divider(height: 20),

          // Note Templates
          if (_noteTemplates.isNotEmpty) ...[
            const Text('QUICK NOTE TEMPLATES', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: AppTheme.textMuted)),
            const SizedBox(height: 6),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: _noteTemplates.map((t) {
                return ActionChip(
                  label: Text(t.title, style: const TextStyle(fontSize: 11)),
                  backgroundColor: AppTheme.violetSoft,
                  side: const BorderSide(color: AppTheme.violetBorder),
                  padding: EdgeInsets.zero,
                  onPressed: () => _applyNoteTemplate(t),
                );
              }).toList(),
            ),
            const SizedBox(height: 14),
          ],

          // Status & Title
          Row(
            children: [
              Expanded(
                flex: 1,
                child: DropdownButtonFormField<String>(
                  value: _statusOptions.contains(_newStatus) ? _newStatus : null,
                  decoration: const InputDecoration(labelText: 'Status'),
                  items: _statusOptions.map((s) => DropdownMenuItem(value: s, child: Text(s))).toList(),
                  onChanged: (v) => setState(() => _newStatus = v),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: TextField(
                  controller: _titleController,
                  decoration: const InputDecoration(labelText: 'Internal Message / Title'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Body Textarea
          TextField(
            controller: _bodyController,
            maxLines: 4,
            decoration: const InputDecoration(
              labelText: 'Note / Feedback',
              hintText: 'Enter call note, customer response, or update details...',
            ),
          ),
          const SizedBox(height: 14),

          // Update Fields Grid (Section 2)
          if (_updateFields.isNotEmpty) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.emeraldSoft.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.emeraldBorder),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('UPDATE CUSTOMER FIELDS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.emeraldText)),
                  const SizedBox(height: 8),
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: _updateFields.length,
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      crossAxisSpacing: 8,
                      mainAxisSpacing: 8,
                      childAspectRatio: 2.4,
                    ),
                    itemBuilder: (context, index) {
                      final f = _updateFields[index];
                      final ctrl = _updateFieldControllers[f.key];
                      return TextField(
                        controller: ctrl,
                        decoration: InputDecoration(
                          labelText: f.label,
                          fillColor: Colors.white,
                          contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
          ],

          // Follow Up Section (Section 3)
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppTheme.amberSoft.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppTheme.amberBorder),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Checkbox(
                      value: _followUp,
                      onChanged: (v) => setState(() => _followUp = v ?? false),
                    ),
                    const Text('Schedule Follow-up', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.amberText)),
                  ],
                ),
                if (_followUp) ...[
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () async {
                            final picked = await showDatePicker(
                              context: context,
                              initialDate: _followUpDate ?? DateTime.now(),
                              firstDate: DateTime(2020),
                              lastDate: DateTime(2030),
                            );
                            if (picked != null) setState(() => _followUpDate = picked);
                          },
                          icon: const Icon(Icons.calendar_today, size: 14),
                          label: Text(
                            _followUpDate != null ? DateFormat('dd MMM yyyy').format(_followUpDate!) : 'Pick Date',
                            style: const TextStyle(fontSize: 12),
                          ),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.remove, size: 18),
                        tooltip: '-1 Day',
                        onPressed: () => _shiftFollowUpDate(-1),
                      ),
                      IconButton(
                        icon: const Icon(Icons.add, size: 18),
                        tooltip: '+1 Day',
                        onPressed: () => _shiftFollowUpDate(1),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Checkbox(
                        value: _followUpTimeOn,
                        onChanged: (v) => setState(() => _followUpTimeOn = v ?? false),
                      ),
                      const Text('Time', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                      if (_followUpTimeOn) ...[
                        const SizedBox(width: 8),
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () async {
                              final picked = await showTimePicker(
                                context: context,
                                initialTime: _followUpTime ?? TimeOfDay.now(),
                              );
                              if (picked != null) setState(() => _followUpTime = picked);
                            },
                            child: Text(
                              _followUpTime != null ? _followUpTime!.format(context) : 'Select Time',
                              style: const TextStyle(fontSize: 12),
                            ),
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.remove, size: 18),
                          tooltip: '-15 Mins',
                          onPressed: () => _shiftFollowUpTime(-15),
                        ),
                        IconButton(
                          icon: const Icon(Icons.add, size: 18),
                          tooltip: '+15 Mins',
                          onPressed: () => _shiftFollowUpTime(15),
                        ),
                      ],
                    ],
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Submit Button
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              onPressed: _submittingUpdate ? null : _prepareAndConfirmPostUpdate,
              icon: _submittingUpdate
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.check, size: 18),
              label: const Text('Post Update (Review & Confirm)', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActivityTimeline(Customer c) {
    final displayList = _showAllActivities ? _activities : _activities.take(3).toList();

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.border),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'ACTIVITY TIMELINE (${_activities.length})',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.textMuted),
              ),
              if (_activities.length > 3)
                TextButton(
                  onPressed: () => setState(() => _showAllActivities = !_showAllActivities),
                  child: Text(_showAllActivities ? 'Show Less' : 'View More (${_activities.length - 3})'),
                ),
            ],
          ),
          const Divider(height: 16),

          if (_activities.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Center(child: Text('No activities recorded yet.', style: TextStyle(color: AppTheme.textMuted))),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: displayList.length,
              separatorBuilder: (_, __) => const Divider(height: 20),
              itemBuilder: (context, index) {
                final a = displayList[index];

                IconData icon;
                Color iconBg;
                Color iconColor;

                switch (a.type) {
                  case 'call':
                    icon = Icons.call;
                    iconBg = AppTheme.skySoft;
                    iconColor = AppTheme.skyText;
                    break;
                  case 'whatsapp':
                    icon = Icons.chat;
                    iconBg = const Color(0xFFDCFCE7);
                    iconColor = const Color(0xFF16A34A);
                    break;
                  case 'email':
                    icon = Icons.mail;
                    iconBg = AppTheme.amberSoft;
                    iconColor = AppTheme.amberText;
                    break;
                  case 'sms':
                    icon = Icons.sms;
                    iconBg = AppTheme.violetSoft;
                    iconColor = AppTheme.violetText;
                    break;
                  case 'status':
                    icon = Icons.sync_alt;
                    iconBg = AppTheme.emeraldSoft;
                    iconColor = AppTheme.emeraldText;
                    break;
                  default:
                    icon = Icons.notes;
                    iconBg = const Color(0xFFF1F5F9);
                    iconColor = AppTheme.textMain;
                }

                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(color: iconBg, borderRadius: BorderRadius.circular(8)),
                      child: Icon(icon, color: iconColor, size: 16),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(a.title.isNotEmpty ? a.title : a.type.toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                              Text(a.createdAt, style: const TextStyle(fontSize: 10.5, color: AppTheme.textMuted)),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text('By ${a.userName}', style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                          const SizedBox(height: 6),
                          Text(a.body, style: const TextStyle(fontSize: 12.5, height: 1.35)),

                          // Field Changes preview
                          if (a.fieldChanges != null && a.fieldChanges!.isNotEmpty) ...[
                            const SizedBox(height: 6),
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF8FAFC),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: AppTheme.border),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: a.fieldChanges!.entries.map((e) {
                                  final val = e.value as Map;
                                  return Text(
                                    '${e.key}: ${val['from'] ?? "—"} → ${val['to'] ?? "—"}',
                                    style: const TextStyle(fontSize: 11, color: AppTheme.textMain),
                                  );
                                }).toList(),
                              ),
                            ),
                          ],

                          // Images
                          if (a.images.isNotEmpty) ...[
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: a.images.map((img) {
                                return ClipRRect(
                                  borderRadius: BorderRadius.circular(8),
                                  child: Image.network(
                                    img.url.startsWith('http') ? img.url : '$kSiteBaseUrl/${img.url}',
                                    width: 70,
                                    height: 70,
                                    fit: BoxFit.cover,
                                    errorBuilder: (_, __, ___) => Container(
                                      width: 70,
                                      height: 70,
                                      color: Colors.grey.shade200,
                                      child: const Icon(Icons.broken_image, size: 24),
                                    ),
                                  ),
                                );
                              }).toList(),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                );
              },
            ),
        ],
      ),
    );
  }
}
