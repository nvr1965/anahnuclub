<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\QueueBacklog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The cron's work stays cheap on shared hosting: no PHP process without a reason, no page built for its badges. */
class BackgroundLoadTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $queue, int $availableAt, ?int $reservedAt = null): void
    {
        DB::table('jobs')->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0, 'reserved_at' => $reservedAt,
            'available_at' => $availableAt, 'created_at' => $availableAt]);
    }

    private function worker(string $queues)
    {
        return collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', "queue:work --queue=$queues "));
    }

    public function test_a_queue_worker_starts_only_when_its_queue_has_a_job_ready()
    {
        config(['queue.default' => 'database']);
        $mail = $this->worker('mail');
        $main = $this->worker('default,previews');

        $this->assertFalse($mail->filtersPass(app()));
        $this->assertFalse($main->filtersPass(app()));

        // A translation retried later, and a job a running worker holds: nothing to do yet.
        $this->job('default', now()->addHour()->getTimestamp());
        $this->job('default', now()->subMinute()->getTimestamp(), now()->subSeconds(10)->getTimestamp());
        $this->assertFalse($main->filtersPass(app()));

        $this->job('previews', now()->subMinute()->getTimestamp());
        $this->assertTrue($main->filtersPass(app()));
        $this->assertFalse($mail->filtersPass(app()));

        $this->job('mail', now()->getTimestamp());
        $this->assertTrue($mail->filtersPass(app()));
    }

    public function test_a_job_left_reserved_by_a_killed_worker_counts_again_after_retry_after()
    {
        config(['queue.default' => 'database', 'queue.connections.database.retry_after' => 90]);
        $this->job('mail', now()->subMinutes(10)->getTimestamp(), now()->subMinutes(5)->getTimestamp());

        $this->assertTrue(QueueBacklog::ready(['mail']));
    }

    public function test_without_the_database_queue_the_worker_runs_as_before()
    {
        config(['queue.default' => 'sync']);

        $this->assertTrue(QueueBacklog::ready(['mail']));
    }

    public function test_the_authors_posts_go_out_inside_the_scheduler()
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertNotNull($events->firstWhere('description', 'feeds-publish'));
        $this->assertFalse($events->contains(fn ($e) => str_contains($e->command ?? '', 'feeds:publish')));
    }

    public function test_the_badges_are_counted_alone()
    {
        $this->getJson('/counts')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/counts')
            ->assertOk()
            ->assertExactJson(['messages' => 0, 'friend_requests' => 0, 'notifications' => 0]);
    }
}
