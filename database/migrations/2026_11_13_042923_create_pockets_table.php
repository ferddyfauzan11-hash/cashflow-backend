<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pockets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name', 50);
            $table->string('icon', 20)->nullable();
            $table->string('color', 7)->default('#3B82F6');
            $table->decimal('target_amount', 15, 2)->nullable();
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->boolean('has_auto_target')->default(false);
            $table->integer('target_months')->default(6);
            $table->integer('order_index')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_deleted', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pockets');
    }
};
