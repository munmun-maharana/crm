import 'dart:io';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AppConfig {
  AppConfig._();
  static final AppConfig instance = AppConfig._();

  /// Production live domain
  static const String productionDomain = 'https://crm.vehiclegps.in';

  /// Local Android Emulator domain (maps 10.0.2.2 to host PC localhost)
  static const String localEmulatorDomain = 'http://10.0.2.2/all_project/crm/public';

  /// Local PC / Desktop localhost
  static const String localPcDomain = 'http://localhost/all_project/crm/public';

  static const String _prefEnvKey = 'pulse_app_env_mode';
  static const String _prefCustomUrlKey = 'pulse_app_custom_url';

  String _currentBaseUrl = productionDomain;
  bool _isPhysicalDevice = false;
  String _mode = 'auto'; // 'auto', 'production', 'local', 'custom'
  String _customUrl = '';

  bool get isPhysicalDevice => _isPhysicalDevice;
  String get mode => _mode;
  String get customUrl => _customUrl;
  String get currentBaseUrl => _currentBaseUrl;

  /// Dynamic site base URL
  static String get siteBaseUrl => instance._currentBaseUrl;

  /// Dynamic API base URL
  static String get apiBaseUrl => '${instance._currentBaseUrl}/api/v1';

  /// Initialize and detect device environment
  Future<void> init() async {
    try {
      if (kIsWeb) {
        _isPhysicalDevice = true;
      } else if (Platform.isAndroid) {
        final info = await DeviceInfoPlugin().androidInfo;
        _isPhysicalDevice = info.isPhysicalDevice;
      } else if (Platform.isIOS) {
        final info = await DeviceInfoPlugin().iosInfo;
        _isPhysicalDevice = info.isPhysicalDevice;
      } else {
        _isPhysicalDevice = true;
      }
    } catch (_) {
      _isPhysicalDevice = kReleaseMode;
    }

    final prefs = await SharedPreferences.getInstance();
    _mode = prefs.getString(_prefEnvKey) ?? 'auto';
    _customUrl = prefs.getString(_prefCustomUrlKey) ?? '';

    _computeBaseUrl();
  }

  void _computeBaseUrl() {
    if (_mode == 'production') {
      _currentBaseUrl = productionDomain;
    } else if (_mode == 'local') {
      _currentBaseUrl = localEmulatorDomain;
    } else if (_mode == 'custom' && _customUrl.isNotEmpty) {
      _currentBaseUrl = _customUrl;
    } else {
      // Auto mode:
      // When on a real physical device or in release build -> use production URL
      // When on emulator or local debugging -> use local emulator URL
      if (_isPhysicalDevice || kReleaseMode) {
        _currentBaseUrl = productionDomain;
      } else {
        _currentBaseUrl = localEmulatorDomain;
      }
    }

    // Remove any trailing slashes
    if (_currentBaseUrl.endsWith('/')) {
      _currentBaseUrl = _currentBaseUrl.substring(0, _currentBaseUrl.length - 1);
    }
  }

  Future<void> setMode(String mode, {String? customUrl}) async {
    _mode = mode;
    if (customUrl != null) _customUrl = customUrl;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefEnvKey, mode);
    if (customUrl != null) {
      await prefs.setString(_prefCustomUrlKey, customUrl);
    }

    _computeBaseUrl();
  }

  String get currentEnvironmentLabel {
    if (_mode == 'production') return 'Live (crm.vehiclegps.in)';
    if (_mode == 'local') return 'Local (10.0.2.2)';
    if (_mode == 'custom') return 'Custom (${_customUrl.isEmpty ? "Not set" : _customUrl})';
    return _isPhysicalDevice ? 'Auto: Real Device (crm.vehiclegps.in)' : 'Auto: Local Emulator (10.0.2.2)';
  }
}

/// Dynamic getters so all existing code seamlessly references the active URL
String get kApiBaseUrl => AppConfig.apiBaseUrl;
String get kSiteBaseUrl => AppConfig.siteBaseUrl;
