<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\URL;

class FinancialReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Записи и финансы';
    protected static ?int $navigationSort = 999;
    protected static ?string $title = 'Генератор отчётов';
    protected static ?string $slug = 'financial-report';
    
    protected static string $view = 'filament.pages.financial-report';
    
    public ?string $period = 'month';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    public string $format = 'csv';

    public function getDownloadUrl(): ?string
    {
        if ($this->period === 'custom' && (! $this->dateFrom || ! $this->dateTo)) {
            return null;
        }

        return URL::route('financial-report.download', array_filter([
            'period' => $this->period,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'format' => $this->format,
        ]));
    }
}
