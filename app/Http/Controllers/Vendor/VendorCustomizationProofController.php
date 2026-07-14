<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Customization\CustomizationFileStorage;
use App\Domain\Customization\ProofWorkflowService;
use App\Http\Controllers\Controller;
use App\Models\CustomizationProof;
use App\Models\OrderItem;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 7 — vendor proof workflow + secure file access for vendor side.
 *
 * Vendor can:
 *   - upload a proof draft against an order_item they own
 *   - send the proof to the customer (advances order_item status to proof_uploaded)
 *   - download any customer-uploaded customization file for that order_item
 *
 * Scoping: every action looks up the OrderItem via
 * `OrderItem::where('vendor_id', $vendor->id)->findOrFail(...)`. Customer
 * uploads + proofs live on the PRIVATE disk; access is only via the
 * controllers (vendor: this controller; customer: CustomizationFileController).
 */
class VendorCustomizationProofController extends Controller
{
    public function __construct(
        protected ProofWorkflowService $proofs,
        protected CustomizationFileStorage $storage,
    ) {}

    public function upload(Request $request, int $orderItemId): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $orderItem = OrderItem::where('vendor_id', $vendor->id)->findOrFail($orderItemId);

        $data = $request->validate([
            'file'        => 'required|file|mimetypes:image/jpeg,image/png,image/webp,application/pdf|max:10240', // 10MB
            'vendor_note' => 'nullable|string|max:1000',
            'send_now'    => 'nullable|boolean',
        ]);

        $proof = $this->proofs->uploadDraft(
            item: $orderItem,
            vendorUser: $request->user(),
            file: $data['file'],
            note: $data['vendor_note'] ?? null,
        );

        if ($data['send_now'] ?? false) {
            $this->proofs->send($proof);
        }

        return back()->with('success', 'Proof uploaded' . (($data['send_now'] ?? false) ? ' and sent to customer.' : ' as a draft.'));
    }

    public function send(Request $request, int $orderItemId, int $proofId): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $orderItem = OrderItem::where('vendor_id', $vendor->id)->findOrFail($orderItemId);
        $proof = $orderItem->proofs()->findOrFail($proofId);

        if (! in_array($proof->status, [CustomizationProof::STATUS_DRAFT, CustomizationProof::STATUS_REJECTED], true)) {
            return back()->with('error', 'This proof cannot be sent in its current state.');
        }

        $this->proofs->send($proof);
        return back()->with('success', 'Proof sent to customer for review.');
    }

    /**
     * Vendor downloads any private-disk file related to an order_item they own:
     *   customer-uploaded customization file, or a vendor-uploaded proof.
     * Access is enforced by the order_item scope.
     */
    public function downloadFile(Request $request, int $orderItemId, string $kind, int $rowId): StreamedResponse
    {
        $vendor = $this->vendor($request);
        $orderItem = OrderItem::where('vendor_id', $vendor->id)->findOrFail($orderItemId);

        if ($kind === 'customization') {
            $row = $orderItem->customizations()->findOrFail($rowId);
            $path = $row->file_path;
            $name = $row->file_original_name ?? 'download';
        } elseif ($kind === 'proof') {
            $row = $orderItem->proofs()->findOrFail($rowId);
            $path = $row->file_path;
            $name = $row->file_original_name;
        } else {
            abort(404);
        }

        if (! $path) abort(404);

        $stream = $this->storage->readStream($path);
        abort_if($stream === null, 404);

        $mime = $this->storage->mimeTypeOf($path) ?? 'application/octet-stream';
        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function vendor(Request $request): Vendor
    {
        /** @var Vendor $v */
        $v = $request->attributes->get('vendor');
        if (! $v) {
            throw new \Symfony\Component\HttpFoundation\Exception\BadRequestException('Vendor context not resolved.');
        }
        return $v;
    }
}
