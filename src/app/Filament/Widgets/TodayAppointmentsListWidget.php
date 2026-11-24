<?php

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Appointment;
use App\Support\AppointmentTimePolicy;

class TodayAppointmentsListWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Записи на сегодня';

    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        [$todayStart, $todayEnd] = AppointmentTimePolicy::todayRange();

        return $table
            ->query(
                Appointment::with(['client', 'service'])
                    ->whereBetween('start_at', [$todayStart, $todayEnd])
                    ->orderBy('start_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_at')
                    ->label('Время')
                    ->dateTime('H:i'),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Клиент'),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Услуга'),
                Tables\Columns\TextColumn::make('price_at_moment')
                    ->label('Цена')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ' ') . ' ' . (\App\Models\Setting::getSettings()?->currency ?? '₽')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'scheduled' => 'Запланировано',
                        'completed' => 'Выполнено',
                        'cancelled' => 'Отменено',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('Подтвердить завершение')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Appointment $record): bool => $record->status === 'scheduled')
                    ->action(function (Appointment $record): void {
                        $record->update([
                            'status' => 'completed',
                            'is_paid' => true,
                        ]);
                    }),
            ])
            ->paginated(false);
    }
}
