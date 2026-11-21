<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The badges' numbers alone, for an open tab's refresh every half minute (see HandleInertiaRequests::counts). */
class CountsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(HandleInertiaRequests::counts($request->user()))
            ->header('Cache-Control', 'no-store, private');
    }
}
