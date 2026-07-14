<?php

declare(strict_types=1);

namespace App\Domain\Customization;

use App\Models\CustomizationProof;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7 — ProofWorkflowService.
 *
 * Foundation for the design-proof approval loop:
 *   vendor uploads a proof → vendor sends → customer approves OR rejects
 *
 * Each upload creates a new CustomizationProof row attached to the
 * order_item. The order_item's customization_status is advanced
 * automatically:
 *   draft proof saved          → customization_status stays the same
 *   proof sent to customer     → customization_status = proof_uploaded
 *   customer approves          → customization_status = customer_approved
 *   customer rejects           → customization_status = customer_rejected
 */
class ProofWorkflowService
{
    public function __construct(protected CustomizationFileStorage $storage) {}

    public function uploadDraft(OrderItem $item, User $vendorUser, UploadedFile $file, ?string $note = null): CustomizationProof
    {
        $vendorId = $item->vendor_id;
        $path = $this->storage->storeVendorProof($file, $vendorId, $item->id);

        return CustomizationProof::create([
            'order_item_id'      => $item->id,
            'vendor_id'          => $vendorId,
            'file_path'          => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime'          => $file->getMimeType() ?: 'application/octet-stream',
            'file_size_bytes'    => (int) ($file->getSize() ?: 0),
            'status'             => CustomizationProof::STATUS_DRAFT,
            'vendor_note'        => $note,
        ]);
    }

    public function send(CustomizationProof $proof): CustomizationProof
    {
        return DB::transaction(function () use ($proof) {
            $proof->update([
                'status'  => CustomizationProof::STATUS_SENT,
                'sent_at' => now(),
            ]);
            $proof->orderItem?->update([
                'customization_status' => OrderItem::CUST_PROOF_UPLOADED,
            ]);
            return $proof->fresh();
        });
    }

    public function approve(CustomizationProof $proof, ?string $customerNote = null): CustomizationProof
    {
        return DB::transaction(function () use ($proof, $customerNote) {
            $proof->update([
                'status'            => CustomizationProof::STATUS_APPROVED,
                'customer_response' => $customerNote,
                'responded_at'      => now(),
            ]);
            $proof->orderItem?->update([
                'customization_status' => OrderItem::CUST_CUSTOMER_APPROVED,
            ]);
            return $proof->fresh();
        });
    }

    public function reject(CustomizationProof $proof, string $customerReason): CustomizationProof
    {
        return DB::transaction(function () use ($proof, $customerReason) {
            $proof->update([
                'status'            => CustomizationProof::STATUS_REJECTED,
                'customer_response' => $customerReason,
                'responded_at'      => now(),
            ]);
            $proof->orderItem?->update([
                'customization_status' => OrderItem::CUST_CUSTOMER_REJECTED,
            ]);
            return $proof->fresh();
        });
    }
}
