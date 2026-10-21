<?php

namespace Tests\Feature;

use App\Jobs\TranslateContent;
use App\Models\Post;
use App\Models\User;
use App\Support\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_a_way_to_hand_the_page_over_first_the_work_goes_to_the_queue()
    {
        // Tests run on the command line, where PHP cannot finish a request early, like a plain CGI host.
        $this->assertFalse(Background::canFinishEarly());
        Queue::fake();

        $this->actingAs(User::factory()->create())->post('/posts', ['body' => 'Привет'])->assertRedirect();

        Queue::assertPushed(TranslateContent::class, fn ($job) => $job->model->is(Post::latest('id')->first()));
    }
}
