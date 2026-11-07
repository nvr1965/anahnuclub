<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Support\Badges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Levels and special badges: what each level asks for, and how many members hold each. */
class BadgeController extends AdminController
{
    public function index(): Response
    {
        $levels = User::query()->groupBy('level')->selectRaw('level, count(*) as n')->pluck('n', 'level');
        $special = [];
        foreach (Badges::SPECIAL as $badge) {
            $special[$badge] = User::query()->whereJsonContains('badges', $badge)->count();
        }

        return Inertia::render('admin/badges', [
            'thresholds' => Badges::thresholds(),
            'levels' => array_map(fn ($slug, $i) => ['slug' => $slug, 'count' => (int) ($levels[$i] ?? 0)], Badges::LEVELS, array_keys(Badges::LEVELS)),
            'special' => array_map(fn ($slug) => ['slug' => $slug, 'count' => $special[$slug]], Badges::SPECIAL),
        ]);
    }

    /** Saves what the levels ask for and counts every member's level again. */
    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(Badges::DEFAULT_THRESHOLDS) as $level) {
            foreach (Badges::MEASURES as $m) {
                $rules["thresholds.$level.$m"] = ['required', 'integer', 'min:0', 'max:1000000'];
            }
        }
        Badges::saveThresholds($request->validate($rules)['thresholds']);
        Badges::refreshAll();

        return back();
    }
}
