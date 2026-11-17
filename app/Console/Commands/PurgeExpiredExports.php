<?php

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Apaga do disco os ZIPs vencidos (7 dias) e marca o registro como expired.
 */
class PurgeExpiredExports extends Command
{
    protected $signature = 'exports:purge-expired';

    protected $description = 'Apaga os ZIPs de exportação expirados';

    public function handle(): int
    {
        $count = 0;
        $disk = Storage::disk('local');

        Export::withoutGlobalScopes()
            ->where('status', 'ready')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($exports) use (&$count, $disk) {
                foreach ($exports as $export) {
                    if ($export->path !== null && str_starts_with($export->path, 'tenants/') && ! str_contains($export->path, '..')) {
                        $disk->delete($export->path);
                    }

                    $export->forceFill(['status' => 'expired', 'path' => null, 'bytes' => null])->save();
                    $count++;
                }
            });

        $this->info("ZIPs expirados apagados: {$count}");

        return self::SUCCESS;
    }
}
