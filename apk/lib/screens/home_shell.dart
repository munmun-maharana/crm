import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../widgets/change_password_dialog.dart';
import 'crm_screen.dart';
import 'dashboard_screen.dart';
import 'login_screen.dart';
import 'operations_screen.dart';
import 'settings_screen.dart';
import 'user_management_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _NavItem {
  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final Widget page;

  const _NavItem({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    required this.page,
  });
}

class _HomeShellState extends State<HomeShell> {
  int _selectedIndex = 0;
  String? _targetFilter;
  String? _targetStatus;

  void _navigateToCrm([String? filter, String? status]) {
    final crmIdx = _tabs.indexWhere((t) => t.label == 'CRM');
    if (crmIdx != -1) {
      setState(() {
        _targetFilter = filter;
        _targetStatus = status;
        _selectedIndex = crmIdx;
      });
    }
  }

  List<_NavItem> get _tabs {
    final items = <_NavItem>[
      _NavItem(
        label: 'Dashboard',
        icon: Icons.dashboard_outlined,
        selectedIcon: Icons.dashboard,
        page: DashboardScreen(onNavigateToCrm: _navigateToCrm),
      ),
    ];

    if (ApiService.instance.canAccessCrm) {
      items.add(
        _NavItem(
          label: 'CRM',
          icon: Icons.people_outline,
          selectedIcon: Icons.people,
          page: CrmScreen(
            pipeline: 'crm',
            initialFilter: _targetFilter,
            initialStatus: _targetStatus,
          ),
        ),
      );
    }

    if (ApiService.instance.canAccessOperations) {
      items.add(
        const _NavItem(
          label: 'Team Ops',
          icon: Icons.engineering_outlined,
          selectedIcon: Icons.engineering,
          page: OperationsScreen(),
        ),
      );
    }

    if (ApiService.instance.isAdmin) {
      items.add(
        const _NavItem(
          label: 'Users',
          icon: Icons.manage_accounts_outlined,
          selectedIcon: Icons.manage_accounts,
          page: UserManagementScreen(),
        ),
      );
      items.add(
        const _NavItem(
          label: 'Settings',
          icon: Icons.settings_outlined,
          selectedIcon: Icons.settings,
          page: SettingsScreen(),
        ),
      );
    }

    return items;
  }

  Future<void> _logout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Logout?'),
        content: const Text('Are you sure you want to log out of PulseCRM?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Logout'),
          ),
        ],
      ),
    );
    if (confirm != true) return;

    await ApiService.instance.logout();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  void _openChangePassword() {
    showDialog(
      context: context,
      builder: (_) => const ChangePasswordDialog(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tabs = _tabs;
    final safeIndex = _selectedIndex.clamp(0, tabs.length - 1);
    final user = ApiService.instance.user;

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: AppTheme.primarySoft,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(5),
                    child: Image.asset('assets/icons/app_icon.png', width: 22, height: 22, fit: BoxFit.cover),
                  ),
                  const SizedBox(width: 6),
                  RichText(
                    text: const TextSpan(
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textMain),
                      children: [
                        TextSpan(text: 'Pulse'),
                        TextSpan(text: 'CRM', style: TextStyle(color: AppTheme.primary)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(tabs[safeIndex].label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  Text(
                    '${user?['name'] ?? ""} • ${user?['role_label'] ?? ""}',
                    style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.lock_reset, size: 20),
            tooltip: 'Change Password',
            onPressed: _openChangePassword,
          ),
          IconButton(
            icon: const Icon(Icons.logout, size: 20, color: Colors.red),
            tooltip: 'Logout',
            onPressed: _logout,
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(color: AppTheme.border, height: 1),
        ),
      ),
      body: IndexedStack(
        index: safeIndex,
        children: tabs.map((t) => t.page).toList(),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: safeIndex,
        onDestinationSelected: (idx) {
          setState(() {
            _selectedIndex = idx;
            _targetFilter = null;
            _targetStatus = null;
          });
        },
        backgroundColor: Colors.white,
        indicatorColor: AppTheme.primarySoft,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: tabs
            .map(
              (t) => NavigationDestination(
                icon: Icon(t.icon),
                selectedIcon: Icon(t.selectedIcon, color: AppTheme.primary),
                label: t.label,
              ),
            )
            .toList(),
      ),
    );
  }
}
