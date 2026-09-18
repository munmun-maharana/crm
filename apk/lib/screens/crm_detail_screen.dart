import 'package:flutter/material.dart';

import '../services/api.dart';
import 'activation_request_screen.dart';
import 'crm_history_screen.dart';
import 'login_screen.dart';

const _activityTypes = ['note', 'call', 'whatsapp', 'email', 'sms'];
const _statuses = [
  'new',
  'interested',
  'contacted',
  'converted',
  'lost',
  'request',
];

class CrmDetailScreen extends StatefulWidget {
  const CrmDetailScreen({
    super.key,
    required this.customerId,
    this.pipeline = 'crm',
  });

  final int customerId;
  final String pipeline;

  @override
  State<CrmDetailScreen> createState() => _CrmDetailScreenState();
}

class _CrmDetailScreenState extends State<CrmDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _customer;
  List<dynamic> _activities = [];
  List<dynamic> _activationRequests = [];
  List<Map<String, dynamic>> _templates = [];
  List<String> _statusOptions = List.from(_statuses);

  String _type = 'note';
  String? _statusChange;
  final _titleCtrl = TextEditingController();
  final _bodyCtrl = TextEditingController();
  bool _followUp = false;
  DateTime? _followDate;
  bool _timeOn = false;
  TimeOfDay? _followTime;
  bool _posting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _titleCtrl.dispose();
    _bodyCtrl.dispose();
    super.dispose();
  }

  void _syncFormFromCustomer() {
    final c = _customer;
    if (c == null) return;
    final st = '${c['status'] ?? ''}';
    _statusChange = _statusOptions.contains(st) ? st : null;
    _followUp = c['follow_up'] == true;
    _timeOn = c['follow_up_has_time'] == true;
    final rawAt = c['follow_up_at']?.toString();
    if (rawAt != null && rawAt.isNotEmpty) {
      final dt = DateTime.tryParse(rawAt)?.toLocal();
      if (dt != null) {
        _followDate = DateTime(dt.year, dt.month, dt.day);
        if (_timeOn) {
          _followTime = TimeOfDay(hour: dt.hour, minute: dt.minute);
        }
      }
    } else {
      _followDate = null;
      _followTime = null;
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ApiClient.instance.crmShow(
        widget.customerId,
        pipeline: widget.pipeline,
      );
      if (!mounted) return;
      final statuses = (data['statuses'] as List?)
              ?.map((e) => e.toString())
              .toList() ??
          _statuses;
      setState(() {
        _customer = Map<String, dynamic>.from(data['customer'] as Map);
        _activities = (data['activities'] as List?) ?? [];
        _activationRequests = (data['activation_requests'] as List?) ?? [];
        _statusOptions = statuses;
        _templates = ((data['note_templates'] as List?) ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        _syncFormFromCustomer();
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      final msg = e.toString().replaceFirst('Exception: ', '');
      if (msg.contains('Session expired')) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const LoginScreen()),
        );
        return;
      }
      setState(() {
        _error = msg.isEmpty ? 'Could not load record' : msg;
        _loading = false;
      });
    }
  }

  void _applyTemplate(Map<String, dynamic> tpl) {
    final title = '${tpl['title'] ?? ''}'.trim();
    final body = '${tpl['body'] ?? ''}'.trim();
    setState(() {
      if (title.isNotEmpty) _titleCtrl.text = title;
      if (body.isNotEmpty) {
        _bodyCtrl.text = body;
      } else if (title.isNotEmpty) {
        _bodyCtrl.text = title;
      }
    });
  }

  String _fmtDate(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')}-${d.month.toString().padLeft(2, '0')}-${d.year}';

  String _fmtDateApi(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _fmtTimeApi(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _followDate ?? now,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 3),
    );
    if (picked != null) setState(() => _followDate = picked);
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _followTime ?? TimeOfDay.now(),
    );
    if (picked != null) setState(() => _followTime = picked);
  }

  Future<void> _post() async {
    final body = _bodyCtrl.text.trim();
    if (body.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please write a note.')),
      );
      return;
    }
    if (_followUp && _followDate == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Follow-up date required.')),
      );
      return;
    }
    if (_followUp && _timeOn && _followTime == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a follow-up time, or uncheck Time.')),
      );
      return;
    }

    setState(() => _posting = true);
    try {
      final payload = <String, dynamic>{
        'type': _type,
        'body': body,
        if (_titleCtrl.text.trim().isNotEmpty) 'title': _titleCtrl.text.trim(),
        if (_statusChange != null && _statusChange!.isNotEmpty)
          'status_change': _statusChange,
        'follow_up': _followUp,
        if (_followUp && _followDate != null)
          'follow_up_date': _fmtDateApi(_followDate!),
        'follow_up_time_on': _followUp && _timeOn,
        if (_followUp && _timeOn && _followTime != null)
          'follow_up_time': _fmtTimeApi(_followTime!),
      };
      final res = await ApiClient.instance.crmAddActivity(
        widget.customerId,
        payload,
        pipeline: widget.pipeline,
      );
      if (!mounted) return;
      _titleCtrl.clear();
      _bodyCtrl.clear();
      setState(() {
        _customer = Map<String, dynamic>.from(res['customer'] as Map);
        _activities = (res['activities'] as List?) ?? [];
        _syncFormFromCustomer();
        _posting = false;
      });
      FocusScope.of(context).unfocus();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Update posted')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
      );
      setState(() => _posting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    const brand = Color(0xFF0F6CBD);

    return Scaffold(
      backgroundColor: const Color(0xFFF3F2F1),
      appBar: AppBar(
        title: Text(_customer?['name']?.toString() ?? 'Customer'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF201F1E),
        elevation: 0,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _DetailsCard(customer: _customer!),
                      if (widget.pipeline == 'operations') ...[
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton.icon(
                            onPressed: () async {
                              try {
                                final started = await ApiClient.instance
                                    .startActivation(widget.customerId);
                                final draftId =
                                    (started['id'] as num?)?.toInt();
                                if (!mounted) return;
                                final ok =
                                    await Navigator.of(context).push<bool>(
                                  MaterialPageRoute(
                                    builder: (_) => ActivationRequestScreen(
                                      customerId: widget.customerId,
                                      customerName:
                                          '${_customer?['name'] ?? 'Customer'}',
                                      activationRequestId: draftId,
                                    ),
                                  ),
                                );
                                if (mounted) _load();
                                if (ok != true && draftId != null) {
                                  // draft left empty — still show card after reload
                                }
                              } catch (e) {
                                if (!mounted) return;
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(
                                      e
                                          .toString()
                                          .replaceFirst('Exception: ', ''),
                                    ),
                                  ),
                                );
                              }
                            },
                            style: FilledButton.styleFrom(
                              backgroundColor: brand,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            icon: const Icon(Icons.bolt_outlined, size: 18),
                            label: const Text(
                              '+ Activation request',
                              style: TextStyle(fontWeight: FontWeight.w700),
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        const Text(
                          'ACTIVATION REQUESTS',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF64748B),
                            letterSpacing: 0.6,
                          ),
                        ),
                        const SizedBox(height: 8),
                        if (_activationRequests.isEmpty)
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFEDEBE9)),
                            ),
                            child: const Text(
                              'No activation requests yet',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: Color(0xFF94A3B8)),
                            ),
                          )
                        else
                          ..._activationRequests.map((raw) {
                            final r = Map<String, dynamic>.from(raw as Map);
                            final isDraft = '${r['status']}' == 'draft';
                            final items = ((r['items'] as List?) ?? [])
                                .map((e) => Map<String, dynamic>.from(e as Map))
                                .toList();
                            return Container(
                              margin: const EdgeInsets.only(bottom: 8),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                border:
                                    Border.all(color: const Color(0xFFEDEBE9)),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          '${_customer?['name'] ?? 'Customer'}',
                                          style: const TextStyle(
                                            fontSize: 14,
                                            fontWeight: FontWeight.w800,
                                          ),
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 6,
                                          vertical: 2,
                                        ),
                                        decoration: BoxDecoration(
                                          color: isDraft
                                              ? const Color(0xFFF1F5F9)
                                              : const Color(0xFFFEF3C7),
                                          borderRadius:
                                              BorderRadius.circular(5),
                                        ),
                                        child: Text(
                                          '${r['status_label'] ?? r['status'] ?? ''}'
                                              .toUpperCase(),
                                          style: TextStyle(
                                            fontSize: 9,
                                            fontWeight: FontWeight.w800,
                                            color: isDraft
                                                ? const Color(0xFF475569)
                                                : const Color(0xFF92400E),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    '${r['created_at'] ?? r['ago'] ?? ''}',
                                    style: const TextStyle(
                                      fontSize: 11,
                                      color: Color(0xFF94A3B8),
                                    ),
                                  ),
                                  if (isDraft) ...[
                                    const SizedBox(height: 10),
                                    OutlinedButton(
                                      onPressed: () async {
                                        final ok = await Navigator.of(context)
                                            .push<bool>(
                                          MaterialPageRoute(
                                            builder: (_) =>
                                                ActivationRequestScreen(
                                              customerId: widget.customerId,
                                              customerName:
                                                  '${_customer?['name'] ?? 'Customer'}',
                                              activationRequestId:
                                                  (r['id'] as num).toInt(),
                                            ),
                                          ),
                                        );
                                        if (ok == true && mounted) _load();
                                      },
                                      child: const Text(
                                        'Select devices & submit',
                                      ),
                                    ),
                                  ] else ...[
                                    if (items.isNotEmpty) ...[
                                      const SizedBox(height: 8),
                                      ...items.map(
                                        (line) => Padding(
                                          padding:
                                              const EdgeInsets.only(bottom: 4),
                                          child: Row(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Container(
                                                margin:
                                                    const EdgeInsets.only(top: 2),
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                  horizontal: 6,
                                                  vertical: 2,
                                                ),
                                                decoration: BoxDecoration(
                                                  color: const Color(0xFFDEECF9),
                                                  borderRadius:
                                                      BorderRadius.circular(4),
                                                ),
                                                child: Text(
                                                  '${line['type'] ?? ''}'
                                                      .toUpperCase(),
                                                  style: const TextStyle(
                                                    fontSize: 9,
                                                    fontWeight: FontWeight.w800,
                                                    color: Color(0xFF0F6CBD),
                                                  ),
                                                ),
                                              ),
                                              const SizedBox(width: 8),
                                              Expanded(
                                                child: Text(
                                                  '${line['label'] ?? ''}',
                                                  style: const TextStyle(
                                                    fontSize: 13,
                                                    fontWeight: FontWeight.w700,
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                    ] else if ((r['item_label'] ?? '')
                                        .toString()
                                        .isNotEmpty) ...[
                                      const SizedBox(height: 6),
                                      Text(
                                        '${r['item_label']}',
                                        style: const TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                    if ((r['notes'] ?? '')
                                        .toString()
                                        .isNotEmpty) ...[
                                      const SizedBox(height: 6),
                                      Text(
                                        '${r['notes']}',
                                        style: const TextStyle(
                                          fontSize: 12,
                                          color: Color(0xFF475569),
                                        ),
                                      ),
                                    ],
                                    if ((r['user_name'] ?? '')
                                        .toString()
                                        .isNotEmpty) ...[
                                      const SizedBox(height: 6),
                                      Text(
                                        'by ${r['user_name']}',
                                        style: const TextStyle(
                                          fontSize: 11,
                                          color: Color(0xFF94A3B8),
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ],
                                  ],
                                ],
                              ),
                            );
                          }),
                      ] else ...[
                      const SizedBox(height: 16),
                      _AddUpdateCard(
                        brand: brand,
                        type: _type,
                        statusChange: _statusChange,
                        statusOptions: _statusOptions,
                        titleCtrl: _titleCtrl,
                        bodyCtrl: _bodyCtrl,
                        followUp: _followUp,
                        followDate: _followDate,
                        timeOn: _timeOn,
                        followTime: _followTime,
                        templates: _templates,
                        posting: _posting,
                        fmtDate: _fmtDate,
                        onType: (v) => setState(() => _type = v),
                        onStatus: (v) => setState(() => _statusChange = v),
                        onFollowUp: (v) => setState(() {
                          _followUp = v;
                          if (!v) {
                            _timeOn = false;
                          } else {
                            _followDate ??= DateTime.now();
                          }
                        }),
                        onTimeOn: (v) => setState(() {
                          _timeOn = v;
                          if (v && _followTime == null) {
                            _followTime = TimeOfDay.now();
                          }
                        }),
                        onPickDate: _pickDate,
                        onPickTime: _pickTime,
                        onTemplate: _applyTemplate,
                        onPost: _post,
                      ),
                      const SizedBox(height: 12),
                      _LatestChatCard(
                        brand: brand,
                        activities: _activities,
                        onOpenHistory: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                                builder: (_) => CrmHistoryScreen(
                                  customerId: widget.customerId,
                                  customerName:
                                      '${_customer?['name'] ?? 'Customer'}',
                                  initialActivities: _activities,
                                  pipeline: widget.pipeline,
                                ),
                            ),
                          );
                        },
                      ),
                      ],
                      const SizedBox(height: 24),
                    ],
                  ),
                ),
    );
  }
}

class _LatestChatCard extends StatelessWidget {
  const _LatestChatCard({
    required this.brand,
    required this.activities,
    required this.onOpenHistory,
  });

  final Color brand;
  final List<dynamic> activities;
  final VoidCallback onOpenHistory;

  @override
  Widget build(BuildContext context) {
    final latest = activities.isEmpty
        ? null
        : Map<String, dynamic>.from(activities.first as Map);

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onOpenHistory,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: brand.withValues(alpha: 0.35)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.forum_outlined, size: 18, color: brand),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      activities.isEmpty
                          ? 'Chat history'
                          : 'Latest chat · ${activities.length} updates',
                      style: TextStyle(
                        color: brand,
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                      ),
                    ),
                  ),
                  Icon(Icons.chevron_right, size: 18, color: brand),
                ],
              ),
              if (latest != null) ...[
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEFF6FF),
                              borderRadius: BorderRadius.circular(5),
                            ),
                            child: Text(
                              '${latest['type'] ?? 'note'}'.toUpperCase(),
                              style: TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.w800,
                                color: brand,
                              ),
                            ),
                          ),
                          const Spacer(),
                          Text(
                            '${latest['created_at'] ?? ''}',
                            style: const TextStyle(
                              fontSize: 10,
                              color: Color(0xFF94A3B8),
                            ),
                          ),
                        ],
                      ),
                      if ((latest['title'] ?? '').toString().isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          '${latest['title']}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                      if ((latest['body'] ?? '').toString().isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          '${latest['body']}',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFF475569),
                            height: 1.3,
                          ),
                        ),
                      ],
                      if ((latest['user_name'] ?? '').toString().isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          'by ${latest['user_name']}',
                          style: const TextStyle(
                            fontSize: 10,
                            color: Color(0xFF94A3B8),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ] else ...[
                const SizedBox(height: 8),
                const Text(
                  'No chat yet — tap to open history',
                  style: TextStyle(
                    fontSize: 12,
                    color: Color(0xFF94A3B8),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _AddUpdateCard extends StatelessWidget {
  const _AddUpdateCard({
    required this.brand,
    required this.type,
    required this.statusChange,
    required this.statusOptions,
    required this.titleCtrl,
    required this.bodyCtrl,
    required this.followUp,
    required this.followDate,
    required this.timeOn,
    required this.followTime,
    required this.templates,
    required this.posting,
    required this.fmtDate,
    required this.onType,
    required this.onStatus,
    required this.onFollowUp,
    required this.onTimeOn,
    required this.onPickDate,
    required this.onPickTime,
    required this.onTemplate,
    required this.onPost,
  });

  final Color brand;
  final String type;
  final String? statusChange;
  final List<String> statusOptions;
  final TextEditingController titleCtrl;
  final TextEditingController bodyCtrl;
  final bool followUp;
  final DateTime? followDate;
  final bool timeOn;
  final TimeOfDay? followTime;
  final List<Map<String, dynamic>> templates;
  final bool posting;
  final String Function(DateTime) fmtDate;
  final ValueChanged<String> onType;
  final ValueChanged<String?> onStatus;
  final ValueChanged<bool> onFollowUp;
  final ValueChanged<bool> onTimeOn;
  final VoidCallback onPickDate;
  final VoidCallback onPickTime;
  final ValueChanged<Map<String, dynamic>> onTemplate;
  final VoidCallback onPost;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFEDEBE9)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(Icons.edit_note, size: 18, color: brand),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Add update',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF201F1E),
                      ),
                    ),
                    Text(
                      'Note, status change & follow-up',
                      style: TextStyle(
                        fontSize: 11,
                        color: Color(0xFF94A3B8),
                      ),
                    ),
                  ],
                ),
              ),
              FilledButton.icon(
                onPressed: posting ? null : onPost,
                style: FilledButton.styleFrom(
                  backgroundColor: brand,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                icon: posting
                    ? const SizedBox(
                        width: 14,
                        height: 14,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.send, size: 14),
                label: const Text('Post', style: TextStyle(fontSize: 13)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              _MiniDropdown<String>(
                value: type,
                items: _activityTypes,
                labelOf: (v) => v[0].toUpperCase() + v.substring(1),
                onChanged: (v) {
                  if (v != null) onType(v);
                },
              ),
              _MiniDropdown<String>(
                value: statusChange,
                hint: 'Status',
                items: statusOptions,
                labelOf: (v) => v[0].toUpperCase() + v.substring(1),
                onChanged: onStatus,
                allowNull: true,
              ),
              SizedBox(
                width: 140,
                child: TextField(
                  controller: titleCtrl,
                  style: const TextStyle(fontSize: 12),
                  decoration: _inputDeco('Title (optional)'),
                ),
              ),
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Wrap(
                  spacing: 4,
                  runSpacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    _CheckChip(
                      label: 'Follow up',
                      value: followUp,
                      activeColor: const Color(0xFFFEF3C7),
                      activeText: const Color(0xFF92400E),
                      onChanged: onFollowUp,
                    ),
                    if (followUp) ...[
                      InkWell(
                        onTap: onPickDate,
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(
                                Icons.calendar_today_outlined,
                                size: 12,
                                color: Color(0xFF64748B),
                              ),
                              const SizedBox(width: 6),
                              Text(
                                followDate != null
                                    ? fmtDate(followDate!)
                                    : 'Date',
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      _CheckChip(
                        label: 'Time',
                        value: timeOn,
                        activeColor: const Color(0xFFDEECF9),
                        activeText: brand,
                        onChanged: onTimeOn,
                      ),
                      if (timeOn)
                        InkWell(
                          onTap: onPickTime,
                          borderRadius: BorderRadius.circular(8),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: Text(
                              followTime?.format(context) ?? 'Pick time',
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          TextField(
            controller: bodyCtrl,
            minLines: 3,
            maxLines: 6,
            style: const TextStyle(fontSize: 13, height: 1.4),
            decoration: _inputDeco('What happened / note…').copyWith(
              fillColor: const Color(0xFFF8FAFC),
            ),
          ),
          if (templates.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Text(
              'QUICK NOTES',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w700,
                color: Color(0xFF94A3B8),
                letterSpacing: 0.5,
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final tpl in templates)
                  ActionChip(
                    label: Text(
                      '${tpl['title'] ?? ''}',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    onPressed: () => onTemplate(tpl),
                    backgroundColor: Colors.white,
                    side: const BorderSide(color: Color(0xFFE2E8F0)),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(20),
                    ),
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    visualDensity: VisualDensity.compact,
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  InputDecoration _inputDeco(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
      isDense: true,
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: brand),
      ),
    );
  }
}

class _MiniDropdown<T> extends StatelessWidget {
  const _MiniDropdown({
    required this.value,
    required this.items,
    required this.labelOf,
    required this.onChanged,
    this.hint,
    this.allowNull = false,
  });

  final T? value;
  final List<T> items;
  final String Function(T) labelOf;
  final ValueChanged<T?> onChanged;
  final String? hint;
  final bool allowNull;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<T?>(
          value: value,
          hint: hint != null
              ? Text(hint!, style: const TextStyle(fontSize: 12))
              : null,
          isDense: true,
          style: const TextStyle(
            fontSize: 12,
            color: Color(0xFF201F1E),
            fontWeight: FontWeight.w600,
          ),
          items: [
            if (allowNull)
              DropdownMenuItem<T?>(
                value: null,
                child: Text(hint ?? '—', style: const TextStyle(fontSize: 12)),
              ),
            ...items.map(
              (e) => DropdownMenuItem<T?>(
                value: e,
                child: Text(labelOf(e), style: const TextStyle(fontSize: 12)),
              ),
            ),
          ],
          onChanged: onChanged,
        ),
      ),
    );
  }
}

class _CheckChip extends StatelessWidget {
  const _CheckChip({
    required this.label,
    required this.value,
    required this.onChanged,
    required this.activeColor,
    required this.activeText,
  });

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color activeColor;
  final Color activeText;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => onChanged(!value),
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        decoration: BoxDecoration(
          color: value ? activeColor : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: 14,
              height: 14,
              child: Checkbox(
                value: value,
                onChanged: (v) => onChanged(v ?? false),
                materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                visualDensity: VisualDensity.compact,
              ),
            ),
            const SizedBox(width: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: value ? activeText : const Color(0xFF64748B),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailsCard extends StatelessWidget {
  const _DetailsCard({required this.customer});

  final Map<String, dynamic> customer;

  @override
  Widget build(BuildContext context) {
    final rows = <MapEntry<String, String>>[
      MapEntry('Status', '${customer['status'] ?? '—'}'),
      MapEntry('Phone', _val(customer['phone'])),
      MapEntry('Email', _val(customer['email'])),
      MapEntry('Company', _val(customer['company'])),
      MapEntry('City', _val(customer['city'])),
      MapEntry('Source', _val(customer['source'])),
      MapEntry('Assigned', _val(customer['assignee_name'])),
      MapEntry('Follow-up', _val(customer['follow_up_label'])),
      MapEntry('Notes', _val(customer['notes'])),
    ].where((r) => r.value != '—').toList();

    final status = rows.where((r) => r.key == 'Status').toList();
    final rest = rows.where((r) => r.key != 'Status').toList();

    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFEDEBE9)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  '${customer['name'] ?? '—'}',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF201F1E),
                    height: 1.2,
                  ),
                ),
              ),
              if (status.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(left: 8),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDEECF9),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    status.first.value,
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F6CBD),
                    ),
                  ),
                ),
            ],
          ),
          if (rest.isNotEmpty) ...[
            const SizedBox(height: 8),
            ...List.generate((rest.length + 1) ~/ 2, (i) {
              final left = rest[i * 2];
              final right = i * 2 + 1 < rest.length ? rest[i * 2 + 1] : null;
              return Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: _CompactField(entry: left)),
                    const SizedBox(width: 10),
                    Expanded(
                      child: right != null
                          ? _CompactField(entry: right)
                          : const SizedBox.shrink(),
                    ),
                  ],
                ),
              );
            }),
          ],
        ],
      ),
    );
  }

  String _val(dynamic v) {
    final s = '${v ?? ''}'.trim();
    return s.isEmpty ? '—' : s;
  }
}

class _CompactField extends StatelessWidget {
  const _CompactField({required this.entry});

  final MapEntry<String, String> entry;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          entry.key.toUpperCase(),
          style: const TextStyle(
            fontSize: 9,
            fontWeight: FontWeight.w700,
            color: Color(0xFF94A3B8),
            letterSpacing: 0.3,
            height: 1.1,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          entry.value,
          maxLines: entry.key == 'Notes' ? 2 : 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: Color(0xFF201F1E),
            height: 1.2,
          ),
        ),
      ],
    );
  }
}
