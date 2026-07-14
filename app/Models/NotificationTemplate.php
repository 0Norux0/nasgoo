<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'event_key',
        'channel',
        'locale',
        'subject',
        'body',
        'placeholders',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_active'    => 'boolean',
        ];
    }

    /**
     * Render the template body with placeholders replaced.
     *
     * @param array<string, scalar|null> $variables
     */
    public function render(array $variables = []): string
    {
        $body = $this->body;
        foreach ($variables as $key => $value) {
            $body = str_replace('{{ '.$key.' }}', (string) $value, $body);
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
        }
        return $body;
    }

    /** @return array<int, string> */
    public static function supportedEventKeys(): array
    {
        return [
            'user.registered',
            'user.email_verification',
            'password.reset',
            'vendor.approved',
            'vendor.rejected',
            'product.approved',
            'product.rejected',
            'order.placed',
            'order.confirmed',
            'order.shipped',
            'order.delivered',
            'booking.created',
            'booking.confirmed',
            'payout.requested',
            'payout.approved',
            // Phase 2 — vendor system
            'vendor.application_submitted',
            'vendor.suspended',
            'vendor.subscription_activated',
            'vendor.package_changed',
            'vendor.commission_changed',
        ];
    }

    /** @return array<int, string> */
    public static function supportedChannels(): array
    {
        return ['mail', 'database', 'sms', 'whatsapp', 'push'];
    }
}
