<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loan\StoreInstallmentRequest;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanInstallmentController extends Controller
{
    /**
     * GET /api/loans/{id}/installments
     * List all active installments for a loan.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        $loan = Loan::where('id', $id)
            ->where('user_id', $userId)
            ->active()
            ->firstOrFail();

        $installments = $loan->installments()
            ->active()
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $installments]);
    }

    /**
     * POST /api/loans/{id}/installments
     * Record an installment payment + overpayment business logic.
     */
    public function store(StoreInstallmentRequest $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;
        $amountPaid = (float) $request->amount_paid;

        return DB::transaction(function () use ($request, $id, $userId, $amountPaid) {
            $loan = Loan::where('id', $id)
                ->where('user_id', $userId)
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            if ($loan->status === 'completed') {
                return response()->json([
                    'message' => 'Pinjaman sudah lunas.',
                ], 422);
            }

            $remaining = (float) $loan->remaining_amount;
            $overpayment = 0.0;
            $autoIncomeGenerated = false;

            if ($amountPaid > $remaining) {
                $overpayment = $amountPaid - $remaining;
                $loan->remaining_amount = 0;
                $loan->status = 'completed';
                $autoIncomeGenerated = true;

                // Find or create 'Kelebihan Bayar Pinjaman' category
                $category = Category::where('user_id', $userId)
                    ->where('name', 'Kelebihan Bayar Pinjaman')
                    ->where('is_deleted', false)
                    ->first();

                if (!$category) {
                    $category = Category::create([
                        'user_id' => $userId,
                        'name' => 'Kelebihan Bayar Pinjaman',
                        'type' => 'income',
                        'icon' => '🔄',
                        'is_default' => true,
                    ]);
                }

                // Auto-create income transaction into Unallocated
                Transaction::create([
                    'user_id' => $userId,
                    'category_id' => $category->id,
                    'pocket_id' => null, // Unallocated
                    'type' => 'income',
                    'amount' => $overpayment,
                    'description' => "Kelebihan bayar pinjaman ke {$loan->contact_name}",
                    'transaction_date' => $request->payment_date,
                ]);
            } elseif ($amountPaid == $remaining) {
                $loan->remaining_amount = 0;
                $loan->status = 'completed';
            } else {
                $loan->remaining_amount = $remaining - $amountPaid;
            }

            $loan->save();

            // Create installment record
            $installment = LoanInstallment::create([
                'loan_id' => $loan->id,
                'user_id' => $userId,
                'amount_paid' => $amountPaid,
                'overpayment_amount' => $overpayment,
                'payment_date' => $request->payment_date,
                'auto_income_generated' => $autoIncomeGenerated,
                'notes' => $request->notes,
            ]);

            $message = $loan->status === 'completed'
                ? "Pinjaman ke {$loan->contact_name} LUNAS!" . ($overpayment > 0 ? " Kelebihan Rp " . number_format($overpayment, 0, ',', '.') . " dicatat sebagai pemasukan." : "")
                : "Cicilan berhasil dicatat.";

            return response()->json([
                'message' => $message,
                'data' => $installment,
                'loan' => $loan->fresh(),
            ], 201);
        });
    }
}
