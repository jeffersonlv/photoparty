<?php

use App\Models\AuditLog;
use App\Models\HomeItem;
use App\Models\HomeSection;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\HomeSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    (new HomeSeeder)->run();
    $this->admin = User::factory()->admin()->create();
});

function faqContent(array $overrides = []): array
{
    return array_replace_recursive([
        'pt_BR' => ['title' => 'Dúvidas'],
        'en' => ['title' => 'Questions'],
    ], $overrides);
}

function sectionKeys(): array
{
    return HomeSection::query()->orderBy('sort_order')->pluck('key')->all();
}

it('lists sections in order', function () {
    $this->actingAs($this->admin)->get('/admin/home')->assertOk()
        ->assertSeeInOrder(['Topo (hero)', 'Como funciona', 'Para quem é', 'Recursos', 'Planos e preços', 'Perguntas frequentes', 'Chamada final']);
});

it('toggles a section and audits it', function () {
    $this->actingAs($this->admin)->post('/admin/home/sections/faq/toggle')->assertRedirect();

    expect(HomeSection::query()->where('key', 'faq')->value('enabled'))->toBeFalse();

    $this->actingAs($this->admin)->post('/admin/home/sections/faq/toggle');
    expect(HomeSection::query()->where('key', 'faq')->value('enabled'))->toBeTrue();
    expect(AuditLog::query()->where('action', 'home.section_toggled')->count())->toBe(2);
});

it('moves sections up and down', function () {
    expect(sectionKeys())->toEqual(['hero', 'how_it_works', 'audiences', 'features', 'pricing', 'faq', 'final_cta']);

    $this->actingAs($this->admin)->post('/admin/home/sections/faq/move', ['direction' => 'up'])->assertRedirect();
    expect(sectionKeys())->toEqual(['hero', 'how_it_works', 'audiences', 'features', 'faq', 'pricing', 'final_cta']);

    $this->actingAs($this->admin)->post('/admin/home/sections/hero/move', ['direction' => 'down']);
    expect(sectionKeys())->toEqual(['how_it_works', 'hero', 'audiences', 'features', 'faq', 'pricing', 'final_cta']);

    $this->actingAs($this->admin)->post('/admin/home/sections/how_it_works/move', ['direction' => 'up']);
    expect(sectionKeys()[0])->toBe('how_it_works');

    $this->actingAs($this->admin)->post('/admin/home/sections/faq/move', ['direction' => 'diagonal'])->assertSessionHasErrors('direction');
    expect(AuditLog::query()->where('action', 'home.section_moved')->count())->toBe(3);
});

it('edits section content per language', function () {
    $this->actingAs($this->admin)->get('/admin/home/sections/faq')->assertOk()->assertSee('Perguntas frequentes');

    $this->actingAs($this->admin)->put('/admin/home/sections/faq', ['content' => faqContent()])->assertRedirect();

    $section = HomeSection::query()->where('key', 'faq')->first();
    expect($section->content['pt_BR']['title'])->toBe('Dúvidas')
        ->and($section->content['en']['title'])->toBe('Questions')
        ->and($section->updated_by)->toBe($this->admin->id);
    expect(AuditLog::query()->where('action', 'home.section_updated')->exists())->toBeTrue();
});

it('only stores fields defined in the section schema', function () {
    $this->actingAs($this->admin)->put('/admin/home/sections/faq', ['content' => faqContent(['pt_BR' => ['evil' => '<b>x</b>', 'html' => 'y']])]);

    expect(HomeSection::query()->where('key', 'faq')->first()->content['pt_BR'])->toHaveKey('title')->not->toHaveKeys(['evil', 'html']);
});

it('validates section forms', function () {
    // título obrigatório em cada idioma
    $this->actingAs($this->admin)->put('/admin/home/sections/faq', ['content' => faqContent(['pt_BR' => ['title' => '']])])
        ->assertSessionHasErrors('content.pt_BR.title');

    // tamanho máximo
    $this->actingAs($this->admin)->put('/admin/home/sections/faq', ['content' => faqContent(['en' => ['title' => str_repeat('a', 500)]])])
        ->assertSessionHasErrors('content.en.title');

    // idioma ausente / formato errado
    $this->actingAs($this->admin)->put('/admin/home/sections/faq', ['content' => ['pt_BR' => ['title' => 'x']]])
        ->assertSessionHasErrors('content.en');
    $this->actingAs($this->admin)->put('/admin/home/sections/faq', [])->assertSessionHasErrors('content');
    $this->actingAs($this->admin)->put('/admin/home/sections/faq', ['content' => ['pt_BR' => ['title' => ['x']], 'en' => ['title' => 'y']]])
        ->assertSessionHasErrors('content.pt_BR.title');

    expect(HomeSection::query()->where('key', 'faq')->first()->content['pt_BR']['title'])->toBe('Perguntas frequentes');
});

it('rejects unknown sections', function () {
    $this->actingAs($this->admin)->get('/admin/home/sections/nope')->assertNotFound();
});

it('creates, edits, toggles, reorders and deletes items', function () {
    $section = HomeSection::query()->where('key', 'faq')->first();
    $before = $section->items()->count();

    $payload = ['data' => [
        'pt_BR' => ['question' => 'Pergunta nova?', 'answer' => 'Resposta nova.'],
        'en' => ['question' => 'New question?', 'answer' => 'New answer.'],
    ], 'enabled' => '1'];

    $this->actingAs($this->admin)->get('/admin/home/sections/faq/items/create')->assertOk();
    $this->actingAs($this->admin)->post('/admin/home/sections/faq/items', $payload)->assertRedirect();

    $item = $section->items()->get()->last();
    expect($section->items()->count())->toBe($before + 1)
        ->and($item->data['pt_BR']['question'])->toBe('Pergunta nova?')
        ->and($item->enabled)->toBeTrue();

    $payload['data']['pt_BR']['question'] = 'Editada?';
    unset($payload['enabled']);
    $this->actingAs($this->admin)->put("/admin/home/sections/faq/items/{$item->id}", $payload)->assertRedirect();
    $item->refresh();
    expect($item->data['pt_BR']['question'])->toBe('Editada?')->and($item->enabled)->toBeFalse();

    $this->actingAs($this->admin)->post("/admin/home/sections/faq/items/{$item->id}/toggle");
    expect($item->fresh()->enabled)->toBeTrue();

    $this->actingAs($this->admin)->post("/admin/home/sections/faq/items/{$item->id}/move", ['direction' => 'up']);
    $ids = $section->items()->pluck('id')->all();
    expect($ids[count($ids) - 2])->toBe($item->id);

    $this->actingAs($this->admin)->delete("/admin/home/sections/faq/items/{$item->id}")->assertRedirect();
    expect(HomeItem::query()->find($item->id))->toBeNull();

    expect(AuditLog::query()->pluck('action')->unique()->sort()->values()->all())->toEqual([
        'home.item_created', 'home.item_deleted', 'home.item_moved', 'home.item_toggled', 'home.item_updated',
    ]);
});

it('validates item forms and limits', function () {
    $this->actingAs($this->admin)->post('/admin/home/sections/faq/items', ['data' => ['pt_BR' => ['question' => '', 'answer' => 'x'], 'en' => ['question' => 'q', 'answer' => 'a']]])
        ->assertSessionHasErrors('data.pt_BR.question');
    $this->actingAs($this->admin)->post('/admin/home/sections/faq/items', [])->assertSessionHasErrors('data');

    // seção sem itens (hero) => 404
    $this->actingAs($this->admin)->get('/admin/home/sections/hero/items/create')->assertNotFound();

    // limite máximo de itens
    $section = HomeSection::query()->where('key', 'how_it_works')->first();
    $max = config('home_sections.sections.how_it_works.items.max');
    while ($section->items()->count() < $max) {
        $section->items()->create(['data' => ['pt_BR' => ['title' => 't', 'text' => 'x']]]);
    }

    $this->actingAs($this->admin)->post('/admin/home/sections/how_it_works/items', ['data' => [
        'pt_BR' => ['title' => 'a', 'text' => 'b'], 'en' => ['title' => 'a', 'text' => 'b'],
    ]])->assertSessionHas('error');
    expect($section->items()->count())->toBe($max);
});

it('saves site settings and validates them', function () {
    $this->actingAs($this->admin)->get('/admin/home/settings')->assertOk();

    $this->actingAs($this->admin)->put('/admin/home/settings', [
        'seo_title_pt_BR' => 'Novo título',
        'seo_title_en' => 'New title',
        'seo_description_pt_BR' => 'Descrição',
        'seo_description_en' => '',
        'contact_email' => 'oi@example.test',
        'contact_phone' => '+353 1 234',
    ])->assertRedirect();

    expect(SiteSetting::query()->find('seo_title_pt_BR')->value)->toBe('Novo título')
        ->and(SiteSetting::query()->find('seo_description_en')->value)->toBeNull()
        ->and(SiteSetting::query()->find('contact_email')->value)->toBe('oi@example.test');
    expect(AuditLog::query()->where('action', 'home.settings_updated')->exists())->toBeTrue();

    $this->actingAs($this->admin)->put('/admin/home/settings', ['contact_email' => 'not-an-email'])->assertSessionHasErrors('contact_email');
    $this->actingAs($this->admin)->put('/admin/home/settings', ['seo_title_en' => str_repeat('x', 200)])->assertSessionHasErrors('seo_title_en');
});

it('previews a section without saving anything', function () {
    $before = HomeSection::query()->where('key', 'faq')->first()->content;

    $this->actingAs($this->admin)->post('/admin/home/sections/faq/preview', [
        'preview_locale' => 'pt_BR',
        'content' => faqContent(['pt_BR' => ['title' => 'TITULO SO NA PREVIA']]),
    ])->assertOk()->assertSee('TITULO SO NA PREVIA')->assertSee('Prévia');

    expect(HomeSection::query()->where('key', 'faq')->first()->content)->toEqual($before);

    // Via formulário principal (PUT com formaction) também funciona e continua sem salvar.
    $this->actingAs($this->admin)->put('/admin/home/sections/faq/preview', [
        'preview_locale' => 'en',
        'content' => faqContent(['en' => ['title' => 'ONLY IN PREVIEW']]),
    ])->assertOk()->assertSee('ONLY IN PREVIEW');
    expect(HomeSection::query()->where('key', 'faq')->first()->content)->toEqual($before);

    // Prévia valida o formulário.
    $this->actingAs($this->admin)->post('/admin/home/sections/faq/preview', ['content' => []])->assertSessionHasErrors('content');

    // Prévia de seção desativada mostra a seção.
    HomeSection::query()->where('key', 'faq')->update(['enabled' => false]);
    $this->actingAs($this->admin)->post('/admin/home/sections/faq/preview', ['preview_locale' => 'pt_BR', 'content' => faqContent(['pt_BR' => ['title' => 'DESLIGADA']])])->assertSee('DESLIGADA');

    // Página inteira
    $this->actingAs($this->admin)->get('/admin/home/preview?locale=en')->assertOk()->assertSee('Preview');
});
