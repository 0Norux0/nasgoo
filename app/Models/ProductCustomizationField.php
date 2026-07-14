<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $key
 * @property string $label
 * @property ?array $label_translations
 * @property string $type
 * @property bool $required
 * @property int $sort_order
 * @property ?array $allowed_file_types
 * @property ?int $max_file_size_kb
 * @property ?int $max_text_length
 * @property ?array $options
 * @property int $extra_fee_minor
 * @property ?string $placeholder
 * @property ?string $helper_text
 * @property bool $is_active
 */
class ProductCustomizationField extends Model
{
    use HasFactory;

    public const TYPE_IMAGE     = 'image';
    public const TYPE_TEXT      = 'text';
    public const TYPE_TEXTAREA  = 'textarea';
    public const TYPE_COLOR     = 'color';
    public const TYPE_FONT      = 'font';
    public const TYPE_PLACEMENT = 'placement';
    public const TYPE_DROPDOWN  = 'dropdown';
    public const TYPE_SIZE      = 'size';
    public const TYPE_CHECKBOX  = 'checkbox';

    public const ALL_TYPES = [
        self::TYPE_IMAGE, self::TYPE_TEXT, self::TYPE_TEXTAREA,
        self::TYPE_COLOR, self::TYPE_FONT, self::TYPE_PLACEMENT,
        self::TYPE_DROPDOWN, self::TYPE_SIZE, self::TYPE_CHECKBOX,
    ];

    public const FILE_TYPES = [self::TYPE_IMAGE];
    public const TEXT_TYPES = [self::TYPE_TEXT, self::TYPE_TEXTAREA];
    public const SELECTION_TYPES = [self::TYPE_COLOR, self::TYPE_FONT, self::TYPE_PLACEMENT, self::TYPE_DROPDOWN, self::TYPE_SIZE];

    protected $fillable = [
        'product_id', 'key', 'label', 'label_translations',
        'type', 'required', 'sort_order',
        'allowed_file_types', 'max_file_size_kb', 'max_text_length',
        'options', 'extra_fee_minor',
        'placeholder', 'helper_text', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'label_translations'   => 'array',
            'allowed_file_types'   => 'array',
            'options'              => 'array',
            'required'             => 'boolean',
            'is_active'            => 'boolean',
            'sort_order'           => 'integer',
            'max_file_size_kb'     => 'integer',
            'max_text_length'      => 'integer',
            'extra_fee_minor'      => 'integer',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function isFileField(): bool      { return in_array($this->type, self::FILE_TYPES, true); }
    public function isTextField(): bool      { return in_array($this->type, self::TEXT_TYPES, true); }
    public function isSelectionField(): bool { return in_array($this->type, self::SELECTION_TYPES, true); }
}
