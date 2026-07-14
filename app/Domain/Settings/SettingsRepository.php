<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class SettingsRepository
{
    private const CACHE_PREFIX = 'setting:';
    private const CACHE_TTL = 600;

    /**
     * Read a setting value. Returns $default if not found.
     */
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX."{$group}:{$key}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($group, $key, $default) {
            $setting = Setting::where('group', $group)->where('key', $key)->first();
            return $setting ? $setting->typedValue() : $default;
        });
    }

    /**
     * Write a setting. Wraps the value in the storage envelope.
     */
    public function set(string $group, string $key, mixed $value, string $type = 'string'): Setting
    {
        $setting = Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => Setting::wrap($value), 'type' => $type],
        );

        Cache::forget(self::CACHE_PREFIX."{$group}:{$key}");

        return $setting;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return Cache::remember(
            self::CACHE_PREFIX."group:{$group}",
            self::CACHE_TTL,
            fn () => Setting::where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->toArray()
        );
    }

    public function forget(string $group, string $key): void
    {
        Setting::where('group', $group)->where('key', $key)->delete();
        Cache::forget(self::CACHE_PREFIX."{$group}:{$key}");
        Cache::forget(self::CACHE_PREFIX."group:{$group}");
    }

    public function flushCache(): void
    {
        Cache::flush();
    }
}
