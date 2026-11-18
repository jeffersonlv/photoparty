<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoEvent;
use Illuminate\Console\Command;

/**
 * Demonstração da página inicial: mantém o evento sempre ao vivo (ajusta início/fim) e apaga
 * TODAS as fotos e arquivos dela. Roda de hora em hora (routes/console.php).
 * Sem evento de demo (seeder não rodou), não faz nada.
 */
class ResetDemo extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Apaga as fotos da demonstração ao vivo e mantém o evento de demo ao vivo';

    public function handle(DemoEvent $demo): int
    {
        $event = $demo->event();

        if ($event === null) {
            $this->warn('Evento de demonstração não existe (php artisan db:seed --class=DemoSeeder).');

            return self::SUCCESS;
        }

        $photos = $demo->purgePhotos($event);
        $demo->keepLive($event);

        $this->info("Demo reiniciada. Fotos apagadas: {$photos}");

        return self::SUCCESS;
    }
}
