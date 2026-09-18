<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivationRequest;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $stockRows = InventoryItem::query()
            ->with('type:id,name')
            ->where('held_by_user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        $byType = InventoryType::active()->ordered()->get()->map(function ($type) use ($stockRows) {
            $items = $stockRows->where('inventory_type_id', $type->id);
            $inStock = (int) $items->where('status', 'in_stock')->sum('quantity');
            $assigned = (int) $items->where('status', 'assigned')->sum('quantity');
            $damaged = (int) $items->where('status', 'damaged')->sum('quantity');
            $repair = (int) $items->where('status', 'repair')->sum('quantity');
            $total = (int) $items->sum('quantity');

            return [
                'id' => $type->id,
                'name' => $type->name,
                'total' => $total,
                'in_stock' => $inStock,
                'assigned' => $assigned,
                'damaged' => $damaged,
                'repair' => $repair,
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();

        $items = $stockRows->map(fn ($item) => [
            'id' => $item->id,
            'type' => $item->type?->name,
            'name' => $item->name,
            'code' => $item->code,
            'quantity' => $item->quantity,
            'status' => $item->status,
            'status_label' => $item->statusLabel(),
        ])->values();

        $crm = null;
        if ($user->appAccessCrm()) {
            $base = Customer::query()->visibleTo($user)->inPipeline('crm');
            $crm = [
                'total' => (clone $base)->count(),
                'today' => (clone $base)->whereDate('created_at', today())->count(),
                'follow_up' => (clone $base)->whereNotNull('follow_up_at')
                    ->whereDate('follow_up_at', '<=', today())
                    ->count(),
            ];
        }

        return response()->json([
            'show_crm' => $user->appAccessCrm(),
            'show_stock' => true,
            'stock_summary' => [
                'total' => (int) $stockRows->sum('quantity'),
                'in_stock' => (int) $stockRows->where('status', 'in_stock')->sum('quantity'),
                'assigned' => (int) $stockRows->where('status', 'assigned')->sum('quantity'),
                'damaged' => (int) $stockRows->where('status', 'damaged')->sum('quantity'),
                'repair' => (int) $stockRows->where('status', 'repair')->sum('quantity'),
            ],
            'stock_by_type' => $byType,
            'stock_items' => $items,
            'crm' => $crm,
        ]);
    }

    public function crm(Request $request): JsonResponse
    {
        abort_unless($request->user()->appAccessCrm(), 403);

        return $this->customerListing($request, 'crm');
    }

    public function operations(Request $request): JsonResponse
    {
        abort_unless($request->user()->appAccessOperations(), 403);

        return $this->customerListing($request, 'operations');
    }

    private function customerListing(Request $request, string $pipeline): JsonResponse
    {
        $q = trim((string) $request->string('q'));
        $status = trim((string) $request->string('status'));
        $filter = trim((string) $request->string('filter'));

        $base = Customer::query()
            ->visibleTo($request->user())
            ->inPipeline($pipeline);

        $summary = [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'follow_up' => (clone $base)->where('follow_up', true)
                ->whereNotNull('follow_up_at')
                ->whereDate('follow_up_at', '<=', today())
                ->count(),
            'new' => (clone $base)->whereRaw('LOWER(status) = ?', ['new'])->count(),
        ];

        $rowsQuery = (clone $base)
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            }))
            ->when($filter === 'follow_up_today', function ($query) {
                $query->where('follow_up', true)
                    ->whereDate('follow_up_at', today())
                    ->orderByRaw('CASE WHEN follow_up_has_time = 1 THEN 0 ELSE 1 END')
                    ->orderBy('follow_up_at');
            })
            ->when($filter !== 'follow_up_today' && $status !== '', fn ($query) => $query->where('status', $status))
            ->when($filter !== 'follow_up_today', fn ($query) => $query->latest());

        $rows = $rowsQuery
            ->limit(100)
            ->get(['id', 'name', 'phone', 'company', 'city', 'status', 'follow_up', 'follow_up_at']);

        return response()->json([
            'summary' => $summary,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'filter' => $filter,
                'statuses' => ['new', 'interested', 'contacted', 'converted', 'lost', 'request'],
            ],
            'customers' => $rows->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'company' => $c->company,
                'city' => $c->city,
                'status' => $c->status,
                'follow_up' => $c->followUpScheduleLabel(),
            ]),
        ]);
    }

    public function inventory(Request $request): JsonResponse
    {
        abort_unless($request->user()->appAccessInventory(), 403);

        $user = $request->user();
        $q = trim((string) $request->string('q'));
        $status = trim((string) $request->string('status'));
        $types = InventoryType::active()->ordered()->get(['id', 'name']);
        $typeId = (int) $request->integer('type');
        if (! $typeId && $types->isNotEmpty()) {
            $typeId = (int) $types->first()->id;
        }

        $mine = InventoryItem::query()
            ->where('held_by_user_id', $user->id);

        $rawTotals = (clone $mine)
            ->selectRaw('inventory_type_id, status, COALESCE(SUM(quantity), 0) as total')
            ->groupBy('inventory_type_id', 'status')
            ->get();

        $typeTabs = $types->map(function ($type) use ($rawTotals) {
            $rows = $rawTotals->where('inventory_type_id', $type->id);

            return [
                'id' => $type->id,
                'name' => $type->name,
                'total' => (int) $rows->sum('total'),
                'in_stock' => (int) ($rows->firstWhere('status', 'in_stock')?->total ?? 0),
            ];
        })->filter(fn ($row) => $row['total'] > 0)->values();

        if ($typeTabs->isNotEmpty() && ! $typeTabs->contains(fn ($t) => (int) $t['id'] === $typeId)) {
            $typeId = (int) $typeTabs->first()['id'];
        }

        $selected = $typeTabs->firstWhere('id', $typeId);
        $items = (clone $mine)
            ->with(['type:id,name', 'heldBy:id,name'])
            ->when($typeId > 0, fn ($query) => $query->where('inventory_type_id', $typeId))
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->limit(200)
            ->get();

        return response()->json([
            'type_id' => $typeId ?: null,
            'types' => $typeTabs,
            'summary' => [
                'total' => (int) ($selected['total'] ?? 0),
                'in_stock' => (int) ($selected['in_stock'] ?? 0),
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'statuses' => array_keys(InventoryItem::STATUSES),
            ],
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type?->name,
                'type_id' => $item->inventory_type_id,
                'name' => $item->name,
                'code' => $item->code,
                'quantity' => $item->quantity,
                'status' => $item->status,
                'status_label' => $item->statusLabel(),
                'held_by' => $item->heldBy?->name,
            ])->values(),
        ]);
    }

    public function inventoryTypes(Request $request): JsonResponse
    {
        abort_unless($request->user()->appAccessOperations(), 403);

        $myItems = InventoryItem::query()
            ->with('type:id,name')
            ->where('held_by_user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'inventory_type_id', 'name', 'code', 'status', 'quantity']);

        $myStock = $myItems->groupBy('inventory_type_id')->map(function ($items, $typeId) {
            $type = $items->first()?->type;

            return [
                'id' => (int) $typeId,
                'name' => $type?->name ?? 'Type #'.$typeId,
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => trim($item->name.($item->code ? ' · '.$item->code : '')),
                    'name' => $item->name,
                    'code' => $item->code,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'types' => InventoryType::active()->ordered()->get(['id', 'name']),
            'my_stock' => $myStock,
        ]);
    }

    public function startActivation(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->appAccessOperations(), 403);
        abort_unless(($customer->pipeline ?? '') === 'operations', 404);
        abort_unless($customer->accessibleBy($request->user()), 403);

        $row = ActivationRequest::create([
            'customer_id' => $customer->id,
            'inventory_type_id' => null,
            'inventory_item_id' => null,
            'requested_by' => $request->user()->id,
            'status' => 'draft',
            'notes' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Activation request started.',
            'id' => $row->id,
        ]);
    }

    public function storeActivation(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->appAccessOperations(), 403);
        abort_unless(($customer->pipeline ?? '') === 'operations', 404);
        abort_unless($customer->accessibleBy($request->user()), 403);

        $data = $request->validate([
            'activation_request_id' => ['nullable', 'integer', 'exists:activation_requests,id'],
            'inventory_item_ids' => ['required', 'array', 'min:1'],
            'inventory_item_ids.*' => ['integer', 'exists:inventory_items,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $itemIds = collect($data['inventory_item_ids'])->unique()->values();
        $items = InventoryItem::query()
            ->with('type:id,name')
            ->whereIn('id', $itemIds)
            ->where('held_by_user_id', $request->user()->id)
            ->get();

        if ($items->count() !== $itemIds->count()) {
            return response()->json([
                'ok' => false,
                'message' => 'Selected item is not in your stock.',
            ], 422);
        }

        $draft = null;
        if (! empty($data['activation_request_id'])) {
            $draft = ActivationRequest::query()
                ->whereKey($data['activation_request_id'])
                ->where('customer_id', $customer->id)
                ->where('status', 'draft')
                ->first();

            if (! $draft) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Activation card not found or already submitted.',
                ], 422);
            }
        }

        $first = $items->first();
        $labels = [];

        $requestRow = \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $request, $data, $items, $first, $draft, &$labels) {
            if ($draft) {
                $draft->items()->delete();
                $row = $draft;
                $row->update([
                    'inventory_type_id' => $first->inventory_type_id,
                    'inventory_item_id' => $first->id,
                    'status' => 'pending',
                    'notes' => $data['notes'] ?? null,
                ]);
            } else {
                $row = ActivationRequest::create([
                    'customer_id' => $customer->id,
                    'inventory_type_id' => $first->inventory_type_id,
                    'inventory_item_id' => $first->id,
                    'requested_by' => $request->user()->id,
                    'status' => 'pending',
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            foreach ($items as $item) {
                $row->items()->create([
                    'inventory_item_id' => $item->id,
                    'inventory_type_id' => $item->inventory_type_id,
                ]);
                $label = trim($item->name.($item->code ? ' · '.$item->code : ''));
                $labels[] = ($item->type?->name ? $item->type->name.': ' : '').$label;
            }

            return $row;
        });

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => 'system',
            'title' => 'Activation request',
            'body' => implode("\n", $labels)
                .(! empty($data['notes']) ? "\n".$data['notes'] : ''),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Activation request submitted.',
            'id' => $requestRow->id,
        ]);
    }

    public function inventoryHistory(Request $request, InventoryItem $inventory): JsonResponse
    {
        abort_unless($request->user()->appAccessInventory(), 403);
        abort_unless((int) $inventory->held_by_user_id === (int) $request->user()->id, 403);

        $rows = $inventory->histories()->with('user:id,name')->get()->map(fn ($h) => [
            'id' => $h->id,
            'action' => $h->action,
            'title' => $h->title,
            'body' => $h->body,
            'changes' => $h->changes ?? [],
            'user' => $h->user?->name ?: 'System',
            'at' => $h->created_at?->format('d M Y · h:i A'),
            'ago' => $h->created_at?->diffForHumans(),
        ]);

        return response()->json([
            'item' => $inventory->name,
            'code' => $inventory->code,
            'status_label' => $inventory->statusLabel(),
            'quantity' => $inventory->quantity,
            'histories' => $rows,
        ]);
    }
}
