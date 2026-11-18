<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Services\PrivacySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Termos e Política de privacidade (LGPD + GDPR), versionados por config('photoparty.privacy.legal_version').
 *
 * Texto em lang/{pt_BR,en}/legal.php; dados do operador/DPO vêm de site_settings (PrivacySettings),
 * nunca inventados: vazio => "[a preencher]". ?lang=pt_BR|en força o idioma (link da página do convidado).
 *
 * Aviso de revisão jurídica (decisão documentada no README): o admin vê o aviso em destaque
 * ("Este texto é um modelo e deve ser revisado por um advogado...") e a lista de campos vazios;
 * o público vê só uma nota discreta no rodapé do texto, enquanto PHOTOPARTY_LEGAL_REVIEWED=false.
 */
class LegalController extends Controller
{
    public function __construct(private readonly PrivacySettings $settings)
    {
    }

    public function terms(Request $request): View
    {
        return $this->render($request, 'terms');
    }

    public function privacy(Request $request): View
    {
        return $this->render($request, 'privacy');
    }

    private function render(Request $request, string $page): View
    {
        $lang = $request->query('lang');

        if (is_string($lang) && in_array($lang, config('photoparty.locales'), true)) {
            App::setLocale($lang);
        }

        $replace = $this->replacements();
        $content = $this->fill(__('legal.'.$page), $replace);
        $user = $request->user();
        $isAdmin = $user !== null && $user->isAdmin();

        return view('home.legal', [
            'page' => $page,
            'content' => $content,
            'cookies' => $this->fill(__('legal.cookies_table'), $replace),
            'version' => config('photoparty.privacy.legal_version'),
            'reviewed' => (bool) config('photoparty.privacy.legal_reviewed'),
            'isAdmin' => $isAdmin,
            'missing' => $isAdmin ? array_map(fn (string $key) => __('admin.privacy.fields.'.$key), $this->settings->missing()) : [],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $d = $this->settings->display();
        $raw = $this->settings->values();

        return [
            ':company_id' => $d['privacy_company_id'],
            ':company' => $d['privacy_company_name'],
            ':address' => $d['privacy_company_address'],
            ':contact' => $d['privacy_contact_email'],
            ':dpo_email' => $d['privacy_dpo_email'],
            ':dpo' => $d['privacy_dpo_name'],
            ':region' => $d['privacy_hosting_region'],
            ':transfer' => $raw['privacy_transfer_note'] !== '' ? $raw['privacy_transfer_note'] : __('legal.transfer_default'),
            ':trial_days' => (string) config('photoparty.trial.retention_days'),
            ':lgpd_days' => (string) config('photoparty.privacy.response_days.lgpd'),
            ':gdpr_days' => (string) config('photoparty.privacy.response_days.gdpr'),
            ':cookie_days' => (string) config('photoparty.privacy.device_cookie_days'),
            ':ip_days' => (string) config('photoparty.privacy.audit_ip_retention_days'),
            ':session_minutes' => (string) config('session.lifetime'),
            ':session_cookie' => (string) config('session.cookie'),
            ':request_url' => config('photoparty.public_url').route('privacy.request.create', [], false),
            ':version' => (string) config('photoparty.privacy.legal_version'),
        ];
    }

    private function fill(mixed $value, array $replace): mixed
    {
        if (is_string($value)) {
            return strtr($value, $replace);
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->fill($v, $replace), $value);
        }

        return $value;
    }
}
