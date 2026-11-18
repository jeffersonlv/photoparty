<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Agendamentos
|--------------------------------------------------------------------------
|
| No Hostinger, configure no hPanel um cron a cada minuto:
|   * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
|
| Ordem importa: o worker da fila roda por até 50 s, então fica por último.
|
*/

Schedule::command('accounts:expire-trials')->everyMinute()->withoutOverlapping();

Schedule::command('events:refresh-statuses')->everyMinute()->withoutOverlapping();

// ZIPs de exportação expiram em 7 dias.
Schedule::command('exports:purge-expired')->hourly()->withoutOverlapping();

// LGPD/GDPR: retenção (fotos/ZIPs de eventos vencidos, órfãos, IPs de auditoria > 90 dias).
Schedule::command('privacy:enforce-retention')->dailyAt('03:17')->withoutOverlapping();

// Fila (driver database) processada pelo cron; sem processo longo no servidor compartilhado.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2);
