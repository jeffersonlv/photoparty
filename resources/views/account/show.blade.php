<x-layouts.app :title="__('account.title')" width="max-w-3xl">
    <h1 class="page-title mb-6">{{ __('account.title') }}</h1>

    <section class="card mb-6">
        <h2 class="section-title mb-3">{{ __('account.data_title') }}</h2>
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold text-muted">{{ __('account.account_name') }}</dt><dd class="font-bold">{{ $account->name }}</dd></div>
            <div><dt class="text-xs font-semibold text-muted">{{ __('account.plan') }}</dt><dd class="font-bold">{{ $account->plan?->name ?? __('app.dashboard.no_plan') }} · {{ $account->status->label() }}</dd></div>
            <div><dt class="text-xs font-semibold text-muted">{{ __('account.user') }}</dt><dd>{{ $user->name }} · {{ $user->email }}</dd></div>
            <div><dt class="text-xs font-semibold text-muted">{{ __('account.retention') }}</dt><dd>{{ __('account.retention_days', ['days' => $retentionDays]) }}</dd></div>
        </dl>
    </section>

    <section class="card mb-6">
        <h2 class="section-title mb-2">{{ __('account.privacy_title') }}</h2>
        <p class="mb-2 text-sm">{{ __('account.privacy_roles') }}</p>
        <p class="text-sm"><a class="font-bold text-brand-700 underline" href="{{ route('legal.privacy') }}">{{ __('account.privacy_policy') }}</a> · <a class="font-bold text-brand-700 underline" href="{{ route('legal.terms') }}">{{ __('account.terms') }}</a></p>
    </section>

    @if ($isOwner)
        <section class="card mb-6">
            <h2 class="section-title mb-2">{{ __('account.export_title') }}</h2>
            <p class="mb-4 text-sm">{{ __('account.export_text') }}</p>
            <a class="btn btn-secondary" href="{{ route('account.export') }}">{{ __('account.export_button') }}</a>
        </section>

        <section class="card ring-red-200">
            <h2 class="section-title mb-2 text-red-700">{{ __('account.delete_title') }}</h2>
            <p class="mb-4 text-sm">{{ __('account.delete_text') }}</p>
            <x-confirm-form :action="route('account.destroy')" method="DELETE" :confirm="__('account.delete_confirm_dialog')" class="space-y-4">
                <div>
                    <x-label for="password">{{ __('account.delete_password') }}</x-label>
                    <x-password-input name="password" />
                    <x-input-error name="password" />
                </div>
                <label class="choice">
                    <input type="checkbox" name="confirm" value="1" class="checkbox" required>
                    {{ __('account.delete_confirm') }}
                </label>
                <x-input-error name="confirm" />
                <button type="submit" class="btn btn-danger">{{ __('account.delete_button') }}</button>
            </x-confirm-form>
        </section>
    @else
        <p class="alert alert-info">{{ __('account.owner_only') }}</p>
    @endif
</x-layouts.app>
