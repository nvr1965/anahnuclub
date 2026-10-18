<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

abstract class AdminController extends Controller
{
    /** Case-insensitive LIKE: ILIKE on PostgreSQL, LIKE elsewhere. */
    protected function like(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    protected function pattern(string $q): string
    {
        return '%'.addcslashes($q, '%_\\').'%';
    }
}
