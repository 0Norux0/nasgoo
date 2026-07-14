<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── v10.5 TypeScript build-blocker fixes ───

it('AdminLayout.tsx uses canonical SharedProps type (not inline incomplete type)', function () {
    $src = file_get_contents(resource_path('js/Layouts/AdminLayout.tsx'));
    // Must NOT use the broken inline type that caused TS2344
    expect($src)->not->toContain('usePage<{ auth:');
    expect($src)->not->toContain('PageAuth');  // the bad inline interface name
    // MUST use canonical SharedProps
    expect($src)->toContain('usePage<SharedProps>()');
    expect($src)->toContain("from '@/types/inertia'");
});

it('Admin/Reports/Index.tsx does NOT import Link unused (TS6133)', function () {
    $src = file_get_contents(resource_path('js/Pages/Admin/Reports/Index.tsx'));
    expect($src)->not->toMatch('/^import \{ Link, router \} from \'@inertiajs\/react\'/m');
    expect($src)->toMatch('/^import \{ router \} from \'@inertiajs\/react\'/m');
});

it('Vendor/Reports/Index.tsx does NOT import Link unused (TS6133)', function () {
    $src = file_get_contents(resource_path('js/Pages/Vendor/Reports/Index.tsx'));
    expect($src)->not->toMatch('/^import \{ Link, router \} from \'@inertiajs\/react\'/m');
    expect($src)->toMatch('/^import \{ router \} from \'@inertiajs\/react\'/m');
});

it('Vendor Orders Show.tsx uses named ChangeEvent type (not React.ChangeEvent namespace)', function () {
    $src = file_get_contents(resource_path('js/Pages/Vendor/Orders/Show.tsx'));
    expect($src)->not->toContain('React.ChangeEvent');
    expect($src)->toContain('type ChangeEvent');
});

it('No layout in resources/js/Layouts uses inline incomplete usePage<{...}> generic', function () {
    foreach (glob(resource_path('js/Layouts/*.tsx')) as $f) {
        $src = file_get_contents($f);
        // Inline incomplete usePage<{ ... }> generic — always wrong
        // (real project type is augmented via PageProps extends SharedProps)
        expect($src)->not->toMatch('/usePage<\{[^}]*\}>/');
    }
});

it('VERSION file reports Phase 10 v10.5', function () {
    expect(trim((string) file_get_contents(base_path('VERSION'))))->toBe('Phase 10 v10.5');
});
