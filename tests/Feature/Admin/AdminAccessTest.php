<?php

use App\Models\Account;
use App\Models\Frame;
use App\Models\HomeItem;
use App\Models\HomeSection;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\HomeSeeder;

/**
 * Todas as rotas /admin: não-admin => 404; visitante => login; admin => acesso.
 *
 * @return array<int, array{0: string, 1: string}> [método, url]
 */
function adminAccessRoutes(): array
{
    $account = Account::factory()->create();
    $plan = Plan::factory()->create();
    $frame = new Frame(['type' => 'template', 'template_key' => 'x', 'name' => 'M']);
    $frame->save();

    (new HomeSeeder)->run();
    $section = HomeSection::query()->where('key', 'faq')->first();
    $item = $section->items()->first();

    return [
        ['GET', '/admin'],
        ['GET', '/admin/accounts'],
        ['GET', "/admin/accounts/{$account->uuid}"],
        ['PUT', "/admin/accounts/{$account->uuid}/plan"],
        ['PUT', "/admin/accounts/{$account->uuid}/status"],
        ['POST', "/admin/accounts/{$account->uuid}/extend-trial"],
        ['GET', '/admin/plans'],
        ['GET', '/admin/plans/create'],
        ['POST', '/admin/plans'],
        ['GET', "/admin/plans/{$plan->id}/edit"],
        ['PUT', "/admin/plans/{$plan->id}"],
        ['DELETE', "/admin/plans/{$plan->id}"],
        ['GET', '/admin/frames'],
        ['POST', "/admin/frames/{$frame->uuid}/toggle"],
        ['POST', "/admin/frames/{$frame->uuid}/move"],
        ['GET', '/admin/audit'],
        ['GET', '/admin/home'],
        ['GET', '/admin/home/preview'],
        ['GET', '/admin/home/settings'],
        ['PUT', '/admin/home/settings'],
        ['GET', '/admin/home/sections/faq'],
        ['PUT', '/admin/home/sections/faq'],
        ['POST', '/admin/home/sections/faq/toggle'],
        ['POST', '/admin/home/sections/faq/move'],
        ['POST', '/admin/home/sections/faq/preview'],
        ['GET', '/admin/home/sections/faq/items/create'],
        ['POST', '/admin/home/sections/faq/items'],
        ['GET', "/admin/home/sections/faq/items/{$item->id}/edit"],
        ['PUT', "/admin/home/sections/faq/items/{$item->id}"],
        ['DELETE', "/admin/home/sections/faq/items/{$item->id}"],
        ['POST', "/admin/home/sections/faq/items/{$item->id}/toggle"],
        ['POST', "/admin/home/sections/faq/items/{$item->id}/move"],
    ];
}

it('returns 404 on every /admin route for owners and moderators', function () {
    $routes = adminAccessRoutes();
    $owner = User::factory()->create();
    $moderator = User::factory()->moderator()->create();

    foreach ([$owner, $moderator] as $user) {
        foreach ($routes as [$method, $url]) {
            $this->actingAs($user)->call($method, $url)->assertNotFound("{$method} {$url}");
        }
    }
});

it('returns 404 for non-admins even when the e-mail is not verified', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/admin/accounts')->assertNotFound();
});

it('redirects guests to login', function () {
    $this->get('/admin/accounts')->assertRedirect(route('login'));
    $this->post('/admin/plans')->assertRedirect(route('login'));
});

it('lets the admin open every GET page', function () {
    $routes = adminAccessRoutes();
    $admin = User::factory()->admin()->create();

    foreach ($routes as [$method, $url]) {
        if ($method !== 'GET') {
            continue;
        }

        $response = $this->actingAs($admin)->get($url);
        expect($response->status())->toBeIn([200, 302], "{$method} {$url}");
    }
});

it('does not expose the admin area through the account dashboard routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/dashboard')->assertNotFound();
});

it('keeps the HomeItem model reachable only inside its own section', function () {
    (new HomeSeeder)->run();
    $admin = User::factory()->admin()->create();
    $faqItem = HomeSection::query()->where('key', 'faq')->first()->items()->first();

    // Item de outra seção na URL desta => 404 (scopeBindings).
    $this->actingAs($admin)->get("/admin/home/sections/features/items/{$faqItem->id}/edit")->assertNotFound();
    expect(HomeItem::query()->count())->toBeGreaterThan(0);
});
