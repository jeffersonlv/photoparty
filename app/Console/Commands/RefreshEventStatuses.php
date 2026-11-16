<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Atualiza o status gravado dos eventos publicados conforme o horário
 * (scheduled -> live -> ended -> expired). Roda a cada minuto pelo scheduler.
 * O status também é recalculado na leitura (Event::computeStatus), então o
 * cron só mantém a coluna em dia para listagens e filtros.
 */
class RefreshEventStatuses extends Command
{
    protected $signature = 'events:refresh-statuses';

    protected $description = 'Atualiza o estado dos eventos (agendado, ao vivo, encerrado, expirado) pelo horário';

    public function handle(): int
    {
        $now = now();
        $changed = 0;

        Event::withoutGlobalScopes()
            ->whereNotNull('published_at')
            ->where('status', '!=', EventStatus::Expired->value)
            ->chunkById(200, function ($events) use ($now, &$changed) {
                foreach ($events as $event) {
                    if ($event->refreshStatus($now)) {
                        $event->saveQuietly();
                        $changed++;
                    }
                }
            });

        $this->info("Eventos atualizados: {$changed}");

        return self::SUCCESS;
    }
}
