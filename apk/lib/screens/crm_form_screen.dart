import 'package:flutter/material.dart';

import '../services/api.dart';

const _statuses = [
  'new',
  'interested',
  'contacted',
  'converted',
  'lost',
  'request',
];

class CrmFormScreen extends StatefulWidget {
  const CrmFormScreen({super.key, this.customer, this.pipeline = 'crm'});

  final Map<String, dynamic>? customer;
  final String pipeline;

  bool get isEdit => customer != null;

  @override
  State<CrmFormScreen> createState() => _CrmFormScreenState();
}

class _CrmFormScreenState extends State<CrmFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late final TextEditingController _company;
  late final TextEditingController _city;
  late final TextEditingController _source;
  late final TextEditingController _notes;
  late String _status;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final c = widget.customer ?? {};
    _name = TextEditingController(text: '${c['name'] ?? ''}');
    _phone = TextEditingController(text: '${c['phone'] ?? ''}');
    _email = TextEditingController(text: '${c['email'] ?? ''}');
    _company = TextEditingController(text: '${c['company'] ?? ''}');
    _city = TextEditingController(text: '${c['city'] ?? ''}');
    _source = TextEditingController(text: '${c['source'] ?? ''}');
    _notes = TextEditingController(text: '${c['notes'] ?? ''}');
    final st = '${c['status'] ?? 'new'}';
    _status = _statuses.contains(st) ? st : 'new';
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    _company.dispose();
    _city.dispose();
    _source.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      final payload = {
        'name': _name.text.trim(),
        'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
        'company': _company.text.trim().isEmpty ? null : _company.text.trim(),
        'city': _city.text.trim().isEmpty ? null : _city.text.trim(),
        'source': _source.text.trim().isEmpty ? null : _source.text.trim(),
        'status': _status,
        'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
      };
      final Map<String, dynamic> res;
      if (widget.isEdit) {
        res = await ApiClient.instance.crmUpdate(
          (widget.customer!['id'] as num).toInt(),
          payload,
          pipeline: widget.pipeline,
        );
      } else {
        res = await ApiClient.instance.crmCreate(
          payload,
          pipeline: widget.pipeline,
        );
      }
      if (!mounted) return;
      Navigator.of(context).pop(res);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
      setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    const brand = Color(0xFF0F6CBD);
    return Scaffold(
      backgroundColor: const Color(0xFFF3F2F1),
      appBar: AppBar(
        title: Text(
          widget.isEdit
              ? 'Edit customer'
              : (widget.pipeline == 'operations'
                  ? 'New ops record'
                  : 'New customer'),
        ),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF201F1E),
        elevation: 0,
        actions: [
          TextButton(
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(
                    'Save',
                    style: TextStyle(
                      color: brand,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _field(
              label: 'Name *',
              controller: _name,
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'Name is required' : null,
            ),
            _field(
              label: 'Phone',
              controller: _phone,
              keyboard: TextInputType.phone,
            ),
            _field(
              label: 'Email',
              controller: _email,
              keyboard: TextInputType.emailAddress,
            ),
            _field(label: 'Company', controller: _company),
            _field(label: 'City', controller: _city),
            _field(label: 'Source', controller: _source),
            const SizedBox(height: 4),
            const Text(
              'STATUS',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: Color(0xFF64748B),
                letterSpacing: 0.4,
              ),
            ),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFEDEBE9)),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _status,
                  isExpanded: true,
                  items: _statuses
                      .map(
                        (s) => DropdownMenuItem(
                          value: s,
                          child: Text(s),
                        ),
                      )
                      .toList(),
                  onChanged: (v) {
                    if (v != null) setState(() => _status = v);
                  },
                ),
              ),
            ),
            const SizedBox(height: 12),
            _field(
              label: 'Notes',
              controller: _notes,
              maxLines: 4,
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _saving ? null : _save,
              style: FilledButton.styleFrom(
                backgroundColor: brand,
                minimumSize: const Size.fromHeight(48),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: Text(widget.isEdit ? 'Save changes' : 'Create customer'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _field({
    required String label,
    required TextEditingController controller,
    TextInputType? keyboard,
    int maxLines = 1,
    String? Function(String?)? validator,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        keyboardType: keyboard,
        maxLines: maxLines,
        validator: validator,
        decoration: InputDecoration(
          labelText: label,
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFEDEBE9)),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: Color(0xFFEDEBE9)),
          ),
        ),
      ),
    );
  }
}
