import 'package:flutter/material.dart';

import '../services/api.dart';
import 'crm_screen.dart';
import 'dashboard_screen.dart';
import 'inventory_screen.dart';
import 'login_screen.dart';
import 'operations_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _NavItem {
  const _NavItem({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    required this.page,
  });

  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final Widget page;
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  List<_NavItem> get _tabs {
    final items = <_NavItem>[
      const _NavItem(
        label: 'Dashboard',
        icon: Icons.dashboard_outlined,
        selectedIcon: Icons.dashboard,
        page: DashboardScreen(embedded: true),
      ),
    ];

    // Fixed order: Dashboard → CRM → Inventory → Team Ops
    if (ApiClient.instance.canAccessCrm) {
      items.add(const _NavItem(
        label: 'CRM',
        icon: Icons.people_outline,
        selectedIcon: Icons.people,
        page: CrmScreen(),
      ));
    }
    if (ApiClient.instance.canAccessInventory) {
      items.add(const _NavItem(
        label: 'Inventory',
        icon: Icons.inventory_2_outlined,
        selectedIcon: Icons.inventory_2,
        page: InventoryScreen(),
      ));
    }
    if (ApiClient.instance.canAccessOperations) {
      items.add(const _NavItem(
        label: 'Team Ops',
        icon: Icons.engineering_outlined,
        selectedIcon: Icons.engineering,
        page: OperationsScreen(),
      ));
    }

    return items;
  }

  Future<void> _logout() async {
    await ApiClient.instance.logout();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tabs = _tabs;
    final safeIndex = _index.clamp(0, tabs.length - 1);
    final user = ApiClient.instance.user;

    return Scaffold(
      backgroundColor: const Color(0xFFF3F2F1),
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF201F1E),
        elevation: 0,
        titleSpacing: 16,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              tabs[safeIndex].label,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
            ),
            Text(
              '${user?['name'] ?? ''} · ${user?['role_label'] ?? ''}',
              style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: _logout,
            icon: const Icon(Icons.logout),
            tooltip: 'Logout',
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(height: 1, color: const Color(0xFFEDEBE9)),
        ),
      ),
      body: IndexedStack(
        index: safeIndex,
        children: tabs.map((t) => t.page).toList(),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: safeIndex,
        onDestinationSelected: (i) => setState(() => _index = i),
        backgroundColor: Colors.white,
        indicatorColor: const Color(0xFFDEECF9),
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: tabs
            .map(
              (t) => NavigationDestination(
                icon: Icon(t.icon),
                selectedIcon: Icon(t.selectedIcon, color: const Color(0xFF0F6CBD)),
                label: t.label,
              ),
            )
            .toList(),
      ),
    );
  }
}
