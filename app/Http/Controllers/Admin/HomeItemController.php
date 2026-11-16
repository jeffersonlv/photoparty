<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeItem;
use App\Models\HomeSection;
use App\Services\AuditLogger;
use App\Services\HomeContent;
use App\Support\HomeSchema;
use App\Support\Reorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Itens repetíveis de uma seção. Rotas com scopeBindings: o item precisa ser da seção.
 */
class HomeItemController extends Controller
{
    public function __construct(private AuditLogger $audit, private HomeContent $home) {}

    public function create(HomeSection $section): View
    {
        $this->ensureHasItems($section);

        return view('admin.home.item-form', ['section' => $section, 'item' => new HomeItem, 'schema' => $section->schema()['items']]);
    }

    public function store(Request $request, HomeSection $section): RedirectResponse
    {
        $schema = $this->ensureHasItems($section);

        if ($section->items()->reorder()->count() >= $schema['max']) {
            return back()->with('error', __('admin.home.items_max', ['max' => $schema['max']]));
        }

        $data = $this->validated($request, $schema);

        $item = $section->items()->create(['data' => $data]);
        $item->forceFill([
            'enabled' => $request->boolean('enabled', true),
            'sort_order' => (int) $section->items()->reorder()->max('sort_order') + 1,
        ])->save();

        $this->saved($section, 'home.item_created', $item);

        return redirect()->route('admin.home.sections.edit', $section)->with('status', __('admin.home.saved'));
    }

    public function edit(HomeSection $section, HomeItem $item): View
    {
        return view('admin.home.item-form', ['section' => $section, 'item' => $item, 'schema' => $this->ensureHasItems($section)]);
    }

    public function update(Request $request, HomeSection $section, HomeItem $item): RedirectResponse
    {
        $schema = $this->ensureHasItems($section);

        $item->forceFill([
            'data' => $this->validated($request, $schema),
            'enabled' => $request->boolean('enabled'),
        ])->save();

        $this->saved($section, 'home.item_updated', $item);

        return redirect()->route('admin.home.sections.edit', $section)->with('status', __('admin.home.saved'));
    }

    public function destroy(HomeSection $section, HomeItem $item): RedirectResponse
    {
        $this->ensureHasItems($section);

        $item->delete();

        $this->saved($section, 'home.item_deleted', $item);

        return redirect()->route('admin.home.sections.edit', $section)->with('status', __('admin.home.saved'));
    }

    public function toggle(HomeSection $section, HomeItem $item): RedirectResponse
    {
        $this->ensureHasItems($section);

        $item->forceFill(['enabled' => ! $item->enabled])->save();

        $this->saved($section, 'home.item_toggled', $item, ['enabled' => $item->enabled]);

        return back()->with('status', __('admin.home.saved'));
    }

    public function move(Request $request, HomeSection $section, HomeItem $item): RedirectResponse
    {
        $this->ensureHasItems($section);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        Reorder::move($section->items()->get(), $item, $direction);

        $this->saved($section, 'home.item_moved', $item, ['direction' => $direction]);

        return back()->with('status', __('admin.home.saved'));
    }

    /**
     * @return array{max: int, fields: array<string, array<string, mixed>>}
     */
    private function ensureHasItems(HomeSection $section): array
    {
        $schema = $section->schema()['items'] ?? null;

        abort_if($schema === null, 404);

        return $schema;
    }

    /**
     * @param  array{fields: array<string, array<string, mixed>>}  $schema
     * @return array<string, array<string, string>>
     */
    private function validated(Request $request, array $schema): array
    {
        $validated = $request->validate(
            HomeSchema::rules($schema['fields'], 'data'),
            [],
            HomeSchema::attributes($schema['fields'], 'data'),
        );

        return HomeSchema::extract($schema['fields'], $validated['data']);
    }

    private function saved(HomeSection $section, string $action, HomeItem $item, array $meta = []): void
    {
        $section->forceFill(['updated_by' => auth()->id()])->save();

        $this->home->forget();
        $this->audit->log($action, $item, ['section' => $section->key, 'item_id' => $item->id] + $meta);
    }
}
