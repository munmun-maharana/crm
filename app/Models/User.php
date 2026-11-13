<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_FRONT_OFFICE = 'front_office';
    public const ROLE_COORDINATOR = 'coordinator';
    public const ROLE_OPERATION = 'operation';
    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_FRONT_OFFICE => 'Front Office',
        self::ROLE_COORDINATOR => 'Coordinator',
        self::ROLE_OPERATION => 'Operation',
        self::ROLE_ADMIN => 'Admin',
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'meta',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'meta' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function roleOptions(): array
    {
        $field = CrmField::inPipeline('users')->where('key', 'role')->first();
        $fromField = collect($field?->options ?? [])
            ->map(fn ($o) => trim((string) $o))
            ->filter()
            ->values()
            ->all();

        $keys = $fromField !== []
            ? $fromField
            : array_keys(self::ROLES);

        $options = [];
        foreach ($keys as $key) {
            $options[$key] = self::ROLES[$key]
                ?? ucwords(str_replace('_', ' ', $key));
        }

        return $options;
    }

    public function fieldValue(string $key): mixed
    {
        if (in_array($key, CrmField::USER_CORE_KEYS, true)) {
            return $this->{$key};
        }

        return data_get($this->meta, $key);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_to');
    }

    public function roleLabel(): string
    {
        $options = self::roleOptions();

        return $options[$this->role]
            ?? (self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role)));
    }

    public function isAdmin(): bool
    {
        return strtolower(trim((string) $this->role)) === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (array_key_exists('is_active', $this->attributes) && $this->attributes['is_active'] !== null) {
            return (bool) $this->attributes['is_active'];
        }

        if (is_array($this->meta) && array_key_exists('is_active', $this->meta)) {
            return (bool) $this->meta['is_active'];
        }

        return true;
    }

    public function isCoordinator(): bool
    {
        return strtolower(trim((string) $this->role)) === self::ROLE_COORDINATOR;
    }

    public function isFrontOffice(): bool
    {
        return strtolower(trim((string) $this->role)) === self::ROLE_FRONT_OFFICE;
    }

    public function isOperation(): bool
    {
        return strtolower(trim((string) $this->role)) === self::ROLE_OPERATION;
    }

    public function canSeeAllRecords(): bool
    {
        return $this->isAdmin() || $this->isCoordinator();
    }

    public function canAccessDashboard(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->canAccessCrm() || $this->canAccessOperations() || $this->appAccessFlag('access_dashboard', false);
    }

    public function canAccessCrm(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->appAccessFlag('access_crm', $this->isCoordinator() || $this->isFrontOffice());
    }

    public function canAccessCallCrm(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->appAccessFlag('access_call_crm', $this->isCoordinator() || $this->isFrontOffice());
    }

    public function canAccessOperations(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->appAccessFlag('access_operations', $this->isCoordinator() || $this->isOperation());
    }

    public function canAccessPincodes(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->appAccessFlag('access_pincodes', $this->isCoordinator() || $this->isFrontOffice() || $this->isOperation());
    }

    public function canAccessInventory(): bool
    {
        return false;
    }

    public function canEditCustomers(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->appAccessFlag('can_edit', false);
    }

    public function canDeleteCustomers(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->appAccessFlag('can_delete', false);
    }

    public function defaultRoute(): string
    {
        if ($this->isAdmin() || $this->canAccessDashboard()) {
            return route('dashboard');
        }
        if ($this->canAccessCallCrm()) {
            return route('call-crm.index');
        }
        if ($this->canAccessCrm()) {
            return route('customers.index');
        }
        if ($this->canAccessOperations()) {
            return route('operations.index');
        }
        if ($this->canAccessPincodes()) {
            return route('pincodes.index');
        }

        return route('login');
    }

    /** Mobile app tab access — Dashboard is always allowed. */
    public function appAccessCrm(): bool
    {
        return $this->appAccessFlag('access_crm', $this->isAdmin() || $this->isCoordinator() || $this->isFrontOffice());
    }

    public function appAccessCallCrm(): bool
    {
        return $this->appAccessFlag('access_call_crm', $this->isAdmin() || $this->isCoordinator() || $this->isFrontOffice());
    }

    public function appAccessOperations(): bool
    {
        return $this->appAccessFlag('access_operations', $this->isAdmin() || $this->isCoordinator() || $this->isOperation());
    }

    public function appAccessPincodes(): bool
    {
        return $this->appAccessFlag('access_pincodes', $this->isAdmin() || $this->isCoordinator() || $this->isFrontOffice() || $this->isOperation());
    }

    public function appAccessInventory(): bool
    {
        return false;
    }

    public function appAccessFlags(): array
    {
        return [
            'crm' => $this->appAccessCrm(),
            'call_crm' => $this->appAccessCallCrm(),
            'operations' => $this->appAccessOperations(),
            'pincodes' => $this->appAccessPincodes(),
            'inventory' => $this->appAccessInventory(),
        ];
    }

    public function appAccessFlag(string $key, bool $roleDefault): bool
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        if (array_key_exists($key, $meta)) {
            return filter_var($meta[$key], FILTER_VALIDATE_BOOLEAN);
        }

        return $roleDefault;
    }

    public function canAssignUsers(): bool
    {
        return $this->isAdmin() || $this->isCoordinator();
    }

    public function canBulkMap(): bool
    {
        return $this->isAdmin() || $this->isCoordinator();
    }

    public static function roleRule(): string
    {
        $keys = array_keys(self::roleOptions());
        if ($keys === []) {
            $keys = array_keys(self::ROLES);
        }

        return 'in:'.implode(',', $keys);
    }
}
