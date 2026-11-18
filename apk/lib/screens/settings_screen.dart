import 'package:flutter/material.dart';
import '../models/crm_field.dart';
import '../models/menu_setting.dart';
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
  List<CrmField> _productFields = [];
  List<MenuSetting> _menuSettings = [];
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
    _tabController = TabController(length: 8, vsync: this);
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
      _productFields = ((rawFields['products'] as List?) ?? [])
          .whereType<Map>()
          .map((m) => CrmField.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      final rawMenus = (res['menu_settings'] as List?) ?? [];
      _menuSettings = rawMenus
          .whereType<Map>()
          .map((m) => MenuSetting.fromJson(Map<String, dynamic>.from(m)))
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
            Tab(icon: Icon(Icons.grid_view, size: 16), text: 'All Menus'),
            Tab(text: 'CRM Fields'),
            Tab(text: 'Operations Fields'),
            Tab(text: 'Product Fields'),
            Tab(text: 'Sidebar Menus'),
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
                    _buildOverviewHubTab(),
                    _buildFieldsTab('crm', _crmFields),
                    _buildFieldsTab('operations', _opFields),
                    _buildFieldsTab('products', _productFields),
                    _buildMenuSettingsTab(),
                    _buildNotesTab(),
                    _buildOpAssignTab(),
                    _buildWhatsappAndBookingTab(),
                  ],
                ),
    );
  }

  Widget _buildOverviewHubTab() {
    return ListView(
      padding: const EdgeInsets.all(14),
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFFEEF2FF),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFC7D2FE)),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFE0E7FF),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.dashboard_customize, color: Color(0xFF4338CA), size: 22),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'SETTINGS & CONFIGURATION HUB',
                      style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold, color: Color(0xFF3730A3), letterSpacing: 0.5),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Tap any menu button below to open and configure its settings.',
                      style: TextStyle(fontSize: 12, color: Color(0xFF4338CA)),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),

        _buildHubCard(
          title: 'CRM Fields',
          subtitle: 'Manage customer lead form fields, table columns & Excel mappings.',
          badge: '${_crmFields.length} Fields',
          icon: Icons.people_outline,
          color: const Color(0xFF4F46E5),
          bgColor: const Color(0xFFEEF2FF),
          onTap: () => _tabController.animateTo(1),
        ),
        _buildHubCard(
          title: 'Operations Fields',
          subtitle: 'Technical inspection checklists, technician forms & operation fields.',
          badge: '${_opFields.length} Fields',
          icon: Icons.engineering_outlined,
          color: const Color(0xFF059669),
          bgColor: const Color(0xFFECFDF5),
          onTap: () => _tabController.animateTo(2),
        ),
        _buildHubCard(
          title: 'Product Fields',
          subtitle: 'Product catalog attributes, pricing fields & Call CRM checkboxes.',
          badge: '${_productFields.length} Fields',
          icon: Icons.inventory_2_outlined,
          color: const Color(0xFFD97706),
          bgColor: const Color(0xFFFFFBEB),
          onTap: () => _tabController.animateTo(3),
        ),
        _buildHubCard(
          title: 'Sidebar Menus',
          subtitle: 'Rename navigation menu items and rearrange position order.',
          badge: '${_menuSettings.length} Menus',
          icon: Icons.menu_open,
          color: const Color(0xFF7C3AED),
          bgColor: const Color(0xFFF5F3FF),
          onTap: () => _tabController.animateTo(4),
        ),
        _buildHubCard(
          title: 'Note Templates',
          subtitle: 'Predefined response notes and remarks for quick 1-tap logging.',
          badge: '${_noteTemplates.length} Templates',
          icon: Icons.note_alt_outlined,
          color: const Color(0xFFEA580C),
          bgColor: const Color(0xFFFFF7ED),
          onTap: () => _tabController.animateTo(5),
        ),
        _buildHubCard(
          title: 'Operations Assign',
          subtitle: 'Automated job assignment rules and workload distribution.',
          badge: (_opSetting['auto_assign'] == true) ? 'Active' : 'Manual',
          icon: Icons.alt_route,
          color: const Color(0xFF0D9488),
          bgColor: const Color(0xFFF0FDFA),
          onTap: () => _tabController.animateTo(6),
        ),
        _buildHubCard(
          title: 'WhatsApp & Booking',
          subtitle: 'Status automation message templates & technician visit slots.',
          badge: 'Automation',
          icon: Icons.chat_outlined,
          color: const Color(0xFF16A34A),
          bgColor: const Color(0xFFF0FDF4),
          onTap: () => _tabController.animateTo(7),
        ),
      ],
    );
  }

  Widget _buildHubCard({
    required String title,
    required String subtitle,
    required String badge,
    required IconData icon,
    required Color color,
    required Color bgColor,
    required VoidCallback onTap,
  }) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppTheme.border),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: bgColor,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: color, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          title,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14.5),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: bgColor,
                            borderRadius: BorderRadius.circular(4),
                            border: Border.all(color: color.withValues(alpha: 0.3)),
                          ),
                          child: Text(
                            badge,
                            style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: color),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(fontSize: 11.5, color: AppTheme.textMuted),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(Icons.chevron_right, color: Colors.grey.shade400, size: 20),
            ],
          ),
        ),
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

  Widget _buildMenuSettingsTab() {
    return ListView(
      padding: const EdgeInsets.all(14),
      children: [
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFEEF2FF),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFC7D2FE)),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFE0E7FF),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.swap_vert, color: Color(0xFF4338CA), size: 20),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'SIDEBAR MENU NAMES & POSITIONS',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF3730A3)),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Use ▲ ▼ buttons to rearrange sidebar menu order. Tap edit to rename. Changes sync across web & mobile.',
                      style: TextStyle(fontSize: 11, color: Color(0xFF4338CA)),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),

        for (int i = 0; i < _menuSettings.length; i++)
          _buildMenuSettingCard(_menuSettings[i], i),
      ],
    );
  }

  Widget _buildMenuSettingCard(MenuSetting menu, int index) {
    return Card(
      key: ValueKey('menu_${menu.id}'),
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: menu.isCustomized ? const Color(0xFFBAE6FD) : AppTheme.border),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppTheme.primarySoft,
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: AppTheme.primary.withValues(alpha: 0.3)),
                  ),
                  child: Text(
                    '#${index + 1}',
                    style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: AppTheme.primary),
                  ),
                ),
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(4),
                    border: Border.all(color: AppTheme.border),
                  ),
                  child: Text(
                    menu.key.toUpperCase(),
                    style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.textMuted, fontFamily: 'monospace'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Default: ${menu.defaultLabel}',
                    style: const TextStyle(fontSize: 11.5, color: AppTheme.textMuted),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                if (menu.isCustomized)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF3C7),
                      borderRadius: BorderRadius.circular(4),
                      border: Border.all(color: const Color(0xFFFDE68A)),
                    ),
                    child: const Text('CUSTOMIZED', style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Color(0xFF92400E))),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    _iconForMenu(menu.key),
                    size: 18,
                    color: menu.isCustomized ? AppTheme.primary : AppTheme.textMuted,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    menu.label,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: menu.isCustomized ? AppTheme.primary : AppTheme.textMain,
                    ),
                  ),
                ),
                // Reorder Position Buttons: Move Up & Move Down
                IconButton(
                  icon: const Icon(Icons.arrow_upward, size: 18),
                  tooltip: 'Move Up',
                  color: index > 0 ? AppTheme.primary : Colors.grey.shade300,
                  onPressed: index > 0 ? () => _moveMenuPosition(index, index - 1) : null,
                ),
                IconButton(
                  icon: const Icon(Icons.arrow_downward, size: 18),
                  tooltip: 'Move Down',
                  color: index < _menuSettings.length - 1 ? AppTheme.primary : Colors.grey.shade300,
                  onPressed: index < _menuSettings.length - 1 ? () => _moveMenuPosition(index, index + 1) : null,
                ),
                IconButton(
                  icon: const Icon(Icons.edit_outlined, size: 18, color: AppTheme.primary),
                  tooltip: 'Rename Menu',
                  onPressed: () => _openEditMenuDialog(menu),
                ),
                if (menu.isCustomized)
                  IconButton(
                    icon: const Icon(Icons.replay, size: 18, color: Colors.orange),
                    tooltip: 'Reset to Default',
                    onPressed: () => _resetMenuToDefault(menu),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  IconData _iconForMenu(String key) {
    switch (key) {
      case 'dashboard':
        return Icons.dashboard_outlined;
      case 'crm':
        return Icons.people_outline;
      case 'call_crm':
        return Icons.support_agent_outlined;
      case 'operations':
        return Icons.engineering_outlined;
      case 'pincodes':
        return Icons.pin_drop_outlined;
      case 'products':
        return Icons.inventory_2_outlined;
      case 'users':
        return Icons.manage_accounts_outlined;
      case 'settings':
        return Icons.settings_outlined;
      default:
        return Icons.menu;
    }
  }

  Future<void> _moveMenuPosition(int oldIndex, int newIndex) async {
    if (newIndex < 0 || newIndex >= _menuSettings.length || oldIndex == newIndex) return;

    setState(() {
      final item = _menuSettings.removeAt(oldIndex);
      _menuSettings.insert(newIndex, item);
    });

    try {
      final orderIds = _menuSettings.map((m) => m.id).toList();
      await ApiService.instance.menuSettingsReorder(orderIds);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Position updated: "${_menuSettings[newIndex].label}" moved to #${newIndex + 1}'),
            duration: const Duration(seconds: 1),
            backgroundColor: AppTheme.primary,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update position: $e'), backgroundColor: Colors.red),
        );
        _fetchSettings();
      }
    }
  }

  Future<void> _openEditMenuDialog(MenuSetting menu) async {
    final ctrl = TextEditingController(text: menu.label);
    String? dialogError;
    bool saving = false;

    await showDialog(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: [
                  const Icon(Icons.edit, size: 18, color: AppTheme.primary),
                  const SizedBox(width: 8),
                  Text('Rename "${menu.defaultLabel}"', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                ],
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (dialogError != null) ...[
                    Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                    const SizedBox(height: 8),
                  ],
                  Text('System Key: ${menu.key}', style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                  const SizedBox(height: 10),
                  TextField(
                    controller: ctrl,
                    autofocus: true,
                    decoration: InputDecoration(
                      labelText: 'Menu Name *',
                      hintText: menu.defaultLabel,
                    ),
                  ),
                ],
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
                  ),
                  onPressed: saving
                      ? null
                      : () async {
                          final newLabel = ctrl.text.trim();
                          if (newLabel.isEmpty) {
                            setDialogState(() => dialogError = 'Menu name cannot be empty.');
                            return;
                          }

                          setDialogState(() {
                            saving = true;
                            dialogError = null;
                          });

                          try {
                            await ApiService.instance.menuSettingsUpdate(menu.id, newLabel);
                            if (context.mounted) {
                              Navigator.of(ctx).pop();
                              _fetchSettings();
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text('Menu renamed to "$newLabel"!'), backgroundColor: Colors.green),
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
                      : const Text('Save'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  Future<void> _resetMenuToDefault(MenuSetting menu) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Reset Menu Name?'),
        content: Text('Reset "${menu.label}" back to default "${menu.defaultLabel}"?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.orange, foregroundColor: Colors.white),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Reset'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    try {
      await ApiService.instance.menuSettingsReset(menu.id);
      _fetchSettings();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Menu reset to "${menu.defaultLabel}"'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: Colors.red));
      }
    }
  }
}
