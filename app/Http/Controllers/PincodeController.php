<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\Pincode;
use App\Models\User;
use App\Services\CrmFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PincodeController extends Controller
{
    public function __construct(private CrmFieldService $fields) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccessPincodes(), 403);

        $q = trim($request->string('q')->toString());
        $assignedTo = $request->integer('assigned_to') ?: null;

        $pincodes = Pincode::query()
            ->with('assignee')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('pincode', 'like', "%{$q}%")
                        ->orWhere('area', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%")
                        ->orWhere('state', 'like', "%{$q}%")
                        ->orWhere('zone', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhere('meta', 'like', "%{$q}%");
                });
            })
            ->when($assignedTo, fn ($query) => $query->where('assigned_to', $assignedTo))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $agents = User::orderBy('name')->get(['id', 'name', 'username']);
        $formFields = $this->fields->formFields('pincodes');
        $tableFields = $this->fields->tableFields('pincodes');
        $excelFields = $this->fields->excelFields('pincodes');

        return view('pincodes.index', compact(
            'pincodes',
            'agents',
            'q',
            'assignedTo',
            'formFields',
            'tableFields',
            'excelFields'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessPincodes(), 403);

        $fields = $this->fields->formFields('pincodes');
        $rules = $this->fields->validationRules($fields);
        if (! isset($rules['pincode'])) {
            $rules['pincode'] = ['required', 'string', 'max:30'];
        }
        $request->validate($rules);

        [$core, $meta] = $this->splitPincodePayload($request, $fields);
        $core['pincode'] = trim((string) ($core['pincode'] ?? $request->input('pincode')));
        $core['created_by'] = $request->user()->id;

        Pincode::create([
            ...$core,
            'meta' => $meta ?: null,
        ]);

        return back()->with('success', 'Pincode added.');
    }

    public function update(Request $request, Pincode $pincode): RedirectResponse
    {
        abort_unless($request->user()->canAccessPincodes(), 403);

        // Quick status-only update
        if ($request->has('quick_status') && ! $request->has('pincode')) {
            $request->validate(['quick_status' => ['required', 'string', 'max:60']]);
            $pincode->update(['status' => trim($request->string('quick_status')->toString())]);

            return back()->with('success', 'Pincode status updated.');
        }

        $fields = $this->fields->formFields('pincodes');
        $rules = $this->fields->validationRules($fields);
        if (! isset($rules['pincode'])) {
            $rules['pincode'] = ['required', 'string', 'max:30'];
        }
        $request->validate($rules);

        [$core, $meta] = $this->splitPincodePayload($request, $fields);
        if (empty($core['pincode'])) {
            $core['pincode'] = $pincode->pincode;
        }

        $mergedMeta = array_merge($pincode->meta ?? [], $meta);
        foreach ($meta as $k => $v) {
            if ($v === null || $v === '') {
                unset($mergedMeta[$k]);
            }
        }

        $pincode->update([
            ...$core,
            'meta' => $mergedMeta ?: null,
        ]);

        return back()->with('success', 'Pincode updated.');
    }

    public function destroy(Request $request, Pincode $pincode): RedirectResponse
    {
        abort_unless($request->user()->canAccessPincodes(), 403);

        $pincode->delete();

        return back()->with('success', 'Pincode deleted.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessPincodes(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:pincodes,id'],
            'action_type' => ['required', 'in:status,assign,delete'],
            'bulk_status' => ['nullable', 'string', 'max:60'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $ids = collect($data['ids'])->unique()->values();
        $count = $ids->count();

        if ($data['action_type'] === 'delete') {
            Pincode::whereIn('id', $ids)->delete();

            return back()->with('success', "Deleted {$count} pincode(s).");
        }

        if ($data['action_type'] === 'status') {
            $newStatus = trim((string) ($data['bulk_status'] ?? ''));
            if ($newStatus === '') {
                return back()->withErrors(['bulk_status' => 'Please select a status to apply.']);
            }
            Pincode::whereIn('id', $ids)->update(['status' => $newStatus]);

            return back()->with('success', "Updated status to {$newStatus} for {$count} pincode(s).");
        }

        if ($data['action_type'] === 'assign') {
            $userId = $data['assigned_to'] ?? null;
            Pincode::whereIn('id', $ids)->update(['assigned_to' => $userId]);

            return back()->with('success', "Updated executive assignment for {$count} pincode(s).");
        }

        return back();
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canAccessPincodes(), 403);

        $q = trim((string) $request->input('q', ''));

        $pincodes = Pincode::query()
            ->with('assignee')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('pincode', 'like', "%{$q}%")
                        ->orWhere('area', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%")
                        ->orWhere('state', 'like', "%{$q}%")
                        ->orWhere('zone', 'like', "%{$q}%");
                });
            })
            ->orderBy('pincode')
            ->get();

        $excelFields = $this->fields->excelFields('pincodes');
        if ($excelFields->isEmpty()) {
            $excelFields = CrmField::inPipeline('pincodes')->active()->ordered()->get();
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pincodes');

        $col = 1;
        foreach ($excelFields as $field) {
            $sheet->setCellValue([$col, 1], $field->label ?: $field->key);
            $col++;
        }
        $sheet->setCellValue([$col, 1], 'Created At');

        $rowNum = 2;
        foreach ($pincodes as $p) {
            $col = 1;
            foreach ($excelFields as $field) {
                if ($field->key === 'assigned_to') {
                    $val = $p->assignee?->name ?: 'Unassigned';
                } else {
                    $val = $p->fieldValue($field->key);
                    if (is_array($val)) {
                        $val = implode(', ', $val);
                    }
                }
                $sheet->setCellValue([$col, $rowNum], (string) ($val ?? ''));
                $col++;
            }
            $sheet->setCellValue([$col, $rowNum], $p->created_at ? $p->created_at->format('d M Y, h:i A') : '');
            $rowNum++;
        }

        $totalCols = $excelFields->count() + 1;
        foreach (range(1, max(1, $totalCols)) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'pincodes-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function demo(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canAccessPincodes(), 403);

        $excelFields = $this->fields->excelFields('pincodes');
        if ($excelFields->isEmpty()) {
            $excelFields = CrmField::inPipeline('pincodes')->active()->ordered()->get();
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pincodes');

        $col = 1;
        foreach ($excelFields as $field) {
            $sheet->setCellValue([$col, 1], $field->key);
            $col++;
        }

        $samples = [
            'pincode' => '751001',
            'area' => 'Saheed Nagar',
            'city' => 'Bhubaneswar',
            'state' => 'Odisha',
            'zone' => 'Zone A',
            'notes' => 'Full doorstep service available',
        ];

        $col = 1;
        foreach ($excelFields as $field) {
            $sheet->setCellValue([$col, 2], $samples[$field->key] ?? '');
            $col++;
        }

        foreach (range(1, max(1, $excelFields->count())) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'pincodes-demo.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessPincodes(), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $excelFields = $this->fields->excelFields('pincodes');
        if ($excelFields->isEmpty()) {
            $excelFields = CrmField::inPipeline('pincodes')->active()->ordered()->get();
        }
        $keys = $excelFields->pluck('key')->all();

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $imported = 0;
        $skipped = 0;
        $header = null;
        $defaultStatus = Pincode::statusOptions()[0] ?? 'Serviceable';

        foreach ($rows as $row) {
            $values = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_values($row));

            if ($header === null) {
                $header = array_map(fn ($v) => strtolower(trim((string) $v)), $values);
                continue;
            }

            if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $map = [];
            foreach ($excelFields as $field) {
                $aliases = array_unique(array_filter([
                    strtolower($field->key),
                    strtolower($field->label),
                ]));
                foreach ($header as $idx => $col) {
                    if (in_array($col, $aliases, true)) {
                        $map[$field->key] = $values[$idx] ?? null;
                        break;
                    }
                }
            }

            $pinVal = trim((string) ($map['pincode'] ?? ''));
            if ($pinVal === '') {
                $skipped++;
                continue;
            }

            $core = ['pincode' => $pinVal];
            $meta = [];

            foreach ($keys as $key) {
                $value = isset($map[$key]) ? trim((string) $map[$key]) : null;
                if ($value === '') {
                    $value = null;
                }
                if (in_array($key, CrmField::PINCODE_CORE_KEYS, true)) {
                    $core[$key] = $value;
                } elseif ($value !== null) {
                    $meta[$key] = $value;
                }
            }

            if (empty($core['status'])) {
                $core['status'] = $defaultStatus;
            }
            $core['created_by'] = $request->user()->id;
            $core['meta'] = $meta ?: null;

            Pincode::create($core);
            $imported++;
        }

        return back()->with('success', "Imported {$imported} pincode(s)".($skipped ? ", skipped {$skipped}." : '.'));
    }

    private function splitPincodePayload(Request $request, $fields): array
    {
        $core = [];
        $meta = [];

        foreach ($fields as $field) {
            $value = $request->input($field->key);
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === '') {
                $value = null;
            }

            if (in_array($field->key, CrmField::PINCODE_CORE_KEYS, true)) {
                $core[$field->key] = $value;
            } else {
                $meta[$field->key] = $value;
            }
        }

        if (array_key_exists('status', $core) && ! $core['status']) {
            $core['status'] = Pincode::statusOptions()[0] ?? 'Serviceable';
        }

        return [$core, $meta];
    }
}

