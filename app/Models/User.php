<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property Role $role
 * @property string|null $mobile
 * @property string|null $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password Null until a password is issued (e.g. imported students)
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'role', 'mobile', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Find the user a login identifier belongs to: admins and teachers sign in
     * with their mobile number, students with their student ID.
     */
    public static function findForLogin(string $login): ?self
    {
        $login = trim($login);

        return static::query()
            ->where(fn (Builder $query) => $query
                ->where('mobile', static::normalizeMobile($login))
                ->whereIn('role', Role::mobileLoginRoles()))
            ->orWhere(fn (Builder $query) => $query
                ->where('role', Role::Student)
                ->whereHas('student', fn (Builder $student) => $student->where('student_id', $login)))
            ->first();
    }

    /**
     * The academic record of a student user.
     *
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Normalize a Bangladeshi mobile number to its local 11-digit form
     * (e.g. "+880 1725-215111" becomes "01725215111").
     */
    public static function normalizeMobile(string $mobile): string
    {
        $mobile = preg_replace('/[\s\-()]/', '', $mobile) ?? $mobile;

        if (str_starts_with($mobile, '+880')) {
            return '0'.substr($mobile, 4);
        }

        if (str_starts_with($mobile, '880') && strlen($mobile) === 13) {
            return '0'.substr($mobile, 3);
        }

        return $mobile;
    }

    /**
     * Determine if the user has any of the given roles.
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Always store mobile numbers in normalized form.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function mobile(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : static::normalizeMobile($value),
        );
    }
}
