<?php

namespace Database\Seeders;

use App\Services\Demo\DemoEvent;
use App\Services\HomeContent;
use Illuminate\Database\Seeder;

/**
 * Evento de demonstração da página inicial (conta interna "Photo Party Demo"). Idempotente e
 * seguro em produção:  php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $event = app(DemoEvent::class)->ensure();

        app(HomeContent::class)->forget();

        $this->command?->info('Demo ao vivo: /e/'.$event->slug.' (fotos apagadas de hora em hora por demo:reset).');
    }
}
