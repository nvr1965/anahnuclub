<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A member's own recent sign-ins, to spot one that was not theirs. */
class LoginHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/logins', [
            'logs' => LoginLog::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (LoginLog $log) => [
                    'id' => $log->id,
                    'method' => $log->method,
                    'success' => $log->success,
                    'ip' => $log->ip,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
