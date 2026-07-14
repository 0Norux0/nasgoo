<?php

declare(strict_types=1);

namespace App\Domain\Customization;

use App\Models\Product;
use App\Models\ProductCustomizationField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Phase 7 — CustomizationFieldValidator.
 *
 * Walks the active customization fields for a product and validates the
 * customer-submitted values against each one. Returns a normalized
 * payload ready to be persisted as cart_item_customizations.
 *
 * Inputs:
 *   $textInputs  — assoc map: field_key => string|null|bool (from the form)
 *   $fileInputs  — assoc map: field_key => UploadedFile (from $request->file())
 *
 * Output (per active field):
 *   [
 *     'field' => ProductCustomizationField,
 *     'value' => ?string,
 *     'file'  => ?UploadedFile,
 *     'extra_fee_minor' => int,   // includes option-level extra fee
 *   ]
 *
 * Throws ValidationException with `customizations.{key}` error keys on any
 * required-field omission, type mismatch, file size/mime violation, or
 * length overrun. Errors use the field's `label` so the customer sees a
 * human-readable message.
 */
class CustomizationFieldValidator
{
    public function validate(Product $product, array $textInputs, array $fileInputs): Collection
    {
        $errors = [];
        $normalized = collect();

        /** @var \Illuminate\Database\Eloquent\Collection<int, ProductCustomizationField> $fields */
        $fields = $product->activeCustomizationFields()->get();

        foreach ($fields as $field) {
            $key = "customizations.{$field->key}";
            $value = null;
            $file = null;
            $extraFee = (int) $field->extra_fee_minor;

            if ($field->isFileField()) {
                $file = $fileInputs[$field->key] ?? null;

                if ($field->required && ! $file) {
                    $errors[$key] = [sprintf('%s is required.', $field->label)];
                    continue;
                }
                if ($file instanceof UploadedFile) {
                    $this->validateFile($field, $file, $key, $errors);
                }
            } elseif ($field->isTextField()) {
                $raw = $textInputs[$field->key] ?? null;
                $value = is_string($raw) ? trim($raw) : null;

                if ($field->required && ($value === null || $value === '')) {
                    $errors[$key] = [sprintf('%s is required.', $field->label)];
                    continue;
                }
                if ($value !== null && $value !== '' && $field->max_text_length && mb_strlen($value) > $field->max_text_length) {
                    $errors[$key] = [sprintf('%s cannot exceed %d characters.', $field->label, $field->max_text_length)];
                    continue;
                }
            } elseif ($field->isSelectionField()) {
                $raw = $textInputs[$field->key] ?? null;
                $value = is_string($raw) || is_numeric($raw) ? (string) $raw : null;

                if ($field->required && ($value === null || $value === '')) {
                    $errors[$key] = [sprintf('%s is required.', $field->label)];
                    continue;
                }
                if ($value !== null && $value !== '') {
                    $option = $this->findOption($field, $value);
                    if (! $option) {
                        $errors[$key] = [sprintf('%s: "%s" is not a valid option.', $field->label, $value)];
                        continue;
                    }
                    if (isset($option['extra_fee']) && is_numeric($option['extra_fee'])) {
                        $extraFee += (int) $option['extra_fee'];
                    }
                }
            } elseif ($field->type === ProductCustomizationField::TYPE_CHECKBOX) {
                $raw = $textInputs[$field->key] ?? null;
                $checked = $raw === true || $raw === '1' || $raw === 1 || $raw === 'true' || $raw === 'on';
                $value = $checked ? '1' : '0';

                if ($field->required && ! $checked) {
                    $errors[$key] = [sprintf('%s is required.', $field->label)];
                    continue;
                }
                if (! $checked) {
                    $extraFee = 0;  // unchecked optional checkbox shouldn't bill
                }
            }

            // Skip empty optional fields entirely (no customization row, no fee)
            if (! $field->required && $value === null && $file === null) {
                continue;
            }

            $normalized->push([
                'field'           => $field,
                'value'           => $value,
                'file'            => $file,
                'extra_fee_minor' => $extraFee,
            ]);
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    private function validateFile(ProductCustomizationField $field, UploadedFile $file, string $errKey, array &$errors): void
    {
        // Size check (kilobytes)
        $maxKb = $field->max_file_size_kb ?? 5120; // default 5 MB
        if (($file->getSize() ?: 0) > $maxKb * 1024) {
            $errors[$errKey] = [sprintf('%s file must be %d KB or smaller.', $field->label, $maxKb)];
            return;
        }

        // Extension check
        $allowed = $field->allowed_file_types ?: ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, $allowed, true)) {
            $errors[$errKey] = [sprintf('%s file type "%s" not allowed. Allowed: %s.', $field->label, $ext, implode(', ', $allowed))];
            return;
        }

        // MIME check — guard against renamed executables
        if (! $this->isSafeMime($file->getMimeType() ?: '', $allowed)) {
            $errors[$errKey] = [sprintf('%s file content does not match its extension.', $field->label)];
            return;
        }
    }

    private function isSafeMime(string $mime, array $allowedExts): bool
    {
        $extMimeMap = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'gif'  => ['image/gif'],
            'svg'  => ['image/svg+xml', 'text/xml', 'application/xml'],
            'pdf'  => ['application/pdf'],
        ];
        // Block obviously dangerous MIMEs regardless of extension
        $banned = ['application/x-msdownload', 'application/x-sh', 'application/x-executable', 'text/x-php', 'application/x-php'];
        if (in_array($mime, $banned, true)) {
            return false;
        }
        // Allow only MIMEs that match an allowed extension
        $allowedMimes = [];
        foreach ($allowedExts as $ext) {
            $allowedMimes = array_merge($allowedMimes, $extMimeMap[strtolower($ext)] ?? []);
        }
        return in_array($mime, $allowedMimes, true);
    }

    /** @return ?array{value: string, label: string, extra_fee?: int} */
    private function findOption(ProductCustomizationField $field, string $value): ?array
    {
        foreach ($field->options ?? [] as $opt) {
            if (! is_array($opt)) continue;
            if (($opt['value'] ?? null) == $value) {
                return $opt;
            }
        }
        return null;
    }
}
