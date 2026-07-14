<?php

declare(strict_types=1);

namespace App\Notifications\Vendor;

use App\Models\NotificationTemplate;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Vendor $vendor) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $template = $this->template('mail', $notifiable->locale ?? 'en');
        $body     = $template ? $template->render(['business_name' => $this->vendor->business_name]) : 'Your vendor account has been approved.';
        $subject  = $template?->subject ?? 'Your vendor account is approved';

        return (new MailMessage)
            ->subject($subject)
            ->line($body)
            ->action('Open vendor dashboard', url('/vendor'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $template = $this->template('database', $notifiable->locale ?? 'en');
        return [
            'event'   => 'vendor.approved',
            'vendor_id' => $this->vendor->id,
            'message' => $template?->render(['business_name' => $this->vendor->business_name])
                ?? 'Your vendor account has been approved.',
        ];
    }

    private function template(string $channel, string $locale): ?NotificationTemplate
    {
        return NotificationTemplate::where('event_key', 'vendor.approved')
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->first()
            ?? NotificationTemplate::where('event_key', 'vendor.approved')
                ->where('channel', $channel)
                ->where('locale', 'en')
                ->first();
    }
}
