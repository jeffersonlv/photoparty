{{-- Termos / Privacidade (LGPD + GDPR). Todo texto escapado; conteúdo em lang/*/legal.php. --}}
<x-home.layout :title="$content['title'].' · '.__('app.name')" :index="false">
    <article class="hm-wrap hm-legal">
        @if ($isAdmin)
            <div class="hm-legal__notice" role="note">
                <p>{{ __('legal.admin_banner') }}</p>
                @if ($missing !== [])
                    <p>{{ __('legal.admin_missing', ['fields' => implode(', ', $missing)]) }}
                        <a class="hm-link" href="{{ route('admin.privacy.settings.edit') }}">{{ __('legal.admin_settings_link') }}</a></p>
                @endif
            </div>
        @endif
        <h1>{{ $content['title'] }}</h1>
        <p class="hm-footer__muted">{{ __('legal.version', ['version' => $version]) }}</p>
        <p>{{ $content['intro'] }}</p>

        <nav class="hm-legal__toc" aria-label="{{ __('legal.on_this_page') }}">
            <ol>
                @foreach ($content['sections'] as $section)
                    <li><a class="hm-link" href="#{{ $section['id'] }}">{{ $section['heading'] }}</a></li>
                @endforeach
            </ol>
        </nav>

        @foreach ($content['sections'] as $section)
            <section id="{{ $section['id'] }}">
                <h2>{{ $section['heading'] }}</h2>
                @foreach ($section['paragraphs'] ?? [] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @if (! empty($section['items']))
                    <ul>
                        @foreach ($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($section['cookies']))
                    <div class="hm-legal__table">
                        <table>
                            <thead>
                                <tr><th>{{ $cookies['name'] }}</th><th>{{ $cookies['purpose'] }}</th><th>{{ $cookies['duration'] }}</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($cookies['rows'] as $row)
                                    <tr><td><code>{{ $row['name'] }}</code></td><td>{{ $row['purpose'] }}</td><td>{{ $row['duration'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach

        @unless ($reviewed)
            <p class="hm-legal__draft">{{ __('legal.public_note') }}</p>
        @endunless
        <p><a class="hm-link" href="{{ route('home') }}">&larr; {{ __('legal.back') }}</a></p>
    </article>
</x-home.layout>
