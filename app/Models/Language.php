<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'native_name', 'rtl', 'enabled', 'sort'];

    protected function casts(): array
    {
        return ['rtl' => 'boolean', 'enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('languages.enabled'));
        static::deleted(fn () => Cache::forget('languages.enabled'));
    }

    /** @return Collection<int, Language> */
    public static function enabled(): Collection
    {
        return Cache::rememberForever('languages.enabled', fn () => static::query()
            ->where('enabled', true)->orderBy('sort')->get());
    }

    public static function isEnabled(?string $code): bool
    {
        return $code !== null && static::enabled()->contains('code', $code);
    }
}
