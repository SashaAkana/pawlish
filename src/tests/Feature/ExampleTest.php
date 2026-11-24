<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_panel(): void
    {
        $this->get('/')->assertRedirect('/panel');
    }

    public function test_export_requires_login(): void
    {
        $this->get('/panel/financial-report/download?period=today&format=csv')
            ->assertRedirect(route('filament.admin.auth.login'));
    }
}
