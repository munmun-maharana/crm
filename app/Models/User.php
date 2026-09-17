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
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_to');
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role));
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
        return 'in:'.implode(',', array_keys(self::ROLES));
    }
}
