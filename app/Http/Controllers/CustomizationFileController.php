<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customization\CustomizationFileStorage;
use App\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 7 — customer-facing secure file access.
 *
 * Customer can download:
 *   - any customization file they uploaded against their own order_item
 *   - any proof file attached to their own order_item
 *
 * Scoping is enforced by walking from Order::where('user_id', auth()->id())
 * down to the specific row. Files live on the PRIVATE disk and are NEVER
 * exposed via /storage/...; this is the only path to them for customers.
 */
class CustomizationFileController extends Controller
{
    public function __construct(protected CustomizationFileStorage $storage) {}

    public function show(Request $request, int $orderId, int $itemId, string $kind, int $rowId): StreamedResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($orderId);
        $item  = $order->items()->findOrFail($itemId);

        if ($kind === 'customization') {
            $row = $item->customizations()->findOrFail($rowId);
            $path = $row->file_path;
            $name = $row->file_original_name ?? 'download';
        } elseif ($kind === 'proof') {
            $row = $item->proofs()->findOrFail($rowId);
            $path = $row->file_path;
            $name = $row->file_original_name;
        } else {
            abort(404);
        }
        abort_if(! $path, 404);

        $stream = $this->storage->readStream($path);
        abort_if($stream === null, 404);

        $mime = $this->storage->mimeTypeOf($path) ?? 'application/octet-stream';
        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => 'inline; filename="' . addslashes($name) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
