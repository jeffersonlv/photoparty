<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AccountDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Minha conta" do organizador (titular da conta): dados, exportação (JSON) e exclusão.
 * Exportar e excluir: só o owner da conta (moderador => 403). A conta vem sempre do usuário
 * autenticado, nunca do request.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountDataService $data)
    {
    }

    public function show(Request $request): View
    {
        $user = $request->user();
        $account = $user->account;

        return view('account.show', [
            'user' => $user,
            'account' => $account,
            'retentionDays' => $account->retentionDays(),
            'isOwner' => $user->isOwner(),
        ]);
    }

    public function export(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isOwner(), 403);

        $account = $user->account;
        $json = json_encode($this->data->export($account), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        AuditLog::record('account.exported', $account);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="photo-party-account-'.now()->format('Ymd').'.json"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner(), 403);

        $request->validate([
            'password' => ['required', 'string', 'current_password'],
            'confirm' => ['accepted'],
        ], [], [
            'password' => __('account.delete_password'),
            'confirm' => __('account.delete_confirm_attribute'),
        ]);

        $account = $user->account;

        Auth::guard('web')->logout();
        $this->data->delete($account);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('account.deleted'));
    }
}
