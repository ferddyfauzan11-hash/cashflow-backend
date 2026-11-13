<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loan\StoreLoanRequest;
use App\Http\Requests\Loan\UpdateLoanRequest;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    /**
     * GET /api/loans
     * List user's loans with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Loan::where('user_id', $userId)
            ->active()
            ->withCount(['installments' => function ($q) {
                $q->where('is_deleted', false);
            }]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $loans = $query->orderByDesc('created_at')->get();

        // Update overdue status dynamically if past due_date and still active
        $today = Carbon::today();
        foreach ($loans as $loan) {
            if ($loan->status === 'active' && $loan->due_date && Carbon::parse($loan->due_date)->lt($today)) {
                $loan->update(['status' => 'overdue']);
                $loan->status = 'overdue';
            }
        }

        return response()->json(['data' => $loans]);
    }

    /**
     * POST /api/loans
     * Create new loan.
     */
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $principal = (float) $request->principal_amount;

        $status = 'active';
        if ($request->filled('due_date') && Carbon::parse($request->due_date)->lt(Carbon::today())) {
            $status = 'overdue';
        }

        $loan = Loan::create([
            'user_id' => $userId,
            'contact_name' => $request->contact_name,
            'type' => $request->type,
            'principal_amount' => $principal,
            'remaining_amount' => $principal,
            'due_date' => $request->due_date,
            'status' => $status,
            'notes' => $request->notes,
        ]);

        return response()->json(['data' => $loan], 201);
    }

    /**
     * GET /api/loans/{id}
     * Get single loan detail with active installments.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $loan = Loan::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->with(['installments' => function ($q) {
                $q->where('is_deleted', false)->orderByDesc('payment_date');
            }])
            ->firstOrFail();

        return response()->json(['data' => $loan]);
    }

    /**
     * PUT /api/loans/{id}
     * Update loan.
     */
    public function update(UpdateLoanRequest $request, string $id): JsonResponse
    {
        $loan = Loan::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->firstOrFail();

        $loan->update($request->only([
            'contact_name',
            'type',
            'principal_amount',
            'remaining_amount',
            'due_date',
            'status',
            'notes',
        ]));

        return response()->json(['data' => $loan->fresh()]);
    }

    /**
     * DELETE /api/loans/{id}
     * Soft delete loan.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $loan = Loan::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->firstOrFail();

        $loan->update([
            'is_deleted' => true,
            'updated_at' => now(),
        ]);

        // Soft delete installments too
        $loan->installments()->update([
            'is_deleted' => true,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Pinjaman berhasil dihapus.']);
    }
}
