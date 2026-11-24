<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Setting;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    public function mount(): void
    {
        parent::mount();
        
        $settings = Setting::first();
        if ($settings) {
            redirect()->to(SettingResource::getUrl('edit', ['record' => $settings]));
        } else {
            $settings = Setting::create();
            redirect()->to(SettingResource::getUrl('edit', ['record' => $settings]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}