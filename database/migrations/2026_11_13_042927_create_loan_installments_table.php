<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('user_id');
            $table->decimal('amount_paid', 15, 2);
            $table->decimal('overpayment_amount', 15, 2)->default(0);
            $table->date('payment_date');
            $table->boolean('auto_income_generated')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['loan_id', 'is_deleted']);
            $table->index(['user_id', 'is_deleted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
