<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()->withCount('accounts')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', ['plan' => new Plan(['currency' => 'EUR', 'is_public' => true, 'sort_order' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = new Plan($this->validated($request));
        $plan->save();

        $this->audit->log('plan.created', $plan, ['name' => $plan->name]);

        return redirect()->route('admin.plans.index')->with('status', __('admin.plans.saved'));
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->fill($this->validated($request, $plan));
        $changes = array_keys($plan->getDirty());
        $plan->save();

        $this->audit->log('plan.updated', $plan, ['name' => $plan->name, 'changed' => $changes]);

        return redirect()->route('admin.plans.index')->with('status', __('admin.plans.saved'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->accounts()->exists()) {
            return back()->with('error', __('admin.plans.in_use'));
        }

        $this->audit->log('plan.deleted', $plan, ['name' => $plan->name]);
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', __('admin.plans.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash:ascii', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii'],
            'max_events_per_month' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_photos_per_event' => ['required', 'integer', 'min:1', 'max:1000000'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['allows_custom_frames'] = $request->boolean('allows_custom_frames');
        $data['allows_geofence'] = $request->boolean('allows_geofence');
        $data['white_label'] = $request->boolean('white_label');
        $data['is_public'] = $request->boolean('is_public');

        return $data;
    }
}
