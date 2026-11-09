import 'package:flutter/material.dart';
import 'crm_screen.dart';

class OperationsScreen extends StatelessWidget {
  const OperationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const CrmScreen(pipeline: 'operations');
  }
}
