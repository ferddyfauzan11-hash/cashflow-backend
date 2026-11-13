<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Pocket;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/summary
     * Dashboard aggregated summary for mobile app.
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        // 1. Current Month figures
        $monthIncome = (float) Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('is_deleted', false)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthExpense = (float) Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_deleted', false)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthNet = $monthIncome - $monthExpense;

        // 2. All-Time figures & Unallocated
        $totalIncome = (float) Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('is_deleted', false)
            ->sum('amount');

        $totalExpense = (float) Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_deleted', false)
            ->sum('amount');

        $totalPocketBalance = (float) Pocket::where('user_id', $userId)
            ->where('is_deleted', false)
            ->sum('current_balance');

        $unallocatedBalance = $totalIncome - $totalExpense - $totalPocketBalance;

        // 3. Pockets summary
        $pockets = Pocket::where('user_id', $userId)
            ->active()
            ->orderBy('order_index')
            ->get(['id', 'name', 'icon', 'color', 'current_balance', 'target_amount', 'has_auto_target', 'order_index']);

        // 4. Loans summary
        $activeLoans = Loan::where('user_id', $userId)
            ->where('is_deleted', false)
            ->where('status', '!=', 'completed')
            ->get();

        $loansSummary = [
            'active_count' => $activeLoans->count(),
            'total_lend_remaining' => (float) $activeLoans->where('type', 'lend')->sum('remaining_amount'),
            'total_borrow_remaining' => (float) $activeLoans->where('type', 'borrow')->sum('remaining_amount'),
        ];

        // 5. Last 7 days expense breakdown
        $sevenDaysAgo = $now->copy()->subDays(6)->startOfDay();
        $expensesLast7 = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_deleted', false)
            ->where('transaction_date', '>=', $sevenDaysAgo->toDateString())
            ->selectRaw('transaction_date, SUM(amount) as total_amount')
            ->groupBy('transaction_date')
            ->pluck('total_amount', 'transaction_date');

        $dailyExpenses = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->toDateString();
            $dailyExpenses[] = [
                'date' => $date,
                'amount' => (float) ($expensesLast7[$date] ?? 0),
            ];
        }

        return response()->json([
            'month' => [
                'period' => $now->format('Y-m'),
                'income' => $monthIncome,
                'expense' => $monthExpense,
                'net' => $monthNet,
            ],
            'overall' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'total_pocket_balance' => $totalPocketBalance,
                'unallocated_balance' => $unallocatedBalance,
            ],
            'pockets' => $pockets,
            'loans' => $loansSummary,
            'recent_expense_7_days' => $dailyExpenses,
        ]);
    }
}
