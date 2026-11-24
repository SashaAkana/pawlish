<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->decimal('price_at_moment', 10, 2);
            $table->enum('payment_type', ['cash', 'card', 'transfer'])->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->boolean('is_paid')->default(false);
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();
            
            $table->index('start_at');
            $table->index('status');
            $table->index('is_paid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};