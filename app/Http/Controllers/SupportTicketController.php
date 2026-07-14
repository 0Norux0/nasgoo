<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Domain\Support\SupportTicketService;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    /** GET /tickets — customer's ticket list */
    public function index(Request $request): Response
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->with(['order:id,number', 'vendor:id,business_name'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'statuses' => SupportTicket::STATUSES,
        ]);
    }

    /** GET /tickets/create */
    public function create(Request $request): Response
    {
        return Inertia::render('Tickets/Create', [
            'types' => [
                ['value' => SupportTicket::TYPE_ORDER_ISSUE, 'label' => 'Order issue'],
                ['value' => SupportTicket::TYPE_BOOKING_ISSUE, 'label' => 'Booking issue'],
                ['value' => SupportTicket::TYPE_PAYMENT_ISSUE, 'label' => 'Payment issue'],
                ['value' => SupportTicket::TYPE_PRODUCT_ISSUE, 'label' => 'Product issue'],
                ['value' => SupportTicket::TYPE_VENDOR_COMPLAINT, 'label' => 'Vendor complaint'],
                ['value' => SupportTicket::TYPE_REFUND_REQUEST, 'label' => 'Refund request'],
                ['value' => SupportTicket::TYPE_GENERAL_INQUIRY, 'label' => 'General inquiry'],
            ],
            'priorities' => [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'high', 'label' => 'High'],
                ['value' => 'urgent', 'label' => 'Urgent'],
            ],
        ]);
    }

    /** POST /tickets */
    public function store(Request $request, SupportTicketService $svc): RedirectResponse
    {
        $data = $request->validate([
            'ticket_type' => ['required', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:5'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'booking_id' => ['nullable', 'integer', 'exists:service_bookings,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);
        $ticket = $svc->createTicket($request->user(), $data);
        return redirect("/tickets/{$ticket->id}")->with('success', "Ticket #{$ticket->number} created.");
    }

    /** GET /tickets/{ticket} */
    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $ticket->load(['messages.user:id,name', 'order:id,number', 'vendor:id,business_name']);
        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }

    /** POST /tickets/{ticket}/reply */
    /** POST /tickets/{ticket}/reply */
    public function reply(Request $request, SupportTicket $ticket, SupportTicketService $svc): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'min:1']]);
        $svc->reply($ticket, $request->user(), $data['body'], 'customer', false);
        // v10.11 §4 — explicit redirect to show URL (not back()). back()
        // relies on Referer / URL::previous() which can be ambiguous when
        // Inertia handles redirects via XHR; explicit redirect guarantees
        // show() runs and eager-loads messages.user. Belt-and-suspenders for
        // the lazy-load violation the dev reported.
        return redirect("/tickets/{$ticket->id}")->with('success', 'Reply posted.');
    }

    /** POST /tickets/{ticket}/close — customer closes their own ticket */
    public function close(Request $request, SupportTicket $ticket, SupportTicketService $svc): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $svc->updateStatus($ticket, SupportTicket::STATUS_CLOSED);
        return back()->with('success', 'Ticket closed.');
    }
}
