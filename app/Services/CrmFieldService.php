<?php

namespace App\Services;

use App\Models\CrmField;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CrmFieldService
{
    public function formFields(string $pipeline = 'crm'): Collection
    {
        return CrmField::inPipeline($pipeline)->active()->ordered()->where('in_form', true)->get();
    }

    public function excelFields(string $pipeline = 'crm'): Collection
    {
        return CrmField::inPipeline($pipeline)->active()->ordered()->where('in_excel', true)->get();
    }

    public function tableFields(string $pipeline = 'crm'): Collection
    {
        return CrmField::inPipeline($pipeline)
            ->active()
            ->ordered()
            ->where('in_table', true)
            ->get()
            ->reject(fn (CrmField $f) => in_array(strtolower($f->key), ['date', 'entry_date', 'created_date', 'created_at'], true))
            ->values();
    }

    public function allOrdered(string $pipeline = 'crm'): Collection
    {
        return CrmField::inPipeline($pipeline)->ordered()->get();
    }

    public function validationRules(Collection $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rule = [$field->required ? 'required' : 'nullable'];

            $typeRules = match ($field->type) {
                'email' => ['email'],
                'phone' => ['string', 'regex:/^[0-9+\-\s()]*$/'],
                'number' => ['numeric'],
                'date' => ['date'],
                'time' => ['string'],
                'textarea' => ['string'],
                'select' => ['string'],
                default => ['string'],
            };

            $rule = array_merge($rule, $typeRules);

            if ($field->key === 'assigned_to') {
                $rules[$field->key] = [$field->required ? 'required' : 'nullable', 'exists:users,id'];
                continue;
            }

            $rule[] = 'max:2000';

            $rules[$field->key] = $rule;
        }

        return $rules;
    }

    public function splitPayload(Request $request, Collection $fields): array
    {
        $core = [];
        $meta = [];

        foreach ($fields as $field) {
            $value = $request->input($field->key);
            if ($value === '') {
                $value = null;
            }

            if ($field->isCore()) {
                $core[$field->key] = $value;
            } else {
                $meta[$field->key] = $value;
            }

            if (($field->type === 'phone' || in_array($field->key, ['phone', 'phone_no', 'mobile', 'contact', 'contact_no'], true)) && ! empty($value)) {
                $core['phone'] = $value;
            }
            if (($field->type === 'email' || in_array($field->key, ['email', 'email_address', 'mail'], true)) && ! empty($value)) {
                $core['email'] = $value;
            }
        }

        if (array_key_exists('status', $core) && ! $core['status']) {
            $core['status'] = 'new';
        }

        return [$core, $meta];
    }
}
