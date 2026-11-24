<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'tax_percent',
        'currency',
        'timezone',
        'telegram_bot_token',
        'master_chat_id',
        'reminder_minutes_before',
        'reminder_template',
        'workday_start',
        'workday_end'
    ];

    protected $hidden = ['telegram_bot_token'];

    protected $casts = [
        'tax_percent' => 'integer',
        'reminder_minutes_before' => 'integer',
        'workday_start' => 'datetime:H:i:s',
        'workday_end' => 'datetime:H:i:s'
    ];

    public static function getSettings(): ?self
    {
        return self::first();
    }
}
