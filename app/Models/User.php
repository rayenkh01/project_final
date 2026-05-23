<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['email', 'password', 'direction', 'role', 'tel', 'invitation_token_hash', 'invitation_expires_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $timestamps = false;

    public const ROLE_BUSINESS = 'business';

    public const ROLE_OPERATIONAL = 'operationnel';

    public const ROLE_ADMIN = 'admin';

    public const ORACLE_ROLE_ADMIN = 'ADMIN';

    public const ORACLE_ROLE_BUSINESS = 'BUSINESS';

    public const ORACLE_ROLE_OPERATION = 'OPERATION';

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_BUSINESS,
            self::ROLE_OPERATIONAL,
            self::ROLE_ADMIN,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function oracleRoleOptions(): array
    {
        return [
            self::ORACLE_ROLE_ADMIN => 'Administrateur',
            self::ORACLE_ROLE_BUSINESS => 'Analyste Business',
            self::ORACLE_ROLE_OPERATION => 'Analyste Operationnel',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function oracleRoles(): array
    {
        return array_keys(self::oracleRoleOptions());
    }

    public static function roleLabel(?string $role): string
    {
        $normalizedRole = self::normalizeRole($role);

        return [
            self::ROLE_ADMIN => 'Administrateur',
            self::ROLE_BUSINESS => 'Analyste Business',
            self::ROLE_OPERATIONAL => 'Analyste Operationnel',
        ][$normalizedRole] ?? 'Utilisateur';
    }

    public static function normalizeRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return match ($role) {
            self::ROLE_ADMIN, 'administrator', 'administrateur' => self::ROLE_ADMIN,
            self::ROLE_OPERATIONAL, 'operational', 'operationnel', 'operation', 'ops' => self::ROLE_OPERATIONAL,
            self::ROLE_BUSINESS, 'analyste_business', 'business_analyst', 'analyste business' => self::ROLE_BUSINESS,
            default => self::ROLE_BUSINESS,
        };
    }

    public static function toOracleRole(?string $role): string
    {
        return match (self::normalizeRole($role)) {
            self::ROLE_ADMIN => self::ORACLE_ROLE_ADMIN,
            self::ROLE_OPERATIONAL => self::ORACLE_ROLE_OPERATION,
            default => self::ORACLE_ROLE_BUSINESS,
        };
    }

    public function dashboardRole(): string
    {
        return self::normalizeRole(session('active_role') ?? $this->role);
    }

    public function hasDashboardRole(string ...$roles): bool
    {
        return in_array($this->dashboardRole(), $roles, true);
    }

    public function hasPendingInvitation(): bool
    {
        return filled($this->invitation_token_hash);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
