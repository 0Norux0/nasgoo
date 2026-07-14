<?php
declare(strict_types=1);
namespace App\Domain\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    /**
     * Create a new ticket with the customer's opening message.
     *
     * @param array{ticket_type:string, subject:string, body:string,
     *              priority?:string, order_id?:int|null,
     *              booking_id?:int|null, vendor_id?:int|null,
     *              product_id?:int|null} $data
     */
    public function createTicket(User $user, array $data): SupportTicket
    {
        return DB::transaction(function () use ($user, $data) {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'number' => SupportTicket::generateNumber(),
                'ticket_type' => $data['ticket_type'],
                'subject' => $data['subject'],
                'priority' => $data['priority'] ?? SupportTicket::PRIORITY_NORMAL,
                'status' => SupportTicket::STATUS_OPEN,
                'order_id' => $data['order_id'] ?? null,
                'booking_id' => $data['booking_id'] ?? null,
                'vendor_id' => $data['vendor_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'last_replied_at' => Carbon::now(),
            ]);
            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => $data['body'],
                'author_role' => SupportTicketMessage::ROLE_CUSTOMER,
                'is_internal' => false,
                'attachments' => [],
            ]);
            return $ticket->fresh(['messages']);
        });
    }

    /**
     * Post a reply on a ticket and update its status accordingly.
     */
    public function reply(SupportTicket $ticket, User $user, string $body, string $role, bool $isInternal = false): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $user, $body, $role, $isInternal) {
            $msg = SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => $body,
                'author_role' => $role,
                'is_internal' => $isInternal,
                'attachments' => [],
            ]);

            // Status transitions on non-internal replies
            if (! $isInternal) {
                $newStatus = $role === SupportTicketMessage::ROLE_CUSTOMER
                    ? SupportTicket::STATUS_PENDING       // customer replied → awaiting staff
                    : SupportTicket::STATUS_ANSWERED;     // staff replied → answered
                $ticket->update([
                    'status' => $newStatus,
                    'last_replied_at' => Carbon::now(),
                ]);
            }
            return $msg;
        });
    }

    public function updateStatus(SupportTicket $ticket, string $newStatus): SupportTicket
    {
        $attrs = ['status' => $newStatus];
        if ($newStatus === SupportTicket::STATUS_RESOLVED) {
            $attrs['resolved_at'] = Carbon::now();
        } elseif ($newStatus === SupportTicket::STATUS_CLOSED) {
            $attrs['closed_at'] = Carbon::now();
        }
        $ticket->update($attrs);
        return $ticket;
    }
}
