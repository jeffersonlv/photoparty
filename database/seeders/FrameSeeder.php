<?php

namespace Database\Seeders;

use App\Models\Frame;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Registra os modelos prontos (public/frames/<key>/config.json) como molduras do
 * sistema: account_id nulo, type "template". Idempotente (pode rodar em produção:
 * `php artisan db:seed --class=FrameSeeder`).
 */
class FrameSeeder extends Seeder
{
    public const TEMPLATE_KEYS = ['led_billboard', 'magazine_cover', 'wanted_poster', 'floral'];

    public function run(): void
    {
        foreach (self::TEMPLATE_KEYS as $key) {
            $file = public_path("frames/{$key}/config.json");

            if (! is_file($file)) {
                throw new RuntimeException("Config da moldura não encontrado: frames/{$key}/config.json");
            }

            $config = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);

            if (($config['key'] ?? null) !== $key) {
                throw new RuntimeException("Config da moldura {$key} com key divergente.");
            }

            // Sem usuário autenticado no console: o global scope não filtra.
            $frame = Frame::withoutGlobalScopes()
                ->whereNull('account_id')
                ->where('type', 'template')
                ->where('template_key', $key)
                ->first() ?? new Frame();

            $frame->forceFill([
                'type' => 'template',
                'template_key' => $key,
                'name' => $config['name']['pt'] ?? $key,
                'config' => $config,
                'portrait_path' => "frames/{$key}",
                'landscape_path' => "frames/{$key}",
            ]);
            $frame->account_id = null;
            $frame->save();
        }
    }
}
