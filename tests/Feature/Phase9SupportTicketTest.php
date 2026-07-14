<?php
declare(strict_types=1);

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function p9TicketCustomer(string $email = 'p9-ticket-cust@test'): User
{
    return User::factory()->create(['email' => $email, 'role' => 'customer']);
}

it('customer can create a support ticket with an opening message', function () {
    $user = p9TicketCustomer();

    $this->actingAs($user);
    $this->post('/tickets', [
        'ticket_type' => 'general_inquiry',
        'subject' => 'How does shipping work?',
        'body' => 'Hi, what is the average shipping time to Kuwait City?',
        'priority' => 'normal',
    ])->assertRedirect();

    $ticket = SupportTicket::where('user_id', $user->id)->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->number)->toMatch('/^TKT-\d{6}-\d{4}$/');
    expect($ticket->status)->toBe(SupportTicket::STATUS_OPEN);
    expect($ticket->messages()->count())->toBe(1);
    expect($ticket->messages->first()->author_role)->toBe('customer');
});

it('customer can only see their own tickets', function () {
    $alice = p9TicketCustomer('p9-alice@test');
    $bob = p9TicketCustomer('p9-bob@test');

    $aliceTicket = SupportTicket::create([
        'user_id' => $alice->id,
        'number' => 'TKT-' . now()->format('ymd') . '-9001',
        'ticket_type' => 'general_inquiry',
        'subject' => 'Alice ticket',
        'priority' => 'normal',
        'status' => 'open',
    ]);

    // Bob can't view Alice's ticket
    $this->actingAs($bob);
    $this->get("/tickets/{$aliceTicket->id}")->assertForbidden();

    // Alice can
    $this->actingAs($alice);
    $this->get("/tickets/{$aliceTicket->id}")->assertOk();
});

it('customer reply flips ticket status to pending; admin reply flips it to answered', function () {
    $customer = p9TicketCustomer('p9-flip-cust@test');
    $admin = User::factory()->create(['email' => 'p9-flip-admin@test', 'role' => 'admin']);

    $ticket = SupportTicket::create([
        'user_id' => $customer->id,
        'number' => 'TKT-' . now()->format('ymd') . '-9002',
        'ticket_type' => 'general_inquiry',
        'subject' => 'Test status flips',
        'priority' => 'normal',
        'status' => 'answered',     // simulate admin already replied
    ]);

    // Customer replies → status should become 'pending'
    $this->actingAs($customer);
    $this->post("/tickets/{$ticket->id}/reply", ['body' => 'Thanks but I have another question.'])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_PENDING);

    // Now use SupportTicketService directly (admin replies)
    $svc = app(\App\Domain\Support\SupportTicketService::class);
    $svc->reply($ticket->fresh(), $admin, 'Sure, ask away.', 'admin', false);

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_ANSWERED);
});

it('customer can close their own ticket', function () {
    $customer = p9TicketCustomer('p9-close-cust@test');
    $ticket = SupportTicket::create([
        'user_id' => $customer->id,
        'number' => 'TKT-' . now()->format('ymd') . '-9003',
        'ticket_type' => 'general_inquiry',
        'subject' => 'Closable',
        'priority' => 'normal',
        'status' => 'answered',
    ]);

    $this->actingAs($customer);
    $this->post("/tickets/{$ticket->id}/close")->assertRedirect();

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_CLOSED);
    expect($ticket->fresh()->closed_at)->not->toBeNull();
});

it('vendor only sees tickets assigned to their vendor', function () {
    // Two vendors
    $vendorUser1 = User::factory()->create(['email' => 'p9-vsee-1@test', 'role' => 'vendor']);
    $vendor1 = Vendor::factory()->create(['user_id' => $vendorUser1->id, 'status' => 'approved']);
    $vendorUser2 = User::factory()->create(['email' => 'p9-vsee-2@test', 'role' => 'vendor']);
    $vendor2 = Vendor::factory()->create(['user_id' => $vendorUser2->id, 'status' => 'approved']);

    $customer = p9TicketCustomer('p9-vsee-cust@test');

    $myTicket = SupportTicket::create([
        'user_id' => $customer->id,
        'number' => 'TKT-' . now()->format('ymd') . '-9101',
        'ticket_type' => 'vendor_complaint',
        'subject' => 'For vendor1',
        'priority' => 'normal',
        'status' => 'open',
        'vendor_id' => $vendor1->id,
    ]);

    $otherTicket = SupportTicket::create([
        'user_id' => $customer->id,
        'number' => 'TKT-' . now()->format('ymd') . '-9102',
        'ticket_type' => 'vendor_complaint',
        'subject' => 'For vendor2',
        'priority' => 'normal',
        'status' => 'open',
        'vendor_id' => $vendor2->id,
    ]);

    // Vendor 1 can view their own
    $this->actingAs($vendorUser1);
    $this->get("/vendor/tickets/{$myTicket->id}")->assertOk();
    // ...but NOT vendor 2's
    $this->get("/vendor/tickets/{$otherTicket->id}")->assertForbidden();
});

it('generateNumber always produces a unique TKT- string', function () {
    $n1 = SupportTicket::generateNumber();
    expect($n1)->toMatch('/^TKT-\d{6}-\d{4}$/');

    SupportTicket::create([
        'user_id' => p9TicketCustomer('p9-gen-1@test')->id,
        'number' => $n1,
        'ticket_type' => 'general_inquiry',
        'subject' => 'Holder',
        'priority' => 'normal',
        'status' => 'open',
    ]);

    $n2 = SupportTicket::generateNumber();
    expect($n2)->not->toBe($n1);
});
