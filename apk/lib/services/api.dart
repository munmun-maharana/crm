import 'api_service.dart';

// Backwards-compatibility alias for previous ApiClient calls
class ApiClient {
  ApiClient._();
  static final ApiService instance = ApiService.instance;
}
