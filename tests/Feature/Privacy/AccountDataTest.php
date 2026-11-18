<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GuestUpload;

/*
| "Minha conta": exportação (acesso/portabilidade) e exclusão da conta do organizador.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->accountA = Account::factory()->active()->create(['name' => 'Conta A']);
    $this->accountB = Account::factory()->active()->create(['name' => 'Conta B Secreta']);
    $this->eventA = GuestUpload::liveEvent($this->accountA, ['name' => 'Festa A']);
    $this->eventB = GuestUpload::liveEvent($this->accountB, ['name' => 'Festa B Secreta']);
    $this->photoA = GuestUpload::storedPhoto($this->eventA);
    $this->photoB = GuestUpload::storedPhoto($this->eventB);
    $this->ownerB = User::factory()->for($this->accountB)->create(['email' => 'dono-b@example.com']);
    $this->ownerA = ownerFor($this->accountA);
});

it('shows the account page to members', function () {
    $this->actingAs($this->ownerA)->get(route('account.show'))->assertOk()->assertSee('Conta A')->assertSee('Excluir conta');

    $moderator = User::factory()->for($this->accountA)->moderator()->create();
    $this->actingAs($moderator)->get(route('account.show'))->assertOk()->assertDontSee('Excluir conta e todos os dados');
});

it('exports the account data as JSON without other accounts\' data or secrets', function () {
    $response = $this->actingAs($this->ownerA)->get(route('account.export'))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
    $body = $response->getContent();
    $json = json_decode($body, true);

    expect($json['account']['name'])->toBe('Conta A')
        ->and($json['events'])->toHaveCount(1)
        ->and($json['events'][0]['name'])->toBe('Festa A')
        ->and($json['events'][0]['photos'][0]['uuid'])->toBe($this->photoA->uuid)
        ->and($json['users'][0]['email'])->toBe($this->ownerA->email);

    expect($body)->not->toContain('Conta B Secreta')
        ->not->toContain('Festa B Secreta')
        ->not->toContain($this->photoB->uuid)
        ->not->toContain('dono-b@example.com')
        ->not->toContain('device_hash')
        ->not->toContain('original_path')
        ->not->toContain((string) $this->eventA->uploadToken())
        ->not->toContain('password');
});

it('only lets the owner export or delete', function () {
    $moderator = User::factory()->for($this->accountA)->moderator()->create();

    $this->actingAs($moderator)->get(route('account.export'))->assertForbidden();
    $this->actingAs($moderator)->delete(route('account.destroy'), ['password' => 'Password!123', 'confirm' => '1'])->assertForbidden();
    expect(Account::query()->whereKey($this->accountA->id)->exists())->toBeTrue();
});

it('requires the current password and confirmation to delete the account', function () {
    $this->actingAs($this->ownerA)->delete(route('account.destroy'), ['password' => 'errada', 'confirm' => '1'])->assertSessionHasErrors('password');
    $this->actingAs($this->ownerA)->delete(route('account.destroy'), ['password' => 'Password!123'])->assertSessionHasErrors('confirm');

    expect(Account::query()->whereKey($this->accountA->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists($this->photoA->original_path);
});

it('deletes the account, its data and files, keeps the other account intact and audits without personal data', function () {
    $uuid = $this->accountA->uuid;
    AuditLog::record('event.deleted', $this->eventA, ['name' => 'Festa A'], $this->ownerA);

    $this->actingAs($this->ownerA)->delete(route('account.destroy'), ['password' => 'Password!123', 'confirm' => '1'])
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect(Account::query()->whereKey($this->accountA->id)->exists())->toBeFalse()
        ->and(User::query()->where('account_id', $this->accountA->id)->exists())->toBeFalse()
        ->and(Event::withoutGlobalScopes()->where('account_id', $this->accountA->id)->exists())->toBeFalse()
        ->and(Photo::withoutGlobalScopes()->where('account_id', $this->accountA->id)->exists())->toBeFalse();
    expect(Storage::disk('local')->allFiles("tenants/{$uuid}"))->toBe([]);

    // Conta B intacta.
    Storage::disk('local')->assertExists($this->photoB->original_path);
    expect(Photo::withoutGlobalScopes()->whereKey($this->photoB->id)->exists())->toBeTrue();

    $log = AuditLog::query()->where('action', 'account.deleted')->sole();
    $serialized = json_encode($log->toArray());
    expect($log->ip)->toBeNull()->and($log->user_id)->toBeNull()
        ->and($serialized)->not->toContain('Conta A')
        ->and($serialized)->not->toContain($this->ownerA->email)
        ->and($log->meta['account_uuid'])->toBe($uuid);

    // Logs antigos da conta perderam meta (nomes) e IP.
    expect(AuditLog::query()->where('action', 'event.deleted')->sole()->meta)->toBeNull();
});
