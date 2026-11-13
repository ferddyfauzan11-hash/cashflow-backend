<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PocketAllocation\StorePocketAllocationRequest;
use App\Models\Pocket;
use App\Models\PocketAllocation;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PocketAllocationController extends Controller
{
    /**
     * GET /api/pocket-allocations
     * List allocations with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PocketAllocation::where('user_id', $request->user()->id)
            ->active()
            ->with(['fromPocket', 'toPocket']);

        if ($request->has('pocket_id')) {
            $pocketId = $request->pocket_id;
            $query->where(function ($q) use ($pocketId) {
                $q->where('from_pocket_id', $pocketId)
                  ->orWhere('to_pocket_id', $pocketId);
            });
        }

        if ($request->has('start_date')) {
            $query->where('allocation_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('allocation_date', '<=', $request->end_date);
        }

        $allocations = $query->orderByDesc('allocation_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $allocations]);
    }

    /**
     * POST /api/pocket-allocations
     * Transfer dana antar pocket.
     */
    public function store(StorePocketAllocationRequest $request): JsonResponse
    {
        $fromPocketId = $request->from_pocket_id;
        $toPocketId = $request->to_pocket_id;
        $amount = (float) $request->amount;
        $userId = $request->user()->id;

        // Validasi: from dan to tidak boleh sama
        if ($fromPocketId === $toPocketId) {
            return response()->json([
                'message' => 'Pocket asal dan tujuan tidak boleh sama.',
            ], 422);
        }

        // Validasi: minimal salah satu harus ada (bukan Unallocated→Unallocated)
        if ($fromPocketId === null && $toPocketId === null) {
            return response()->json([
                'message' => 'Minimal salah satu pocket harus dipilih.',
            ], 422);
        }

        return DB::transaction(function () use ($fromPocketId, $toPocketId, $amount, $userId, $request) {
            // Validasi & update source pocket
            if ($fromPocketId !== null) {
                $fromPocket = Pocket::where('id', $fromPocketId)
                    ->where('user_id', $userId)
                    ->active()
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((float) $fromPocket->current_balance < $amount) {
                    return response()->json([
                        'message' => 'Saldo pocket tidak cukup.',
                    ], 422);
                }

                $fromPocket->decrement('current_balance', $amount);
            } else {
                // From Unallocated — cek saldo Unallocated
                $unallocatedBalance = $this->getUnallocatedBalance($userId);
                if ($unallocatedBalance < $amount) {
                    return response()->json([
                        'message' => 'Saldo Unallocated tidak cukup.',
                    ], 422);
                }
            }

            // Update destination pocket
            if ($toPocketId !== null) {
                $toPocket = Pocket::where('id', $toPocketId)
                    ->where('user_id', $userId)
                    ->active()
                    ->lockForUpdate()
                    ->firstOrFail();

                $toPocket->increment('current_balance', $amount);
            }

            // Create allocation record
            $allocation = PocketAllocation::create([
                'user_id' => $userId,
                'from_pocket_id' => $fromPocketId,
                'to_pocket_id' => $toPocketId,
                'amount' => $amount,
                'note' => $request->note,
                'allocation_date' => $request->allocation_date,
            ]);

            $allocation->load(['fromPocket', 'toPocket']);

            return response()->json(['data' => $allocation], 201);
        });
    }

    /**
     * DELETE /api/pocket-allocations/{id}
     * Soft delete — revert balances.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $allocation = PocketAllocation::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->firstOrFail();

        DB::transaction(function () use ($allocation) {
            // Revert source pocket balance
            if ($allocation->from_pocket_id !== null) {
                $fromPocket = Pocket::find($allocation->from_pocket_id);
                if ($fromPocket && !$fromPocket->is_deleted) {
                    $fromPocket->increment('current_balance', $allocation->amount);
                }
            }

            // Revert destination pocket balance
            if ($allocation->to_pocket_id !== null) {
                $toPocket = Pocket::find($allocation->to_pocket_id);
                if ($toPocket && !$toPocket->is_deleted) {
                    $toPocket->decrement('current_balance', $allocation->amount);
                }
            }

            $allocation->update([
                'is_deleted' => true,
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Transfer dibatalkan.']);
    }

    /**
     * Calculate Unallocated balance.
     * Unallocated = total_income - total_expense - sum(pocket_balances)
     */
    private function getUnallocatedBalance(string $userId): float
    {
        $totalIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('is_deleted', false)
            ->sum('amount');

        $totalExpense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_deleted', false)
            ->sum('amount');

        $totalPocketBalance = Pocket::where('user_id', $userId)
            ->where('is_deleted', false)
            ->sum('current_balance');

        return (float) $totalIncome - (float) $totalExpense - (float) $totalPocketBalance;
    }
}
