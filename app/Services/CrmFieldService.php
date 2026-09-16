<?php

namespace App\Services;

use App\Models\CrmField;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CrmFieldService
{
    public function formFields(): Collection
    {
        return CrmField::active()->ordered()->where('in_form', true)->get();
    }

    public function excelFields(): Collection
    {
        return CrmField::active()->ordered()->where('in_excel', true)->get();
    }

    public function tableFields(): Collection
    {
        return CrmField::active()->ordered()->where('in_table', true)->get();
    }

    public function allOrdered(): Collection
    {
        return CrmField::ordered()->get();
    }

    public function validationRules(Collection $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rule = [$field->required ? 'required' : 'nullable'];

            $rule[] = match ($field->type) {
                'email' => 'email',
                'textarea' => 'string',
                'select' => ! empty($field->options)
                    ? Rule::in($field->options)
                    : 'string',
                default => 'string',
            };

            if ($field->key === 'assigned_to') {
                $rules[$field->key] = [$field->required ? 'required' : 'nullable', 'exists:users,id'];
                continue;
            }

            if ($field->type !== 'select') {
                $rule[] = 'max:2000';
            }

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
        }

        if (! isset($core['status']) || ! $core['status']) {
            $core['status'] = 'new';
        }

        return [$core, $meta];
    }
}
