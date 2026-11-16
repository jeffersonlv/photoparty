<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\AuditLogger;
use App\Services\HomeContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    public function __construct(private AuditLogger $audit, private HomeContent $home) {}

    public function edit(): View
    {
        return view('admin.home.settings', [
            'definitions' => config('home_sections.settings'),
            'values' => SiteSetting::allKeyed(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $fields = $this->fields();
        $rules = [];

        foreach ($fields as $field => $def) {
            $rules[$field] = ['nullable', 'string', 'max:'.$def['max'], ...(! empty($def['email']) ? ['email'] : [])];
        }

        $data = $request->validate($rules);

        foreach ($fields as $field => $def) {
            $value = isset($data[$field]) ? trim($data[$field]) : '';

            SiteSetting::query()->updateOrCreate(['key' => $field], ['value' => $value === '' ? null : $value]);
        }

        $this->home->forget();
        $this->audit->log('home.settings_updated', null, ['fields' => array_keys($fields)]);

        return back()->with('status', __('admin.home.saved'));
    }

    /**
     * Chaves reais gravadas em site_settings (campos por idioma viram key_locale).
     *
     * @return array<string, array<string, mixed>>
     */
    private function fields(): array
    {
        $fields = [];

        foreach (config('home_sections.settings') as $key => $def) {
            if ($def['per_locale']) {
                foreach (config('photoparty.locales') as $locale) {
                    $fields["{$key}_{$locale}"] = $def;
                }
            } else {
                $fields[$key] = $def;
            }
        }

        return $fields;
    }
}
