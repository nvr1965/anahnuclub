<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The club does not show how many members it or its groups have: not to guests, not to members. */
class NoMemberCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_tells_how_many_members_there_are()
    {
        $owner = User::factory()->create();
        $group = Group::create(['owner_id' => $owner->id, 'name' => 'Новости', 'slug' => 'news', 'published' => true]);
        $group->members()->attach($owner->id, ['role' => 'owner', 'approved' => true]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->missing('sidebar.members_count')
            ->missing('sidebar.groups.0.posts_count'));

        $this->actingAs($owner);
        $this->get('/feed')->assertInertia(fn ($page) => $page->missing('sidebar.members_count'));
        $this->get('/groups')->assertInertia(fn ($page) => $page->missing('groups.data.0.members_count'));
        $this->get('/groups/news')->assertInertia(fn ($page) => $page->missing('group.members_count'));
        $this->get('/people')->assertInertia(fn ($page) => $page->missing('total'));
        $this->get('/search?q=Новости')->assertInertia(fn ($page) => $page->missing('results.groups.0.members_count'));
    }
}
