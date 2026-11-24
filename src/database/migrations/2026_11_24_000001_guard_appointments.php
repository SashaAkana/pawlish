<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new \RuntimeException('Pawlish supports SQLite only.');
        }

        DB::unprepared("CREATE TRIGGER pawlish_service_guard BEFORE DELETE ON services WHEN EXISTS (SELECT 1 FROM appointments WHERE service_id = OLD.id) BEGIN SELECT RAISE(ABORT, 'Service has booking history'); END");

        $recount = static fn (string $condition): string => "UPDATE clients SET
            total_visits = (SELECT COUNT(*) FROM appointments WHERE client_id = clients.id AND status = 'completed' AND is_paid = 1),
            total_spent = COALESCE((SELECT SUM(price_at_moment) FROM appointments WHERE client_id = clients.id AND status = 'completed' AND is_paid = 1), 0)
            WHERE {$condition}";
        DB::statement($recount('1 = 1'));
        foreach (['insert' => 'id = NEW.client_id', 'update' => 'id IN (OLD.client_id, NEW.client_id)', 'delete' => 'id = OLD.client_id'] as $event => $condition) {
            DB::unprepared("CREATE TRIGGER pawlish_stats_{$event} AFTER {$event} ON appointments BEGIN " . $recount($condition) . '; END');
        }
        foreach (['insert' => '1 = 1', 'update' => 'a.id != OLD.id'] as $event => $exclude) {
            $operation = $event === 'update' ? 'UPDATE OF start_at, end_at, status' : 'INSERT';
            DB::unprepared("CREATE TRIGGER pawlish_overlap_{$event} BEFORE {$operation} ON appointments
                WHEN NEW.status != 'cancelled' AND EXISTS (SELECT 1 FROM appointments a
                    WHERE {$exclude} AND a.status != 'cancelled' AND a.start_at < NEW.end_at AND a.end_at > NEW.start_at)
                BEGIN SELECT RAISE(ABORT, 'Appointment overlaps an existing booking'); END");
        }
    }

    public function down(): void
    {
        foreach (['service_guard', 'stats_insert', 'stats_update', 'stats_delete', 'overlap_insert', 'overlap_update'] as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS pawlish_' . $trigger);
        }
    }
};
