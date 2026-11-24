<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'type',
        'title',
        'amount',
        'expense_date',
        'is_recurring',
        'recurring_period',
        'recurring_source_id',
        'recurring_month'
    ];

    protected $casts = [
        'expense_date' => 'date',
        'is_recurring' => 'boolean',
        'amount' => 'decimal:2'
    ];

    protected $attributes = [
        'recurring_period' => 'none',
        'is_recurring' => false
    ];
}