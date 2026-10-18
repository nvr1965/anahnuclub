<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_chat_and_messages_are_translated_for_the_reader()
    {
        $fake = new FakeTranslator;
        $this->app->instance(Translator::class, $fake);
        $anna = User::factory()->create(['locale' => 'ru']);
        $dana = User::factory()->create(['locale' => 'he']);

        $this->actingAs($anna)->post("/people/{$dana->id}/message")->assertRedirect();
        $conversation = Conversation::firstOrFail();
        $this->post("/people/{$dana->id}/message")->assertRedirect("/messages/{$conversation->id}");
        $this->assertSame(1, Conversation::count());

        $this->post("/messages/{$conversation->id}", ['body' => 'Привет, как дела?'])->assertRedirect();
        // Translated only into the reader's language.
        $this->assertSame(1, $fake->calls);

        $this->actingAs($dana)->get('/feed')->assertInertia(fn ($page) => $page->where('counts.messages', 1));
        $this->get("/messages/{$conversation->id}")->assertInertia(fn ($page) => $page
            ->component('messages/index')
            ->where('messages.0.body', 'Привет, как дела?')
            ->where('messages.0.translation.fields.body', '[he] Привет, как дела?')
            ->where('conversations.0.with.id', $anna->id)
        );
        $this->get('/feed')->assertInertia(fn ($page) => $page->where('counts.messages', 0));
    }

    public function test_outsiders_cannot_read_or_write_a_conversation()
    {
        [$anna, $boris, $eve] = User::factory()->count(3)->create();
        $conversation = Conversation::between($anna, $boris);

        $this->actingAs($eve)->get("/messages/{$conversation->id}")->assertNotFound();
        $this->post("/messages/{$conversation->id}", ['body' => 'hi'])->assertNotFound();
    }
}
