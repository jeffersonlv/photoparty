<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Models\SiteSetting;
use App\Services\AuditLogger;
use App\Services\PrivacySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin: solicitações de privacidade (privacy_requests) e configurações de privacidade
 * (razão social, contato, encarregado/DPO, região da hospedagem) em site_settings.
 * Auditoria sem dados pessoais (só uuid/status/nomes de campos).
 */
class PrivacyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly PrivacySettings $settings)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');
        $status = in_array($status, [...PrivacyRequest::STATUSES, 'all'], true) ? $status : 'open';

        $requests = PrivacyRequest::query()
            ->with(['account:id,name,uuid', 'event:id,name,slug'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderBy('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.privacy.requests', [
            'requests' => $requests,
            'status' => $status,
            'openCount' => PrivacyRequest::query()->where('status', 'open')->count(),
            'missing' => $this->settings->missing(),
        ]);
    }

    public function update(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(PrivacyRequest::STATUSES)],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $privacyRequest->status = $data['status'];
        $privacyRequest->resolution_note = $data['resolution_note'] ?? null;
        $privacyRequest->completed_at = $data['status'] === 'done' ? now() : null;
        $privacyRequest->save();

        $this->audit->log('privacy_request.'.$data['status'], $privacyRequest, ['uuid' => $privacyRequest->uuid]);

        return back()->with('status', __('admin.privacy.updated'));
    }

    public function editSettings(): View
    {
        return view('admin.privacy.settings', [
            'fields' => PrivacySettings::FIELDS,
            'values' => SiteSetting::query()->whereIn('key', array_keys(PrivacySettings::FIELDS))->pluck('value', 'key')->all(),
            'missing' => $this->settings->missing(),
            'configEmail' => (string) config('photoparty.privacy_contact_email'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $rules = [];

        foreach (PrivacySettings::FIELDS as $key => $def) {
            $rules[$key] = ['nullable', 'string', 'max:'.$def['max'], ...(! empty($def['email']) ? ['email'] : [])];
        }

        $data = $request->validate($rules);

        foreach (array_keys(PrivacySettings::FIELDS) as $key) {
            $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value === '' ? null : $value]);
        }

        $this->audit->log('privacy.settings_updated', null, ['fields' => array_keys(PrivacySettings::FIELDS)]);

        return back()->with('status', __('admin.privacy.saved'));
    }
}
