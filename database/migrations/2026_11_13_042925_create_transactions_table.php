<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('category_id');
            $table->uuid('pocket_id')->nullable();
            $table->enum('type', ['income', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->string('description', 255)->nullable();
            $table->date('transaction_date');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->foreign('pocket_id')->references('id')->on('pockets')->onDelete('set null');
            $table->index(['user_id', 'type', 'is_deleted']);
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'pocket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
