<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customization\ProofWorkflowService;
use App\Models\CustomizationProof;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 7 — customer responds to a proof attached to their own order.
 *
 * Scoping: lookup uses Order::where('user_id', auth()->id())->findOrFail($orderId)
 * then $order->items()->findOrFail($itemId)->proofs()->findOrFail($proofId).
 * Other customers' proofs return 404.
 *
 * Only sent proofs can be responded to. Draft/already-approved/already-rejected
 * proofs return a friendly error.
 */
class CustomerProofResponseController extends Controller
{
    public function __construct(protected ProofWorkflowService $proofs) {}

    public function approve(Request $request, int $orderId, int $itemId, int $proofId): RedirectResponse
    {
        $proof = $this->locate($request, $orderId, $itemId, $proofId);
        if ($proof->status !== CustomizationProof::STATUS_SENT) {
            return back()->with('error', 'This proof can no longer be approved (status: ' . $proof->status . ').');
        }
        $data = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);
        $this->proofs->approve($proof, $data['note'] ?? null);
        return back()->with('success', 'Proof approved. Your vendor can now start production.');
    }

    public function reject(Request $request, int $orderId, int $itemId, int $proofId): RedirectResponse
    {
        $proof = $this->locate($request, $orderId, $itemId, $proofId);
        if ($proof->status !== CustomizationProof::STATUS_SENT) {
            return back()->with('error', 'This proof can no longer be rejected (status: ' . $proof->status . ').');
        }
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);
        $this->proofs->reject($proof, $data['reason']);
        return back()->with('success', 'Proof rejected. Your vendor has been notified.');
    }

    private function locate(Request $request, int $orderId, int $itemId, int $proofId): CustomizationProof
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($orderId);
        $item  = $order->items()->findOrFail($itemId);
        return $item->proofs()->findOrFail($proofId);
    }
}
