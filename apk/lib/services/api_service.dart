import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';

class ApiService {
  ApiService._();
  static final ApiService instance = ApiService._();

  static const String _tokenKey = 'pulse_api_token';
  static const String _userKey = 'pulse_user_json';

  String? token;
  Map<String, dynamic>? user;

  bool get isAuthenticated => token != null && token!.isNotEmpty;
  bool get isAdmin => user?['is_admin'] == true || user?['role'] == 'admin';
  bool get isCoordinator => user?['is_coordinator'] == true || user?['role'] == 'coordinator';
  bool get isOperation => user?['is_operation'] == true || user?['role'] == 'operation';
  bool get canAccessCrm => user?['can_access_crm'] == true;
  bool get canAccessOperations => user?['can_access_operations'] == true;
  bool get canAssignUsers => user?['can_assign_users'] == true;
  bool get canBulkMap => user?['can_bulk_map'] == true;
  bool get canSeeAll => user?['can_see_all'] == true;

  Future<void> loadSession() async {
    final prefs = await SharedPreferences.getInstance();
    token = prefs.getString(_tokenKey);
    final rawUser = prefs.getString(_userKey);
    if (rawUser != null && rawUser.isNotEmpty) {
      try {
        user = jsonDecode(rawUser) as Map<String, dynamic>;
      } catch (_) {}
    }
  }

  Future<void> saveSession(String newToken, Map<String, dynamic> newUser) async {
    token = newToken;
    user = newUser;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, newToken);
    await prefs.setString(_userKey, jsonEncode(newUser));
  }

  Future<void> clearSession() async {
    token = null;
    user = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Exception _buildError(http.Response res, Map<String, dynamic>? body) {
    if (res.statusCode == 401) {
      return Exception('Session expired. Please log in again.');
    }
    final errors = body?['errors'];
    if (errors is Map && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) {
        return Exception(first.first.toString());
      }
      return Exception(first.toString());
    }
    final msg = body?['message']?.toString();
    if (msg != null && msg.isNotEmpty) {
      return Exception(msg);
    }
    return Exception('Request failed (${res.statusCode})');
  }

  Future<Map<String, dynamic>> _parse(http.Response res) async {
    Map<String, dynamic>? body;
    try {
      final decoded = jsonDecode(res.body);
      if (decoded is Map<String, dynamic>) {
        body = decoded;
      }
    } catch (_) {}

    if (res.statusCode == 401) {
      await clearSession();
      throw Exception('Session expired');
    }
    if (res.statusCode >= 400 || (body != null && body['ok'] == false)) {
      throw _buildError(res, body);
    }
    return body ?? <String, dynamic>{'ok': true};
  }

  String _qs(Map<String, dynamic> params) {
    final filtered = <String, String>{};
    params.forEach((k, v) {
      if (v != null) {
        final s = v.toString().trim();
        if (s.isNotEmpty) filtered[k] = s;
      }
    });
    if (filtered.isEmpty) return '';
    return '?${filtered.entries.map((e) => '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value)}').join('&')}';
  }

  Future<Map<String, dynamic>> get(String path, [Map<String, dynamic>? query]) async {
    final url = '$kApiBaseUrl$path${query != null ? _qs(query) : ''}';
    final res = await http.get(Uri.parse(url), headers: _headers);
    return _parse(res);
  }

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) async {
    final url = '$kApiBaseUrl$path';
    final res = await http.post(
      Uri.parse(url),
      headers: _headers,
      body: jsonEncode(body ?? {}),
    );
    return _parse(res);
  }

  Future<Map<String, dynamic>> put(String path, [Map<String, dynamic>? body]) async {
    final url = '$kApiBaseUrl$path';
    final res = await http.put(
      Uri.parse(url),
      headers: _headers,
      body: jsonEncode(body ?? {}),
    );
    return _parse(res);
  }

  Future<Map<String, dynamic>> delete(String path, [Map<String, dynamic>? body]) async {
    final url = '$kApiBaseUrl$path';
    final res = await http.delete(
      Uri.parse(url),
      headers: _headers,
      body: jsonEncode(body ?? {}),
    );
    return _parse(res);
  }

  // --- Auth APIs ---
  Future<Map<String, dynamic>> login(String username, String password) async {
    final data = await post('/login', {'username': username, 'password': password});
    if (data['token'] != null && data['user'] is Map) {
      await saveSession(
        data['token'] as String,
        Map<String, dynamic>.from(data['user'] as Map),
      );
    }
    return data;
  }

  Future<Map<String, dynamic>> me() async {
    final data = await get('/me');
    if (data['user'] is Map) {
      user = Map<String, dynamic>.from(data['user'] as Map);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_userKey, jsonEncode(user));
    }
    return data;
  }

  Future<void> logout() async {
    try {
      await post('/logout');
    } catch (_) {}
    await clearSession();
  }

  Future<Map<String, dynamic>> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) {
    return put('/change-password', {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
  }

  // --- Dashboard ---
  Future<Map<String, dynamic>> dashboard() => get('/dashboard');

  // --- Customers (CRM & Team Ops) ---
  Future<Map<String, dynamic>> customers({
    String pipeline = 'crm',
    String? q,
    String? status,
    String? filter,
    int? assignedTo,
    int page = 1,
    int perPage = 15,
  }) {
    return get('/customers', {
      'pipeline': pipeline,
      'q': q,
      'status': status,
      'filter': filter,
      'assigned_to': assignedTo,
      'page': page,
      'per_page': perPage,
    });
  }

  Future<Map<String, dynamic>> customerMeta({String pipeline = 'crm'}) {
    return get('/customers/meta', {'pipeline': pipeline});
  }

  Future<Map<String, dynamic>> customerShow(int id) => get('/customers/$id');

  Future<Map<String, dynamic>> customerStore(Map<String, dynamic> data) =>
      post('/customers', data);

  Future<Map<String, dynamic>> customerUpdate(int id, Map<String, dynamic> data) =>
      put('/customers/$id', data);

  Future<Map<String, dynamic>> customerDestroy(int id) => delete('/customers/$id');

  Future<Map<String, dynamic>> customerAddActivity(
    int id,
    Map<String, dynamic> data, {
    List<http.MultipartFile>? files,
  }) async {
    if (files == null || files.isEmpty) {
      return post('/customers/$id/activities', data);
    }

    final req = http.MultipartRequest('POST', Uri.parse('$kApiBaseUrl/customers/$id/activities'));
    _headers.forEach((k, v) {
      if (k != 'Content-Type') req.headers[k] = v;
    });
    data.forEach((k, v) {
      if (v != null) {
        if (v is Map) {
          v.forEach((innerK, innerV) {
            if (innerV != null) {
              req.fields['$k[$innerK]'] = innerV.toString();
            }
          });
        } else {
          req.fields[k] = v.toString();
        }
      }
    });
    for (final f in files) {
      req.files.add(f);
    }
    final streamed = await req.send();
    final res = await http.Response.fromStream(streamed);
    return _parse(res);
  }

  Future<Map<String, dynamic>> customerAssignOperations(int id) =>
      post('/customers/$id/assign-operations');

  Future<Map<String, dynamic>> customerAutoAction(int id, String channel) =>
      post('/customers/$id/auto/$channel');

  Future<Map<String, dynamic>> customerCompose(
    int id, {
    required String channel,
    required String message,
    String? subject,
    String? link,
  }) {
    return post('/customers/$id/compose', {
      'channel': channel,
      'message': message,
      if (subject != null) 'subject': subject,
      if (link != null) 'link': link,
    });
  }

  Future<Map<String, dynamic>> customerBulkMap(List<int> ids, int agentId) {
    return post('/customers/bulk-map', {
      'ids': ids,
      'assigned_to': agentId,
    });
  }

  Future<Map<String, dynamic>> customerBulkWhatsapp(List<int> ids) {
    return post('/customers/bulk-whatsapp', {'ids': ids});
  }

  Future<Map<String, dynamic>> customerBulkEmail(List<int> ids, [String recipient = '']) {
    return post('/customers/bulk-email', {
      'ids': ids,
      'recipient': recipient,
    });
  }

  Future<Map<String, dynamic>> requests({int page = 1}) =>
      get('/requests', {'page': page});

  // --- Booking Slots ---
  Future<Map<String, dynamic>> bookingSlots({String? date}) =>
      get('/booking-slots', {'date': date});

  Future<Map<String, dynamic>> bookingSlotBook(
    int slotId, {
    required String customerName,
    int? customerId,
    String? notes,
  }) {
    return post('/booking-slots/$slotId/book', {
      'customer_name': customerName,
      'customer_id': customerId,
      'notes': notes,
    });
  }

  // --- Admin Settings ---
  Future<Map<String, dynamic>> settingsIndex() => get('/settings');

  Future<Map<String, dynamic>> settingsBookingSlots({String? slotDate}) =>
      get('/settings/booking-slots', {'slot_date': slotDate});

  Future<Map<String, dynamic>> settingsBookingSlotStore(Map<String, dynamic> data) =>
      post('/settings/booking-slots', data);

  Future<Map<String, dynamic>> settingsBookingSlotUpdateSetting(Map<String, dynamic> data) =>
      put('/settings/booking-slots/setting', data);

  Future<Map<String, dynamic>> settingsBookingSlotUpdate(int id, Map<String, dynamic> data) =>
      put('/settings/booking-slots/$id', data);

  Future<Map<String, dynamic>> settingsBookingSlotDestroy(int id) =>
      delete('/settings/booking-slots/$id');

  Future<Map<String, dynamic>> settingsBookingSlotDestroyDate(String slotDate) =>
      delete('/settings/booking-slots/date', {'slot_date': slotDate});

  Future<Map<String, dynamic>> settingsFieldStore(Map<String, dynamic> data) =>
      post('/settings/fields', data);

  Future<Map<String, dynamic>> settingsFieldReorder(List<int> order) =>
      post('/settings/fields/reorder', {'order': order});

  Future<Map<String, dynamic>> settingsFieldUpdate(int id, Map<String, dynamic> data) =>
      put('/settings/fields/$id', data);

  Future<Map<String, dynamic>> settingsFieldDestroy(int id) =>
      delete('/settings/fields/$id');

  Future<Map<String, dynamic>> settingsNoteTemplateStore(Map<String, dynamic> data) =>
      post('/settings/note-templates', data);

  Future<Map<String, dynamic>> settingsNoteTemplateUpdate(int id, Map<String, dynamic> data) =>
      put('/settings/note-templates/$id', data);

  Future<Map<String, dynamic>> settingsNoteTemplateDestroy(int id) =>
      delete('/settings/note-templates/$id');

  Future<Map<String, dynamic>> settingsOperationSettingUpdate(Map<String, dynamic> data) =>
      put('/settings/operation-setting', data);

  Future<Map<String, dynamic>> settingsWhatsappSettingUpdate(Map<String, dynamic> data) =>
      put('/settings/whatsapp-setting', data);

  // --- Legacy Compatibility Methods ---
  Future<Map<String, dynamic>> crmShow(int id, {String pipeline = 'crm'}) => customerShow(id);

  Future<Map<String, dynamic>> crmCreate(Map<String, dynamic> data, {String pipeline = 'crm'}) {
    final payload = {'pipeline': pipeline, ...data};
    return customerStore(payload);
  }

  Future<Map<String, dynamic>> crmUpdate(int id, Map<String, dynamic> data, {String pipeline = 'crm'}) =>
      customerUpdate(id, data);

  Future<Map<String, dynamic>> crmAddActivity(int id, Map<String, dynamic> data, {String pipeline = 'crm'}) =>
      customerAddActivity(id, data);

  Future<Map<String, dynamic>> inventoryTypes() =>
      get('/operations/inventory-types');

  Future<Map<String, dynamic>> startActivation(int customerId) =>
      post('/operations/$customerId/activation-requests/start');

  Future<Map<String, dynamic>> submitActivation(int customerId, Map<String, dynamic> data) =>
      post('/operations/$customerId/activation-requests', data);

  Future<Map<String, dynamic>> inventory({int? typeId, String q = '', String status = ''}) =>
      get('/inventory', {if (typeId != null) 'type': typeId, 'q': q, 'status': status});

  Future<Map<String, dynamic>> inventoryHistory(int id) =>
      get('/inventory/$id/history');

  // --- User Management ---
  Future<Map<String, dynamic>> usersList({int page = 1}) =>
      get('/users', {'page': page});

  Future<Map<String, dynamic>> userStore(Map<String, dynamic> data) =>
      post('/users', data);

  Future<Map<String, dynamic>> userUpdate(int id, Map<String, dynamic> data) =>
      put('/users/$id', data);

  Future<Map<String, dynamic>> userDestroy(int id) => delete('/users/$id');
}
