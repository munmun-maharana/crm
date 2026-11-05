<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CrmField;
use App\Services\CrmFieldService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    public function __construct(private CrmFieldService $fields) {}

    public function index(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessCrm(), 403);

        return redirect()->route('customers.index', ['excel' => 1]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canAccessCrm(), 403);

        $data = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'status' => ['nullable', 'string', 'max:100'],
        ]);

        $fromDate = ! empty($data['from_date']) ? Carbon::parse($data['from_date'])->startOfDay() : null;
        $toDate = ! empty($data['to_date']) ? Carbon::parse($data['to_date'])->endOfDay() : null;
        $status = trim((string) ($data['status'] ?? ''));

        $customers = Customer::query()
            ->visibleTo($request->user())
            ->inPipeline('crm')
            ->with('assignee')
            ->when($fromDate, fn ($q) => $q->where('created_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('created_at', '<=', $toDate))
            ->when($status !== '', function ($q) use ($status) {
                $q->where(function ($sub) use ($status) {
                    $sub->where('status', $status)
                        ->orWhere('status', str_replace(' ', '-', $status))
                        ->orWhere('status', str_replace('-', ' ', $status));
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $excelFields = $this->fields->excelFields('crm');
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');

        $col = 1;
        foreach ($excelFields as $field) {
            $sheet->setCellValue([$col, 1], $field->label ?: $field->key);
            $col++;
        }
        $sheet->setCellValue([$col, 1], 'Follow-up / Booking Date');
        $col++;
        $sheet->setCellValue([$col, 1], 'Created At');

        $rowNum = 2;
        foreach ($customers as $c) {
            $col = 1;
            foreach ($excelFields as $field) {
                if ($field->key === 'assigned_to') {
                    $val = $c->assignee?->name ?: 'Unassigned';
                } elseif (in_array(strtolower($field->key), ['date', 'entry_date', 'created_date'], true)) {
                    $val = $c->fieldValue($field->key) ?: ($c->created_at ? $c->created_at->format('d M Y') : '');
                } else {
                    $val = $c->fieldValue($field->key);
                    if (is_array($val)) {
                        $val = implode(', ', $val);
                    }
                }
                $sheet->setCellValue([$col, $rowNum], (string) ($val ?? ''));
                $col++;
            }

            $actionDateStr = '';
            if ($c->follow_up && $c->follow_up_at) {
                $actionDateStr = 'Follow-up: ' . $c->follow_up_at->format('d M Y') . ($c->follow_up_has_time ? ' ' . $c->follow_up_at->format('h:i A') : '');
            } elseif ($c->follow_up_at) {
                $slotTime = $c->meta['booking_slot_time'] ?? null;
                $actionDateStr = 'Booked: ' . $c->follow_up_at->format('d M Y') . ($slotTime ? ' (' . $slotTime . ')' : '');
            }
            $sheet->setCellValue([$col, $rowNum], $actionDateStr);
            $col++;

            $sheet->setCellValue([$col, $rowNum], $c->created_at ? $c->created_at->format('d M Y, h:i A') : '');
            $rowNum++;
        }

        $totalCols = $excelFields->count() + 2;
        if ($totalCols > 0) {
            foreach (range(1, $totalCols) as $i) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'crm-customers-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function demo(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canAccessCrm(), 403);

        $excelFields = $this->fields->excelFields();
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');

        $col = 1;
        foreach ($excelFields as $field) {
            $sheet->setCellValue([$col, 1], $field->key);
            $col++;
        }

        // sample row
        $samples = [
            'name' => 'Demo Customer',
            'email' => 'demo@example.com',
            'phone' => '9876543210',
            'company' => 'Demo Co',
            'city' => 'Delhi',
            'source' => 'Excel',
            'status' => 'interested',
            'notes' => 'Sample row — replace with real data',
        ];
        $col = 1;
        foreach ($excelFields as $field) {
            $sheet->setCellValue([$col, 2], $samples[$field->key] ?? '');
            $col++;
        }

        foreach (range(1, $excelFields->count()) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'crm-customers-demo.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessCrm(), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $excelFields = $this->fields->excelFields();
        $keys = $excelFields->pluck('key')->all();

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $imported = 0;
        $skipped = 0;
        $header = null;

        foreach ($rows as $i => $row) {
            $values = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_values($row));

            if ($header === null) {
                $header = array_map(fn ($v) => strtolower(trim((string) $v)), $values);
                continue;
            }

            if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $map = $this->mapRow($header, $values, $excelFields);

            if (empty($map['name'])) {
                $skipped++;
                continue;
            }

            $core = [];
            $meta = [];
            foreach ($keys as $key) {
                $value = $map[$key] ?? null;
                if ($key === 'status') {
                    $value = $this->normalizeStatus($value);
                }
                if (in_array($key, CrmField::CORE_KEYS, true)) {
                    $core[$key] = $value;
                } else {
                    $meta[$key] = $value;
                }
            }

            if (empty($core['source'])) {
                $core['source'] = 'excel';
            }
            $core['assigned_to'] = $request->user()->id;
            $core['pipeline'] = 'crm';
            $core['meta'] = $meta ?: null;

            $customer = Customer::create($core);

            CustomerActivity::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'type' => 'system',
                'title' => 'Imported from Excel',
                'body' => 'Bulk upload.',
            ]);

            $imported++;
        }

        return back()->with('success', "Imported {$imported} customers".($skipped ? ", skipped {$skipped}." : '.'));
    }

    private function mapRow(array $header, array $values, $excelFields): array
    {
        $out = [];
        foreach ($excelFields as $field) {
            $aliases = array_unique(array_filter([
                strtolower($field->key),
                strtolower($field->label),
            ]));
            foreach ($header as $idx => $col) {
                if (in_array($col, $aliases, true)) {
                    $out[$field->key] = $values[$idx] ?? null;
                    break;
                }
            }
        }

        return $out;
    }

    private function normalizeStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));
        $allowed = ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];

        return in_array($status, $allowed, true) ? $status : 'new';
    }
}
