<?php

namespace App\Http\Controllers\Admin;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Messages sent through the feedback form. */
class ContactController extends AdminController
{
    public function index(): Response
    {
        return Inertia::render('admin/contact', [
            'messages' => ContactMessage::query()->latest()->paginate(30)->through(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'subject' => $m->subject,
                'body' => $m->body,
                'user_id' => $m->user_id,
                'locale' => $m->locale,
                'read' => $m->read_at !== null,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(ContactMessage $message): RedirectResponse
    {
        $message->update(['read_at' => $message->read_at ? null : now()]);

        return back();
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return back();
    }
}
