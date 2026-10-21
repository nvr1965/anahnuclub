<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_sign_in_first_and_come_back_to_the_shared_link()
    {
        $this->get('/share?url=https://example.com/news/1')->assertRedirect(route('login'));
        $this->assertStringContainsString('/share?url=', session('url.intended'));
    }

    public function test_the_composer_opens_with_the_title_and_link()
    {
        $this->actingAs(User::factory()->create())
            ->get('/share?'.http_build_query(['url' => 'https://example.com/news/1', 'title' => 'Новости дня']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('share')
                ->where('body', "Новости дня\n\nhttps://example.com/news/1")
                ->where('url', 'https://example.com/news/1'));
    }

    public function test_only_web_links_are_taken()
    {
        $this->actingAs(User::factory()->create())
            ->get('/share?'.http_build_query(['url' => 'javascript:alert(1)', 'text' => 'Привет']))
            ->assertInertia(fn (Assert $page) => $page->where('body', 'Привет')->where('url', null));
    }
}
