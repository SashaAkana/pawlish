<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\DashboardOverviewWidget;
use App\Filament\Widgets\TodayAppointmentsListWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\File;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PanelPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->favicon(asset('favicon.ico'))
            ->id('admin')
            ->path('panel')
            ->login()
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => $this->renderAppIcons(),
            )
            ->colors([
                'primary' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                DashboardOverviewWidget::class,
                TodayAppointmentsListWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function renderAppIcons(): HtmlString
    {
        $versionedAsset = static fn (string $file): string => e(asset($file) . '?v=' . (File::exists(public_path($file)) ? File::lastModified(public_path($file)) : time()));

        return new HtmlString(implode("\n", [
            '<link rel="icon" type="image/x-icon" href="' . $versionedAsset('favicon.ico') . '">',
            '<link rel="icon" type="image/png" sizes="16x16" href="' . $versionedAsset('favicon-16x16.png') . '">',
            '<link rel="icon" type="image/png" sizes="32x32" href="' . $versionedAsset('favicon-32x32.png') . '">',
            '<link rel="apple-touch-icon" sizes="180x180" href="' . $versionedAsset('apple-touch-icon-180x180.png') . '">',
            '<link rel="manifest" href="' . $versionedAsset('site.webmanifest') . '">',
            '<meta name="theme-color" content="#f59e0b">',
            '<meta name="apple-mobile-web-app-capable" content="yes">',
            '<meta name="apple-mobile-web-app-status-bar-style" content="default">',
            '<meta name="apple-mobile-web-app-title" content="Pawlish">',
            '<meta name="mobile-web-app-capable" content="yes">',
        ]));
    }
}
