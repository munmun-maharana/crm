<?php

namespace App\Http\Controllers;

use App\Models\CrmField;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::latest()->paginate(20);
        $fields = CrmField::inPipeline('users')->active()->ordered()->get();
        $formFields = $fields->where('in_form', true)->values();
        $tableFields = $fields->where('in_table', true)->values();

        return view('users.index', compact('users', 'formFields', 'tableFields'));
    }

    public function store(Request $request): RedirectResponse
    {
        $fields = CrmField::inPipeline('users')->active()->where('in_form', true)->ordered()->get();
        [$core, $meta] = $this->validatedPayload($request, $fields, null);

        User::create([
            ...$core,
            'meta' => $meta ?: null,
        ]);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $fields = CrmField::inPipeline('users')->active()->where('in_form', true)->ordered()->get();

        // Quick role-only update from table dropdown
        if ($request->has('role') && ! $request->has('name') && ! $request->filled('username')) {
            $request->validate(['role' => ['required', User::roleRule()]]);
            $user->role = $request->string('role')->toString();
            $user->save();

            return back()->with('success', 'User updated.');
        }

        [$core, $meta] = $this->validatedPayload($request, $fields, $user);

        $user->fill(collect($core)->except('password')->all());
        if (! empty($core['password'])) {
            $user->password = $core['password'];
        }
        $user->meta = $meta ?: null;
        $user->save();

        return back()->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    private function validatedPayload(Request $request, $fields, ?User $user): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $base = $field->required ? ['required'] : ['nullable'];
            if ($field->key === 'password') {
                $rules['password'] = $user
                    ? ['nullable', 'string', 'min:6']
                    : ['required', 'string', 'min:6'];
                continue;
            }
            if ($field->key === 'username') {
                $rules['username'] = array_merge($base, [
                    'string', 'max:60', 'alpha_dash',
                    $user
                        ? Rule::unique('users', 'username')->ignore($user->id)
                        : Rule::unique('users', 'username'),
                ]);
                continue;
            }
            if ($field->key === 'email') {
                $rules['email'] = array_merge($base, [
                    'email', 'max:120',
                    $user
                        ? Rule::unique('users', 'email')->ignore($user->id)
                        : Rule::unique('users', 'email'),
                ]);
                continue;
            }
            if ($field->key === 'role') {
                $rules['role'] = array_merge($base, [User::roleRule()]);
                continue;
            }
            if ($field->key === 'name') {
                $rules['name'] = array_merge($base, ['string', 'max:120']);
                continue;
            }

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
            $rules[$field->key] = array_merge($base, $typeRules, ['max:500']);
        }

        // Always allow role for quick table updates when form fields incomplete
        if (! isset($rules['role'])) {
            $rules['role'] = ['nullable', User::roleRule()];
        }
        if (! isset($rules['name'])) {
            $rules['name'] = ['nullable', 'string', 'max:120'];
        }
        if (! isset($rules['username'])) {
            $rules['username'] = ['nullable', 'string', 'max:60'];
        }
        if (! isset($rules['email'])) {
            $rules['email'] = ['nullable', 'email', 'max:120'];
        }

        $data = $request->validate($rules);
        $core = [];
        $meta = is_array($user?->meta) ? $user->meta : [];

        foreach ($fields as $field) {
            $value = $data[$field->key] ?? null;
            if ($field->key === 'password') {
                if ($value !== null && $value !== '') {
                    $core['password'] = $value;
                }
                continue;
            }
            if (in_array($field->key, CrmField::USER_CORE_KEYS, true)) {
                $core[$field->key] = is_string($value) ? trim($value) : $value;
            } else {
                if ($value === null || $value === '') {
                    unset($meta[$field->key]);
                } else {
                    $meta[$field->key] = is_string($value) ? trim($value) : $value;
                }
            }
        }

        // Mobile app tab access (Dashboard always on)
        $meta['access_crm'] = $request->boolean('access_crm');
        $meta['access_operations'] = $request->boolean('access_operations');
        $meta['access_inventory'] = $request->boolean('access_inventory');

        if (empty($core['name']) && $user) {
            $core['name'] = $user->name;
        }
        if (empty($core['username']) && $user) {
            $core['username'] = $user->username;
        }
        if (empty($core['email']) && $user) {
            $core['email'] = $user->email;
        }
        if (empty($core['role']) && $user) {
            $core['role'] = $user->role;
        }

        return [$core, $meta];
    }
}
