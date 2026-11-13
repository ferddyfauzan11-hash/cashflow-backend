<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Models\Pocket;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * GET /api/transactions
     * List transactions with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Transaction::where('user_id', $userId)
            ->active()
            ->with(['category', 'pocket']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('pocket_id')) {
            if ($request->pocket_id === 'unallocated' || $request->pocket_id === '') {
                $query->whereNull('pocket_id');
            } else {
                $query->where('pocket_id', $request->pocket_id);
            }
        }

        if ($request->filled('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        $perPage = (int) $request->input('per_page', 50);
        $transactions = $query->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($transactions);
    }

    /**
     * POST /api/transactions
     * Create new transaction + adjust pocket balance.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $pocketId = $request->pocket_id;
        $type = $request->type;
        $amount = (float) $request->amount;

        return DB::transaction(function () use ($request, $userId, $pocketId, $type, $amount) {
            if ($pocketId) {
                $pocket = Pocket::where('id', $pocketId)
                    ->where('user_id', $userId)
                    ->active()
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($type === 'expense') {
                    if ((float) $pocket->current_balance < $amount) {
                        return response()->json([
                            'message' => 'Saldo pocket tidak cukup.',
                        ], 422);
                    }
                    $pocket->decrement('current_balance', $amount);
                } elseif ($type === 'income') {
                    $pocket->increment('current_balance', $amount);
                }
            }

            $data = [
                'user_id' => $userId,
                'category_id' => $request->category_id,
                'pocket_id' => $pocketId,
                'type' => $type,
                'amount' => $amount,
                'description' => $request->description,
                'transaction_date' => $request->transaction_date,
            ];

            if ($request->filled('id')) {
                $data['id'] = $request->id;
            }

            $transaction = Transaction::create($data);
            $transaction->load(['category', 'pocket']);

            return response()->json(['data' => $transaction], 201);
        });
    }

    /**
     * PUT /api/transactions/{id}
     * Update transaction + revert & apply pocket balances.
     */
    public function update(UpdateTransactionRequest $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        return DB::transaction(function () use ($request, $id, $userId) {
            $transaction = Transaction::where('id', $id)
                ->where('user_id', $userId)
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            $oldPocketId = $transaction->pocket_id;
            $oldType = $transaction->type;
            $oldAmount = (float) $transaction->amount;

            $newPocketId = $request->has('pocket_id') ? $request->pocket_id : $oldPocketId;
            $newType = $request->input('type', $oldType);
            $newAmount = $request->has('amount') ? (float) $request->amount : $oldAmount;

            // 1. Revert old pocket effect
            if ($oldPocketId) {
                $oldPocket = Pocket::where('id', $oldPocketId)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if ($oldPocket) {
                    if ($oldType === 'income') {
                        $oldPocket->decrement('current_balance', $oldAmount);
                    } elseif ($oldType === 'expense') {
                        $oldPocket->increment('current_balance', $oldAmount);
                    }
                }
            }

            // 2. Apply new pocket effect
            if ($newPocketId) {
                $newPocket = Pocket::where('id', $newPocketId)
                    ->where('user_id', $userId)
                    ->active()
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($newType === 'expense') {
                    if ((float) $newPocket->current_balance < $newAmount) {
                        return response()->json([
                            'message' => 'Saldo pocket tidak cukup.',
                        ], 422);
                    }
                    $newPocket->decrement('current_balance', $newAmount);
                } elseif ($newType === 'income') {
                    $newPocket->increment('current_balance', $newAmount);
                }
            }

            // 3. Update transaction record
            $updateData = $request->only([
                'category_id',
                'description',
                'transaction_date',
            ]);
            $updateData['type'] = $newType;
            $updateData['amount'] = $newAmount;
            if ($request->has('pocket_id')) {
                $updateData['pocket_id'] = $newPocketId;
            }

            $transaction->update($updateData);
            $transaction->refresh();
            $transaction->load(['category', 'pocket']);

            return response()->json(['data' => $transaction]);
        });
    }

    /**
     * DELETE /api/transactions/{id}
     * Soft delete transaction + revert pocket balance.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        return DB::transaction(function () use ($id, $userId) {
            $transaction = Transaction::where('id', $id)
                ->where('user_id', $userId)
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->pocket_id) {
                $pocket = Pocket::where('id', $transaction->pocket_id)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if ($pocket) {
                    if ($transaction->type === 'income') {
                        $pocket->decrement('current_balance', (float) $transaction->amount);
                    } elseif ($transaction->type === 'expense') {
                        $pocket->increment('current_balance', (float) $transaction->amount);
                    }
                }
            }

            $transaction->update([
                'is_deleted' => true,
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Transaksi berhasil dihapus.',
            ]);
        });
    }
}
