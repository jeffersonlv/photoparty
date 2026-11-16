<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $account = new Account(['name' => $data['name']]);
            $account->status = AccountStatus::Trial;
            $account->save();

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->account_id = $account->id;
            $user->role = UserRole::Owner;

            // Confirmação de e-mail desligada (config photoparty.require_email_verification):
            // a conta já nasce verificada e o middleware 'verified' continua no lugar,
            // pronto para quando a confirmação for religada.
            if (! config('photoparty.require_email_verification')) {
                $user->email_verified_at = now();
            }

            $user->save();

            return $user;
        });

        if (config('photoparty.require_email_verification')) {
            // Envio direto (sem o evento Registered) para evitar e-mail em dobro
            // caso o listener padrão também esteja registrado.
            $user->sendEmailVerificationNotification();
        }

        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::record('auth.registered', $user->account, user: $user);

        return config('photoparty.require_email_verification')
            ? redirect()->route('verification.notice')
            : redirect()->route('dashboard');
    }
}
