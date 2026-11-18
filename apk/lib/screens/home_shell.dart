import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../widgets/change_password_dialog.dart';
import 'crm_screen.dart';
import 'dashboard_screen.dart';
import 'login_screen.dart';
import 'operations_screen.dart';
import 'products_screen.dart';
import 'settings_screen.dart';
import 'user_management_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _NavItem {
  final String key;
  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final Widget page;

  const _NavItem({
    required this.key,
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

  @override
  void initState() {
    super.initState();
    _refreshMenuData();
  }

  void _refreshMenuData() {
    ApiService.instance.loadMenuLabels().then((_) {
      if (mounted) setState(() {});
    });
  }

  void _navigateToCrm([String? filter, String? status]) {
    final crmIdx = _tabs.indexWhere((t) => t.key == 'crm');
    if (crmIdx != -1) {
      setState(() {
        _targetFilter = filter;
        _targetStatus = status;
        _selectedIndex = crmIdx;
      });
    }
  }

  List<_NavItem> get _tabs {
    final dashLabel = ApiService.instance.menuLabel('dashboard', 'Dashboard');
    final crmLabel = ApiService.instance.menuLabel('crm', 'CRM');
    final callCrmLabel = ApiService.instance.menuLabel('call_crm', 'CALL CRM');
    final bookingLabel = ApiService.instance.menuLabel('booking', 'Assign Service');
    final productsLabel = ApiService.instance.menuLabel('products', 'Products');
    final opsLabel = ApiService.instance.menuLabel('operations', 'Team Ops');
    final usersLabel = ApiService.instance.menuLabel('users', 'Users');
    final settingsLabel = ApiService.instance.menuLabel('settings', 'Settings');

    final items = <_NavItem>[
      _NavItem(
        key: 'dashboard',
        label: dashLabel,
        icon: Icons.dashboard_outlined,
        selectedIcon: Icons.dashboard,
        page: DashboardScreen(onNavigateToCrm: _navigateToCrm),
      ),
    ];

    if (ApiService.instance.canAccessCrm) {
      items.add(
        _NavItem(
          key: 'crm',
          label: crmLabel,
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

    if (ApiService.instance.canAccessCallCrm) {
      items.add(
        _NavItem(
          key: 'call_crm',
          label: callCrmLabel,
          icon: Icons.support_agent_outlined,
          selectedIcon: Icons.support_agent,
          page: const CrmScreen(
            pipeline: 'call_crm',
          ),
        ),
      );
    }

    if (ApiService.instance.canAccessBooking) {
      items.add(
        _NavItem(
          key: 'booking',
          label: bookingLabel,
          icon: Icons.book_online_outlined,
          selectedIcon: Icons.book_online,
          page: const CrmScreen(
            pipeline: 'booking',
          ),
        ),
      );
    }

    if (ApiService.instance.canAccessOperations) {
      items.add(
        _NavItem(
          key: 'operations',
          label: opsLabel,
          icon: Icons.engineering_outlined,
          selectedIcon: Icons.engineering,
          page: const OperationsScreen(),
        ),
      );
    }

    if (ApiService.instance.canAccessProducts) {
      items.add(
        _NavItem(
          key: 'products',
          label: productsLabel,
          icon: Icons.inventory_2_outlined,
          selectedIcon: Icons.inventory_2,
          page: const ProductsScreen(),
        ),
      );
    }

    if (ApiService.instance.isAdmin) {
      items.add(
        _NavItem(
          key: 'users',
          label: usersLabel,
          icon: Icons.manage_accounts_outlined,
          selectedIcon: Icons.manage_accounts,
          page: const UserManagementScreen(),
        ),
      );
      items.add(
        _NavItem(
          key: 'settings',
          label: settingsLabel,
          icon: Icons.settings_outlined,
          selectedIcon: Icons.settings,
          page: const SettingsScreen(),
        ),
      );
    }

    // Dynamic rearranging according to ApiService.instance.menuOrder!
    final order = ApiService.instance.menuOrder;
    if (order.isNotEmpty) {
      items.sort((a, b) {
        final idxA = order.indexOf(a.key);
        final idxB = order.indexOf(b.key);
        final posA = idxA == -1 ? 999 : idxA;
        final posB = idxB == -1 ? 999 : idxB;
        return posA.compareTo(posB);
      });
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

  Future<bool> _showExitConfirmation() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.red.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.exit_to_app, color: Colors.red, size: 22),
            ),
            const SizedBox(width: 10),
            const Text('Exit App?', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          ],
        ),
        content: const Text(
          'Are you sure you want to close PulseCRM?',
          style: TextStyle(fontSize: 14, color: AppTheme.textMuted),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel', style: TextStyle(color: AppTheme.textMuted)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Exit'),
          ),
        ],
      ),
    );
    return confirmed ?? false;
  }

  @override
  Widget build(BuildContext context) {
    final tabs = _tabs;
    final safeIndex = _selectedIndex.clamp(0, tabs.length - 1);
    final user = ApiService.instance.user;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        if (_selectedIndex != 0) {
          setState(() {
            _selectedIndex = 0;
            _targetFilter = null;
            _targetStatus = null;
          });
          return;
        }
        final shouldExit = await _showExitConfirmation();
        if (shouldExit && context.mounted) {
          SystemNavigator.pop();
        }
      },
      child: Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      drawer: _buildDrawer(tabs, safeIndex),
      appBar: AppBar(
        leading: Builder(
          builder: (ctx) => IconButton(
            icon: const Icon(Icons.menu),
            tooltip: 'Navigation Menu',
            onPressed: () => Scaffold.of(ctx).openDrawer(),
          ),
        ),
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
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(tabs[safeIndex].label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
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
          // If switching to/from settings, refresh menu names & positions
          if (tabs[idx].key == 'settings' || (safeIndex < tabs.length && tabs[safeIndex].key == 'settings')) {
            _refreshMenuData();
          }
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
    ),
    );
  }

  Widget _buildDrawer(List<_NavItem> tabs, int safeIndex) {
    final user = ApiService.instance.user;
    return Drawer(
      backgroundColor: const Color(0xFFF8FAFC),
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Drawer Header
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(bottom: BorderSide(color: AppTheme.border)),
              ),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Image.asset('assets/icons/app_icon.png', width: 38, height: 38, fit: BoxFit.cover),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        RichText(
                          text: const TextSpan(
                            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textMain),
                            children: [
                              TextSpan(text: 'Pulse'),
                              TextSpan(text: 'CRM', style: TextStyle(color: AppTheme.primary)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${user?['name'] ?? ""} • ${user?['role_label'] ?? ""}',
                          style: const TextStyle(fontSize: 11.5, color: AppTheme.textMuted),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            // Favorites Header (Direct match with web CRM sidebar!)
            Padding(
              padding: const EdgeInsets.only(left: 16, right: 8, top: 14, bottom: 6),
              child: Row(
                children: [
                  const Text(
                    'FAVORITES',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.8,
                      color: Color(0xFF6366F1),
                    ),
                  ),
                  const Spacer(),
                  IconButton(
                    icon: const Icon(Icons.refresh, size: 16, color: AppTheme.textMuted),
                    tooltip: 'Refresh Menus',
                    onPressed: _refreshMenuData,
                  ),
                ],
              ),
            ),

            // Navigation Items in exact rearranged position order!
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                itemCount: tabs.length,
                separatorBuilder: (_, __) => const SizedBox(height: 6),
                itemBuilder: (context, idx) {
                  final item = tabs[idx];
                  final isSelected = idx == safeIndex;
                  return InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () {
                      Navigator.of(context).pop();
                      setState(() {
                        _selectedIndex = idx;
                        _targetFilter = null;
                        _targetStatus = null;
                      });
                      if (item.key == 'settings') {
                        _refreshMenuData();
                      }
                    },
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      decoration: BoxDecoration(
                        color: isSelected ? const Color(0xFF0F6CBD) : Colors.white,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: isSelected ? const Color(0xFF0F6CBD) : const Color(0xFFC7D2FE).withValues(alpha: 0.6),
                        ),
                        boxShadow: isSelected
                            ? [
                                BoxShadow(
                                  color: const Color(0xFF0F6CBD).withValues(alpha: 0.25),
                                  blurRadius: 6,
                                  offset: const Offset(0, 2),
                                )
                              ]
                            : null,
                      ),
                      child: Row(
                        children: [
                          Icon(
                            isSelected ? item.selectedIcon : item.icon,
                            size: 19,
                            color: isSelected ? Colors.white : const Color(0xFF4338CA),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              item.label.toUpperCase(),
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                                letterSpacing: 0.6,
                                color: isSelected ? Colors.white : const Color(0xFF1E293B),
                              ),
                            ),
                          ),
                          if (isSelected)
                            const Icon(Icons.check, size: 16, color: Colors.white)
                          else
                            const Icon(Icons.chevron_right, size: 16, color: Color(0xFF94A3B8)),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),

            // Drawer Footer (matches web sidebar bottom section)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: const BoxDecoration(
                color: Color(0xFFCCFBF1),
                border: Border(top: BorderSide(color: AppTheme.border)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      user?['name'] ?? 'User',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF134E4A)),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.lock_reset, size: 18, color: Color(0xFF0F766E)),
                    tooltip: 'Change Password',
                    onPressed: () {
                      Navigator.of(context).pop();
                      _openChangePassword();
                    },
                  ),
                  IconButton(
                    icon: const Icon(Icons.logout, size: 18, color: Colors.red),
                    tooltip: 'Logout',
                    onPressed: () {
                      Navigator.of(context).pop();
                      _logout();
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
