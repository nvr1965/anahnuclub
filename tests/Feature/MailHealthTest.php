<?php

namespace Tests\Feature;

use App\Support\MailHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MailHealthTest extends TestCase
{
    use RefreshDatabase;

    private function sendingFromTheQueue(): void
    {
        config(['mail.default' => 'smtp', 'queue.default' => 'database']);
    }

    public function test_a_cron_that_never_ran_is_named_with_the_folder_it_must_run_in()
    {
        $this->sendingFromTheQueue();

        $problems = MailHealth::problems();

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('cron', $problems[0]);
        $this->assertStringContainsString(base_path(), $problems[0]);
    }

    public function test_the_scheduler_marks_each_run_and_then_there_is_nothing_to_report()
    {
        $this->sendingFromTheQueue();

        $heartbeat = collect(app(Schedule::class)->events())->firstWhere('description', 'heartbeat');
        $heartbeat->run(app());

        $this->assertNotNull(MailHealth::lastCronRun());
        $this->assertSame([], MailHealth::problems());
    }

    public function test_letters_stuck_while_cron_runs_point_to_the_log()
    {
        $this->sendingFromTheQueue();
        Cache::forever('schedule:last-run', now()->getTimestamp());
        DB::table('jobs')->insert(['queue' => 'mail', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->getTimestamp(), 'created_at' => now()->subHour()->getTimestamp()]);

        $this->assertSame(1, MailHealth::waiting());
        $this->assertStringContainsString('laravel.log', implode(' ', MailHealth::problems()));
    }
}
