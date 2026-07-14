<?php

declare(strict_types=1);

namespace App\Notifications\Vendor;

use App\Models\NotificationTemplate;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Vendor $vendor,
        public readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = NotificationTemplate::where('event_key', 'vendor.rejected')
            ->where('channel', 'mail')->where('locale', $notifiable->locale ?? 'en')->first()
            ?? NotificationTemplate::where('event_key', 'vendor.rejected')
                ->where('channel', 'mail')->where('locale', 'en')->first();

        $body = $template?->render([
            'business_name' => $this->vendor->business_name,
            'reason'        => $this->reason,
        ]) ?? "Your vendor application was not approved. Reason: {$this->reason}";

        return (new MailMessage)
            ->subject($template?->subject ?? 'Vendor application rejected')
            ->line($body);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event'     => 'vendor.rejected',
            'vendor_id' => $this->vendor->id,
            'reason'    => $this->reason,
        ];
    }
}
