import 'package:flutter/material.dart';

class SearchFilterBar extends StatelessWidget {
  const SearchFilterBar({
    super.key,
    required this.controller,
    required this.hint,
    required this.onSubmitted,
    required this.onFilterTap,
    this.hasActiveFilter = false,
    this.onClear,
  });

  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onSubmitted;
  final VoidCallback onFilterTap;
  final bool hasActiveFilter;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    const brand = Color(0xFF0F6CBD);
    return Row(
      children: [
        Expanded(
          child: ListenableBuilder(
            listenable: controller,
            builder: (context, _) {
              return TextField(
                controller: controller,
                textInputAction: TextInputAction.search,
                onSubmitted: onSubmitted,
                decoration: InputDecoration(
                  hintText: hint,
                  hintStyle:
                      const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
                  prefixIcon: const Icon(
                    Icons.search,
                    size: 20,
                    color: Color(0xFF94A3B8),
                  ),
                  suffixIcon: controller.text.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.close, size: 18),
                          onPressed: () {
                            controller.clear();
                            onClear?.call();
                          },
                        ),
                  isDense: true,
                  filled: true,
                  fillColor: Colors.white,
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFEDEBE9)),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFEDEBE9)),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: brand),
                  ),
                ),
              );
            },
          ),
        ),
        const SizedBox(width: 8),
        Material(
          color: hasActiveFilter ? const Color(0xFFDEECF9) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: onFilterTap,
            borderRadius: BorderRadius.circular(12),
            child: Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: hasActiveFilter
                      ? brand.withValues(alpha: 0.45)
                      : const Color(0xFFEDEBE9),
                ),
              ),
              child: Icon(
                Icons.tune,
                color: hasActiveFilter ? brand : const Color(0xFF64748B),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class FilterOption {
  const FilterOption({required this.value, required this.label});
  final String value;
  final String label;
}

Future<Map<String, String>?> showListFilterSheet({
  required BuildContext context,
  required String title,
  required List<FilterOption> statusOptions,
  String? currentStatus,
  String? currentFilter,
  bool showFollowUp = false,
}) {
  return showModalBottomSheet<Map<String, String>>(
    context: context,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
    ),
    builder: (ctx) {
      var status = currentStatus ?? '';
      var filter = currentFilter ?? '';
      return StatefulBuilder(
        builder: (context, setModal) {
          const brand = Color(0xFF0F6CBD);
          Widget chip({
            required String label,
            required bool selected,
            required VoidCallback onTap,
          }) {
            return Padding(
              padding: const EdgeInsets.only(right: 8, bottom: 8),
              child: ChoiceChip(
                label: Text(label),
                selected: selected,
                onSelected: (_) => onTap(),
                selectedColor: const Color(0xFFDEECF9),
                labelStyle: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: selected ? brand : const Color(0xFF475569),
                ),
                side: BorderSide(
                  color: selected
                      ? brand.withValues(alpha: 0.4)
                      : const Color(0xFFE2E8F0),
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
            );
          }

          return SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      TextButton(
                        onPressed: () {
                          Navigator.pop(ctx, {'status': '', 'filter': ''});
                        },
                        child: const Text('Clear'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'STATUS',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF94A3B8),
                      letterSpacing: 0.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    children: [
                      chip(
                        label: 'All',
                        selected: status.isEmpty && filter != 'follow_up_today',
                        onTap: () => setModal(() {
                          status = '';
                          if (filter == 'follow_up_today') filter = '';
                        }),
                      ),
                      for (final opt in statusOptions)
                        chip(
                          label: opt.label,
                          selected: status == opt.value &&
                              filter != 'follow_up_today',
                          onTap: () => setModal(() {
                            status = opt.value;
                            filter = '';
                          }),
                        ),
                    ],
                  ),
                  if (showFollowUp) ...[
                    const SizedBox(height: 8),
                    const Text(
                      'QUICK FILTER',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF94A3B8),
                        letterSpacing: 0.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      children: [
                        chip(
                          label: 'Follow-up today',
                          selected: filter == 'follow_up_today',
                          onTap: () => setModal(() {
                            filter = 'follow_up_today';
                            status = '';
                          }),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: () {
                        Navigator.pop(ctx, {
                          'status': status,
                          'filter': filter,
                        });
                      },
                      style: FilledButton.styleFrom(
                        backgroundColor: brand,
                        minimumSize: const Size.fromHeight(46),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Apply filter'),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      );
    },
  );
}
