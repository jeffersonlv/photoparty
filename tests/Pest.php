<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Cria um owner verificado para a conta informada (ou para uma conta nova).
 * Crie todos os registros de outras contas ANTES de actingAs(): com usuário
 * autenticado, a trait BelongsToAccount recusa gravar em outra conta.
 */
function ownerFor(?Account $account = null): User
{
    return User::factory()->for($account ?? Account::factory()->create())->create();
}

/**
 * Dados válidos para o formulário de evento (duração padrão: 2 h, válida no teste).
 *
 * @return array<string, mixed>
 */
function eventPayload(array $overrides = []): array
{
    $startsAt = now()->addMinutes(30)->startOfMinute();

    return array_merge([
        'name' => 'Festa de teste',
        'starts_at' => $startsAt->format('Y-m-d\TH:i'),
        'ends_at' => $startsAt->copy()->addHours(2)->format('Y-m-d\TH:i'),
        'moderation' => 'pre',
        'welcome_message' => 'Olá!',
        'ask_guest_name' => '1',
        'max_photos_per_guest' => 5,
        'enabled_filters' => ['none', 'bw'],
        'language' => 'pt_BR',
        'image_authorization' => '1',
    ], $overrides);
}
