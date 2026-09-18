<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccessInventory(), 403);

        $types = InventoryType::active()->ordered()->get();
        $typeId = $request->integer('type');
        if (! $typeId && $types->isNotEmpty()) {
            $typeId = (int) $types->first()->id;
        }

        $items = InventoryItem::with(['type', 'heldBy'])
            ->when($typeId, fn ($q) => $q->where('inventory_type_id', $typeId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statusKeys = array_keys(InventoryItem::STATUSES);

        $rawTotals = InventoryItem::query()
            ->selectRaw('inventory_type_id, status, COALESCE(SUM(quantity), 0) as total')
            ->groupBy('inventory_type_id', 'status')
            ->get();

        $statusTotals = $types->mapWithKeys(function ($type) use ($rawTotals, $statusKeys) {
            $rows = $rawTotals->where('inventory_type_id', $type->id);
            $counts = [];
            foreach ($statusKeys as $key) {
                $counts[$key] = (int) ($rows->firstWhere('status', $key)?->total ?? 0);
            }
            $counts['all'] = (int) $rows->sum('total');

            return [$type->id => $counts];
        });

        $typeCounts = $statusTotals[$typeId] ?? array_merge(
            ['all' => 0],
            array_fill_keys($statusKeys, 0)
        );

        $users = User::query()->orderBy('name')->get(['id', 'name', 'username', 'role']);

        return view('inventory.index', compact(
            'types',
            'items',
            'typeId',
            'users',
            'statusTotals',
            'typeCounts'
        ));
    }

    public function userStock(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAccessInventory(), 403);

        $typeId = $request->integer('type');
        abort_unless($typeId > 0, 422);

        $type = InventoryType::findOrFail($typeId);

        $items = InventoryItem::query()
            ->where('inventory_type_id', $typeId)
            ->whereNotNull('held_by_user_id')
            ->orderBy('held_by_user_id')
            ->orderBy('name')
            ->get(['id', 'held_by_user_id', 'name', 'code', 'quantity', 'status']);

        $users = User::query()
            ->whereIn('id', $items->pluck('held_by_user_id')->unique())
            ->get(['id', 'name', 'username'])
            ->keyBy('id');

        $list = $items->groupBy('held_by_user_id')->map(function ($group, $userId) use ($users, $type) {
            $user = $users[$userId] ?? null;

            return [
                'user_id' => (int) $userId,
                'name' => $user?->name ?? 'User #'.$userId,
                'username' => $user?->username,
                'total' => (int) $group->sum('quantity'),
                'in_stock' => (int) $group->where('status', 'in_stock')->sum('quantity'),
                'items' => $group->map(fn ($item) => [
                    'id' => $item->id,
                    'type' => $type->name,
                    'name' => $item->name,
                    'code' => $item->code ?: '—',
                    'quantity' => (int) $item->quantity,
                    'status' => $item->status,
                    'status_label' => $item->statusLabel(),
                ])->values(),
            ];
        })->sortByDesc('in_stock')->values();

        return response()->json([
            'type' => $type->name,
            'users' => $list,
            'totals' => [
                'total' => (int) $list->sum('total'),
                'in_stock' => (int) $list->sum('in_stock'),
            ],
        ]);
    }

    public function shiftStock(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAccessInventory(), 403);

        $data = $request->validate([
            'inventory_type_id' => ['required', 'exists:inventory_types,id'],
            'from_user_id' => ['required', 'exists:users,id'],
            'to_user_id' => ['required', 'exists:users,id', 'different:from_user_id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $typeId = (int) $data['inventory_type_id'];
        $fromId = (int) $data['from_user_id'];
        $toId = (int) $data['to_user_id'];
        $need = (int) $data['quantity'];

        $available = (int) InventoryItem::query()
            ->where('inventory_type_id', $typeId)
            ->where('held_by_user_id', $fromId)
            ->where('status', 'in_stock')
            ->sum('quantity');

        if ($need > $available) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$available} in-stock units available with this user.",
            ]);
        }

        $fromName = User::find($fromId)?->name ?? 'User';
        $toName = User::find($toId)?->name ?? 'User';

        DB::transaction(function () use ($typeId, $fromId, $toId, $need, $fromName, $toName) {
            $remaining = $need;
            $items = InventoryItem::query()
                ->where('inventory_type_id', $typeId)
                ->where('held_by_user_id', $fromId)
                ->where('status', 'in_stock')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                if ($remaining <= 0) {
                    break;
                }

                if ($item->quantity <= $remaining) {
                    $moved = $item->quantity;
                    $item->update(['held_by_user_id' => $toId]);
                    $item->recordHistory(
                        'updated',
                        'Stock shifted',
                        "Stock with: {$fromName} → {$toName}\nQty moved: {$moved}"
                    );
                    $remaining -= $moved;
                    continue;
                }

                $item->update(['quantity' => $item->quantity - $remaining]);
                $item->recordHistory(
                    'updated',
                    'Stock shifted out',
                    "Stock with: {$fromName} → {$toName}\nQty moved: {$remaining}\nRemaining here: {$item->quantity}"
                );

                $newItem = InventoryItem::create([
                    'inventory_type_id' => $item->inventory_type_id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'quantity' => $remaining,
                    'status' => 'in_stock',
                    'held_by_user_id' => $toId,
                    'notes' => $item->notes,
                ]);
                $newItem->recordHistory(
                    'created',
                    'Stock shifted in',
                    "Received from {$fromName} → {$toName}\nQty: {$remaining}"
                );
                $remaining = 0;
            }
        });

        return response()->json(['ok' => true, 'message' => "Shifted {$need} units from {$fromName} to {$toName}."]);
    }

    public function history(Request $request, InventoryItem $inventory): JsonResponse
    {
        abort_unless($request->user()->canAccessInventory(), 403);

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
            'histories' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessInventory(), 403);

        $data = $this->validatedItem($request);
        $item = InventoryItem::create($data);

        $parts = [
            'Type: '.$item->displayValue('inventory_type_id', $item->inventory_type_id),
            'Qty: '.$item->quantity,
            'Status: '.$item->statusLabel(),
        ];
        if ($item->code) {
            $parts[] = 'Code: '.$item->code;
        }
        if ($item->held_by_user_id) {
            $parts[] = 'Stock with: '.$item->displayValue('held_by_user_id', $item->held_by_user_id);
        }
        if ($item->assigned_to_name) {
            $parts[] = 'Assigned to: '.$item->assigned_to_name;
        }

        $item->recordHistory('created', 'Entry created', implode(' · ', $parts));

        return redirect()
            ->route('inventory.index', ['type' => $data['inventory_type_id']])
            ->with('success', 'Inventory item added.');
    }

    public function update(Request $request, InventoryItem $inventory): RedirectResponse
    {
        abort_unless($request->user()->canAccessInventory(), 403);

        $track = ['inventory_type_id', 'name', 'code', 'quantity', 'status', 'held_by_user_id', 'assigned_to_name', 'notes'];
        $before = $inventory->only($track);

        $data = $this->validatedItem($request);
        $inventory->update($data);

        $changes = $inventory->diffChanges($before, $inventory->only($track));
        if ($changes) {
            $lines = collect($changes)->map(fn ($c) => $c['label'].': '.$c['from'].' → '.$c['to'])->all();
            $hot = collect($changes)->contains(fn ($c) => in_array($c['field'], ['held_by_user_id', 'assigned_to_name', 'status'], true));
            $inventory->recordHistory(
                'updated',
                $hot ? 'Stock / assignment updated' : 'Item edited',
                implode("\n", $lines),
                $changes
            );
        }

        return redirect()
            ->route('inventory.index', ['type' => $data['inventory_type_id']])
            ->with('success', 'Inventory item updated.');
    }

    public function destroy(Request $request, InventoryItem $inventory): RedirectResponse
    {
        abort_unless($request->user()->canAccessInventory(), 403);

        $typeId = $inventory->inventory_type_id;
        $inventory->delete();

        return redirect()
            ->route('inventory.index', ['type' => $typeId])
            ->with('success', 'Inventory item deleted.');
    }

    private function validatedItem(Request $request): array
    {
        $status = $request->input('status');

        $data = $request->validate([
            'inventory_type_id' => ['required', 'exists:inventory_types,id'],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:'.implode(',', array_keys(InventoryItem::STATUSES))],
            'held_by_user_id' => [
                Rule::requiredIf(fn () => $status === 'in_stock'),
                'nullable',
                'exists:users,id',
            ],
            'assigned_to_name' => [
                Rule::requiredIf(fn () => $status === 'assigned'),
                'nullable',
                'string',
                'max:160',
            ],
            'notes' => ['nullable', 'string'],
        ]);

        if (($data['status'] ?? '') === 'assigned') {
            $data['held_by_user_id'] = null;
        } elseif (($data['status'] ?? '') !== 'in_stock') {
            unset($data['held_by_user_id']);
        }

        if (($data['status'] ?? '') !== 'assigned') {
            $data['assigned_to_name'] = null;
        }

        return $data;
    }
}
