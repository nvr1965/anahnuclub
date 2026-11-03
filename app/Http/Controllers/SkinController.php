<?php

namespace App\Http\Controllers;

use App\Support\Skins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SkinController extends Controller
{
    /** Members keep their style on every device; guests keep it in this browser. */
    public function update(Request $request): RedirectResponse
    {
        $skin = $request->validate(['skin' => ['required', 'string', Rule::in(Skins::enabled())]])['skin'];

        $request->user()?->forceFill(['ui_skin' => $skin])->save();

        return back()->withCookie(cookie()->forever(Skins::COOKIE, $skin));
    }
}
