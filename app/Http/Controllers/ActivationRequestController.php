<?php

namespace App\Http\Controllers;

use App\Models\ActivationRequest;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivationRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canSeeAllRecords(), 403);

        $status = trim((string) $request->string('status'));
        $typeId = $request->integer('type') ?: null;
        $q = trim((string) $request->string('q'));

        $rows = ActivationRequest::query()
            ->with([
                'customer:id,name,phone,company,city,pipeline,source_customer_id',
                'items.inventoryType:id,name',
                'items.inventoryItem:id,name,code,inventory_type_id',
                'inventoryType:id,name',
                'inventoryItem:id,name,code,inventory_type_id',
                'requester:id,name',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($status === '', fn ($query) => $query->where('status', '!=', 'draft'))
            ->when($typeId, function ($query) use ($typeId) {
                $query->where(function ($outer) use ($typeId) {
                    $outer->where('inventory_type_id', $typeId)
                        ->orWhereHas('items', fn ($inner) => $inner->where('inventory_type_id', $typeId));
                });
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($outer) use ($q) {
                    $outer->whereHas('customer', function ($inner) use ($q) {
                        $inner->where('name', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%")
                            ->orWhere('company', 'like', "%{$q}%");
                    })->orWhereHas('items.inventoryItem', function ($inner) use ($q) {
                        $inner->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    });
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $types = InventoryType::active()->ordered()->get(['id', 'name']);
        $statuses = collect(ActivationRequest::STATUSES)->except('draft')->all();

        return view('activation.index', [
            'rows' => $rows,
            'types' => $types,
            'status' => $status,
            'typeId' => $typeId,
            'q' => $q,
            'statuses' => $statuses,
        ]);
    }

    public function start(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->canAccessOperations(), 403);
        abort_unless($customer->isOperations(), 404);
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

    public function store(Request $request, Customer $customer): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->canAccessOperations(), 403);
        abort_unless($customer->isOperations(), 404);
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

        $requestRow = DB::transaction(function () use ($customer, $request, $data, $items, $first, $draft, &$labels) {
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

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Activation request submitted.',
                'id' => $requestRow->id,
            ]);
        }

        return back()->with('success', 'Activation request submitted.');
    }

    public function update(Request $request, ActivationRequest $activation): RedirectResponse
    {
        abort_unless($request->user()->canSeeAllRecords(), 403);
        abort_if($activation->status === 'draft', 404);

        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,fulfilled'],
        ]);

        $becomingApproved = $data['status'] === 'approved' && $activation->status !== 'approved';

        if ($becomingApproved && $activation->items()->count() === 0 && ! $activation->inventory_item_id) {
            return back()->withErrors(['status' => 'Cannot approve — no devices selected.']);
        }

        DB::transaction(function () use ($activation, $data, $becomingApproved, $request) {
            $activation->update(['status' => $data['status']]);

            if ($becomingApproved) {
                $this->assignDevicesToCustomer($activation, $request);
            }
        });

        return back()->with(
            'success',
            $becomingApproved
                ? 'Activation approved — devices assigned to customer.'
                : 'Activation status updated.'
        );
    }

    private function assignDevicesToCustomer(ActivationRequest $activation, Request $request): void
    {
        $activation->load([
            'customer:id,name',
            'items.inventoryItem',
            'inventoryItem',
        ]);

        $customerName = trim((string) ($activation->customer?->name ?? ''));
        if ($customerName === '') {
            return;
        }

        $items = $activation->items
            ->map(fn ($row) => $row->inventoryItem)
            ->filter()
            ->values();

        if ($items->isEmpty() && $activation->inventoryItem) {
            $items = collect([$activation->inventoryItem]);
        }

        $assignedLabels = [];

        foreach ($items as $item) {
            $track = ['status', 'held_by_user_id', 'assigned_to_name'];
            $before = $item->only($track);

            $item->update([
                'status' => 'assigned',
                'held_by_user_id' => null,
                'assigned_to_name' => $customerName,
            ]);

            $changes = $item->diffChanges($before, $item->only($track));
            if ($changes) {
                $item->recordHistory(
                    'updated',
                    'Assigned via activation approve',
                    implode("\n", collect($changes)->map(
                        fn ($c) => $c['label'].': '.$c['from'].' → '.$c['to']
                    )->all()),
                    $changes
                );
            }

            $assignedLabels[] = trim($item->name.($item->code ? ' · '.$item->code : ''));
        }

        if ($assignedLabels) {
            CustomerActivity::create([
                'customer_id' => $activation->customer_id,
                'user_id' => $request->user()->id,
                'type' => 'system',
                'title' => 'Devices assigned',
                'body' => 'Assigned to '.$customerName.":\n".implode("\n", $assignedLabels),
            ]);
        }
    }
}
