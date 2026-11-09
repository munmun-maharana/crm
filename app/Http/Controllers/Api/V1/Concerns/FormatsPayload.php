<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\CrmField;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared JSON shapes used by every mobile v1 endpoint.
 */
trait FormatsPayload
{
    protected function pipelineOf(?string $value): string
    {
        return $value === 'operations' ? 'operations' : 'crm';
    }

    protected function statusOptionsFor(string $pipeline): array
    {
        $field = CrmField::inPipeline($pipeline)->where('key', 'status')->first();

        return ! empty($field?->options)
            ? array_values($field->options)
            : ['new', 'interested', 'contacted', 'converted', 'lost', 'request'];
    }

    protected function fieldPayload(CrmField $f): array
    {
        return [
            'id' => $f->id,
            'pipeline' => $f->pipeline,
            'key' => $f->key,
            'label' => $f->label,
            'type' => $f->type,
            'options' => array_values($f->options ?? []),
            'required' => (bool) $f->required,
            'active' => (bool) $f->active,
            'in_form' => (bool) $f->in_form,
            'in_table' => (bool) $f->in_table,
            'in_excel' => (bool) $f->in_excel,
            'in_update' => (bool) $f->in_update,
            'auto_select' => (bool) $f->auto_select,
            'is_system' => (bool) $f->is_system,
            'is_core' => $f->isCore(),
            'maps_from' => $f->maps_from,
            'sort_order' => (int) $f->sort_order,
        ];
    }

    protected function fieldsPayload(Collection $fields): array
    {
        return $fields->map(fn (CrmField $f) => $this->fieldPayload($f))->values()->all();
    }

    protected function toneFor(?string $status): string
    {
        $map = [
            'new' => 'sky', 'pending' => 'amber', 'no answer' => 'slate', 'follow-up' => 'amber',
            'interested' => 'teal', 'contacted' => 'blue', 'converted' => 'emerald', 'success' => 'emerald',
            'cancelled' => 'rose', 'lost' => 'rose', 'confirmed' => 'emerald', 'request' => 'amber',
        ];

        return $map[strtolower(trim((string) $status))] ?? 'sky';
    }

    /** Date summary identical to the web list (booking / follow-up / created). */
    protected function dateSummary(Customer $c): array
    {
        if ($c->follow_up && $c->follow_up_at) {
            return [
                'kind' => 'follow_up',
                'date' => $c->follow_up_at->format('d M Y'),
                'time' => $c->follow_up_has_time ? $c->follow_up_at->format('h:i A') : 'Anytime',
            ];
        }
        if ($c->follow_up_at) {
            $meta = is_array($c->meta) ? $c->meta : [];
            $slot = $meta['booking_slot_time'] ?? $meta['booking_time'] ?? ($c->follow_up_has_time ? $c->follow_up_at->format('h:i A') : null);

            return ['kind' => 'booked', 'date' => $c->follow_up_at->format('d M Y'), 'time' => $slot ?: 'Booked'];
        }
        $entry = $c->fieldValue('date') ?: $c->created_at?->format('d M Y');

        return ['kind' => 'created', 'date' => $entry ? (string) $entry : null, 'time' => null];
    }

    protected function customerRow(Customer $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'company' => $c->company,
            'city' => $c->city,
            'source' => $c->source,
            'status' => $c->status,
            'status_tone' => $this->toneFor($c->status),
            'notes' => $c->notes,
            'pipeline' => $c->pipeline ?? 'crm',
            'assigned_to' => $c->assigned_to,
            'assignee_name' => $c->assignee?->name,
            'follow_up' => (bool) $c->follow_up,
            'follow_up_at' => $c->follow_up_at?->toIso8601String(),
            'follow_up_has_time' => (bool) $c->follow_up_has_time,
            'follow_up_label' => $c->followUpScheduleLabel(),
            'date_summary' => $this->dateSummary($c),
            'last_contact_at' => $c->last_contact_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
            'created_label' => $c->created_at?->format('d M Y'),
            'meta' => (object) (is_array($c->meta) ? $c->meta : []),
        ];
    }

    protected function activityPayload(CustomerActivity $a): array
    {
        $meta = is_array($a->meta) ? $a->meta : [];
        if (! empty($meta['images']) && is_array($meta['images'])) {
            $meta['images'] = array_map(function ($img) {
                if (is_array($img) && ! empty($img['path'])) {
                    $img['url'] = asset($img['path']);
                }

                return $img;
            }, $meta['images']);
        }

        return [
            'id' => $a->id,
            'type' => $a->type,
            'title' => $a->title,
            'body' => $a->body,
            'user_name' => $a->user?->name ?: 'System',
            'created_at' => $a->created_at?->format('d M Y · h:i A'),
            'ago' => $a->created_at?->diffForHumans(),
            'meta' => (object) $meta,
        ];
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'role_label' => $user->roleLabel(),
            'is_admin' => $user->isAdmin(),
            'is_coordinator' => $user->isCoordinator(),
            'is_operation' => $user->isOperation(),
            'can_access_crm' => $user->canAccessCrm(),
            'can_access_operations' => $user->canAccessOperations(),
            'can_access_inventory' => false,
            'can_assign_users' => $user->canAssignUsers(),
            'can_bulk_map' => $user->canBulkMap(),
            'can_see_all' => $user->canSeeAllRecords(),
            'meta' => (object) (is_array($user->meta) ? $user->meta : []),
        ];
    }
}

