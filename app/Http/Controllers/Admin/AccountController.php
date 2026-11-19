<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Plan;
use App\Services\AccountListSettings;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request, AccountListSettings $settings): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $perPage = $settings->perPage();
        $sections = [];

        foreach ($settings->order() as $status) {
            $query = Account::query()
                ->with('plan')
                ->withCount(['events', 'users'])
                ->where('status', $status)
                ->when($filters['q'] ?? null, function ($query, string $term) {
                    $like = '%'.addcslashes($term, '%_\\').'%';

                    $query->where(function ($q) use ($like) {
                        $q->where('name', 'like', $like)
                            ->orWhere('uuid', 'like', $like)
                            ->orWhereHas('users', fn ($u) => $u->where('email', 'like', $like));
                    });
                });

            // Expiração mais próxima primeiro (sem data por último), depois nome.
            $column = match ($status) {
                AccountStatus::Trial->value => 'trial_ends_at',
                AccountStatus::Active->value => 'paid_until',
                default => null,
            };
            if ($column !== null) {
                $query->orderByRaw("{$column} is null")->orderBy($column);
            }
            $query->orderBy('name')->orderBy('id');

            $sections[$status] = $query
                ->paginate($perPage[$status], ['*'], 'page_'.$status)
                ->fragment('status-'.$status)
                ->withQueryString();
        }

        return view('admin.accounts.index', [
            'sections' => $sections,
            'filters' => $filters,
            'order' => $settings->order(),
            'perPage' => $perPage,
            'perPageOptions' => AccountListSettings::PER_PAGE_OPTIONS,
        ]);
    }

    public function updateSettings(Request $request, AccountListSettings $settings): RedirectResponse
    {
        $statuses = AccountListSettings::statusValues();

        $rules = [
            'order' => ['required', 'array', 'size:'.count($statuses)],
            'order.*' => ['required', 'string', 'distinct', Rule::in($statuses)],
            'per_page' => ['required', 'array'],
            'move' => ['nullable', 'string', 'regex:/^[a-z_]+:(up|down)$/'],
        ];
        foreach ($statuses as $status) {
            $rules["per_page.{$status}"] = ['required', 'integer', Rule::in(AccountListSettings::PER_PAGE_OPTIONS)];
        }

        $data = $request->validate($rules);

        $order = array_values($data['order']);
        if (! empty($data['move'])) {
            [$status, $direction] = explode(':', $data['move']);
            $i = array_search($status, $order, true);
            $j = $i === false ? null : ($direction === 'up' ? $i - 1 : $i + 1);
            if ($j !== null && isset($order[$j])) {
                [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            }
        }

        $perPage = [];
        foreach ($statuses as $status) {
            $perPage[$status] = (int) $data['per_page'][$status];
        }

        $before = ['order' => $settings->order(), 'per_page' => $settings->perPage()];
        $after = ['order' => $order, 'per_page' => $perPage];

        $settings->save($order, $perPage);

        if ($before !== $after) {
            $this->audit->log('account.list_settings_changed', null, ['from' => $before, 'to' => $after]);
        }

        return redirect()->route('admin.accounts.index')
            ->with('status', __('admin.accounts.settings_saved'));
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
            'paid_until' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = $account->plan_id;
        $account->forceFill(['plan_id' => $data['plan_id'] ?? null])->save();

        $this->audit->log('account.plan_changed', $account, ['from' => $from, 'to' => $account->plan_id]);

        if ($request->has('paid_until')) {
            $this->setPaidUntil($account, $data['paid_until'] ?? null);
        }

        return back()->with('status', __('admin.accounts.plan_updated'));
    }

    public function updatePaidUntil(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'paid_until' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $this->setPaidUntil($account, $data['paid_until'] ?? null);

        return back()->with('status', __('admin.accounts.paid_until_updated'));
    }

    /**
     * A data informada vale até o fim do dia (23:59:59, fuso da aplicação).
     */
    private function setPaidUntil(Account $account, ?string $date): void
    {
        $to = $date === null || $date === '' ? null : Carbon::createFromFormat('Y-m-d', $date)->endOfDay()->setMicroseconds(0);
        $from = $account->paid_until;

        if (($from === null && $to === null) || ($from !== null && $to !== null && $from->equalTo($to))) {
            return;
        }

        $account->forceFill(['paid_until' => $to])->save();

        $this->audit->log('account.paid_until_changed', $account, [
            'from' => $from?->toIso8601String(),
            'to' => $to?->toIso8601String(),
        ]);
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
