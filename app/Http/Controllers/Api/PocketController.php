<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pocket\StorePocketRequest;
use App\Http\Requests\Pocket\UpdatePocketRequest;
use App\Models\Pocket;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PocketController extends Controller
{
    /**
     * GET /api/pockets
     * List semua pocket user (ordered by order_index).
     */
    public function index(Request $request): JsonResponse
    {
        $pockets = Pocket::where('user_id', $request->user()->id)
            ->active()
            ->orderBy('order_index')
            ->get()
            ->map(function ($pocket) use ($request) {
                // Auto-calculate target untuk Dana Darurat
                if ($pocket->has_auto_target) {
                    $pocket->target_amount = $this->calculateAutoTarget(
                        $request->user()->id,
                        $pocket->target_months
                    );
                }
                return $pocket;
            });

        return response()->json(['data' => $pockets]);
    }

    /**
     * POST /api/pockets
     * Create pocket baru.
     */
    public function store(StorePocketRequest $request): JsonResponse
    {
        // Auto-increment order_index
        $maxOrder = Pocket::where('user_id', $request->user()->id)
            ->active()
            ->max('order_index') ?? -1;

        $pocket = Pocket::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'icon' => $request->icon,
            'color' => $request->color ?? '#3B82F6',
            'target_amount' => $request->target_amount,
            'has_auto_target' => $request->boolean('has_auto_target', false),
            'target_months' => $request->input('target_months', 6),
            'order_index' => $maxOrder + 1,
        ]);

        // Calculate auto target if enabled
        if ($pocket->has_auto_target) {
            $pocket->target_amount = $this->calculateAutoTarget(
                $request->user()->id,
                $pocket->target_months
            );
            $pocket->save();
        }

        return response()->json(['data' => $pocket], 201);
    }

    /**
     * PUT /api/pockets/{id}
     * Update pocket.
     */
    public function update(UpdatePocketRequest $request, string $id): JsonResponse
    {
        $pocket = Pocket::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->firstOrFail();

        $pocket->update($request->only([
            'name', 'icon', 'color', 'target_amount',
            'has_auto_target', 'target_months', 'order_index',
        ]));

        $pocket->refresh();

        // Recalculate auto target if enabled
        if ($pocket->has_auto_target) {
            $pocket->target_amount = $this->calculateAutoTarget(
                $request->user()->id,
                $pocket->target_months
            );
            $pocket->save();
        }

        return response()->json(['data' => $pocket]);
    }

    /**
     * DELETE /api/pockets/{id}
     * Soft delete — balance dikembalikan ke Unallocated.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $pocket = Pocket::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->firstOrFail();

        DB::transaction(function () use ($pocket) {
            // Set semua transaksi pocket ini ke Unallocated (pocket_id = NULL)
            Transaction::where('pocket_id', $pocket->id)
                ->where('is_deleted', false)
                ->update(['pocket_id' => null, 'updated_at' => now()]);

            // Soft delete pocket — balance effectively returns to Unallocated
            $pocket->update([
                'current_balance' => 0,
                'is_deleted' => true,
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Pocket berhasil dihapus. Saldo dikembalikan ke Unallocated.']);
    }

    /**
     * Calculate auto target Dana Darurat.
     * target = avg pengeluaran 3 bulan terakhir × target_months
     */
    private function calculateAutoTarget(string $userId, int $targetMonths): float
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3)->startOfMonth();

        $totalExpense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_deleted', false)
            ->where('transaction_date', '>=', $threeMonthsAgo->toDateString())
            ->sum('amount');

        $avgMonthly = $totalExpense / 3;

        return round($avgMonthly * $targetMonths, 2);
    }
}
