<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'Клиенты и услуги';
    protected static ?string $modelLabel = 'Услуга';
    protected static ?string $pluralModelLabel = 'Услуги';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация об услуге')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название услуги')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('base_price')
                            ->label('Базовая цена')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn () => \App\Models\Setting::getSettings()?->currency ?? '₽'),
                        Forms\Components\TextInput::make('duration_minutes')
                            ->label('Длительность (минут)')
                            ->required()
                            ->numeric()
                            ->minValue(15)
                            ->step(5),
                        Forms\Components\Toggle::make('is_duration_fixed')
                            ->label('Жёсткая длительность')
                            ->helperText('Если включено, конец записи фиксируется по длительности услуги.')
                            ->default(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Цена')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ' ') . ' ' . (\App\Models\Setting::getSettings()?->currency ?? '₽'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Длительность')
                    ->formatStateUsing(fn (string $state): string => $state . ' мин')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_duration_fixed')
                    ->label('Фикс. длительность')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активность')
                    ->placeholder('Все услуги')
                    ->trueLabel('Только активные')
                    ->falseLabel('Только неактивные'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn ($record) => ! $record->appointments()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
