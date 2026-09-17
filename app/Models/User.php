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
        'meta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'meta' => 'array',
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
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCoordinator(): bool
    {
        return $this->role === self::ROLE_COORDINATOR;
    }

    public function isFrontOffice(): bool
    {
        return $this->role === self::ROLE_FRONT_OFFICE;
    }

    public function isOperation(): bool
    {
        return $this->role === self::ROLE_OPERATION;
    }

    public function canSeeAllRecords(): bool
    {
        return $this->isAdmin() || $this->isCoordinator();
    }

    public function canAccessCrm(): bool
    {
        return $this->isAdmin() || $this->isCoordinator() || $this->isFrontOffice();
    }

    public function canAccessOperations(): bool
    {
        return $this->isAdmin() || $this->isCoordinator() || $this->isOperation();
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
