<?php

namespace App\Http\Controllers\Admin;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The login journal: who signed in when and from where, and the failed attempts. */
class LoginController extends AdminController
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('filter');
        $user = $request->integer('user') ? User::find($request->integer('user')) : null;

        $logs = LoginLog::query()
            ->with('user:id,name,email')
            ->when($user, fn (Builder $b) => $b->where('user_id', $user->id))
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w
                ->where('ip', $this->like(), $this->pattern($q))
                ->orWhere('login', $this->like(), $this->pattern($q))
                ->orWhereHas('user', fn (Builder $u) => $u
                    ->where('name', $this->like(), $this->pattern($q))
                    ->orWhere('email', $this->like(), $this->pattern($q)))))
            ->when($filter === 'failed', fn (Builder $b) => $b->where('success', false))
            ->orderByDesc('id')
            ->paginate(50)->withQueryString()
            ->through(fn (LoginLog $log) => $log->toRow());

        return Inertia::render('admin/logins', [
            'logs' => $logs,
            'q' => $q,
            'filter' => $filter,
            'user' => $user ? ['id' => $user->id, 'name' => $user->name] : null,
            'today' => LoginLog::where('success', true)->where('created_at', '>=', now()->startOfDay())->count(),
            'failed_today' => LoginLog::where('success', false)->where('created_at', '>=', now()->startOfDay())->count(),
        ]);
    }
}
