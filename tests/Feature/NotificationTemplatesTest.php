<?php

declare(strict_types=1);

use App\Models\NotificationTemplate;
use Database\Seeders\NotificationTemplatesSeeder;

beforeEach(function () {
    $this->seed(NotificationTemplatesSeeder::class);
});

it('seeds templates for all spec event keys', function () {
    $events = NotificationTemplate::distinct('event_key')->pluck('event_key')->toArray();

    foreach (NotificationTemplate::supportedEventKeys() as $required) {
        expect($events)->toContain($required);
    }

    expect(count($events))->toBeGreaterThanOrEqual(20);
});

it('seeds templates in English and Arabic', function () {
    $locales = NotificationTemplate::distinct('locale')->pluck('locale')->toArray();
    expect($locales)->toContain('en')->toContain('ar');
});

it('seeds templates for both mail and database channels', function () {
    $channels = NotificationTemplate::distinct('channel')->pluck('channel')->toArray();
    expect($channels)->toContain('mail')->toContain('database');
});

it('renders a template with placeholder substitution', function () {
    $template = NotificationTemplate::where('event_key', 'user.registered')
        ->where('locale', 'en')
        ->where('channel', 'mail')
        ->first();

    expect($template)->not->toBeNull();

    $rendered = $template->render([
        'site_name' => 'TestMarket',
        'name'      => 'Ahmed',
    ]);

    expect($rendered)->toContain('Ahmed')
        ->and($rendered)->not->toContain('{{ name }}');
});
