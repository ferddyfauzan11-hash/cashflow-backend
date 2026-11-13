<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Pocket;
use App\Models\PocketAllocation;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncController extends Controller
{
    /**
     * POST /api/sync/push
     * Sync records from device to server (Last-Write-Wins).
     */
    public function push(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $syncedIds = [
            'categories' => [],
            'pockets' => [],
            'pocket_allocations' => [],
            'transactions' => [],
            'loans' => [],
            'loan_installments' => [],
        ];
        $errors = [];

        $entities = [
            'categories' => Category::class,
            'pockets' => Pocket::class,
            'pocket_allocations' => PocketAllocation::class,
            'transactions' => Transaction::class,
            'loans' => Loan::class,
            'loan_installments' => LoanInstallment::class,
        ];

        DB::beginTransaction();
        try {
            foreach ($entities as $key => $modelClass) {
                $records = $request->input($key, []);
                if (!is_array($records)) {
                    continue;
                }

                foreach ($records as $item) {
                    if (empty($item['id'])) {
                        continue;
                    }

                    try {
                        $existing = $modelClass::where('id', $item['id'])
                            ->where('user_id', $userId)
                            ->first();

                        $clientUpdatedAt = isset($item['updated_at'])
                            ? Carbon::parse($item['updated_at'])
                            : Carbon::now();

                        if (!$existing) {
                            // Insert record
                            $item['user_id'] = $userId;
                            $model = new $modelClass();
                            $model->forceFill($item);
                            $model->save();
                            $syncedIds[$key][] = $item['id'];
                        } else {
                            // Compare updated_at: Last-Write-Wins
                            $serverUpdatedAt = Carbon::parse($existing->updated_at);
                            if ($clientUpdatedAt->gt($serverUpdatedAt)) {
                                $item['user_id'] = $userId;
                                $existing->forceFill($item);
                                $existing->save();
                                $syncedIds[$key][] = $item['id'];
                            } else {
                                // Server is newer or equal, skip but consider acknowledged
                                $syncedIds[$key][] = $item['id'];
                            }
                        }
                    } catch (Throwable $e) {
                        $errors[] = [
                            'table' => $key,
                            'id' => $item['id'] ?? null,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Sync push failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => 'ok',
            'synced_ids' => $syncedIds,
            'errors' => $errors,
        ]);
    }

    /**
     * GET /api/sync/pull?last_synced_at={timestamp}
     * Pull records updated after last_synced_at from server.
     */
    public function pull(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $lastSyncedAt = $request->query('last_synced_at');

        $queryFn = function ($query) use ($userId, $lastSyncedAt) {
            $query->where('user_id', $userId);
            if (!empty($lastSyncedAt)) {
                $query->where('updated_at', '>', Carbon::parse($lastSyncedAt));
            }
            return $query->get();
        };

        $data = [
            'categories' => $queryFn(Category::query()),
            'pockets' => $queryFn(Pocket::query()),
            'pocket_allocations' => $queryFn(PocketAllocation::query()),
            'transactions' => $queryFn(Transaction::query()),
            'loans' => $queryFn(Loan::query()),
            'loan_installments' => $queryFn(LoanInstallment::query()),
        ];

        return response()->json([
            'status' => 'ok',
            'data' => $data,
            'server_timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }
}
