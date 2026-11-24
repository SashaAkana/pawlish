<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->integer('tax_percent')->default(6);
            $table->string('currency')->default('₽');
            $table->string('telegram_bot_token')->nullable();
            $table->string('master_chat_id')->nullable();
            $table->integer('reminder_minutes_before')->default(60);
            $table->text('reminder_template')->default('Напоминание: {client_name}, {service} в {date} {time}, {price} {currency}');
            $table->time('workday_start')->default('09:00:00');
            $table->time('workday_end')->default('20:00:00');
            $table->timestamps();
        });
        
        DB::table('settings')->insert([
            'tax_percent' => 6,
            'currency' => '₽',
            'reminder_minutes_before' => 60,
            'reminder_template' => 'Напоминание: {client_name}, {service} в {date} {time}, {price} {currency}',
            'workday_start' => '09:00:00',
            'workday_end' => '20:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};