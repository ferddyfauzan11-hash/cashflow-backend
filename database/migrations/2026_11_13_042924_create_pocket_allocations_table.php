<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pocket_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('from_pocket_id')->nullable();
            $table->uuid('to_pocket_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('note', 255)->nullable();
            $table->date('allocation_date');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('from_pocket_id')->references('id')->on('pockets')->onDelete('set null');
            $table->foreign('to_pocket_id')->references('id')->on('pockets')->onDelete('set null');
            $table->index(['user_id', 'is_deleted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pocket_allocations');
    }
};
