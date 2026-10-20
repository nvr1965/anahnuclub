<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $dana->id, 'accepted' => true]);

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

    public function test_files_are_sent_in_a_chat_and_only_its_members_open_them()
    {
        Storage::fake('local');
        [$anna, $dana, $eve] = User::factory()->count(3)->create();
        DB::table('friendships')->insert(['user_id' => $anna->id, 'friend_id' => $dana->id, 'accepted' => true]);
        $conversation = Conversation::between($anna, $dana);

        $this->actingAs($anna)->post("/messages/{$conversation->id}", [
            'files' => [UploadedFile::fake()->image('sea.jpg', 40, 40), UploadedFile::fake()->create('plan.pdf', 120, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $this->post("/messages/{$conversation->id}", ['files' => [UploadedFile::fake()->create('virus.exe', 10)]])->assertSessionHasErrors('files.0');

        $this->actingAs($dana)->get("/messages/{$conversation->id}")->assertInertia(fn ($page) => $page
            ->where('messages.0.body', '')
            ->where('messages.0.files.0.name', 'sea.jpg')
            ->where('messages.0.files.0.image', true)
            ->where('messages.0.files.1.image', false)
            ->where('conversations.0.last.body', '📎 sea.jpg, plan.pdf'));

        $file = MessageAttachment::where('name', 'plan.pdf')->firstOrFail();
        $this->get("/messages/files/{$file->id}")->assertOk()->assertDownload('plan.pdf');
        $this->actingAs($eve)->get("/messages/files/{$file->id}")->assertNotFound();
    }

    public function test_older_messages_are_translated_when_the_reader_switches_language()
    {
        $fake = new FakeTranslator;
        $this->app->instance(Translator::class, $fake);
        [$anna, $dana, $eve] = User::factory()->count(3)->create(['locale' => 'ru']);
        $conversation = Conversation::between($anna, $dana);
        $message = $conversation->messages()->create(['user_id' => $anna->id, 'body' => 'Привет', 'locale' => 'ru', 'created_at' => now()->subDay()]);

        $this->actingAs($dana)->post('/locale', ['locale' => 'he']);
        $this->get("/messages/{$conversation->id}")->assertInertia(fn ($page) => $page->where('messages.0.translation.pending', true));
        $this->postJson('/translations', ['items' => [['type' => 'message', 'id' => $message->id]]])
            ->assertJsonPath('results.0.fields.body', '[he] Привет');

        // Someone outside the chat gets nothing.
        $this->actingAs($eve)->postJson('/translations', ['items' => [['type' => 'message', 'id' => $message->id]]])->assertJsonPath('results', []);
    }
}
