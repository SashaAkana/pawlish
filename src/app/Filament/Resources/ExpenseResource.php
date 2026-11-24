<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Setting;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Записи и финансы';
    protected static ?string $modelLabel = 'Расход';
    protected static ?string $pluralModelLabel = 'Расходы';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация о расходе')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Тип расхода')
                            ->options([
                                'rent' => 'Аренда',
                                'material' => 'Материалы',
                                'other' => 'Прочее',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'rent') {
                                    $set('title', 'Аренда рабочего места');
                                }
                            }),
                        Forms\Components\TextInput::make('title')
                            ->label('Наименование')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('amount')
                            ->label('Сумма')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix(Setting::getSettings()->currency ?? '₽'),
                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Дата расхода')
                            ->required()
                            ->default(now()),
                        Forms\Components\Toggle::make('is_recurring')
                            ->label('Повторяющийся расход')
                            ->reactive()
                            ->helperText('Автоматически создавать каждый месяц'),
                        Forms\Components\Select::make('recurring_period')
                            ->label('Периодичность')
                            ->options([
                                'monthly' => 'Каждый месяц',
                                'none' => 'Не повторять',
                            ])
                            ->default('none')
                            ->visible(fn (callable $get) => $get('is_recurring')),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'rent' => 'warning',
                        'material' => 'info',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'rent' => 'Аренда',
                        'material' => 'Материалы',
                        'other' => 'Прочее',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Наименование')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Сумма')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', ' ') . ' ' . (\App\Models\Setting::getSettings()?->currency ?? '₽'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('Повторяется')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'rent' => 'Аренда',
                        'material' => 'Материалы',
                        'other' => 'Прочее',
                    ]),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('От'),
                        Forms\Components\DatePicker::make('until')
                            ->label('До'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($query, $date) => $query->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn ($query, $date) => $query->whereDate('expense_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
