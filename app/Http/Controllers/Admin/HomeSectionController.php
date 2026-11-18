<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use App\Models\SiteSetting;
use App\Services\Demo\DemoEvent;
use App\Services\AuditLogger;
use App\Services\HomeContent;
use App\Support\HomeSchema;
use App\Support\Reorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeSectionController extends Controller
{
    public function __construct(private AuditLogger $audit, private HomeContent $home) {}

    public function index(): View
    {
        return view('admin.home.index', [
            'sections' => $this->ordered(),
        ]);
    }

    public function edit(HomeSection $section): View
    {
        return view('admin.home.edit', [
            'section' => $section->load('items'),
            'schema' => $section->schema(),
        ]);
    }

    public function update(Request $request, HomeSection $section): RedirectResponse
    {
        $fields = $section->schema()['fields'] ?? [];

        $validated = $request->validate(
            HomeSchema::rules($fields, 'content'),
            [],
            HomeSchema::attributes($fields, 'content'),
        );

        $section->forceFill([
            'content' => HomeSchema::extract($fields, $validated['content']),
            'updated_by' => $request->user()->id,
        ])->save();

        // Hero: liga/desliga a demonstração ao vivo (evento de demo + mini-telão); desligada, mostra o bloco estático.
        if ($section->key === 'hero') {
            $demoLive = $request->validate(['demo_live' => ['nullable', 'in:0,1']])['demo_live'] ?? null;

            if ($demoLive !== null) {
                SiteSetting::query()->updateOrCreate(['key' => DemoEvent::SETTING_KEY], ['value' => $demoLive]);
                $this->audit->log('home.demo_toggled', $section, ['enabled' => $demoLive === '1']);
            }
        }

        $this->home->forget();
        $this->audit->log('home.section_updated', $section, ['key' => $section->key]);

        return redirect()->route('admin.home.sections.edit', $section)->with('status', __('admin.home.saved'));
    }

    public function toggle(HomeSection $section): RedirectResponse
    {
        $section->forceFill(['enabled' => ! $section->enabled, 'updated_by' => auth()->id()])->save();

        $this->home->forget();
        $this->audit->log('home.section_toggled', $section, ['key' => $section->key, 'enabled' => $section->enabled]);

        return back()->with('status', __('admin.home.saved'));
    }

    public function move(Request $request, HomeSection $section): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        Reorder::move($this->ordered(), $section, $direction);

        $this->home->forget();
        $this->audit->log('home.section_moved', $section, ['key' => $section->key, 'direction' => $direction]);

        return back()->with('status', __('admin.home.saved'));
    }

    /**
     * Prévia da página inteira (sem cache, só seções ativas).
     */
    public function preview(Request $request): View
    {
        $locale = $this->previewLocale($request);

        return view('home.show', $this->previewData($locale));
    }

    /**
     * Prévia com o conteúdo do formulário SEM salvar nada.
     */
    public function previewSection(Request $request, HomeSection $section): View
    {
        $fields = $section->schema()['fields'] ?? [];
        $locale = $this->previewLocale($request, 'preview_locale');

        $validated = $request->validate(
            HomeSchema::rules($fields, 'content'),
            [],
            HomeSchema::attributes($fields, 'content'),
        );

        $content = HomeSchema::extract($fields, $validated['content']);

        return view('home.show', $this->previewData($locale, $section->key, $content[$locale] ?? []));
    }

    private function previewData(string $locale, ?string $key = null, ?array $override = null): array
    {
        app()->setLocale($locale);

        return $this->home->preview($locale, $key, $override) + [
            'plans' => $this->home->publicPlans(),
            'preview' => true,
        ];
    }

    private function previewLocale(Request $request, string $input = 'locale'): string
    {
        $locale = $request->input($input);

        return in_array($locale, config('photoparty.locales'), true) ? $locale : app()->getLocale();
    }

    private function ordered()
    {
        return HomeSection::query()->with('updater')->orderBy('sort_order')->orderBy('id')->get();
    }
}
