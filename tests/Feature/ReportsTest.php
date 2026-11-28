<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Group;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_and_guests_report_what_they_see_and_the_team_is_told()
    {
        Notification::fake();
        config(['services.contact.email' => 'team@anahnu.club']);
        $author = User::factory()->create();
        $post = Post::create(['user_id' => $author->id, 'body' => 'Плохой <b>текст</b><br>вторая строка', 'audience' => 'public']);
        $reader = User::factory()->create();

        $this->actingAs($reader)->post('/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'abuse'])->assertRedirect();
        // A second click by the same member changes nothing.
        $this->actingAs($reader)->post('/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertRedirect();
        $this->assertSame(1, Report::count());
        $report = Report::first();
        $this->assertSame([$reader->id, $author->id, 'abuse', 'open'], [$report->reporter_id, $report->author_id, $report->reason, $report->status]);
        $this->assertSame("Плохой текст\nвторая строка", $report->snapshot);
        Notification::assertSentOnDemand(ReportReceived::class);

        // A guest leaves an email; "other" needs a word on what is wrong.
        auth()->logout();
        $comment = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'body' => 'Мой текст без спроса']);
        $this->post('/reports', ['type' => 'comment', 'id' => $comment->id, 'reason' => 'copyright'])->assertSessionHasErrors('email');
        $this->post('/reports', ['type' => 'comment', 'id' => $comment->id, 'reason' => 'other', 'email' => 'owner@example.com'])->assertSessionHasErrors('details');
        $this->post('/reports', ['type' => 'comment', 'id' => $comment->id, 'reason' => 'copyright', 'email' => 'owner@example.com', 'details' => 'Это мой рассказ'])->assertRedirect();
        $this->assertSame(['owner@example.com', $post->id], [Report::latest('id')->first()->email, Report::latest('id')->first()->post_id]);
    }

    public function test_what_a_visitor_cannot_see_cannot_be_reported()
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Закрытая', 'slug' => 'closed', 'is_private' => true, 'owner_id' => $owner->id]);
        $hidden = Post::create(['user_id' => $owner->id, 'group_id' => $group->id, 'body' => 'Для своих']);

        $this->actingAs(User::factory()->create())->post('/reports', ['type' => 'post', 'id' => $hidden->id, 'reason' => 'spam'])->assertNotFound();
        $this->assertSame(0, Report::count());
    }

    public function test_an_admin_removes_or_keeps_and_the_decision_stays_on_record()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create();
        $bad = Post::create(['user_id' => $author->id, 'body' => 'Оскорбление', 'audience' => 'public']);
        $fine = Post::create(['user_id' => $author->id, 'body' => 'Нормально', 'audience' => 'public']);
        foreach (User::factory()->count(2)->create() as $u) {
            $this->actingAs($u)->post('/reports', ['type' => 'post', 'id' => $bad->id, 'reason' => 'abuse']);
        }
        $this->actingAs(User::factory()->create())->post('/reports', ['type' => 'post', 'id' => $fine->id, 'reason' => 'spam']);

        $this->actingAs(User::factory()->create())->get('/admin/reports')->assertForbidden();
        $this->actingAs($admin)->get('/admin/reports')->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/reports')->where('open', 3)->where('reports.data.0.same', fn ($n) => $n >= 1));

        $first = Report::where('reportable_id', $bad->id)->first();
        $this->actingAs($admin)->patch("/admin/reports/{$first->id}", ['action' => 'remove', 'note' => 'оскорбление'])->assertRedirect();
        $this->assertNull(Post::find($bad->id));
        // Both complaints about it are closed, with who, when and why; the reported text stays.
        $this->assertSame(2, Report::where('reportable_id', $bad->id)->where('status', 'removed')->where('handled_by', $admin->id)->where('note', 'оскорбление')->count());
        $this->assertSame('Оскорбление', $first->fresh()->snapshot);

        $other = Report::where('reportable_id', $fine->id)->first();
        $this->actingAs($admin)->patch("/admin/reports/{$other->id}", ['action' => 'keep'])->assertRedirect();
        $this->assertNotNull(Post::find($fine->id));
        $this->assertSame('kept', $other->fresh()->status);
        $this->actingAs($admin)->get('/admin/reports?status=removed')->assertInertia(fn ($page) => $page->where('reports.total', 2));
    }
}
