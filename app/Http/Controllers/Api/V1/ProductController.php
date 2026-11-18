<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsPayload;
use App\Http\Controllers\Controller;
use App\Models\CrmField;
use App\Models\Product;
use App\Services\CrmFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use FormatsPayload;

    public function __construct(private CrmFieldService $fields) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $q = trim($request->string('q')->toString());
        $status = trim($request->string('status')->toString());
        $category = trim($request->string('category')->toString());
        $perPage = min(50, max(5, (int) $request->integer('per_page', 20)));

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
        $page = $query->ordered()->paginate($perPage);

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

        $categoryField = CrmField::inPipeline('products')->where('key', 'category')->first();
        $categoryOptions = ! empty($categoryField?->options) ? $categoryField->options : ['GPS Tracker', 'Dashcam', 'Fuel Sensor', 'Speed Governor', 'Accessories', 'Subscription', 'Service'];

        $unitField = CrmField::inPipeline('products')->where('key', 'unit')->first();
        $unitOptions = ! empty($unitField?->options) ? $unitField->options : ['pcs', 'unit', 'box', 'set', 'year', 'month'];

        $statusField = CrmField::inPipeline('products')->where('key', 'status')->first();
        $statusOptions = ! empty($statusField?->options) ? $statusField->options : ['Active', 'Inactive'];

        return response()->json([
            'ok' => true,
            'summary' => [
                'total' => $allCount,
                'active' => $activeCount,
                'inactive' => $inactiveCount,
            ],
            'categories' => $categories,
            'category_options' => $categoryOptions,
            'unit_options' => $unitOptions,
            'status_options' => $statusOptions,
            'form_fields' => $this->fieldsPayload($this->fields->formFields('products')),
            'table_fields' => $this->fieldsPayload($this->fields->tableFields('products')),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
            'products' => collect($page->items())->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'price' => $p->price,
                'formatted_price' => $p->formattedPrice(),
                'category' => $p->category,
                'unit' => $p->unit,
                'status' => $p->status ?: ($p->is_active ? 'Active' : 'Inactive'),
                'is_active' => (bool) $p->is_active,
                'description' => $p->description,
                'meta' => $p->meta ?? (object) [],
                'created_at' => $p->created_at?->format('d M Y'),
            ])->values(),
        ]);
    }

    public function meta(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $categoryField = CrmField::inPipeline('products')->where('key', 'category')->first();
        $categoryOptions = ! empty($categoryField?->options) ? $categoryField->options : ['GPS Tracker', 'Dashcam', 'Fuel Sensor', 'Speed Governor', 'Accessories', 'Subscription', 'Service'];

        $unitField = CrmField::inPipeline('products')->where('key', 'unit')->first();
        $unitOptions = ! empty($unitField?->options) ? $unitField->options : ['pcs', 'unit', 'box', 'set', 'year', 'month'];

        $statusField = CrmField::inPipeline('products')->where('key', 'status')->first();
        $statusOptions = ! empty($statusField?->options) ? $statusField->options : ['Active', 'Inactive'];

        return response()->json([
            'ok' => true,
            'form_fields' => $this->fieldsPayload($this->fields->formFields('products')),
            'category_options' => $categoryOptions,
            'unit_options' => $unitOptions,
            'status_options' => $statusOptions,
        ]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        return response()->json([
            'ok' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $product->price,
                'formatted_price' => $product->formattedPrice(),
                'category' => $product->category,
                'unit' => $product->unit,
                'status' => $product->status ?: ($product->is_active ? 'Active' : 'Inactive'),
                'is_active' => (bool) $product->is_active,
                'description' => $product->description,
                'meta' => $product->meta ?? (object) [],
                'created_at' => $product->created_at?->format('d M Y'),
            ],
            'form_fields' => $this->fieldsPayload($this->fields->formFields('products')),
        ]);
    }

    public function toggleActive(Request $request, Product $product): JsonResponse
    {
        abort_unless($request->user()->canAccessProducts(), 403);

        $newActive = ! $product->is_active;
        $newStatus = $newActive ? 'Active' : 'Inactive';
        $product->update([
            'is_active' => $newActive,
            'status' => $newStatus,
        ]);

        return response()->json([
            'ok' => true,
            'message' => "Product marked as {$newStatus}.",
            'is_active' => $newActive,
            'status' => $newStatus,
        ]);
    }
}
