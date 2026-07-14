<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'provider', 'name', 'name_translations', 'description', 'description_translations',
        'is_active', 'position', 'available_at_checkout',
        'config', 'supported_currencies',
    ];

    protected function casts(): array
    {
        return [
            'name_translations'        => 'array',
            'description_translations' => 'array',
            'config'                   => 'array',
            'supported_currencies'     => 'array',
            'is_active'                => 'boolean',
            'available_at_checkout'    => 'boolean',
            'position'                 => 'integer',
        ];
    }

    public function supportsCurrency(string $currency): bool
    {
        if (empty($this->supported_currencies)) return true;
        return in_array($currency, $this->supported_currencies, true);
    }

    public function translatedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        return $this->name_translations[$locale] ?? $this->name;
    }
}
