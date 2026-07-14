<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── v10.4 forensic-package checks ───

it('marketplace:fingerprint command is registered', function () {
    \Illuminate\Support\Facades\Artisan::call('list');
    $output = \Illuminate\Support\Facades\Artisan::output();
    expect($output)->toContain('marketplace:fingerprint');
});

it('marketplace:fingerprint runs cleanly and outputs aggregate SHA-256', function () {
    $exit = \Illuminate\Support\Facades\Artisan::call('marketplace:fingerprint');
    expect($exit)->toBe(0);
    $output = \Illuminate\Support\Facades\Artisan::output();
    expect($output)->toContain('Aggregate fingerprint:');
    // The aggregate is a 64-char hex string
    expect($output)->toMatch('/[0-9a-f]{64}/');
});

it('marketplace:fingerprint --json emits valid JSON with files_found == files_total', function () {
    \Illuminate\Support\Facades\Artisan::call('marketplace:fingerprint', ['--json' => true]);
    $output = trim(\Illuminate\Support\Facades\Artisan::output());
    $data = json_decode($output, true);
    expect($data)->toBeArray();
    expect($data['files_found'])->toBe($data['files_total']);
    expect($data['version'])->toBe('Phase 10 v10.4');
    expect($data['aggregate_sha256'])->toMatch('/^[0-9a-f]{64}$/');
});

it('Exactly one VERSION file exists in the project root (no nested duplicate)', function () {
    $count = count(glob(base_path('VERSION')));
    expect($count)->toBe(1);
    // And no nested marketplace/ directory
    $nested = glob(base_path('marketplace'));
    expect($nested)->toBe([]);
});

it('PHASE_10_v10.4_ACTIVE_CODE_MAP.md exists and references every defect', function () {
    $path = base_path('PHASE_10_v10.4_ACTIVE_CODE_MAP.md');
    expect(is_file($path))->toBeTrue();
    $src = file_get_contents($path);
    foreach (range(1, 10) as $n) {
        expect($src)->toContain("Defect {$n}");
    }
});

it('VERSION file reports Phase 10 v10.4', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.4');
});
