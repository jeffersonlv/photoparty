<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
        ]);

        $accounts = Account::query()
            ->with('plan')
            ->withCount(['events', 'users'])
            ->when($filters['q'] ?? null, function ($query, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('uuid', 'like', $like)
                        ->orWhereHas('users', fn ($u) => $u->where('email', 'like', $like));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.accounts.index', [
            'accounts' => $accounts,
            'filters' => $filters,
            'statuses' => AccountStatus::cases(),
        ]);
    }

    public function show(Account $account): View
    {
        $photos = Photo::withoutGlobalScopes()->where('account_id', $account->id);

        return view('admin.accounts.show', [
            'account' => $account->load(['plan', 'users']),
            'plans' => Plan::query()->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => AccountStatus::cases(),
            'usage' => [
                'events' => Event::withoutGlobalScopes()->where('account_id', $account->id)->count(),
                'photos' => (clone $photos)->count(),
                'bytes' => (int) (clone $photos)->sum('bytes'),
            ],
            'events' => Event::withoutGlobalScopes()->where('account_id', $account->id)->latest('starts_at')->limit(10)->get(),
        ]);
    }

    public function updatePlan(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
        ]);

        $from = $account->plan_id;
        $account->forceFill(['plan_id' => $data['plan_id'] ?? null])->save();

        $this->audit->log('account.plan_changed', $account, ['from' => $from, 'to' => $account->plan_id]);

        return back()->with('status', __('admin.accounts.plan_updated'));
    }

    public function updateStatus(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(AccountStatus::class)],
        ]);

        $from = $account->status->value;
        $account->forceFill(['status' => $data['status']])->save();

        $this->audit->log('account.status_changed', $account, ['from' => $from, 'to' => $data['status']]);

        return back()->with('status', __('admin.accounts.status_updated'));
    }

    public function extendTrial(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        // Teste ainda não iniciado: nada para estender (o teste de 3 h começa na publicação do evento).
        if ($account->trial_used_at === null) {
            return back()->withErrors(['hours' => __('admin.accounts.trial_not_started')]);
        }

        $base = $account->trial_ends_at !== null && $account->trial_ends_at->isFuture() ? $account->trial_ends_at : now();
        $from = $account->trial_ends_at?->toIso8601String();

        $account->forceFill([
            'trial_ends_at' => $base->copy()->addHours((int) $data['hours']),
            'status' => $account->status === AccountStatus::TrialEnded ? AccountStatus::Trial : $account->status,
        ])->save();

        $this->audit->log('account.trial_extended', $account, [
            'hours' => (int) $data['hours'],
            'from' => $from,
            'to' => $account->trial_ends_at->toIso8601String(),
        ]);

        return back()->with('status', __('admin.accounts.trial_extended'));
    }
}
