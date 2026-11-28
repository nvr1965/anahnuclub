<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

namespace App\Http\Controllers\Admin;

use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Complaints from members and guests. Each item is decided once for all its complaints: removed
 * (the post or comment is deleted) or kept; who decided, when and why stays on the record.
 */
class ReportController extends AdminController
{
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['open', 'removed', 'kept'], true) ? $request->query('status') : 'open';
        $reports = Report::query()->where('status', $status)->with(['reporter:id,name', 'author:id,name,blocked', 'handler:id,name'])
            ->latest()->paginate(30)->withQueryString();
        // How many people complained about the same item, to see what to look at first.
        $counts = Report::query()->whereIn('reportable_id', $reports->pluck('reportable_id'))->where('status', $status)
            ->selectRaw('reportable_type, reportable_id, count(*) as n')->groupBy('reportable_type', 'reportable_id')->get()
            ->mapWithKeys(fn ($row) => [$row->reportable_type.':'.$row->reportable_id => $row->n]);

        return Inertia::render('admin/reports', [
            'status' => $status,
            'open' => Report::where('status', 'open')->count(),
            'reports' => $reports->through(fn (Report $r) => [
                'id' => $r->id,
                'type' => $r->reportable_type,
                'target_id' => $r->reportable_id,
                'url' => $r->post_id ? route('posts.show', $r->post_id).($r->reportable_type === 'comment' ? '#comment-'.$r->reportable_id : '') : null,
                'exists' => $r->status === 'open' ? $r->target() !== null : null,
                'snapshot' => $r->snapshot,
                'reason' => $r->reason,
                'details' => $r->details,
                'reporter' => $r->reporter ? ['id' => $r->reporter->id, 'name' => $r->reporter->name] : null,
                'email' => $r->email,
                'author' => $r->author ? ['id' => $r->author->id, 'name' => $r->author->name, 'blocked' => (bool) $r->author->blocked] : null,
                'same' => (int) ($counts[$r->reportable_type.':'.$r->reportable_id] ?? 1),
                'handler' => $r->handler?->name,
                'handled_at' => $r->handled_at?->toIso8601String(),
                'note' => $r->note,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** Removes or keeps the reported item; every open complaint about it is closed with the same decision. */
    public function update(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['remove', 'keep'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['action'] === 'remove') {
            $report->target()?->delete();
        }
        Report::where('reportable_type', $report->reportable_type)->where('reportable_id', $report->reportable_id)->where('status', 'open')
            ->update([
                'status' => $data['action'] === 'remove' ? 'removed' : 'kept',
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

        return back();
    }
}
