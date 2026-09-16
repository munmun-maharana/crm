<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CrmField;
use App\Services\CrmFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    public function __construct(private CrmFieldService $fields) {}

    public function index(): View
    {
        $excelFields = $this->fields->excelFields();

        return view('upload.index', compact('excelFields'));
    }

    public function demo(): StreamedResponse
    {
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
