<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name', 50);
            $table->enum('type', ['income', 'expense']);
            $table->string('icon', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_deleted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
