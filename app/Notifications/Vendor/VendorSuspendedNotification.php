<?php

declare(strict_types=1);

namespace App\Notifications\Vendor;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorSuspendedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Vendor $vendor,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->reason
            ? "Your vendor account has been suspended. Reason: {$this->reason}"
            : 'Your vendor account has been suspended. Contact support for details.';

        return (new MailMessage)
            ->subject('Vendor account suspended')
            ->line($line);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event'     => 'vendor.suspended',
            'vendor_id' => $this->vendor->id,
            'reason'    => $this->reason,
        ];
    }
}
