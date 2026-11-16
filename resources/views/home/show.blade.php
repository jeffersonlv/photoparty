<x-home.layout :title="$settings['seo_title'] ?? __('app.name')" :description="$settings['seo_description'] ?? __('app.tagline')" :settings="$settings" :preview="$preview ?? false">
    @foreach ($sections as $section)
        @includeIf('home.sections.'.$section['key'], ['content' => $section['content'], 'items' => $section['items'], 'plans' => $plans ?? collect()])
    @endforeach
</x-home.layout>
