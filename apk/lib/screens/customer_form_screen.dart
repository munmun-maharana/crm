import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/crm_field.dart';
import '../models/customer.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class CustomerFormScreen extends StatefulWidget {
  final String pipeline;
  final Customer? customer;
  final List<CrmField> formFields;

  const CustomerFormScreen({
    super.key,
    this.pipeline = 'crm',
    this.customer,
    this.formFields = const [],
  });

  @override
  State<CustomerFormScreen> createState() => _CustomerFormScreenState();
}

class _CustomerFormScreenState extends State<CustomerFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, dynamic> _formData = {};
  final Map<String, TextEditingController> _controllers = {};
  bool _loading = false;
  bool _submitting = false;
  List<CrmField> _fields = [];
  List<Map<String, dynamic>> _agents = [];

  bool get isEdit => widget.customer != null;

  @override
  void initState() {
    super.initState();
    _initFields();
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _initFields() async {
    if (widget.formFields.isNotEmpty) {
      _fields = widget.formFields;
    } else {
      setState(() => _loading = true);
      try {
        final meta = await ApiService.instance.customerMeta(pipeline: widget.pipeline);
        final rawFields = (meta['form_fields'] as List?) ?? [];
        _fields = rawFields
            .whereType<Map>()
            .map((m) => CrmField.fromJson(Map<String, dynamic>.from(m)))
            .toList();
        final rawAgents = (meta['agents'] as List?) ?? [];
        _agents = rawAgents.whereType<Map>().map((m) => Map<String, dynamic>.from(m)).toList();
      } catch (_) {}
      setState(() => _loading = false);
    }

    final today = DateFormat('yyyy-MM-dd').format(DateTime.now());

    for (final f in _fields) {
      dynamic initialVal = widget.customer?.getMetaValue(f.key);

      if (initialVal == null || initialVal.toString().isEmpty) {
        if (!isEdit) {
          if (f.key == 'status') {
            initialVal = f.options.isNotEmpty ? f.options.first : 'New';
          } else if (f.type == 'date') {
            initialVal = today;
          } else if (f.type == 'select' && f.autoSelect && f.options.isNotEmpty) {
            initialVal = f.options.first;
          }
        }
      }

      _formData[f.key] = initialVal;
      _controllers[f.key] = TextEditingController(text: initialVal?.toString() ?? '');
    }
    setState(() {});
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);

    try {
      final payload = <String, dynamic>{
        'pipeline': widget.pipeline,
        ..._formData,
      };

      for (final f in _fields) {
        final ctrl = _controllers[f.key];
        if (ctrl != null) {
          payload[f.key] = ctrl.text.trim();
        }
      }

      if (isEdit) {
        await ApiService.instance.customerUpdate(widget.customer!.id, payload);
      } else {
        await ApiService.instance.customerStore(payload);
      }

      if (!mounted) return;
      Navigator.of(context).pop(true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(isEdit ? 'Customer updated!' : 'Customer created!'),
          backgroundColor: Colors.green,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceAll('Exception: ', ''))),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: Text(isEdit ? 'Edit Customer' : 'Add New Customer'),
        actions: [
          TextButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Save', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            isEdit ? 'EDIT RECORD DETAILS' : 'NEW CUSTOMER INFORMATION',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 0.8,
                              color: AppTheme.textMuted,
                            ),
                          ),
                          const Divider(height: 20),
                          ..._fields.map((field) => _buildFieldWidget(field)),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: _submitting ? null : _submit,
                    style: ElevatedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: _submitting
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : Text(isEdit ? 'Save Changes' : 'Create Customer', style: const TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildFieldWidget(CrmField f) {
    final ctrl = _controllers[f.key];
    final isRequired = f.required;

    // Assigned To field
    if (f.key == 'assigned_to') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: DropdownButtonFormField<int>(
          value: _formData[f.key] as int?,
          decoration: InputDecoration(
            labelText: '${f.label}${isRequired ? ' *' : ''}',
          ),
          items: [
            const DropdownMenuItem(value: null, child: Text('Unassigned')),
            ..._agents.map((a) => DropdownMenuItem(value: a['id'] as int, child: Text(a['name']?.toString() ?? ''))),
          ],
          onChanged: (v) => setState(() => _formData[f.key] = v),
          validator: isRequired ? (v) => v == null ? 'Please select assignee' : null : null,
        ),
      );
    }

    // Select Dropdown
    if (f.type == 'select' || f.key == 'status') {
      final opts = f.options.isNotEmpty ? f.options : ['New', 'Interested', 'Contacted', 'Converted', 'Lost', 'Request'];
      final curVal = _formData[f.key]?.toString();
      final matched = opts.firstWhere(
        (o) => o.toLowerCase() == (curVal ?? '').toLowerCase(),
        orElse: () => opts.first,
      );

      return Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: DropdownButtonFormField<String>(
          value: opts.contains(curVal) ? curVal : matched,
          decoration: InputDecoration(
            labelText: '${f.label}${isRequired ? ' *' : ''}',
          ),
          items: opts.map((opt) => DropdownMenuItem(value: opt, child: Text(opt))).toList(),
          onChanged: (v) {
            setState(() {
              _formData[f.key] = v;
              ctrl?.text = v ?? '';
            });
          },
          validator: isRequired ? (v) => (v == null || v.isEmpty) ? 'Please select ${f.label}' : null : null,
        ),
      );
    }

    // Date Field
    if (f.type == 'date') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: InkWell(
          onTap: () async {
            DateTime initial = DateTime.now();
            try {
              if (ctrl != null && ctrl.text.isNotEmpty) initial = DateTime.parse(ctrl.text);
            } catch (_) {}
            final picked = await showDatePicker(
              context: context,
              initialDate: initial,
              firstDate: DateTime(2020),
              lastDate: DateTime(2030),
            );
            if (picked != null) {
              final formatted = DateFormat('yyyy-MM-dd').format(picked);
              setState(() {
                _formData[f.key] = formatted;
                ctrl?.text = formatted;
              });
            }
          },
          child: InputDecorator(
            decoration: InputDecoration(
              labelText: '${f.label}${isRequired ? ' *' : ''}',
              suffixIcon: const Icon(Icons.calendar_today, size: 18),
            ),
            child: Text(
              ctrl?.text.isNotEmpty == true ? ctrl!.text : 'Select Date',
              style: TextStyle(color: ctrl?.text.isNotEmpty == true ? AppTheme.textMain : AppTheme.textMuted),
            ),
          ),
        ),
      );
    }

    // Time Field
    if (f.type == 'time') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: InkWell(
          onTap: () async {
            final picked = await showTimePicker(
              context: context,
              initialTime: TimeOfDay.now(),
            );
            if (picked != null) {
              final formatted = '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}';
              setState(() {
                _formData[f.key] = formatted;
                ctrl?.text = formatted;
              });
            }
          },
          child: InputDecorator(
            decoration: InputDecoration(
              labelText: '${f.label}${isRequired ? ' *' : ''}',
              suffixIcon: const Icon(Icons.access_time, size: 18),
            ),
            child: Text(
              ctrl?.text.isNotEmpty == true ? ctrl!.text : 'Select Time',
              style: TextStyle(color: ctrl?.text.isNotEmpty == true ? AppTheme.textMain : AppTheme.textMuted),
            ),
          ),
        ),
      );
    }

    // Textarea Field
    if (f.type == 'textarea') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: TextFormField(
          controller: ctrl,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: '${f.label}${isRequired ? ' *' : ''}',
          ),
          validator: isRequired ? (v) => (v == null || v.trim().isEmpty) ? 'Enter ${f.label}' : null : null,
        ),
      );
    }

    // Text / Phone / Email / Number
    TextInputType keyboard = TextInputType.text;
    if (f.type == 'email') keyboard = TextInputType.emailAddress;
    if (f.type == 'phone') keyboard = TextInputType.phone;
    if (f.type == 'number') keyboard = TextInputType.number;

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
        controller: ctrl,
        keyboardType: keyboard,
        decoration: InputDecoration(
          labelText: '${f.label}${isRequired ? ' *' : ''}',
        ),
        validator: isRequired ? (v) => (v == null || v.trim().isEmpty) ? 'Enter ${f.label}' : null : null,
      ),
    );
  }
}

