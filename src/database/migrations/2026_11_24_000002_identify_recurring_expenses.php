<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Keep the originating ID even if the original expense is deleted.
            $table->unsignedBigInteger('recurring_source_id')->nullable();
            $table->date('recurring_month')->nullable();
            $table->unique(['recurring_source_id', 'recurring_month'], 'expenses_recurring_identity');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_recurring_identity');
            $table->dropColumn(['recurring_source_id', 'recurring_month']);
        });
    }
};
