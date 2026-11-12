<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CrmField;
use App\Models\Pincode;
use App\Services\CrmFieldService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $pipeline = $request->input('pipeline') === 'call_crm' ? 'call_crm' : 'crm';
        abort_unless(
            $pipeline === 'call_crm' ? $request->user()->canAccessCallCrm() : $request->user()->canAccessCrm(),
            403
        );

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
            ->inPipeline($pipeline)
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

        $excelFields = $this->fields->excelFields($pipeline);
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
        $prefix = $pipeline === 'call_crm' ? 'call-crm-customers-' : 'crm-customers-';
        $fileName = $prefix . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function demo(Request $request): StreamedResponse
    {
        $pipeline = $request->input('pipeline') === 'call_crm' ? 'call_crm' : 'crm';
        abort_unless(
            $pipeline === 'call_crm' ? $request->user()->canAccessCallCrm() : $request->user()->canAccessCrm(),
            403
        );

        $excelFields = $this->fields->excelFields($pipeline);
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
            'pincode' => '751001',
            'area' => 'Saheed Nagar',
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
        $demoName = $pipeline === 'call_crm' ? 'call-crm-customers-demo.xlsx' : 'crm-customers-demo.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $demoName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pipeline = $request->input('pipeline') === 'call_crm' ? 'call_crm' : 'crm';
        abort_unless(
            $pipeline === 'call_crm' ? $request->user()->canAccessCallCrm() : $request->user()->canAccessCrm(),
            403
        );

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $excelFields = $this->fields->excelFields($pipeline);
        $keys = $excelFields->pluck('key')->all();

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $imported = 0;
        $skipped = 0;
        $duplicatePhoneSkipped = 0;
        $header = null;

        $existingPhones = [];
        $pincodeAreaMap = [];
        $pincodeCityMap = [];
        if ($pipeline === 'call_crm') {
            $allPincodes = Pincode::whereNotNull('area')->where('area', '!=', '')->get(['pincode', 'area', 'city']);
            foreach ($allPincodes as $p) {
                $cleanP = preg_replace('/[^0-9]/', '', explode('.', (string) $p->pincode)[0]);
                if ($cleanP !== '') {
                    $pincodeAreaMap[$cleanP] = trim((string) $p->area);
                    if (! empty($p->city)) {
                        $pincodeCityMap[$cleanP] = trim((string) $p->city);
                    }
                }
            }

            $existingCustomers = Customer::where('pipeline', 'call_crm')
                ->select(['id', 'phone', 'meta'])
                ->get();

            foreach ($existingCustomers as $ec) {
                $canon = Customer::normalizePhoneTo10Digits($ec->phone);
                if ($canon !== null && strlen($canon) >= 7) {
                    $existingPhones[$canon] = true;
                }
            }
        }

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

            // In CALL CRM, if a phone number is detected in existing records or in this upload batch, do not add it
            if ($pipeline === 'call_crm') {
                $rawPhone = trim((string) ($core['phone'] ?? $map['phone'] ?? $map['phone_no'] ?? $map['mobile'] ?? $map['contact'] ?? $meta['phone'] ?? $meta['phone_no'] ?? $meta['mobile'] ?? ''));

                if ($rawPhone !== '') {
                    $canonical = Customer::normalizePhoneTo10Digits($rawPhone);
                    if ($canonical !== null && strlen($canonical) >= 7) {
                        if (isset($existingPhones[$canonical])) {
                            $skipped++;
                            $duplicatePhoneSkipped++;
                            continue;
                        }

                        $existingPhones[$canonical] = true;
                    }
                }

                // Auto-fill area according to PIN (from Pincode Manager, database, or Postal API)
                $pinVal = trim((string) ($meta['pincode'] ?? $core['pincode'] ?? $map['pincode'] ?? $map['pin'] ?? ''));
                $cleanPin = preg_replace('/[^0-9]/', '', explode('.', $pinVal)[0]);
                if ($cleanPin === '') {
                    $addr = (string) ($core['address'] ?? $meta['address'] ?? $map['address'] ?? '');
                    if (preg_match('/\b(7\d{5}|[1-9]\d{5})\b/', $addr, $m)) {
                        $cleanPin = $m[1];
                    }
                }

                if ($cleanPin !== '') {
                    $meta['pincode'] = $cleanPin;
                    $matchedArea = $pincodeAreaMap[$cleanPin] ?? null;

                    if (! $matchedArea) {
                        $pRow = Pincode::where('pincode', $cleanPin)->whereNotNull('area')->where('area', '!=', '')->first();
                        if ($pRow) {
                            $matchedArea = $pRow->area;
                            $pincodeAreaMap[$cleanPin] = $matchedArea;
                        }
                    }

                    if (! $matchedArea && strlen($cleanPin) === 6) {
                        try {
                            $res = Http::timeout(2)->get("https://api.postalpincode.in/pincode/{$cleanPin}");
                            if ($res->successful()) {
                                $data = $res->json();
                                $offices = $data[0]['PostOffice'] ?? [];
                                if (! empty($offices[0]['Name'])) {
                                    $matchedArea = $offices[0]['Name'];
                                    $matchedCity = $offices[0]['District'] ?? null;
                                    $pincodeAreaMap[$cleanPin] = $matchedArea;

                                    Pincode::firstOrCreate(
                                        ['pincode' => $cleanPin],
                                        [
                                            'area' => $matchedArea,
                                            'city' => $matchedCity,
                                            'status' => 'Serviceable',
                                            'created_by' => $request->user()->id,
                                        ]
                                    );
                                }
                            }
                        } catch (\Throwable $e) {}
                    }

                    $currentArea = trim((string) ($meta['area'] ?? $core['area'] ?? $map['area'] ?? ''));
                    if ($matchedArea && ($currentArea === '' || empty($meta['area']))) {
                        $meta['area'] = $matchedArea;
                    }
                }
            }

            if (empty($core['source'])) {
                $core['source'] = 'excel';
            }
            $core['assigned_to'] = $pipeline === 'call_crm' ? null : $request->user()->id;
            $core['pipeline'] = $pipeline;
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

        $msg = "Imported {$imported} customers.";
        if ($duplicatePhoneSkipped > 0 || $skipped > 0) {
            $details = [];
            if ($duplicatePhoneSkipped > 0) {
                $details[] = "skipped {$duplicatePhoneSkipped} duplicate phone number(s)";
            }
            $otherSkipped = $skipped - $duplicatePhoneSkipped;
            if ($otherSkipped > 0) {
                $details[] = "skipped {$otherSkipped} empty/invalid record(s)";
            }
            $msg = "Imported {$imported} customers (" . implode(', ', $details) . ").";
        }

        return back()->with('success', $msg);
    }

    private function fieldAliases(string $key, string $label): array
    {
        $aliases = [
            strtolower($key),
            strtolower($label),
            str_replace('_', ' ', strtolower($key)),
            str_replace(' ', '_', strtolower($label)),
            str_replace([' ', '_', '-'], '', strtolower($key)),
            str_replace([' ', '_', '-'], '', strtolower($label)),
        ];

        if (in_array($key, ['pincode', 'pin'], true)) {
            $aliases = array_merge($aliases, [
                'pin', 'pincode', 'pin code', 'pin_code', 'pincode no', 'pin no', 'postal', 'postal code', 'postal_code', 'zip', 'zip code', 'zipcode'
            ]);
        }
        if (in_array($key, ['area', 'ariya'], true)) {
            $aliases = array_merge($aliases, [
                'area', 'ariya', 'locality', 'location', 'area name', 'area_name', 'suburb', 'colony'
            ]);
        }
        if (in_array($key, ['phone', 'phone_no', 'mobile', 'contact'], true)) {
            $aliases = array_merge($aliases, [
                'phone', 'phone no', 'phone_no', 'mobile', 'mobile no', 'mobile_no', 'contact', 'contact no', 'contact_no', 'ph', 'ph no'
            ]);
        }

        return array_unique(array_filter($aliases));
    }

    private function mapRow(array $header, array $values, $excelFields): array
    {
        $out = [];
        foreach ($excelFields as $field) {
            $aliases = $this->fieldAliases($field->key, $field->label);
            foreach ($header as $idx => $col) {
                if (in_array($col, $aliases, true)) {
                    $out[$field->key] = $values[$idx] ?? null;
                    break;
                }
            }
        }

        // Direct common fallbacks if not matched by fields loop
        foreach ($header as $idx => $col) {
            $norm = strtolower(trim((string) $col));
            if (in_array($norm, ['pin', 'pincode', 'pin code', 'pin_code', 'zip', 'zip code'], true) && empty($out['pincode'])) {
                $out['pincode'] = $values[$idx] ?? null;
            }
            if (in_array($norm, ['area', 'ariya', 'locality', 'location'], true) && empty($out['area'])) {
                $out['area'] = $values[$idx] ?? null;
            }
            if (in_array($norm, ['phone', 'phone no', 'phone_no', 'mobile', 'mobile no', 'mobile_no', 'contact'], true) && empty($out['phone'])) {
                $out['phone'] = $values[$idx] ?? null;
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
