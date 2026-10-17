<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_people_and_groups(): void
    {
        $user = User::factory()->create(['name' => 'Searcher']);
        User::factory()->create(['name' => 'Anna Levi']);
        Group::create(['name' => 'Haifa friends', 'slug' => 'haifa-friends', 'owner_id' => $user->id]);

        $this->actingAs($user)->get('/search?q=levi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('search')->has('results.people', 1));

        $this->actingAs($user)->get('/search?q=haifa')
            ->assertInertia(fn ($page) => $page->has('results.groups', 1));
    }
}
