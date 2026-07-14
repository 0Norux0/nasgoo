<?php

declare(strict_types=1);

use App\Domain\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('records a sensitive action via the audit logger', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $this->actingAs($actor);

    $logger = app(AuditLogger::class);
    $logger->roleAssigned($target, 'admin_staff');

    $log = AuditLog::latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('role.assigned')
        ->and($log->user_id)->toBe($actor->id)
        ->and($log->model_type)->toBe($target->getMorphClass())
        ->and($log->model_id)->toBe($target->id)
        ->and($log->after)->toBe(['role' => 'admin_staff']);
});

it('refuses to update an audit log entry', function () {
    $log = AuditLog::create([
        'action'     => 'test.action',
        'created_at' => now(),
    ]);

    expect(fn () => $log->update(['action' => 'tampered']))
        ->toThrow(LogicException::class, 'immutable');
});

it('refuses to delete an audit log entry', function () {
    $log = AuditLog::create([
        'action'     => 'test.action',
        'created_at' => now(),
    ]);

    expect(fn () => $log->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('records before and after state on settings changes', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $logger = app(AuditLogger::class);
    $logger->settingChanged('general', 'site_name', 'Old', 'New');

    $log = AuditLog::latest('id')->first();

    expect($log->before)->toMatchArray(['group' => 'general', 'key' => 'site_name', 'value' => 'Old'])
        ->and($log->after)->toMatchArray(['group' => 'general', 'key' => 'site_name', 'value' => 'New']);
});
