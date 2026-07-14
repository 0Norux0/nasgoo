<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PromotionTarget extends Model
{
    use HasFactory;
    protected $fillable = ['promotion_id', 'targetable_type', 'targetable_id'];
    public function promotion(): BelongsTo { return $this->belongsTo(Promotion::class); }
    public function targetable(): MorphTo { return $this->morphTo(); }
}
