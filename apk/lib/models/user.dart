class AppUser {
  final int id;
  final String name;
  final String username;
  final String? email;
  final String role;
  final String roleLabel;
  final bool isAdmin;
  final bool isCoordinator;
  final bool isOperation;
  final bool canAccessCrm;
  final bool canAccessOperations;
  final bool canAccessInventory;
  final bool canAssignUsers;
  final bool canBulkMap;
  final bool canSeeAll;
  final Map<String, dynamic> meta;
  final Map<String, dynamic> values;

  AppUser({
    required this.id,
    required this.name,
    required this.username,
    this.email,
    required this.role,
    required this.roleLabel,
    this.isAdmin = false,
    this.isCoordinator = false,
    this.isOperation = false,
    this.canAccessCrm = false,
    this.canAccessOperations = false,
    this.canAccessInventory = false,
    this.canAssignUsers = false,
    this.canBulkMap = false,
    this.canSeeAll = false,
    this.meta = const {},
    this.values = const {},
  });

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      username: json['username'] as String? ?? '',
      email: json['email']?.toString(),
      role: json['role'] as String? ?? 'front_office',
      roleLabel: json['role_label'] as String? ?? 'Front Office',
      isAdmin: json['is_admin'] == true,
      isCoordinator: json['is_coordinator'] == true,
      isOperation: json['is_operation'] == true,
      canAccessCrm: json['can_access_crm'] == true,
      canAccessOperations: json['can_access_operations'] == true,
      canAccessInventory: json['can_access_inventory'] == true,
      canAssignUsers: json['can_assign_users'] == true,
      canBulkMap: json['can_bulk_map'] == true,
      canSeeAll: json['can_see_all'] == true,
      meta: (json['meta'] is Map) ? Map<String, dynamic>.from(json['meta'] as Map) : {},
      values: (json['values'] is Map) ? Map<String, dynamic>.from(json['values'] as Map) : {},
    );
  }
}

