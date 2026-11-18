<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * E-mail de redefinição de senha pela fila (driver database, processada pelo cron).
 *
 * Anti-enumeração (OWASP A07): com envio síncrono por SMTP, "esqueci a senha" demorava
 * segundos só quando o e-mail existia. Na fila, a resposta tem o mesmo tempo nos dois casos
 * (junto com o timebox de config/auth.php).
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
