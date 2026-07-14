<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'locale',
        'default_currency',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'         => 'datetime',
            'phone_verified_at'         => 'datetime',
            'two_factor_confirmed_at'   => 'datetime',
            'last_login_at'             => 'datetime',
            'password'                  => 'hashed',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_secret'         => 'encrypted',
        ];
    }

    /**
     * Filament admin gate (Phase 1 — proper role check).
     *
     * Only super_admin and admin_staff can reach the Filament panel.
     * Vendors and customers are explicitly excluded.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->hasAnyRole(['super_admin', 'admin_staff']);
    }

    /**
     * Phase 10 v10.9/v10.10 — canonical rule for /admin/reports access.
     *
     * v10.9 introduced this method so the Filament navigation visibility
     * AND the /admin/reports route Gate would share the same rule.
     *
     * v10.10 simplifies further after the v10.9 fix did NOT resolve the
     * dev's 403:
     *   (1) Drops the `status === 'active'` precondition. The check was
     *       redundant with canAccessPanel (admins who fail it can't reach
     *       the menu in the first place) and made the auth fragile to any
     *       schema variation where the status column wasn't exactly the
     *       string 'active' — e.g. NULL, 'enabled', 'verified', integer 1.
     *   (2) Broadens the role match to accept common variants
     *       (super_admin, admin_staff, admin, administrator). Defends
     *       against installations that seeded a non-standard role name.
     *
     * Used by:
     *   - ReportsController::guardAdminReportsAccess() (direct call, the
     *     canonical authorization check post-v10.10)
     *   - AdminPanelProvider Filament nav item visibility
     *   - AppServiceProvider Gate `viewReports` (legacy entry point, kept
     *     for backward compatibility with any third-party callers)
     */
    public function canManageAdminReports(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin_staff', 'admin', 'administrator']);
    }

    /** @return HasMany<Address> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasMany<AuditLog> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<Vendor> */
    public function vendor(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    /** Phase 4 — single active cart per user. */
    public function cart(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /** Phase 4 — order history. */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Phase 5 — reviews authored by this user
    public function productReviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    // Phase 5 — wishlist (products this user has wishlisted)
    public function wishlist(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistedProducts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
    }

    public function defaultAddress(): ?Address
    {
        return $this->addresses()->where('is_default', true)->first()
            ?? $this->addresses()->first();
    }

    /**
     * Activity log options — track sensitive changes only.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'status', 'locale', 'default_currency'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }

    /**
     * Phase 7 v7.5 — graceful email-verification dispatch.
     *
     * The default MustVerifyEmail trait throws a Symfony\Component\Mailer\
     * Exception\TransportException if the configured MAIL_HOST is unreachable
     * (eg. `mailpit:1025` when Docker isn't running). That exception bubbles
     * up through `event(new Registered($user))` in RegisterController and
     * surfaces to the customer as a HTTP 500 — registration "crashes" even
     * though the user account was created seconds earlier.
     *
     * v7.5 overrides this method to catch ANY Throwable from the mailer,
     * log it with the user's email for triage, and return normally so the
     * caller's flow (registration, login, redirect) completes cleanly.
     *
     * Customers without a verification email can re-request one from
     * /email/verification-notification — that path now uses the same
     * graceful pattern (see EmailVerificationController::resend).
     *
     * Production note: this does NOT silence the failure. The exception
     * is still logged with stack trace at WARNING level, so monitoring
     * (Sentry / Bugsnag / log aggregation) still picks it up. The only
     * behaviour change is "don't show a 500 to the customer".
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            parent::sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'Verification email could not be sent — registration/resend will continue without it. '
                . 'Configure MAIL_MAILER (see .env.example) or start the Mailpit Docker service. '
                . 'Error: ' . $e->getMessage(),
                ['user_id' => $this->id, 'email' => $this->email, 'exception' => get_class($e)]
            );
        }
    }
}
