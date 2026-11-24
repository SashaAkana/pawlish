<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['rent', 'material', 'other']);
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_period', ['monthly', 'none'])->default('none');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};