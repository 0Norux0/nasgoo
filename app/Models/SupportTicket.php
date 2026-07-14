<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SupportTicket extends Model
{
    use HasFactory;
    public const TYPE_ORDER_ISSUE = 'order_issue';
    public const TYPE_BOOKING_ISSUE = 'booking_issue';
    public const TYPE_PAYMENT_ISSUE = 'payment_issue';
    public const TYPE_PRODUCT_ISSUE = 'product_issue';
    public const TYPE_VENDOR_COMPLAINT = 'vendor_complaint';
    public const TYPE_REFUND_REQUEST = 'refund_request';
    public const TYPE_GENERAL_INQUIRY = 'general_inquiry';

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const STATUSES = ['open', 'pending', 'answered', 'resolved', 'closed'];

    protected $fillable = [
        'user_id', 'number', 'ticket_type',
        'order_id', 'booking_id', 'vendor_id', 'product_id',
        'subject', 'priority', 'status', 'assigned_to',
        'last_replied_at', 'resolved_at', 'closed_at',
    ];

    protected $casts = [
        'last_replied_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function booking(): BelongsTo { return $this->belongsTo(ServiceBooking::class, 'booking_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages(): HasMany { return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at'); }

    public static function generateNumber(): string
    {
        // TKT-yymmdd-NNNN, where NNNN is random (collision-checked at insert time)
        $prefix = 'TKT-' . Carbon::now()->format('ymd');
        for ($i = 0; $i < 5; $i++) {
            $candidate = $prefix . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (! self::where('number', $candidate)->exists()) return $candidate;
        }
        throw new \RuntimeException('Could not generate unique ticket number');
    }

    public function scopeForUser(Builder $q, User $u): Builder { return $q->where('user_id', $u->id); }
    public function scopeForVendor(Builder $q, int $vendorId): Builder { return $q->where('vendor_id', $vendorId); }
}
