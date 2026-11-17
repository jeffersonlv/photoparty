<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Cria (ou promove) o administrador da plataforma. A senha é pedida de forma
 * oculta no terminal, para não ficar no histórico do shell nem em arquivos.
 */
class CreateAdmin extends Command
{
    protected $signature = 'photoparty:create-admin {email} {--name=Administrador}';

    protected $description = 'Cria ou atualiza o usuário administrador (sem conta/tenant)';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('E-mail inválido.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Senha do administrador');
        $confirmation = (string) $this->secret('Repita a senha');

        if ($password === '' || $password !== $confirmation) {
            $this->error('As senhas não conferem.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 12) {
            $this->error('Use pelo menos 12 caracteres.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if ($user->exists && $user->account_id !== null) {
            $this->error('Este e-mail pertence a um organizador. Use outro e-mail para o admin.');

            return self::FAILURE;
        }

        $user->name = (string) $this->option('name');
        $user->password = $password;
        $user->role = UserRole::Admin;
        $user->account_id = null;
        $user->email_verified_at ??= now();
        $user->save();

        AuditLog::record('admin.created_from_cli', null, user: $user);

        $this->info("Administrador pronto: {$email}");

        return self::SUCCESS;
    }
}
