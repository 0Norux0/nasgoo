<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Audit logger — writes immutable entries to the audit_logs table.
 *
 * This is separate from spatie/laravel-activitylog. activitylog tracks
 * model changes broadly; AuditLogger is for HIGH-SENSITIVITY actions
 * (role changes, settings changes, payout approvals, etc.) where we
 * want strict before/after captures with IP + user agent.
 */
final class AuditLogger
{
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $notes = null,
    ): AuditLog {
        $request = app(Request::class);

        return AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => $action,
            'model_type' => $subject?->getMorphClass(),
            'model_id'   => $subject?->getKey(),
            'before'     => $before,
            'after'      => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes'      => $notes,
        ]);
    }

    public function roleAssigned(Model $user, string $role): void
    {
        $this->log('role.assigned', $user, null, ['role' => $role]);
    }

    public function roleRemoved(Model $user, string $role): void
    {
        $this->log('role.removed', $user, ['role' => $role], null);
    }

    public function userStatusChanged(Model $user, string $from, string $to): void
    {
        $this->log('user.status_changed', $user, ['status' => $from], ['status' => $to]);
    }

    public function settingChanged(string $group, string $key, mixed $before, mixed $after): void
    {
        $this->log(
            'settings.changed',
            null,
            ['group' => $group, 'key' => $key, 'value' => $before],
            ['group' => $group, 'key' => $key, 'value' => $after],
        );
    }

    public function adminLogin(Model $user): void
    {
        $this->log('admin.login', $user);
    }
}
