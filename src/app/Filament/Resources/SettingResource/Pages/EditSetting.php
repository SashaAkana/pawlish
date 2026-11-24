<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Не показываем токен в открытом виде
        if ($this->record->telegram_bot_token) {
            $data['telegram_bot_token'] = '***';
        }
        
        return $data;
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Если токен не изменился, сохраняем старый
        if (($data['telegram_bot_token'] ?? null) === '***') {
            unset($data['telegram_bot_token']);
        }
        
        if (($data['timezone'] ?? $this->record->timezone) !== $this->record->timezone
            && \App\Models\Appointment::query()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.timezone' => 'Нельзя менять часовой пояс после появления записей без миграции дат.',
            ]);
        }
        if (isset($data['workday_start'], $data['workday_end']) && $data['workday_start'] >= $data['workday_end']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.workday_end' => 'Окончание рабочего дня должно быть позже начала.',
            ]);
        }
        return $data;
    }
}
