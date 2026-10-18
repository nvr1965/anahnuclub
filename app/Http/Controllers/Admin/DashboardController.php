<?php

namespace App\Http\Controllers\Admin;

use App\Models\Comment;
use App\Models\ContentTranslation;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends AdminController
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/index', [
            'stats' => [
                'users' => User::count(),
                'blocked' => User::where('blocked', true)->count(),
                'admins' => User::where('is_admin', true)->count(),
                'groups' => Group::count(),
                'posts' => Post::count(),
                'posts_week' => Post::where('created_at', '>=', now()->subWeek())->count(),
                'comments' => Comment::count(),
                'translations' => ContentTranslation::count(),
            ],
            'translation_driver' => config('services.translation.driver'),
        ]);
    }
}
