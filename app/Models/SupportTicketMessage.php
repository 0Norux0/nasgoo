<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    use HasFactory;
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_VENDOR = 'vendor';

    protected $fillable = ['support_ticket_id', 'user_id', 'body', 'author_role', 'is_internal', 'attachments'];
    protected $casts = ['is_internal' => 'boolean', 'attachments' => 'array'];

    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class, 'support_ticket_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
