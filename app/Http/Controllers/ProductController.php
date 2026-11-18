<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\Product;
use App\Services\CrmFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(private CrmFieldService $fields) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $q = trim($request->string('q')->toString());
        $status = trim($request->string('status')->toString());
        $category = trim($request->string('category')->toString());

        $query = Product::query()
            ->when($q !== '', function ($inner) use ($q) {
                $inner->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('meta', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', function ($inner) use ($status) {
                if (strtolower($status) === 'active') {
                    $inner->where('is_active', true)->where(function ($s) {
                        $s->whereNull('status')->orWhereRaw('LOWER(status) != ?', ['inactive']);
                    });
                } elseif (strtolower($status) === 'inactive') {
                    $inner->where(function ($s) {
                        $s->where('is_active', false)->orWhereRaw('LOWER(status) = ?', ['inactive']);
                    });
                } else {
                    $inner->where('status', $status);
                }
            })
            ->when($category !== '', fn ($inner) => $inner->where('category', $category));

        $totalCount = (clone $query)->count();
        $products = $query->ordered()->paginate(20)->withQueryString();

        $allCount = Product::count();
        $activeCount = Product::active()->count();
        $inactiveCount = $allCount - $activeCount;

        $categories = Product::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();

        $formFields = $this->fields->formFields('products');
        $tableFields = $this->fields->tableFields('products');
        $excelFields = $this->fields->excelFields('products');

        $categoryField = CrmField::inPipeline('products')->where('key', 'category')->first();
        $categoryOptions = ! empty($categoryField?->options) ? $categoryField->options : ['GPS Tracker', 'Dashcam', 'Fuel Sensor', 'Speed Governor', 'Accessories', 'Subscription', 'Service'];

        $unitField = CrmField::inPipeline('products')->where('key', 'unit')->first();
        $unitOptions = ! empty($unitField?->options) ? $unitField->options : ['pcs', 'unit', 'box', 'set', 'year', 'month'];

        $statusField = CrmField::inPipeline('products')->where('key', 'status')->first();
        $statusOptions = ! empty($statusField?->options) ? $statusField->options : ['Active', 'Inactive'];

        return view('products.index', compact(
            'products',
            'q',
            'status',
            'category',
            'allCount',
            'activeCount',
            'inactiveCount',
            'categories',
            'categoryOptions',
            'unitOptions',
            'statusOptions',
            'formFields',
            'tableFields',
            'excelFields'
        ));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $fields = $this->fields->formFields('products');
        $rules = $this->fields->validationRules($fields);
        if (! isset($rules['name'])) {
            $rules['name'] = ['required', 'string', 'max:120'];
        }
        $request->validate($rules);

        [$core, $meta] = $this->splitProductPayload($request, $fields);
        $core['name'] = trim((string) ($core['name'] ?? $request->input('name')));
        $core['is_active'] = strtolower((string) ($core['status'] ?? 'active')) !== 'inactive';
        $core['created_by'] = $request->user()->id;

        $product = Product::create([
            ...$core,
            'meta' => $meta ?: null,
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'message' => 'Product added successfully.',
                'product' => $product,
            ], 201);
        }

        return back()->with('success', 'Product added successfully.');
    }

    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        // Quick status toggle
        if ($request->has('quick_toggle_status')) {
            $newStatus = $product->is_active ? 'Inactive' : 'Active';
            $product->update([
                'status' => $newStatus,
                'is_active' => $newStatus === 'Active',
            ]);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'ok' => true,
                    'message' => "Product status changed to {$newStatus}.",
                    'product' => $product,
                ]);
            }

            return back()->with('success', "Product status changed to {$newStatus}.");
        }

        $fields = $this->fields->formFields('products');
        $rules = $this->fields->validationRules($fields);
        if (! isset($rules['name'])) {
            $rules['name'] = ['required', 'string', 'max:120'];
        }
        $request->validate($rules);

        [$core, $meta] = $this->splitProductPayload($request, $fields);
        if (empty($core['name'])) {
            $core['name'] = $product->name;
        }

        if (isset($core['status'])) {
            $core['is_active'] = strtolower((string) $core['status']) !== 'inactive';
        }

        $mergedMeta = array_merge($product->meta ?? [], $meta);
        foreach ($meta as $k => $v) {
            if ($v === null || $v === '') {
                unset($mergedMeta[$k]);
            }
        }

        $product->update([
            ...$core,
            'meta' => $mergedMeta ?: null,
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'message' => 'Product updated successfully.',
                'product' => $product,
            ]);
        }

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $product->delete();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'message' => 'Product deleted.',
            ]);
        }

        return back()->with('success', 'Product deleted.');
    }

    public function bulkAction(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
            'action_type' => ['required', 'in:active,inactive,delete'],
        ]);

        $ids = $data['ids'];
        $action = $data['action_type'];

        if ($action === 'delete') {
            Product::whereIn('id', $ids)->delete();
            $msg = count($ids) . ' products deleted.';
            return ($request->wantsJson() || $request->is('api/*'))
                ? response()->json(['ok' => true, 'message' => $msg])
                : back()->with('success', $msg);
        }

        if ($action === 'active') {
            Product::whereIn('id', $ids)->update(['status' => 'Active', 'is_active' => true]);
            $msg = count($ids) . ' products marked Active.';
            return ($request->wantsJson() || $request->is('api/*'))
                ? response()->json(['ok' => true, 'message' => $msg])
                : back()->with('success', $msg);
        }

        if ($action === 'inactive') {
            Product::whereIn('id', $ids)->update(['status' => 'Inactive', 'is_active' => false]);
            $msg = count($ids) . ' products marked Inactive.';
            return ($request->wantsJson() || $request->is('api/*'))
                ? response()->json(['ok' => true, 'message' => $msg])
                : back()->with('success', $msg);
        }

        return ($request->wantsJson() || $request->is('api/*'))
            ? response()->json(['ok' => true])
            : back();
    }

    public function listJson(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAccessProducts() || $request->user()->canAccessCallCrm() || $request->user()->canAccessCrm(), 403);

        $products = Product::active()->ordered()->get(['id', 'name', 'code', 'price', 'category', 'unit', 'description']);

        return response()->json([
            'ok' => true,
            'products' => $products,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $excelFields = $this->fields->excelFields('products');
        $headers = $excelFields->pluck('label')->all();

        $rows = Product::query()->ordered()->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');

        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            $col++;
        }

        $rowNum = 2;
        foreach ($rows as $item) {
            $col = 1;
            foreach ($excelFields as $f) {
                $val = $item->fieldValue($f->key);
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                $sheet->setCellValueByColumnAndRow($col, $rowNum, $val ?? '');
                $col++;
            }
            $rowNum++;
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, 'products_'.now()->format('Y_m_d_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadDemo(): StreamedResponse
    {
        abort_unless(auth()->user()->canAccessProducts(), 403);

        $excelFields = $this->fields->excelFields('products');
        $headers = $excelFields->pluck('label')->all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products Sample');

        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            $col++;
        }

        $samples = [
            [
                'name' => 'AIS 140 GPS Tracker',
                'code' => 'GPS-AIS-140',
                'price' => '4500.00',
                'category' => 'GPS Tracker',
                'unit' => 'unit',
                'status' => 'Active',
                'description' => 'Government approved AIS 140 certified GPS device with SOS panic button.',
            ],
            [
                'name' => 'Dual Lens Dashcam 4K',
                'code' => 'CAM-D4K',
                'price' => '6800.00',
                'category' => 'Dashcam',
                'unit' => 'unit',
                'status' => 'Active',
                'description' => 'Front and cabin dual camera with night vision and GPS logging.',
            ],
            [
                'name' => 'Ultrasonic Fuel Level Sensor',
                'code' => 'FUEL-US-01',
                'price' => '8200.00',
                'category' => 'Fuel Sensor',
                'unit' => 'unit',
                'status' => 'Active',
                'description' => 'Non-invasive external fuel sensor with 99% accuracy.',
            ],
        ];

        $rowNum = 2;
        foreach ($samples as $sample) {
            $col = 1;
            foreach ($excelFields as $f) {
                $val = $sample[$f->key] ?? '';
                $sheet->setCellValueByColumnAndRow($col, $rowNum, $val);
                $col++;
            }
            $rowNum++;
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, 'products_demo_format.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $file = $request->file('excel_file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors(['excel_file' => 'The uploaded file has no data rows.']);
        }

        $headerRow = array_shift($rows);
        $excelFields = $this->fields->excelFields('products');

        $colToField = [];
        foreach ($headerRow as $colLetter => $headerText) {
            $norm = strtolower(trim((string) $headerText));
            foreach ($excelFields as $f) {
                if (strtolower(trim($f->label)) === $norm || strtolower(trim($f->key)) === $norm) {
                    $colToField[$colLetter] = $f;
                    break;
                }
            }
        }

        if (empty($colToField)) {
            return back()->withErrors(['excel_file' => 'Could not match any columns with configured Product fields.']);
        }

        $created = 0;
        $updated = 0;

        foreach ($rows as $r) {
            $rowCore = [];
            $rowMeta = [];

            foreach ($colToField as $colLetter => $f) {
                $rawVal = trim((string) ($r[$colLetter] ?? ''));
                if (in_array($f->key, CrmField::PRODUCT_CORE_KEYS, true)) {
                    $rowCore[$f->key] = $rawVal !== '' ? $rawVal : null;
                } else {
                    if ($rawVal !== '') {
                        $rowMeta[$f->key] = $rawVal;
                    }
                }
            }

            if (empty($rowCore['name'])) {
                continue;
            }

            $code = $rowCore['code'] ?? null;
            $existing = null;
            if ($code) {
                $existing = Product::where('code', $code)->first();
            }
            if (! $existing) {
                $existing = Product::where('name', $rowCore['name'])->first();
            }

            $isActive = strtolower((string) ($rowCore['status'] ?? 'active')) !== 'inactive';

            if ($existing) {
                $mergedMeta = array_merge($existing->meta ?? [], $rowMeta);
                $existing->update([
                    ...$rowCore,
                    'is_active' => $isActive,
                    'meta' => $mergedMeta ?: null,
                ]);
                $updated++;
            } else {
                Product::create([
                    ...$rowCore,
                    'is_active' => $isActive,
                    'created_by' => $request->user()->id,
                    'meta' => $rowMeta ?: null,
                ]);
                $created++;
            }
        }

        return back()->with('success', "Import complete: {$created} products created, {$updated} products updated.");
    }

    private function splitProductPayload(Request $request, Collection $formFields): array
    {
        $core = [];
        $meta = [];

        foreach ($formFields as $f) {
            $val = $request->input($f->key);
            if (is_string($val)) {
                $val = trim($val);
            }
            if ($f->type === 'number') {
                $val = ($val !== '' && $val !== null) ? (float) $val : 0;
            }

            if (in_array($f->key, CrmField::PRODUCT_CORE_KEYS, true)) {
                $core[$f->key] = $val !== '' ? $val : null;
            } else {
                if ($val !== '' && $val !== null) {
                    $meta[$f->key] = $val;
                }
            }
        }

        return [$core, $meta];
    }
}
