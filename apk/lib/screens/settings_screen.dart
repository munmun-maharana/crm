import 'package:flutter/material.dart';
import '../models/crm_field.dart';
import '../models/note_template.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import 'booking_slots_admin_screen.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _loading = false;
  String? _error;

  Map<String, dynamic> _fieldTypes = {};
  List<CrmField> _crmFields = [];
  List<CrmField> _opFields = [];
  List<NoteTemplate> _noteTemplates = [];
  Map<String, dynamic> _opSetting = {};
  List<String> _opStatuses = [];
  Map<String, dynamic> _whatsappSetting = {};
  Map<String, dynamic> _bookingSlotSetting = {};

  // Controllers for settings forms
  final _waPhoneController = TextEditingController();
  final _waMsgController = TextEditingController();

  final _opTitleController = TextEditingController();
  final _opBodyController = TextEditingController();
  String? _opSelectedStatus;

  final _bsTitleController = TextEditingController();
  final _bsBodyController = TextEditingController();
  String? _bsSelectedStatus;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 5, vsync: this);
    _fetchSettings();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _waPhoneController.dispose();
    _waMsgController.dispose();
    _opTitleController.dispose();
    _opBodyController.dispose();
    _bsTitleController.dispose();
    _bsBodyController.dispose();
    super.dispose();
  }

  Future<void> _fetchSettings() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await ApiService.instance.settingsIndex();
      if (!mounted) return;

      _fieldTypes = (res['field_types'] is Map) ? Map<String, dynamic>.from(res['field_types'] as Map) : {};

      final rawFields = (res['fields'] is Map) ? res['fields'] as Map : {};
      _crmFields = ((rawFields['crm'] as List?) ?? [])
          .whereType<Map>()
          .map((m) => CrmField.fromJson(Map<String, dynamic>.from(m)))
          .toList();
      _opFields = ((rawFields['operations'] as List?) ?? [])
          .whereType<Map>()
          .map((m) => CrmField.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      _noteTemplates = ((res['note_templates'] as List?) ?? [])
          .whereType<Map>()
          .map((m) => NoteTemplate.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      _opSetting = (res['operation_setting'] is Map) ? Map<String, dynamic>.from(res['operation_setting'] as Map) : {};
      _opStatuses = (res['operation_statuses'] as List?)?.map((e) => e.toString()).toList() ?? [];
      _opSelectedStatus = _opSetting['status']?.toString();
      _opTitleController.text = _opSetting['title']?.toString() ?? '';
      _opBodyController.text = _opSetting['body']?.toString() ?? '';

      _whatsappSetting = (res['whatsapp_setting'] is Map) ? Map<String, dynamic>.from(res['whatsapp_setting'] as Map) : {};
      _waPhoneController.text = _whatsappSetting['phone']?.toString() ?? '';
      _waMsgController.text = _whatsappSetting['message_template']?.toString() ?? '';

      _bookingSlotSetting = (res['booking_slot_setting'] is Map) ? Map<String, dynamic>.from(res['booking_slot_setting'] as Map) : {};
      _bsSelectedStatus = _bookingSlotSetting['status']?.toString();
      _bsTitleController.text = _bookingSlotSetting['title']?.toString() ?? '';
      _bsBodyController.text = _bookingSlotSetting['body']?.toString() ?? '';

      setState(() => _loading = false);
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString().replaceAll('Exception: ', '');
          _loading = false;
        });
      }
    }
  }

  // --- Field Dialog (Add / Edit) ---
  Future<void> _openFieldDialog(String pipeline, [CrmField? field]) async {
    final isEdit = field != null;
    final labelCtrl = TextEditingController(text: field?.label ?? '');
    String type = field?.type ?? 'text';
    final optionsCtrl = TextEditingController(text: field?.options.join(', ') ?? '');
    bool required = field?.required ?? false;
    bool inForm = field?.inForm ?? true;
    bool inTable = field?.inTable ?? false;
    bool inExcel = field?.inExcel ?? (pipeline == 'crm');
    bool inUpdate = field?.inUpdate ?? false;
    bool autoSelect = field?.autoSelect ?? false;
    bool submitting = false;
    String? dialogError;

    final saved = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: Text(isEdit ? 'Edit Field (${field.label})' : 'Add Custom Field'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (dialogError != null) ...[
                      Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                      const SizedBox(height: 8),
                    ],
                    TextField(controller: labelCtrl, decoration: const InputDecoration(labelText: 'Field Label *')),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      value: _fieldTypes.containsKey(type) ? type : 'text',
                      decoration: const InputDecoration(labelText: 'Field Type'),
                      items: _fieldTypes.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value.toString()))).toList(),
                      onChanged: (v) => setDialogState(() => type = v ?? 'text'),
                    ),
                    if (type == 'select') ...[
                      const SizedBox(height: 10),
                      TextField(
                        controller: optionsCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Dropdown Options (comma separated)',
                          hintText: 'Option 1, Option 2, Option 3',
                        ),
                      ),
                      CheckboxListTile(
                        title: const Text('Auto-select first option on create', style: TextStyle(fontSize: 12)),
                        value: autoSelect,
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        onChanged: (v) => setDialogState(() => autoSelect = v ?? false),
                      ),
                    ],
                    const SizedBox(height: 8),
                    CheckboxListTile(
                      title: const Text('Required', style: TextStyle(fontSize: 12)),
                      value: required,
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      onChanged: (v) => setDialogState(() => required = v ?? false),
                    ),
                    CheckboxListTile(
                      title: const Text('Show in Create Form', style: TextStyle(fontSize: 12)),
                      value: inForm,
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      onChanged: (v) => setDialogState(() => inForm = v ?? false),
                    ),
                    CheckboxListTile(
                      title: const Text('Show in Table', style: TextStyle(fontSize: 12)),
                      value: inTable,
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      onChanged: (v) => setDialogState(() => inTable = v ?? false),
                    ),
                    CheckboxListTile(
                      title: const Text('Include in Post Update Section', style: TextStyle(fontSize: 12)),
                      value: inUpdate,
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      onChanged: (v) => setDialogState(() => inUpdate = v ?? false),
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
                ElevatedButton(
                  onPressed: submitting
                      ? null
                      : () async {
                          if (labelCtrl.text.trim().isEmpty) {
                            setDialogState(() => dialogError = 'Label is required.');
                            return;
                          }
                          setDialogState(() {
                            submitting = true;
                            dialogError = null;
                          });
                          try {
                            final payload = <String, dynamic>{
                              'pipeline': pipeline,
                              'label': labelCtrl.text.trim(),
                              'type': type,
                              'options': optionsCtrl.text.trim(),
                              'required': required,
                              'in_form': inForm,
                              'in_table': inTable,
                              'in_excel': inExcel,
                              'in_update': inUpdate,
                              'auto_select': autoSelect,
                            };
                            if (isEdit) {
                              await ApiService.instance.settingsFieldUpdate(field.id, payload);
                            } else {
                              await ApiService.instance.settingsFieldStore(payload);
                            }
                            if (ctx.mounted) Navigator.of(ctx).pop(true);
                          } catch (e) {
                            setDialogState(() {
                              submitting = false;
                              dialogError = e.toString().replaceAll('Exception: ', '');
                            });
                          }
                        },
                  child: Text(isEdit ? 'Save Changes' : 'Create Field'),
                ),
              ],
            );
          },
        );
      },
    );

    if (saved == true) {
      _fetchSettings();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(isEdit ? 'Field updated!' : 'Field created!'), backgroundColor: Colors.green),
        );
      }
    }
  }

  Future<void> _deleteField(CrmField field) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Field?'),
        content: Text('Delete field "${field.label}"? Existing data for this field will be cleared.'),
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
      await ApiService.instance.settingsFieldDestroy(field.id);
      _fetchSettings();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  // --- Note Template Dialog ---
  Future<void> _openNoteTemplateDialog([NoteTemplate? template]) async {
    final isEdit = template != null;
    final titleCtrl = TextEditingController(text: template?.title ?? '');
    final bodyCtrl = TextEditingController(text: template?.body ?? '');
    bool submitting = false;
    String? dialogError;

    final saved = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: Text(isEdit ? 'Edit Note Template' : 'Add Note Template'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (dialogError != null) ...[
                      Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                      const SizedBox(height: 8),
                    ],
                    TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Template Title *')),
                    const SizedBox(height: 10),
                    TextField(controller: bodyCtrl, maxLines: 4, decoration: const InputDecoration(labelText: 'Template Body *')),
                  ],
                ),
              ),
              actions: [
                TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
                ElevatedButton(
                  onPressed: submitting
                      ? null
                      : () async {
                          if (titleCtrl.text.trim().isEmpty || bodyCtrl.text.trim().isEmpty) {
                            setDialogState(() => dialogError = 'Title and body are required.');
                            return;
                          }
                          setDialogState(() {
                            submitting = true;
                            dialogError = null;
                          });
                          try {
                            final payload = {
                              'title': titleCtrl.text.trim(),
                              'body': bodyCtrl.text.trim(),
                            };
                            if (isEdit) {
                              await ApiService.instance.settingsNoteTemplateUpdate(template.id, payload);
                            } else {
                              await ApiService.instance.settingsNoteTemplateStore(payload);
                            }
                            if (ctx.mounted) Navigator.of(ctx).pop(true);
                          } catch (e) {
                            setDialogState(() {
                              submitting = false;
                              dialogError = e.toString().replaceAll('Exception: ', '');
                            });
                          }
                        },
                  child: Text(isEdit ? 'Save Changes' : 'Create Template'),
                ),
              ],
            );
          },
        );
      },
    );

    if (saved == true) {
      _fetchSettings();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(isEdit ? 'Template updated!' : 'Template created!'), backgroundColor: Colors.green),
        );
      }
    }
  }

  Future<void> _deleteNoteTemplate(NoteTemplate template) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Template?'),
        content: Text('Delete template "${template.title}"?'),
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
      await ApiService.instance.settingsNoteTemplateDestroy(template.id);
      _fetchSettings();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  // --- Save Operations Setting ---
  Future<void> _saveOperationSetting() async {
    try {
      await ApiService.instance.settingsOperationSettingUpdate({
        'status': _opSelectedStatus,
        'title': _opTitleController.text.trim(),
        'body': _opBodyController.text.trim(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Operation Team settings saved!'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  // --- Save WhatsApp Setting ---
  Future<void> _saveWhatsappSetting() async {
    try {
      await ApiService.instance.settingsWhatsappSettingUpdate({
        'phone': _waPhoneController.text.trim(),
        'message_template': _waMsgController.text.trim(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('WhatsApp settings saved!'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  // --- Save Booking Slot Setting ---
  Future<void> _saveBookingSlotSetting() async {
    try {
      await ApiService.instance.settingsBookingSlotUpdateSetting({
        'status': _bsSelectedStatus ?? 'confirmed',
        'title': _bsTitleController.text.trim(),
        'body': _bsBodyController.text.trim(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Booking Slot auto-message saved!'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: const Text('CRM Settings & Config'),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          indicatorColor: AppTheme.primary,
          labelColor: AppTheme.primary,
          unselectedLabelColor: AppTheme.textMuted,
          tabs: const [
            Tab(text: 'CRM Fields'),
            Tab(text: 'Operations Fields'),
            Tab(text: 'Note Templates'),
            Tab(text: 'Operations Assign'),
            Tab(text: 'WhatsApp & Booking'),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: Colors.red)))
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _buildFieldsTab('crm', _crmFields),
                    _buildFieldsTab('operations', _opFields),
                    _buildNotesTab(),
                    _buildOpAssignTab(),
                    _buildWhatsappAndBookingTab(),
                  ],
                ),
    );
  }

  Widget _buildFieldsTab(String pipeline, List<CrmField> fields) {
    return ListView(
      padding: const EdgeInsets.all(14),
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              '${pipeline.toUpperCase()} CUSTOM FIELDS (${fields.length})',
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 0.8, color: AppTheme.textMuted),
            ),
            ElevatedButton.icon(
              onPressed: () => _openFieldDialog(pipeline),
              icon: const Icon(Icons.add, size: 16),
              label: const Text('Add Field', style: TextStyle(fontSize: 12)),
            ),
          ],
        ),
        const SizedBox(height: 10),
        ...fields.map((f) {
          return Card(
            child: ListTile(
              title: Text(f.label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              subtitle: Text(
                'Key: ${f.key} • Type: ${f.type.toUpperCase()}${f.required ? " • Required" : ""}',
                style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
              ),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    icon: const Icon(Icons.edit, size: 18),
                    onPressed: () => _openFieldDialog(pipeline, f),
                  ),
                  if (!f.isSystem)
                    IconButton(
                      icon: const Icon(Icons.delete, size: 18, color: Colors.red),
                      onPressed: () => _deleteField(f),
                    ),
                ],
              ),
            ),
          );
        }),
      ],
    );
  }

  Widget _buildNotesTab() {
    return ListView(
      padding: const EdgeInsets.all(14),
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'QUICK NOTE TEMPLATES (${_noteTemplates.length})',
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 0.8, color: AppTheme.textMuted),
            ),
            ElevatedButton.icon(
              onPressed: () => _openNoteTemplateDialog(),
              icon: const Icon(Icons.add, size: 16),
              label: const Text('Add Template', style: TextStyle(fontSize: 12)),
            ),
          ],
        ),
        const SizedBox(height: 10),
        ..._noteTemplates.map((t) {
          return Card(
            child: ListTile(
              title: Text(t.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              subtitle: Text(t.body, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: AppTheme.textMuted)),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    icon: const Icon(Icons.edit, size: 18),
                    onPressed: () => _openNoteTemplateDialog(t),
                  ),
                  IconButton(
                    icon: const Icon(Icons.delete, size: 18, color: Colors.red),
                    onPressed: () => _deleteNoteTemplate(t),
                  ),
                ],
              ),
            ),
          );
        }),
      ],
    );
  }

  Widget _buildOpAssignTab() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Assign to Operation Team Setting', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 4),
                const Text(
                  'When an agent taps "Assign to OPS", these status, title and note are automatically applied to the lead.',
                  style: TextStyle(fontSize: 12, color: AppTheme.textMuted),
                ),
                const Divider(height: 24),
                DropdownButtonFormField<String>(
                  value: _opStatuses.contains(_opSelectedStatus) ? _opSelectedStatus : _opStatuses.firstOrNull,
                  decoration: const InputDecoration(labelText: 'Status After Assigning to OPS'),
                  items: _opStatuses.map((s) => DropdownMenuItem(value: s, child: Text(s))).toList(),
                  onChanged: (v) => setState(() => _opSelectedStatus = v),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _opTitleController,
                  decoration: const InputDecoration(labelText: 'Activity Title'),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _opBodyController,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Activity Body Note'),
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: _saveOperationSetting,
                  child: const Text('Save Operation Setting'),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildWhatsappAndBookingTab() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // WhatsApp Card
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.chat, color: Color(0xFF16A34A)),
                    const SizedBox(width: 8),
                    const Text('WhatsApp Notification Settings', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                  ],
                ),
                const Divider(height: 20),
                TextField(
                  controller: _waPhoneController,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Support WhatsApp Number',
                    hintText: 'e.g. 919876543210',
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _waMsgController,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'Default Welcome Template (Optional)',
                    hintText: 'Hello, welcome to PulseCRM...',
                  ),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF16A34A)),
                  onPressed: _saveWhatsappSetting,
                  child: const Text('Save WhatsApp Setting'),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Booking Slots Setting Card
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.event, color: AppTheme.primary),
                        SizedBox(width: 8),
                        Text('Booking Slot Config', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      ],
                    ),
                    OutlinedButton(
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const BookingSlotsAdminScreen()),
                        );
                      },
                      child: const Text('Manage Slots Schedule →', style: TextStyle(fontSize: 11)),
                    ),
                  ],
                ),
                const Divider(height: 20),
                TextField(
                  controller: _bsTitleController,
                  decoration: const InputDecoration(labelText: 'Auto-message Title on Slot Booked'),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _bsBodyController,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'Auto-message Body Note (use {date} and {time})',
                  ),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _saveBookingSlotSetting,
                  child: const Text('Save Booking Config'),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
