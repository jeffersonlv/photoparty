<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use App\Services\AuditLogger;
use App\Support\Reorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Molduras do sistema (account_id nulo): ativar/desativar e ordenar.
 */
class FrameController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.frames.index', ['frames' => $this->systemFrames()->get()]);
    }

    public function toggle(Frame $frame): RedirectResponse
    {
        $this->ensureSystem($frame);

        $frame->forceFill(['is_active' => ! $frame->is_active])->save();

        $this->audit->log('frame.toggled', $frame, ['name' => $frame->name, 'is_active' => (bool) $frame->is_active]);

        return back()->with('status', __('admin.frames.updated'));
    }

    public function move(Request $request, Frame $frame): RedirectResponse
    {
        $this->ensureSystem($frame);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        Reorder::move($this->systemFrames()->get(), $frame, $direction);

        $this->audit->log('frame.moved', $frame, ['name' => $frame->name, 'direction' => $direction]);

        return back()->with('status', __('admin.frames.updated'));
    }

    private function systemFrames()
    {
        return Frame::withoutGlobalScopes()->whereNull('account_id')->orderBy('sort_order')->orderBy('id');
    }

    private function ensureSystem(Frame $frame): void
    {
        abort_unless($frame->account_id === null, 404);
    }
}
