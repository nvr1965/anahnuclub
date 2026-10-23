<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** One sign-in to the site, or a failed attempt (see LogLogins). Kept for a year. */
class LoginLog extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'login', 'method', 'success', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['success' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subYear());
    }

    public static function record(Request $request, ?User $user, string $method, bool $success, ?string $login = null): void
    {
        static::create([
            'user_id' => $user?->id,
            'login' => $login !== null ? Str::limit(trim($login), 250, '') : null,
            'method' => $method,
            'success' => $success,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '') ?: null,
        ]);
    }

    /** The row for the admin and settings pages. @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email] : null,
            'login' => $this->login,
            'method' => $this->method,
            'success' => $this->success,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
