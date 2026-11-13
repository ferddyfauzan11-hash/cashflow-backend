<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Default categories seeded per user saat register.
     * 13 items sesuai GOAL.md.
     */
    public const DEFAULT_CATEGORIES = [
        // Income (6)
        ['name' => 'Gaji', 'type' => 'income', 'icon' => '💰'],
        ['name' => 'Bonus', 'type' => 'income', 'icon' => '🎁'],
        ['name' => 'Penjualan', 'type' => 'income', 'icon' => '🛒'],
        ['name' => 'Freelance', 'type' => 'income', 'icon' => '💻'],
        ['name' => 'Pemasukan Lain', 'type' => 'income', 'icon' => '📥'],
        ['name' => 'Kelebihan Bayar Pinjaman', 'type' => 'income', 'icon' => '🔄'],

        // Expense (7)
        ['name' => 'Makan & Minum', 'type' => 'expense', 'icon' => '🍔'],
        ['name' => 'Transport', 'type' => 'expense', 'icon' => '🚗'],
        ['name' => 'Belanja', 'type' => 'expense', 'icon' => '🛍️'],
        ['name' => 'Tagihan & Utilitas', 'type' => 'expense', 'icon' => '💡'],
        ['name' => 'Hiburan', 'type' => 'expense', 'icon' => '🎮'],
        ['name' => 'Kesehatan', 'type' => 'expense', 'icon' => '🏥'],
        ['name' => 'Pengeluaran Lain', 'type' => 'expense', 'icon' => '📤'],
    ];

    /**
     * Seed default categories for a specific user.
     */
    public static function seedForUser(string $userId): void
    {
        foreach (self::DEFAULT_CATEGORIES as $cat) {
            Category::create([
                'user_id' => $userId,
                'name' => $cat['name'],
                'type' => $cat['type'],
                'icon' => $cat['icon'],
                'is_default' => true,
            ]);
        }
    }

    /**
     * Run seeder — seed for all existing users (for dev/testing).
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Skip if user already has default categories
            $existing = Category::where('user_id', $user->id)
                ->where('is_default', true)
                ->count();

            if ($existing === 0) {
                self::seedForUser($user->id);
            }
        }
    }
}
