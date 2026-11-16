@props(['action'])
{{-- Confirmação sem JavaScript: o formulário só aparece depois de abrir o <details>. --}}
<details class="adm-confirm">
    <summary class="adm-btn adm-btn--danger adm-btn--sm">{{ __('admin.common.delete') }}</summary>
    <form method="POST" action="{{ $action }}" class="adm-confirm__box">
        @csrf
        @method('DELETE')
        <p>{{ __('admin.common.delete_confirm') }}</p>
        <button type="submit" class="adm-btn adm-btn--danger adm-btn--sm">{{ __('admin.common.delete') }}</button>
    </form>
</details>
