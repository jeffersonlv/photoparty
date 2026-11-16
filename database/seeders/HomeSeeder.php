<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use App\Models\SiteSetting;
use App\Services\HomeContent;
use Illuminate\Database\Seeder;

/**
 * Conteúdo inicial da página inicial (pt_BR e en). Idempotente: seções que já existem
 * (editadas pelo admin) não são sobrescritas. Seguro para rodar em produção:
 *   php artisan db:seed --class=HomeSeeder
 */
class HomeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('home_sections.order') as $position => $key) {
            $data = $this->data()[$key];

            if (HomeSection::query()->where('key', $key)->exists()) {
                continue;
            }

            $section = new HomeSection;
            $section->forceFill([
                'key' => $key,
                'enabled' => true,
                'sort_order' => $position,
                'content' => $data['content'],
            ])->save();

            foreach ($data['items'] ?? [] as $i => $item) {
                $section->items()->create(['data' => $item])->forceFill([
                    'enabled' => true,
                    'sort_order' => $i,
                ])->save();
            }
        }

        foreach ($this->settings() as $key => $value) {
            SiteSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        app(HomeContent::class)->forget();
    }

    /**
     * @return array<string, string>
     */
    private function settings(): array
    {
        return [
            'seo_title_pt_BR' => 'Photo Party: fotos dos convidados no telão do seu evento',
            'seo_title_en' => 'Photo Party: guest photos on your event big screen',
            'seo_description_pt_BR' => 'Seus convidados escaneiam um QR code, tiram a foto no celular, sem baixar app, e ela aparece no telão. Moderação, molduras e privacidade para organizadores de eventos.',
            'seo_description_en' => 'Guests scan a QR code, take a photo on their phone with no app to install, and it shows up on the big screen. Moderation, frames and privacy for event organizers.',
            'contact_email' => 'contato@photo.uailabs.net',
        ];
    }

    /**
     * @return array<string, array{content: array, items?: list<array>}>
     */
    private function data(): array
    {
        return [
            'hero' => [
                'content' => [
                    'pt_BR' => [
                        'eyebrow' => 'Para fotógrafos, cabines de fotos e organizadores',
                        'title' => 'Cada convidado vira fotógrafo. Cada foto vai direto para o telão.',
                        'subtitle' => 'Seus convidados escaneiam um QR code, tiram a foto no próprio celular e ela aparece no telão em segundos. Sem baixar app, sem cadastro, com você no controle do que vai ao ar.',
                        'cta_primary' => 'Testar grátis',
                        'cta_secondary' => 'Ver como funciona',
                        'demo_note' => 'Demonstração ao vivo em breve',
                    ],
                    'en' => [
                        'eyebrow' => 'For photographers, photo booth companies and event organizers',
                        'title' => 'Every guest becomes a photographer. Every photo goes straight to the big screen.',
                        'subtitle' => 'Guests scan a QR code, take a photo on their own phone and it shows up on the screen within seconds. No app to install, no sign-up, and you decide what goes live.',
                        'cta_primary' => 'Try it free',
                        'cta_secondary' => 'See how it works',
                        'demo_note' => 'Live demo coming soon',
                    ],
                ],
            ],
            'how_it_works' => [
                'content' => [
                    'pt_BR' => ['title' => 'Do QR code ao telão em 3 passos', 'subtitle' => 'Você configura em minutos. Seus convidados participam em segundos.'],
                    'en' => ['title' => 'From QR code to big screen in 3 steps', 'subtitle' => 'You set it up in minutes. Your guests join in seconds.'],
                ],
                'items' => [
                    ['pt_BR' => ['title' => 'Crie o evento', 'text' => 'Defina data, horário, moldura e filtros. Imprima ou projete o QR code do evento.'],
                        'en' => ['title' => 'Create the event', 'text' => 'Set the date, time, frame and filters. Print or display the event QR code.']],
                    ['pt_BR' => ['title' => 'Convidados fotografam', 'text' => 'Eles escaneiam o QR code, tiram a foto no navegador do celular, escolhem filtro e moldura e enviam. Nada para instalar.'],
                        'en' => ['title' => 'Guests snap photos', 'text' => 'They scan the QR code, take a photo in their phone browser, pick a filter and frame, and send it. Nothing to install.']],
                    ['pt_BR' => ['title' => 'O telão ganha vida', 'text' => 'As fotos aprovadas aparecem no telão automaticamente. Ao final, baixe tudo em um ZIP.'],
                        'en' => ['title' => 'The screen comes alive', 'text' => 'Approved photos appear on the big screen automatically. Afterwards, download everything as a ZIP.']],
                ],
            ],
            'audiences' => [
                'content' => [
                    'pt_BR' => ['title' => 'Feito para quem organiza eventos', 'subtitle' => 'Uma ferramenta que valoriza o seu trabalho e encanta o seu cliente.'],
                    'en' => ['title' => 'Built for people who run events', 'subtitle' => 'A tool that elevates your work and delights your clients.'],
                ],
                'items' => [
                    ['pt_BR' => ['title' => 'Fotógrafos', 'text' => 'Ofereça uma experiência interativa a mais no pacote do casamento ou da formatura, com a sua marca.'],
                        'en' => ['title' => 'Photographers', 'text' => 'Add an interactive extra to your wedding or graduation packages, with your own branding.']],
                    ['pt_BR' => ['title' => 'Empresas de cabine de fotos', 'text' => 'Escale o atendimento: o telão e as fotos dos convidados complementam a cabine e aumentam o ticket.'],
                        'en' => ['title' => 'Photo booth companies', 'text' => 'Scale your service: the big screen and guest photos complement the booth and raise your ticket.']],
                    ['pt_BR' => ['title' => 'Eventos corporativos', 'text' => 'Moderação antes de ir ao ar, moldura da empresa e privacidade para convenções, lançamentos e confraternizações.'],
                        'en' => ['title' => 'Corporate events', 'text' => 'Pre-moderation, company frames and privacy for conferences, launches and team parties.']],
                    ['pt_BR' => ['title' => 'Festas e casamentos', 'text' => 'Todos os ângulos da festa, feitos por quem estava lá, reunidos num só lugar para os anfitriões.'],
                        'en' => ['title' => 'Parties and weddings', 'text' => 'Every angle of the celebration, shot by the people who were there, gathered in one place for the hosts.']],
                ],
            ],
            'features' => [
                'content' => [
                    'pt_BR' => ['title' => 'Tudo o que você precisa, nada que atrapalha', 'subtitle' => 'Simples para o convidado, completo para o organizador.'],
                    'en' => ['title' => 'Everything you need, nothing that gets in the way', 'subtitle' => 'Simple for the guest, complete for the organizer.'],
                ],
                'items' => [
                    ['pt_BR' => ['title' => 'Sem app', 'text' => 'Funciona direto no navegador do celular, no iPhone e no Android. Zero fricção para o convidado.'],
                        'en' => ['title' => 'No app', 'text' => 'Works right in the phone browser, on iPhone and Android. Zero friction for guests.']],
                    ['pt_BR' => ['title' => 'Privacidade em primeiro lugar', 'text' => 'Fotos isoladas por organizador e por evento, sem rastreadores de terceiros, sem anúncios e com remoção automática dos dados de localização das fotos.'],
                        'en' => ['title' => 'Privacy first', 'text' => 'Photos isolated per organizer and per event, no third-party trackers, no ads, and location data automatically stripped from photos.']],
                    ['pt_BR' => ['title' => 'Moderação', 'text' => 'Aprove antes de ir ao telão ou remova depois. Você decide o que o público vê.'],
                        'en' => ['title' => 'Moderation', 'text' => 'Approve before the screen or remove afterwards. You decide what the audience sees.']],
                    ['pt_BR' => ['title' => 'Molduras e filtros', 'text' => 'Molduras prontas ou personalizadas e filtros para dar a identidade do evento a cada foto.'],
                        'en' => ['title' => 'Frames and filters', 'text' => 'Ready-made or custom frames and filters to give every photo the identity of your event.']],
                    ['pt_BR' => ['title' => 'Telão em tempo real', 'text' => 'Apresentação automática em qualquer tela com navegador, com o QR code do evento sempre visível.'],
                        'en' => ['title' => 'Live big screen', 'text' => 'Automatic slideshow on any screen with a browser, with the event QR code always in view.']],
                    ['pt_BR' => ['title' => 'Entrega em ZIP', 'text' => 'Baixe todas as fotos do evento em um único arquivo e entregue ao seu cliente.'],
                        'en' => ['title' => 'ZIP delivery', 'text' => 'Download all event photos as a single file and hand it over to your client.']],
                ],
            ],
            'pricing' => [
                'content' => [
                    'pt_BR' => ['title' => 'Planos simples e transparentes', 'subtitle' => 'Comece grátis, sem cartão. Escolha um plano quando estiver pronto.', 'cta' => 'Começar agora', 'note' => 'Teste grátis: um evento de até 2 horas e 20 fotos para você conhecer tudo.'],
                    'en' => ['title' => 'Simple, transparent pricing', 'subtitle' => 'Start free, no card needed. Pick a plan when you are ready.', 'cta' => 'Get started', 'note' => 'Free trial: one event of up to 2 hours and 20 photos so you can try everything.'],
                ],
            ],
            'faq' => [
                'content' => [
                    'pt_BR' => ['title' => 'Perguntas frequentes'],
                    'en' => ['title' => 'Frequently asked questions'],
                ],
                'items' => [
                    ['pt_BR' => ['question' => 'Os convidados precisam instalar algum aplicativo?', 'answer' => 'Não. Basta escanear o QR code com a câmera do celular. Tudo acontece no navegador.'],
                        'en' => ['question' => 'Do guests need to install an app?', 'answer' => 'No. They just scan the QR code with the phone camera. Everything happens in the browser.']],
                    ['pt_BR' => ['question' => 'Minhas fotos ficam seguras e privadas?', 'answer' => 'Sim. Cada organizador só enxerga os próprios eventos, as fotos não ficam em pastas públicas e não usamos rastreadores de terceiros. Os dados de localização são removidos das imagens.'],
                        'en' => ['question' => 'Are my photos safe and private?', 'answer' => 'Yes. Each organizer only sees their own events, photos are never in public folders and we use no third-party trackers. Location data is stripped from images.']],
                    ['pt_BR' => ['question' => 'Posso aprovar as fotos antes de irem ao telão?', 'answer' => 'Pode. Escolha a moderação prévia para aprovar cada foto ou a posterior para remover só o que não deveria estar lá.'],
                        'en' => ['question' => 'Can I approve photos before they hit the screen?', 'answer' => 'Yes. Choose pre-moderation to approve each photo, or post-moderation to remove only what should not be there.']],
                    ['pt_BR' => ['question' => 'Preciso de internet no local do evento?', 'answer' => 'Sim, tanto o telão quanto os celulares dos convidados precisam estar conectados. Uma rede Wi-Fi estável ou o 4G/5G dos convidados resolvem.'],
                        'en' => ['question' => 'Do I need internet at the venue?', 'answer' => 'Yes, both the big screen and guests\' phones need a connection. Stable Wi-Fi or guests\' mobile data both work.']],
                    ['pt_BR' => ['question' => 'Por quanto tempo as fotos ficam guardadas?', 'answer' => 'Depende do plano. Antes de expirar, você baixa tudo em um ZIP; depois, as fotos são apagadas automaticamente.'],
                        'en' => ['question' => 'How long are photos kept?', 'answer' => 'It depends on the plan. Before they expire you can download everything as a ZIP; after that, photos are deleted automatically.']],
                ],
            ],
            'final_cta' => [
                'content' => [
                    'pt_BR' => ['title' => 'Pronto para o próximo evento ficar inesquecível?', 'subtitle' => 'Crie sua conta, monte um evento de teste e veja as fotos no telão em poucos minutos.', 'cta' => 'Criar conta grátis'],
                    'en' => ['title' => 'Ready to make your next event unforgettable?', 'subtitle' => 'Create your account, set up a test event and see photos on the big screen in minutes.', 'cta' => 'Create free account'],
                ],
            ],
        ];
    }
}
