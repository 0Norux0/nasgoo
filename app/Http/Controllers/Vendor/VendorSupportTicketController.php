<?php
declare(strict_types=1);
namespace App\Http\Controllers\Vendor;

use App\Domain\Support\SupportTicketService;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorSupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);
        $tickets = SupportTicket::where('vendor_id', $vendor->id)
            ->with(['user:id,name,email', 'order:id,number'])
            ->latest()
            ->paginate(20);
        return Inertia::render('Vendor/Tickets/Index', [
            'tickets' => $tickets,
            'statuses' => SupportTicket::STATUSES,
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor && $ticket->vendor_id === $vendor->id, 403);
        $ticket->load(['messages.user:id,name', 'user:id,name,email', 'order:id,number']);
        return Inertia::render('Vendor/Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, SupportTicketService $svc): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor && $ticket->vendor_id === $vendor->id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'min:1']]);
        $svc->reply($ticket, $request->user(), $data['body'], 'vendor', false);
        // v10.11 §4 — explicit redirect (see SupportTicketController::reply)
        return redirect("/vendor/tickets/{$ticket->id}")->with('success', 'Reply posted.');
    }
}
