<?php

use App\Models\HomeItem;
use App\Models\HomeSection;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\HomeContent;
use Database\Seeders\HomeSeeder;
use Illuminate\Support\Facades\Cache;

it('keeps the route name "home" on /', function () {
    expect(route('home', absolute: false))->toBe('/');
});

it('renders the seeded sections in pt_BR by default', function () {
    (new HomeSeeder)->run();
    Plan::factory()->create(['name' => 'Plano Visível', 'is_public' => true]);

    $response = $this->get('/')->assertOk();

    $response->assertSee('Cada convidado vira fotógrafo')
        ->assertSee('Do QR code ao telão em 3 passos')
        ->assertSee('Fotógrafos')
        ->assertSee('Privacidade em primeiro lugar')
        ->assertSee('Sem app')
        ->assertSee('Plano Visível')
        ->assertSee('Perguntas frequentes')
        ->assertSee('Criar conta grátis')
        ->assertSee('Demonstração ao vivo em breve');
});

it('renders in English when the locale is en', function () {
    (new HomeSeeder)->run();

    $this->withSession(['locale' => 'en'])->get('/')
        ->assertOk()
        ->assertSee('Every guest becomes a photographer')
        ->assertDontSee('Cada convidado vira fotógrafo')
        ->assertSee('Terms of use');
});

it('uses SEO settings for title and description', function () {
    (new HomeSeeder)->run();

    $this->get('/')->assertSee('<title>Photo Party: fotos dos convidados no telão do seu evento</title>', false)
        ->assertSee('<meta name="description"', false);
});

it('hides disabled sections and respects order', function () {
    (new HomeSeeder)->run();
    HomeSection::query()->where('key', 'faq')->update(['enabled' => false]);

    $this->get('/')->assertOk()->assertDontSee('Perguntas frequentes')->assertSee('Do QR code ao telão');

    // Reordena: final_cta antes de hero
    HomeSection::query()->where('key', 'final_cta')->update(['sort_order' => -1]);
    app(HomeContent::class)->forget();

    $this->get('/')->assertSeeInOrder(['Pronto para o próximo evento', 'Cada convidado vira fotógrafo']);
});

it('hides disabled items', function () {
    (new HomeSeeder)->run();
    $item = HomeSection::query()->where('key', 'faq')->first()->items()->first();
    $question = $item->data['pt_BR']['question'];

    $this->get('/')->assertSee($question);

    $item->forceFill(['enabled' => false])->save();
    app(HomeContent::class)->forget();

    $this->get('/')->assertDontSee($question);
});

it('always shows the legal footer, even with every section off or no sections at all', function () {
    $this->get('/')->assertOk()
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.privacy'), false)
        ->assertSee('Termos de uso')
        ->assertSee('Política de privacidade');

    (new HomeSeeder)->run();
    HomeSection::query()->update(['enabled' => false]);
    app(HomeContent::class)->forget();

    $this->get('/')->assertOk()->assertSee(route('legal.terms'), false)->assertDontSee('Cada convidado vira fotógrafo');

    expect(config('home_sections.sections'))->not->toHaveKey('footer');
});

it('serves the placeholder legal pages with a legal-review warning', function () {
    $this->get('/termos')->assertOk()->assertSee('Termos de uso')->assertSee('revisão jurídica');
    $this->get('/privacidade')->assertOk()->assertSee('Política de privacidade')->assertSee('revisão jurídica');

    $this->withSession(['locale' => 'en'])->get('/termos')->assertOk()->assertSee('legal review');
});

it('shows only public plans in pricing', function () {
    (new HomeSeeder)->run();
    Plan::factory()->create(['name' => 'Plano Publico', 'is_public' => true, 'price' => 10]);
    Plan::factory()->create(['name' => 'Plano Secreto', 'is_public' => false]);

    $this->get('/')->assertSee('Plano Publico')->assertDontSee('Plano Secreto');

    // Plano editado aparece sem precisar limpar o cache de conteúdo.
    Plan::query()->where('name', 'Plano Publico')->update(['name' => 'Plano Renomeado']);
    $this->get('/')->assertSee('Plano Renomeado');
});

it('links logged-in users to their dashboard and admins to the admin area', function () {
    (new HomeSeeder)->run();

    $this->get('/')->assertSee(route('login'), false)->assertSee(route('register'), false)->assertDontSee(route('dashboard'), false);

    $this->actingAs(User::factory()->create())->get('/')->assertOk()
        ->assertSee(route('dashboard'), false)
        ->assertSee('Ir para o painel')
        ->assertDontSee('>Entrar<', false);

    $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()
        ->assertSee(route('admin.accounts.index'), false);
});

it('caches the rendered content per locale and clears it when the admin saves', function () {
    (new HomeSeeder)->run();
    $admin = User::factory()->admin()->create();

    $this->withSession(['locale' => 'pt_BR'])->get('/')->assertOk();
    $this->withSession(['locale' => 'en'])->get('/')->assertOk();
    expect(Cache::has('home.page.pt_BR'))->toBeTrue()->and(Cache::has('home.page.en'))->toBeTrue();

    // Mudança direta no banco não aparece: vem do cache.
    HomeSection::query()->where('key', 'final_cta')->update(['content' => ['pt_BR' => ['title' => 'SO NO BANCO', 'cta' => 'x']]]);
    $this->withSession(['locale' => 'pt_BR'])->get('/')->assertDontSee('SO NO BANCO');

    $actions = [
        fn () => $this->put('/admin/home/sections/faq', ['content' => ['pt_BR' => ['title' => 'T1'], 'en' => ['title' => 'T2']]]),
        fn () => $this->post('/admin/home/sections/faq/toggle'),
        fn () => $this->post('/admin/home/sections/faq/move', ['direction' => 'up']),
        fn () => $this->put('/admin/home/settings', ['contact_email' => 'a@example.test']),
        fn () => $this->post('/admin/home/sections/faq/items', ['data' => ['pt_BR' => ['question' => 'q', 'answer' => 'a'], 'en' => ['question' => 'q', 'answer' => 'a']]]),
        fn () => $this->post('/admin/home/sections/faq/items/'.HomeSection::query()->where('key', 'faq')->first()->items()->first()->id.'/toggle'),
    ];

    foreach ($actions as $action) {
        $this->withSession(['locale' => 'pt_BR'])->get('/'); // repovoa o cache
        $this->withSession(['locale' => 'en'])->get('/');
        expect(Cache::has('home.page.pt_BR'))->toBeTrue();

        $this->actingAs($admin);
        $action();

        expect(Cache::has('home.page.pt_BR'))->toBeFalse()->and(Cache::has('home.page.en'))->toBeFalse();
    }

    // Depois de salvar, o conteúdo novo (do banco) aparece.
    $this->withSession(['locale' => 'pt_BR'])->get('/')->assertSee('SO NO BANCO');
});

it('escapes HTML in section content, items and settings (XSS)', function () {
    (new HomeSeeder)->run();
    $admin = User::factory()->admin()->create();
    $payload = '<script>alert("xss")</script>';

    $this->actingAs($admin)->put('/admin/home/sections/hero', ['content' => [
        'pt_BR' => ['title' => $payload, 'subtitle' => '<img src=x onerror=alert(1)>', 'cta_primary' => 'Ok'],
        'en' => ['title' => 'x', 'subtitle' => 'y', 'cta_primary' => 'z'],
    ]])->assertSessionHasNoErrors();

    $this->put('/admin/home/sections/faq/items/'.HomeSection::query()->where('key', 'faq')->first()->items()->first()->id, ['enabled' => '1', 'data' => [
        'pt_BR' => ['question' => $payload, 'answer' => '<b onmouseover=1>a</b>'],
        'en' => ['question' => 'q', 'answer' => 'a'],
    ]])->assertSessionHasNoErrors();

    $this->put('/admin/home/settings', ['seo_title_pt_BR' => '"><script>alert(2)</script>', 'seo_description_pt_BR' => '"><img src=x>'])->assertSessionHasNoErrors();

    auth()->logout();
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->not->toContain('<script>alert')
        ->not->toContain('<img src=x')
        ->not->toContain('<b onmouseover')
        ->toContain('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');

    // Nenhum <script> nem style inline na página (CSP estrita).
    expect($html)->not->toMatch('/<script\b/i')->not->toMatch('/\sstyle=/i');
});

it('escapes HTML in the admin forms and preview too', function () {
    (new HomeSeeder)->run();
    $admin = User::factory()->admin()->create();
    HomeSection::query()->where('key', 'faq')->update(['content' => ['pt_BR' => ['title' => '</textarea><script>alert(1)</script>'], 'en' => ['title' => 'x']]]);

    $html = $this->actingAs($admin)->get('/admin/home/sections/faq')->assertOk()->getContent();
    expect($html)->not->toContain('<script>alert(1)')->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');

    $preview = $this->actingAs($admin)->post('/admin/home/sections/faq/preview', ['preview_locale' => 'pt_BR', 'content' => [
        'pt_BR' => ['title' => '<script>alert(3)</script>'], 'en' => ['title' => 'x'],
    ]])->getContent();
    expect($preview)->not->toContain('<script>alert(3)');
});

it('falls back to the base language when a field is empty in the requested language', function () {
    (new HomeSeeder)->run();
    HomeSection::query()->where('key', 'final_cta')->update(['content' => [
        'pt_BR' => ['title' => 'Titulo base', 'cta' => 'Botao base'],
        'en' => ['title' => '', 'cta' => 'English button'],
    ]]);
    app(HomeContent::class)->forget();

    $this->withSession(['locale' => 'en'])->get('/')->assertSee('Titulo base')->assertSee('English button');
});

it('ignores unknown stored sections gracefully', function () {
    (new HomeSeeder)->run();
    (new HomeSection)->forceFill(['key' => 'unknown', 'enabled' => true, 'sort_order' => 99])->save();
    app(HomeContent::class)->forget();

    $this->get('/')->assertOk();
    expect(SiteSetting::query()->count())->toBeGreaterThan(0);
    expect(HomeItem::query()->count())->toBeGreaterThan(0);
});
