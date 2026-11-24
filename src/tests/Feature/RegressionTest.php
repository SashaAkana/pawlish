<?php

namespace Tests\Feature;

use App\Domain\ValueObjects\FinancialReport;
use App\Jobs\SendMasterReminderJob;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\AppointmentTimePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-24 09:00:00', 'UTC'));
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function booking(array $overrides = []): Appointment
    {
        $client = Client::create(['name' => '=1+1']);
        $service = Service::create(['name' => 'Маникюр', 'base_price' => 1200, 'duration_minutes' => 30]);
        return Appointment::create(array_replace([
            'client_id' => $client->id, 'service_id' => $service->id,
            'start_at' => '2026-11-24 09:40:00', 'end_at' => '2026-11-24 10:10:00',
            'status' => 'scheduled', 'is_paid' => false, 'price_at_moment' => 1200,
        ], $overrides));
    }

    public function test_stats_follow_actual_payments_and_deletion(): void
    {
        $booking = $this->booking(['status' => 'completed']);
        $client = $booking->client;
        $this->assertSame(0, $client->fresh()->total_visits);
        $booking->update(['is_paid' => true]);
        $this->assertSame(1, $client->fresh()->total_visits);
        $this->assertSame('1200.00', $client->fresh()->total_spent);
        $booking->update(['price_at_moment' => 1700]);
        $this->assertSame('1700.00', $client->fresh()->total_spent);
        $booking->update(['is_paid' => false]);
        $this->assertSame(0, $client->fresh()->total_visits);
        $booking->update(['is_paid' => true]);
        $booking->delete();
        $this->assertSame(0, $client->fresh()->total_visits);
        $this->assertSame('0.00', $client->fresh()->total_spent);
    }

    public function test_database_blocks_overlapping_insert_even_without_model(): void
    {
        $booking = $this->booking();
        $row = $booking->getAttributes();
        unset($row['id']);
        $this->expectException(QueryException::class);
        DB::table('appointments')->insert($row);
    }

    public function test_adjacent_booking_and_cancelled_overlap_are_allowed(): void
    {
        $booking = $this->booking();
        $this->booking(['start_at' => '2026-11-24 10:10:00', 'end_at' => '2026-11-24 10:40:00']);
        $this->booking(['status' => 'cancelled']);
        $this->assertSame(3, Appointment::count());
    }

    public function test_cancelled_booking_cannot_be_reactivated_into_occupied_slot(): void
    {
        $this->booking();
        $cancelled = $this->booking(['status' => 'cancelled']);
        $this->expectException(QueryException::class);
        DB::table('appointments')->where('id', $cancelled->id)->update(['status' => 'scheduled']);
    }

    public function test_service_with_history_cannot_be_deleted(): void
    {
        $booking = $this->booking();
        $this->expectException(QueryException::class);
        $booking->service->delete();
    }

    public function test_archived_client_remains_visible_in_booking_history(): void
    {
        $booking = $this->booking();
        $booking->client->delete();
        $this->assertNotNull($booking->fresh()->client);
    }

    public function test_fixed_service_cannot_be_shortened_to_fit_workday(): void
    {
        $service = Service::create(['name' => 'Долгая услуга', 'base_price' => 1000, 'duration_minutes' => 800, 'is_duration_fixed' => true]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->booking(['service_id' => $service->id]);
    }

    public function test_report_includes_first_and_last_calendar_dates(): void
    {
        foreach (['2026-11-01', '2026-11-30', '2026-12-01'] as $date) {
            Expense::create(['type' => 'other', 'title' => 'Материалы', 'amount' => 100, 'expense_date' => $date]);
        }
        $report = FinancialReport::thisMonth();
        $this->assertSame(200.0, $report->expenses);
    }

    public function test_csv_export_escapes_client_formula(): void
    {
        $this->booking(['status' => 'completed', 'is_paid' => true]);
        $response = $this->actingAs(User::factory()->create())->get('/panel/financial-report/download?period=today&format=csv');
        $response->assertOk();
        $this->assertStringContainsString("'=1+1", $response->streamedContent());
    }

    public function test_webhook_is_closed_without_secret(): void
    {
        config(['services.telegram.webhook_secret' => '']);
        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start']])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_webhook_rejects_wrong_chat_even_with_secret(): void
    {
        config(['services.telegram.webhook_secret' => 'test-secret']);
        Setting::getSettings()->update(['telegram_bot_token' => '123:test', 'master_chat_id' => '123']);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson('/api/telegram/webhook', [
            'message' => ['text' => '/start', 'chat' => ['id' => 999, 'type' => 'private']],
        ])->assertForbidden();
        $this->assertSame('123', Setting::getSettings()->master_chat_id);
        Http::assertNothingSent();
    }

    public function test_webhook_accepts_configured_private_chat(): void
    {
        config(['services.telegram.webhook_secret' => 'test-secret']);
        Setting::getSettings()->update(['telegram_bot_token' => '123:test', 'master_chat_id' => '123']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson('/api/telegram/webhook', [
            'message' => ['text' => '/start', 'chat' => ['id' => 123, 'type' => 'private']],
        ])->assertOk();
        Http::assertSentCount(1);
    }

    public function test_reminder_marks_sent_only_after_success(): void
    {
        Setting::getSettings()->update(['telegram_bot_token' => '123:test', 'master_chat_id' => '123']);
        $booking = $this->booking();
        Http::fake(['api.telegram.org/*' => Http::sequence()->push(['ok' => false], 429)->push(['ok' => true])]);
        try {
            (new SendMasterReminderJob($booking->id))->handle();
            $this->fail('Failed Telegram response must trigger a retry');
        } catch (\RuntimeException $e) {
            $this->assertSame('Telegram reminder delivery failed.', $e->getMessage());
        }
        $this->assertFalse($booking->fresh()->reminder_sent);
        (new SendMasterReminderJob($booking->id))->handle();
        $this->assertTrue($booking->fresh()->reminder_sent);
        (new SendMasterReminderJob($booking->id))->handle();
        Http::assertSentCount(2);
    }

    public function test_cancelled_booking_does_not_send_reminder(): void
    {
        Setting::getSettings()->update(['telegram_bot_token' => '123:test', 'master_chat_id' => '123']);
        $booking = $this->booking(['status' => 'cancelled']);
        (new SendMasterReminderJob($booking->id))->handle();
        Http::assertNothingSent();
    }

    public function test_recurring_expenses_are_idempotent_and_do_not_duplicate_first_month(): void
    {
        foreach (['2026-10-15', '2026-10-20', '2026-11-10', '2026-12-10'] as $date) {
            Expense::create(['type' => 'rent', 'title' => 'Аренда', 'amount' => 100, 'expense_date' => $date, 'is_recurring' => true, 'recurring_period' => 'monthly']);
        }
        $this->artisan('expenses:create-recurring')->assertSuccessful();
        $this->artisan('expenses:create-recurring')->assertSuccessful();
        $this->assertSame(2, Expense::whereNotNull('recurring_source_id')->count());
        $this->assertSame(6, Expense::count());
    }

    public function test_workday_does_not_cross_midnight(): void
    {
        $this->assertFalse(AppointmentTimePolicy::isPairWithinWorkday('2026-11-24 10:00:00', '2026-11-25 10:00:00'));
    }
}
