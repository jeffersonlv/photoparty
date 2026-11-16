<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Frame;
use App\Models\User;

function adminMakeFrame(string $name, int $order, ?int $accountId = null, bool $active = true): Frame
{
    $frame = new Frame(['type' => 'template', 'template_key' => strtolower($name), 'name' => $name]);
    $frame->forceFill(['account_id' => $accountId, 'sort_order' => $order, 'is_active' => $active])->save();

    return $frame;
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('lists only system frames, in order', function () {
    $account = Account::factory()->create();
    adminMakeFrame('Segunda', 2);
    adminMakeFrame('Primeira', 1);
    adminMakeFrame('Privada', 0, $account->id);

    $response = $this->actingAs($this->admin)->get('/admin/frames')->assertOk();

    $response->assertSeeInOrder(['Primeira', 'Segunda'])->assertDontSee('Privada');
});

it('toggles a system frame and records the audit log', function () {
    $frame = adminMakeFrame('Ouro', 1);

    $this->actingAs($this->admin)->post("/admin/frames/{$frame->uuid}/toggle")->assertRedirect();
    expect((bool) $frame->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->post("/admin/frames/{$frame->uuid}/toggle")->assertRedirect();
    expect((bool) $frame->fresh()->is_active)->toBeTrue();

    expect(AuditLog::query()->where('action', 'frame.toggled')->count())->toBe(2);
});

it('reorders system frames up and down', function () {
    $a = adminMakeFrame('A', 0);
    $b = adminMakeFrame('B', 1);
    $c = adminMakeFrame('C', 2);

    $this->actingAs($this->admin)->post("/admin/frames/{$c->uuid}/move", ['direction' => 'up'])->assertRedirect();

    expect(Frame::withoutGlobalScopes()->orderBy('sort_order')->pluck('name')->all())->toEqual(['A', 'C', 'B']);

    $this->actingAs($this->admin)->post("/admin/frames/{$a->uuid}/move", ['direction' => 'down'])->assertRedirect();
    expect(Frame::withoutGlobalScopes()->orderBy('sort_order')->pluck('name')->all())->toEqual(['C', 'A', 'B']);

    // Limites: subir o primeiro não muda nada.
    $this->actingAs($this->admin)->post("/admin/frames/{$c->uuid}/move", ['direction' => 'up'])->assertRedirect();
    expect(Frame::withoutGlobalScopes()->orderBy('sort_order')->pluck('name')->all())->toEqual(['C', 'A', 'B']);

    $this->actingAs($this->admin)->post("/admin/frames/{$a->uuid}/move", ['direction' => 'sideways'])->assertSessionHasErrors('direction');

    expect(AuditLog::query()->where('action', 'frame.moved')->count())->toBe(3);
});

it('does not let the admin screen touch an organizer frame', function () {
    $account = Account::factory()->create();
    $private = adminMakeFrame('Privada', 0, $account->id);

    $this->actingAs($this->admin)->post("/admin/frames/{$private->uuid}/toggle")->assertNotFound();
    $this->actingAs($this->admin)->post("/admin/frames/{$private->uuid}/move", ['direction' => 'up'])->assertNotFound();
});
