<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_encrypted',
        'is_public',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value'        => 'array', // JSON-encoded in DB
            'is_encrypted' => 'boolean',
            'is_public'    => 'boolean',
        ];
    }

    /**
     * Cast the stored value to the correct PHP type.
     * Decrypts if is_encrypted is true.
     */
    public function typedValue(): mixed
    {
        $raw = $this->value;

        if ($this->is_encrypted && is_string($raw)) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        // value is array-cast; for primitive types we stored ['value' => ...]
        $stored = is_array($raw) && array_key_exists('v', $raw) ? $raw['v'] : $raw;

        return match ($this->type) {
            'integer'  => is_numeric($stored) ? (int) $stored : null,
            'boolean'  => (bool) $stored,
            'array', 'json' => is_array($stored) ? $stored : (json_decode((string) $stored, true) ?: []),
            default    => is_scalar($stored) ? (string) $stored : (is_array($stored) ? json_encode($stored) : null),
        };
    }

    /**
     * Wrap a primitive in the storage envelope.
     * Use: Setting::create(['group'=>'general','key'=>'site_name','value'=>Setting::wrap('Marketplace')])
     */
    public static function wrap(mixed $value): array
    {
        return ['v' => $value];
    }
}
