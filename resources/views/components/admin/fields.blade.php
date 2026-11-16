@props(['fields', 'prefix', 'values' => []])
{{-- Campos do schema, um bloco por idioma. Nomes: {prefix}[{locale}][{campo}]. --}}
@foreach (config('photoparty.locales') as $locale)
    <fieldset class="adm-fieldset">
        <legend>{{ __('admin.home.content_for', ['locale' => \App\Support\HomeSchema::localeLabel($locale)]) }}</legend>
        @foreach ($fields as $name => $def)
            @php
                $id = "{$prefix}-{$locale}-{$name}";
                $value = old("{$prefix}.{$locale}.{$name}", $values[$locale][$name] ?? '');
            @endphp
            <div class="adm-field">
                <label for="{{ $id }}">{{ __('admin.home.fields.'.$name) }}@if (! empty($def['required'])) <span class="adm-req">*</span>@endif</label>
                @if ($def['type'] === 'textarea')
                    <textarea id="{{ $id }}" name="{{ $prefix }}[{{ $locale }}][{{ $name }}]" rows="3" maxlength="{{ $def['max'] }}">{{ $value }}</textarea>
                @else
                    <input id="{{ $id }}" type="text" name="{{ $prefix }}[{{ $locale }}][{{ $name }}]" value="{{ $value }}" maxlength="{{ $def['max'] }}">
                @endif
            </div>
        @endforeach
    </fieldset>
@endforeach
