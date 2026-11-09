import 'package:flutter/material.dart';
import '../models/user.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';

class UserManagementScreen extends StatefulWidget {
  const UserManagementScreen({super.key});

  @override
  State<UserManagementScreen> createState() => _UserManagementScreenState();
}

class _UserManagementScreenState extends State<UserManagementScreen> {
  bool _loading = false;
  String? _error;
  List<AppUser> _users = [];
  Map<String, dynamic> _roleOptions = {};

  @override
  void initState() {
    super.initState();
    _fetchUsers();
  }

  Future<void> _fetchUsers() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await ApiService.instance.usersList();
      final rawList = (res['users'] as List?) ?? [];
      _users = rawList
          .whereType<Map>()
          .map((m) => AppUser.fromJson(Map<String, dynamic>.from(m)))
          .toList();

      if (res['role_options'] is Map) {
        _roleOptions = Map<String, dynamic>.from(res['role_options'] as Map);
      }
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

  Future<void> _openUserDialog([AppUser? user]) async {
    final isEdit = user != null;
    final nameCtrl = TextEditingController(text: user?.name ?? '');
    final userCtrl = TextEditingController(text: user?.username ?? '');
    final emailCtrl = TextEditingController(text: user?.email ?? '');
    final passCtrl = TextEditingController();
    String role = user?.role ?? 'front_office';
    bool accessCrm = user?.canAccessCrm ?? true;
    bool accessOps = user?.canAccessOperations ?? false;
    bool submitting = false;
    String? dialogError;

    final saved = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: Text(isEdit ? 'Edit User' : 'Add New User'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (dialogError != null) ...[
                      Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                      const SizedBox(height: 8),
                    ],
                    TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name *')),
                    const SizedBox(height: 10),
                    TextField(controller: userCtrl, decoration: const InputDecoration(labelText: 'Username *')),
                    const SizedBox(height: 10),
                    TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email Address')),
                    const SizedBox(height: 10),
                    TextField(
                      controller: passCtrl,
                      obscureText: true,
                      decoration: InputDecoration(
                        labelText: isEdit ? 'New Password (leave blank to keep current)' : 'Password *',
                      ),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      value: _roleOptions.containsKey(role) ? role : _roleOptions.keys.firstOrNull ?? 'front_office',
                      decoration: const InputDecoration(labelText: 'Role'),
                      items: _roleOptions.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value.toString()))).toList(),
                      onChanged: (v) => setDialogState(() => role = v ?? 'front_office'),
                    ),
                    const SizedBox(height: 12),
                    CheckboxListTile(
                      title: const Text('Access CRM', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                      value: accessCrm,
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      onChanged: (v) => setDialogState(() => accessCrm = v ?? false),
                    ),
                    CheckboxListTile(
                      title: const Text('Access Team Operations', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                      value: accessOps,
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      onChanged: (v) => setDialogState(() => accessOps = v ?? false),
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
                          if (nameCtrl.text.trim().isEmpty || userCtrl.text.trim().isEmpty) {
                            setDialogState(() => dialogError = 'Name and username are required.');
                            return;
                          }
                          if (!isEdit && passCtrl.text.isEmpty) {
                            setDialogState(() => dialogError = 'Password is required.');
                            return;
                          }
                          setDialogState(() {
                            submitting = true;
                            dialogError = null;
                          });
                          try {
                            final payload = <String, dynamic>{
                              'name': nameCtrl.text.trim(),
                              'username': userCtrl.text.trim(),
                              'email': emailCtrl.text.trim().isNotEmpty ? emailCtrl.text.trim() : null,
                              if (passCtrl.text.isNotEmpty) 'password': passCtrl.text,
                              'role': role,
                              'access_crm': accessCrm,
                              'access_operations': accessOps,
                            };
                            if (isEdit) {
                              await ApiService.instance.userUpdate(user.id, payload);
                            } else {
                              await ApiService.instance.userStore(payload);
                            }
                            if (ctx.mounted) Navigator.of(ctx).pop(true);
                          } catch (e) {
                            setDialogState(() {
                              submitting = false;
                              dialogError = e.toString().replaceAll('Exception: ', '');
                            });
                          }
                        },
                  child: Text(isEdit ? 'Save Changes' : 'Create User'),
                ),
              ],
            );
          },
        );
      },
    );

    if (saved == true) {
      _fetchUsers();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(isEdit ? 'User updated!' : 'User created!'), backgroundColor: Colors.green),
        );
      }
    }
  }

  Future<void> _deleteUser(AppUser user) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete User?'),
        content: Text('Delete account for ${user.name}?'),
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
      await ApiService.instance.userDestroy(user.id);
      _fetchUsers();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('User deleted.')));
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
        title: const Text('User Management'),
        actions: [
          IconButton(
            icon: const Icon(Icons.person_add),
            tooltip: 'Add User',
            onPressed: () => _openUserDialog(),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: Colors.red)))
              : _users.isEmpty
                  ? const Center(child: Text('No users found.'))
                  : RefreshIndicator(
                      onRefresh: _fetchUsers,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(12),
                        itemCount: _users.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, index) {
                          final u = _users[index];
                          return Card(
                            child: ListTile(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                              leading: CircleAvatar(
                                backgroundColor: AppTheme.indigoSoft,
                                child: Text(
                                  u.name.isNotEmpty ? u.name[0].toUpperCase() : '?',
                                  style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.indigoText),
                                ),
                              ),
                              title: Row(
                                children: [
                                  Expanded(
                                    child: Text(u.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: AppTheme.skySoft,
                                      borderRadius: BorderRadius.circular(6),
                                      border: Border.all(color: AppTheme.skyBorder),
                                    ),
                                    child: Text(
                                      u.roleLabel.toUpperCase(),
                                      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.skyText),
                                    ),
                                  ),
                                ],
                              ),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const SizedBox(height: 4),
                                  Text('@${u.username} • ${u.email ?? "No email"}', style: const TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                                  const SizedBox(height: 4),
                                  Wrap(
                                    spacing: 6,
                                    children: [
                                      if (u.canAccessCrm)
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                                          decoration: BoxDecoration(color: AppTheme.emeraldSoft, borderRadius: BorderRadius.circular(4)),
                                          child: const Text('CRM', style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: AppTheme.emeraldText)),
                                        ),
                                      if (u.canAccessOperations)
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                                          decoration: BoxDecoration(color: AppTheme.tealSoft, borderRadius: BorderRadius.circular(4)),
                                          child: const Text('OPS', style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: AppTheme.tealText)),
                                        ),
                                    ],
                                  ),
                                ],
                              ),
                              trailing: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  IconButton(
                                    icon: const Icon(Icons.edit, size: 18),
                                    onPressed: () => _openUserDialog(u),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.delete, size: 18, color: Colors.red),
                                    onPressed: () => _deleteUser(u),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

