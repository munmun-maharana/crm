import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';

class ApiClient {
  ApiClient._();
  static final ApiClient instance = ApiClient._();

  static const _tokenKey = 'api_token';
  static const _userKey = 'user_json';

  String? token;
  Map<String, dynamic>? user;

  bool get canAccessCrm => user?['can_access_crm'] == true;
  bool get canAccessOperations => user?['can_access_operations'] == true;
  bool get canAccessInventory => user?['can_access_inventory'] == true;

  Future<void> loadSession() async {
    final prefs = await SharedPreferences.getInstance();
    token = prefs.getString(_tokenKey);
    final raw = prefs.getString(_userKey);
    if (raw != null) {
      user = jsonDecode(raw) as Map<String, dynamic>;
    }
  }

  Future<void> _saveSession(String newToken, Map<String, dynamic> newUser) async {
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
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Exception _errorFrom(http.Response res, Map<String, dynamic>? body) {
    if (res.statusCode == 401) {
      return Exception('Session expired');
    }
    final errors = body?['errors'];
    if (errors is Map && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) {
        return Exception(first.first.toString());
      }
    }
    return Exception(
      body?['message']?.toString() ?? 'Request failed (${res.statusCode})',
    );
  }

  Future<Map<String, dynamic>> _parse(http.Response res) async {
    Map<String, dynamic>? body;
    try {
      final decoded = jsonDecode(res.body);
      if (decoded is Map<String, dynamic>) body = decoded;
    } catch (_) {}
    if (res.statusCode == 401) {
      await clearSession();
      throw Exception('Session expired');
    }
    if (res.statusCode >= 400) {
      throw _errorFrom(res, body);
    }
    return body ?? <String, dynamic>{};
  }

  Future<Map<String, dynamic>> _get(String path) async {
    final res = await http.get(
      Uri.parse('$kApiBaseUrl$path'),
      headers: _headers,
    );
    return _parse(res);
  }

  Future<Map<String, dynamic>> _post(String path, [Map<String, dynamic>? data]) async {
    final res = await http.post(
      Uri.parse('$kApiBaseUrl$path'),
      headers: _headers,
      body: jsonEncode(data ?? {}),
    );
    return _parse(res);
  }

  Future<Map<String, dynamic>> _put(String path, [Map<String, dynamic>? data]) async {
    final res = await http.put(
      Uri.parse('$kApiBaseUrl$path'),
      headers: _headers,
      body: jsonEncode(data ?? {}),
    );
    return _parse(res);
  }

  Future<Map<String, dynamic>> login(String username, String password) async {
    final res = await http.post(
      Uri.parse('$kApiBaseUrl/login'),
      headers: _headers,
      body: jsonEncode({'username': username, 'password': password}),
    );
    final body = await _parse(res);
    await _saveSession(
      body['token'] as String,
      Map<String, dynamic>.from(body['user'] as Map),
    );
    return body;
  }

  Future<Map<String, dynamic>> dashboard() => _get('/dashboard');

  Future<Map<String, dynamic>> crm({
    String q = '',
    String status = '',
    String filter = '',
  }) =>
      _get('/crm${_qs({
            if (q.trim().isNotEmpty) 'q': q.trim(),
            if (status.isNotEmpty) 'status': status,
            if (filter.isNotEmpty) 'filter': filter,
          })}');

  Future<Map<String, dynamic>> crmShow(int id, {String pipeline = 'crm'}) =>
      _get('/${_pipe(pipeline)}/$id');
  Future<Map<String, dynamic>> crmCreate(
    Map<String, dynamic> data, {
    String pipeline = 'crm',
  }) =>
      _post('/${_pipe(pipeline)}', data);
  Future<Map<String, dynamic>> crmUpdate(
    int id,
    Map<String, dynamic> data, {
    String pipeline = 'crm',
  }) =>
      _put('/${_pipe(pipeline)}/$id', data);
  Future<Map<String, dynamic>> crmAddActivity(
    int id,
    Map<String, dynamic> data, {
    String pipeline = 'crm',
  }) =>
      _post('/${_pipe(pipeline)}/$id/activities', data);

  Future<Map<String, dynamic>> operations({
    String q = '',
    String status = '',
    String filter = '',
  }) =>
      _get('/operations${_qs({
            if (q.trim().isNotEmpty) 'q': q.trim(),
            if (status.isNotEmpty) 'status': status,
            if (filter.isNotEmpty) 'filter': filter,
          })}');

  Future<Map<String, dynamic>> inventoryTypes() =>
      _get('/operations/inventory-types');

  Future<Map<String, dynamic>> startActivation(int customerId) =>
      _post('/operations/$customerId/activation-requests/start', {});

  Future<Map<String, dynamic>> submitActivation(
    int customerId,
    Map<String, dynamic> data,
  ) =>
      _post('/operations/$customerId/activation-requests', data);

  String _pipe(String pipeline) =>
      pipeline == 'operations' ? 'operations' : 'crm';

  Future<Map<String, dynamic>> inventory({
    int? typeId,
    String q = '',
    String status = '',
  }) {
    return _get('/inventory${_qs({
          if (typeId != null) 'type': '$typeId',
          if (q.trim().isNotEmpty) 'q': q.trim(),
          if (status.isNotEmpty) 'status': status,
        })}');
  }

  Future<Map<String, dynamic>> inventoryHistory(int id) =>
      _get('/inventory/$id/history');

  String _qs(Map<String, String> params) {
    if (params.isEmpty) return '';
    return '?${params.entries.map((e) => '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value)}').join('&')}';
  }

  Future<void> logout() async {
    try {
      await http.post(Uri.parse('$kApiBaseUrl/logout'), headers: _headers);
    } catch (_) {}
    await clearSession();
  }
}
