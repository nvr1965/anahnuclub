<?php

namespace App\Http\Controllers\Admin;

use App\Support\MailHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailController extends AdminController
{
    /** Sends a test letter to the admin right away, to see that letters from the site arrive. */
    public function test(Request $request): JsonResponse
    {
        $error = MailHealth::sendTest($request->user()->email);
        if ($error !== null) {
            return response()->json(['ok' => false, 'text' => $error]);
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return response()->json(['ok' => false, 'text' => __('The letter was only written to the log, because MAIL_MAILER is :mailer.', ['mailer' => config('mail.default')])]);
        }

        return response()->json(['ok' => true, 'text' => __('Sent to :email. Check the inbox and the spam folder.', ['email' => $request->user()->email])]);
    }
}
