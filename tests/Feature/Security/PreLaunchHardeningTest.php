<?php

use App\Models\Account;
use App\Models\Event;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Services\EventScheduler;
use App\Support\ProductionHardening;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
| Regressão da revisão de segurança pré-lançamento (docs/SECURITY_REVIEW.md, módulo 12).
| Cada teste cita o id do achado.
*/

// SR-01: open redirect pela troca de idioma (back() segue o Referer).
it('does not redirect to an external Referer when switching language', function (string $referer) {
    $this->get('/locale/en', ['Referer' => $referer])
        ->assertRedirect(route('home', absolute: false));

    expect(session('locale'))->toBe('en');
})->with([
    'outro site' => 'https://evil.example/phish',
    'protocol-relative' => '//evil.example/phish',
    'laço na própria rota' => 'http://photoparty.test/locale/pt_BR',
]);

it('never redirects to another scheme when switching language', function () {
    $location = $this->get('/locale/en', ['Referer' => 'javascript:alert(1)'])->headers->get('Location');

    expect($location)->toStartWith(url('/'));
});

it('goes back to a same-site page after switching language', function () {
    $this->get('/locale/en', ['Referer' => url('/termos').'?x=1'])
        ->assertRedirect('/termos?x=1');
});

// SR-02: envenenamento do link de redefinição de senha pelo header Host.
it('builds the password reset link from APP_URL, never from the Host header, in production', function () {
    config(['app.url' => 'https://photo.uailabs.net']);
    ProductionHardening::apply(true);
    Notification::fake();
    $user = User::factory()->create();

    $this->post('http://evil.example/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($user, QueuedResetPassword::class, function (QueuedResetPassword $n) use ($user) {
        $url = $n->toMail($user)->actionUrl;

        expect($url)->toStartWith('https://photo.uailabs.net/reset-password/')
            ->and($url)->not->toContain('evil.example');

        return true;
    });
});

// SR-03 / SR-04: cookie de sessão secure e APP_DEBUG desligado em produção, mesmo com .env errado.
it('forces secure session cookies and disables debug in production', function () {
    config(['app.url' => 'https://photo.uailabs.net', 'session.secure' => false, 'app.debug' => true]);
    Log::shouldReceive('critical')->once();

    ProductionHardening::apply(true);

    expect(config('session.secure'))->toBeTrue()
        ->and(config('app.debug'))->toBeFalse();
});

it('leaves the local environment untouched', function () {
    config(['session.secure' => false, 'app.debug' => true]);

    ProductionHardening::apply(false);

    expect(config('session.secure'))->toBeFalse()
        ->and(config('app.debug'))->toBeTrue();
});

// SR-05: sessões antigas caem quando a senha é redefinida.
it('logs out every other session of the user after a password reset', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach ([[$user->id, 'old-session-a'], [$other->id, 'other-user-session']] as [$userId, $id]) {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $userId, 'ip_address' => '10.0.0.1', 'user_agent' => 'x',
            'payload' => base64_encode('a:0:{}'), 'last_activity' => time(),
        ]);
    }

    $token = Password::broker()->createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Nova-Senha-2026!',
        'password_confirmation' => 'Nova-Senha-2026!',
    ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'other-user-session')->exists())->toBeTrue();
});

// SR-06: anti-enumeração por tempo (timebox maior que um bcrypt) e e-mail de reset na fila.
it('time-boxes login and password reset longer than a bcrypt hash and queues the reset e-mail', function () {
    expect((int) config('auth.timebox_duration'))->toBeGreaterThanOrEqual(400000)
        ->and(new QueuedResetPassword('t'))->toBeInstanceOf(ShouldQueue::class);
});

// SR-07: token do convidado na URL não vaza pelo Referer.
it('sends no-referrer on guest token pages and keeps the default elsewhere', function () {
    $this->get('/e/qualquer-evento?t='.str_repeat('a', 64))
        ->assertNotFound()
        ->assertHeader('Referrer-Policy', 'no-referrer');

    $this->get('/login')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

// SR-08: bloqueio de login vai para o log de segurança, sem senha e com e-mail só em hash.
it('writes login lockouts to the security log without credentials', function () {
    $logger = Mockery::mock();
    $logger->shouldReceive('warning')->once()->withArgs(function (string $message, array $context) {
        return isset($context['ip'], $context['email_hash'])
            && ! array_key_exists('password', $context)
            && ! in_array('vitima@example.com', $context, true);
    });
    Log::shouldReceive('channel')->with('security')->andReturn($logger);

    event(new Lockout(Request::create('/login', 'POST', ['email' => 'vitima@example.com', 'password' => 'segredo'])));
});

// SR-09: criação de evento confere de novo as regras de teste com a conta travada.
it('refuses a second trial event even when the first check already passed', function () {
    $account = Account::factory()->trial()->create();
    Event::factory()->for($account)->create();

    $event = new Event(['name' => 'Segunda', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2), 'moderation' => 'pre', 'language' => 'pt_BR']);
    $event->is_trial = true;
    $event->account_id = $account->id;

    $error = app(EventScheduler::class)->createLocked($account, $event);

    expect($error)->toBe(__('app.errors.trial_single_event'))
        ->and(Event::withoutGlobalScopes()->where('account_id', $account->id)->count())->toBe(1);
});

// SR-15: páginas de erro genéricas, sem estilo inline (CSP) e sem detalhes técnicos.
it('renders a generic error page without inline styles or stack traces', function () {
    $html = $this->get('/esta-pagina-nao-existe')->assertNotFound()->getContent();

    expect($html)->toContain('404')
        ->and($html)->toContain('Página não encontrada')
        ->and($html)->not->toContain('<style')
        ->and($html)->not->toContain('vendor/laravel')
        ->and($html)->not->toContain('Stack trace');
});
