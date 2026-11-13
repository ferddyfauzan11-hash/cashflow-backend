<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pocket;
use App\Models\PocketAllocation;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * GET /api/analytics/monthly?month=&year=
     */
    public function monthly(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $transactions = Transaction::where('user_id', $userId)
            ->active()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        $totalIncome = (float) $transactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $transactions->where('type', 'expense')->sum('amount');
        $net = $totalIncome - $totalExpense;

        // Breakdown expense by category
        $categoryBreakdown = Transaction::where('transactions.user_id', $userId)
            ->where('transactions.type', 'expense')
            ->where('transactions.is_deleted', false)
            ->whereBetween('transactions.transaction_date', [$startDate, $endDate])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                'categories.icon',
                DB::raw('SUM(transactions.amount) as total_amount')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.icon')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($item) use ($totalExpense) {
                $item->total_amount = (float) $item->total_amount;
                $item->percentage = $totalExpense > 0 ? round(($item->total_amount / $totalExpense) * 100, 1) : 0;
                return $item;
            });

        // Daily spending pattern
        $dailySpending = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_deleted', false)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('transaction_date', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->get()
            ->map(function ($item) {
                $item->total_amount = (float) $item->total_amount;
                return $item;
            });

        // Top 3 largest expenses
        $topExpenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('is_deleted', false)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->with('category:id,name,icon')
            ->orderByDesc('amount')
            ->limit(3)
            ->get(['id', 'category_id', 'amount', 'description', 'transaction_date']);

        return response()->json([
            'period' => sprintf('%04d-%02d', $year, $month),
            'summary' => [
                'income' => $totalIncome,
                'expense' => $totalExpense,
                'net' => $net,
            ],
            'category_breakdown' => $categoryBreakdown,
            'daily_spending' => $dailySpending,
            'top_expenses' => $topExpenses,
        ]);
    }

    /**
     * GET /api/analytics/yearly?year=
     */
    public function yearly(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $year = (int) $request->input('year', Carbon::now()->year);

        $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear()->toDateString();
        $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear()->toDateString();

        $transactions = Transaction::where('user_id', $userId)
            ->active()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        $totalIncome = (float) $transactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $transactions->where('type', 'expense')->sum('amount');
        $net = $totalIncome - $totalExpense;

        // Monthly trends 1 to 12
        $monthlyTrends = [];
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        for ($m = 1; $m <= 12; $m++) {
            $mStart = Carbon::createFromDate($year, $m, 1)->startOfMonth()->toDateString();
            $mEnd = Carbon::createFromDate($year, $m, 1)->endOfMonth()->toDateString();

            $mTrx = $transactions->filter(function ($t) use ($mStart, $mEnd) {
                $d = Carbon::parse($t->transaction_date)->toDateString();
                return $d >= $mStart && $d <= $mEnd;
            });

            $mIncome = (float) $mTrx->where('type', 'income')->sum('amount');
            $mExpense = (float) $mTrx->where('type', 'expense')->sum('amount');

            $monthlyTrends[] = [
                'month' => $m,
                'month_name' => $monthNames[$m - 1],
                'income' => $mIncome,
                'expense' => $mExpense,
                'net' => $mIncome - $mExpense,
            ];
        }

        // Filter out months that have expenses to find highest/lowest
        $monthsWithExpense = array_filter($monthlyTrends, fn ($item) => $item['expense'] > 0);

        $mostExpensiveMonth = null;
        $leastExpensiveMonth = null;

        if (!empty($monthsWithExpense)) {
            usort($monthsWithExpense, fn ($a, $b) => $b['expense'] <=> $a['expense']);
            $mostExpensiveMonth = reset($monthsWithExpense);
            $leastExpensiveMonth = end($monthsWithExpense);
        }

        return response()->json([
            'year' => $year,
            'summary' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net' => $net,
                'avg_monthly_income' => round($totalIncome / 12, 2),
                'avg_monthly_expense' => round($totalExpense / 12, 2),
                'avg_monthly_saving' => round($net / 12, 2),
            ],
            'monthly_trend' => $monthlyTrends,
            'highlights' => [
                'most_expensive_month' => $mostExpensiveMonth,
                'least_expensive_month' => $leastExpensiveMonth,
            ],
        ]);
    }

    /**
     * GET /api/analytics/pocket/{id}
     */
    public function pocket(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        $pocket = Pocket::where('id', $id)
            ->where('user_id', $userId)
            ->active()
            ->firstOrFail();

        $balance = (float) $pocket->current_balance;
        $target = $pocket->target_amount !== null ? (float) $pocket->target_amount : null;
        $progress = ($target !== null && $target > 0) ? round(($balance / $target) * 100, 1) : null;

        // Allocations history
        $allocations = PocketAllocation::where('user_id', $userId)
            ->where('is_deleted', false)
            ->where(function ($q) use ($id) {
                $q->where('from_pocket_id', $id)->orWhere('to_pocket_id', $id);
            })
            ->with(['fromPocket:id,name', 'toPocket:id,name'])
            ->orderByDesc('allocation_date')
            ->limit(20)
            ->get();

        // Transactions in this pocket
        $transactions = Transaction::where('user_id', $userId)
            ->where('pocket_id', $id)
            ->where('is_deleted', false)
            ->with('category:id,name,icon')
            ->orderByDesc('transaction_date')
            ->limit(20)
            ->get();

        return response()->json([
            'pocket' => [
                'id' => $pocket->id,
                'name' => $pocket->name,
                'icon' => $pocket->icon,
                'color' => $pocket->color,
                'current_balance' => $balance,
                'target_amount' => $target,
                'progress_percentage' => $progress,
            ],
            'recent_allocations' => $allocations,
            'recent_transactions' => $transactions,
        ]);
    }
}
