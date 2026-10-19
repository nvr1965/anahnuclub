<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chat_from_a_non_friend_waits_in_requests_until_accepted_or_answered()
    {
        [$anna, $boris] = User::factory()->count(2)->create();

        $this->actingAs($anna)->post("/people/{$boris->id}/message");
        $chat = Conversation::firstOrFail();
        $this->post("/messages/{$chat->id}", ['body' => 'Привет!'])->assertRedirect();

        $this->actingAs($boris)->get('/feed')->assertInertia(fn ($page) => $page->where('counts.messages', 0));
        $this->get('/messages')->assertInertia(fn ($page) => $page->has('conversations', 0)->has('requests', 1));
        $this->get("/messages/{$chat->id}")->assertInertia(fn ($page) => $page->where('conversation.request', true));

        // Answering lets the chat in.
        $this->post("/messages/{$chat->id}", ['body' => 'Здравствуйте'])->assertRedirect();
        $this->get('/messages')->assertInertia(fn ($page) => $page->has('conversations', 1)->has('requests', 0));
    }

    public function test_a_declined_request_comes_back_only_with_a_new_message()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $this->actingAs($anna)->post("/people/{$boris->id}/message");
        $chat = Conversation::firstOrFail();
        $this->post("/messages/{$chat->id}", ['body' => 'Купите слона']);

        $this->actingAs($boris)->post("/messages/{$chat->id}/decline")->assertRedirect('/messages');
        $this->get('/messages')->assertInertia(fn ($page) => $page->has('requests', 0));

        $this->travel(1)->minutes();
        $this->actingAs($anna)->post("/messages/{$chat->id}", ['body' => 'Ну купите']);
        $this->actingAs($boris)->get('/messages')->assertInertia(fn ($page) => $page->has('requests', 1));
        $this->post("/messages/{$chat->id}/accept");
        $this->get('/messages')->assertInertia(fn ($page) => $page->has('conversations', 1)->has('requests', 0));
    }

    public function test_members_who_take_messages_from_friends_only_cannot_be_written_to_by_others()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $this->actingAs($boris)->put('/settings/messages', ['messages_from' => 'friends'])->assertRedirect();

        $this->actingAs($anna)->get("/people/{$boris->id}")->assertInertia(fn ($page) => $page->where('profile.can_message', false));
        $this->post("/people/{$boris->id}/message")->assertForbidden();
    }

    public function test_blocking_messages_only_shuts_the_chat_but_not_the_rest()
    {
        [$anna, $boris] = User::factory()->count(2)->create();
        $this->actingAs($anna)->post("/people/{$boris->id}/message");
        $chat = Conversation::firstOrFail();
        $this->post("/messages/{$chat->id}", ['body' => 'Привет!']);

        $this->actingAs($boris)->post("/people/{$anna->id}/message-block")->assertRedirect();
        $this->get("/messages/{$chat->id}")->assertInertia(fn ($page) => $page->where('conversation.blocked', 'messages_by_me'));
        $this->get('/settings/blocked')->assertInertia(fn ($page) => $page->has('messenger', 1)->has('people', 0));

        $this->actingAs($anna)->post("/messages/{$chat->id}", ['body' => 'Ещё раз'])->assertForbidden();
        $this->get("/messages/{$chat->id}")->assertInertia(fn ($page) => $page->where('conversation.blocked', 'by_them'));
        // Profiles and the rest of the club stay open.
        $this->get("/people/{$boris->id}")->assertOk()->assertInertia(fn ($page) => $page->where('profile.can_message', false)->where('profile.blocked_by_me', false));

        $this->actingAs($boris)->delete("/people/{$anna->id}/message-block");
        $this->actingAs($anna)->post("/messages/{$chat->id}", ['body' => 'Ещё раз'])->assertRedirect();
    }
}
