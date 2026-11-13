<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     * List semua kategori user (active only).
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::where('user_id', $request->user()->id)
            ->active()
            ->orderBy('type')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * POST /api/categories
     * Create kategori baru.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'type' => $request->type,
            'icon' => $request->icon,
            'is_default' => false,
        ]);

        return response()->json(['data' => $category], 201);
    }

    /**
     * PUT /api/categories/{id}
     * Update kategori.
     */
    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = Category::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->firstOrFail();

        $category->update($request->only(['name', 'type', 'icon']));

        return response()->json(['data' => $category->fresh()]);
    }

    /**
     * DELETE /api/categories/{id}
     * Soft delete kategori.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $category = Category::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->firstOrFail();

        // Cek apakah ada transaksi aktif yang pakai kategori ini
        $activeTransactions = $category->transactions()
            ->where('is_deleted', false)
            ->count();

        if ($activeTransactions > 0) {
            return response()->json([
                'message' => "Kategori tidak bisa dihapus karena masih digunakan oleh {$activeTransactions} transaksi.",
            ], 422);
        }

        $category->update([
            'is_deleted' => true,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
