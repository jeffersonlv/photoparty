<?php

namespace App\Http\Controllers\Frames;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Frame;
use App\Services\Frames\CustomFrameManager;
use App\Services\Frames\FrameGuide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tela "Molduras" do organizador (módulo 8): modelos prontos + molduras personalizadas
 * (plano pago). A ativação por evento fica aqui, em cada moldura personalizada.
 */
class FrameLibraryController extends Controller
{
    public function __construct(private readonly CustomFrameManager $manager) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Frame::class);

        $account = $request->user()->account;

        $templates = Frame::query()
            ->whereNull('account_id')
            ->where('type', Frame::TYPE_TEMPLATE)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $customs = Frame::query()
            ->where('account_id', $account->id)
            ->where('type', Frame::TYPE_CUSTOM)
            ->with(['events' => fn ($q) => $q->select('events.id', 'events.uuid')])
            ->latest('id')
            ->get();

        return view('frames.index', [
            'account' => $account,
            'templates' => $templates,
            'customs' => $customs,
            'events' => $this->candidateEvents(),
            'canUse' => $account->canUseCustomFrames(),
            'limit' => Frame::MAX_CUSTOM_PER_ACCOUNT,
            'sizes' => \App\Services\Frames\CustomFrameImage::SIZES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Frame::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'portrait' => ['nullable', 'file', 'max:2048'],
            'landscape' => ['nullable', 'file', 'max:2048'],
        ], [
            'portrait.max' => __('frames.errors.too_large'),
            'landscape.max' => __('frames.errors.too_large'),
            'portrait.uploaded' => __('frames.errors.too_large'),
            'landscape.uploaded' => __('frames.errors.too_large'),
        ]);

        $this->manager->create($request->user()->account, trim($data['name']), [
            'portrait' => $request->file('portrait'),
            'landscape' => $request->file('landscape'),
        ]);

        return redirect()->route('frames.index')->with('status', __('frames.uploaded'));
    }

    public function updateEvents(Request $request, Frame $frame): RedirectResponse
    {
        $this->authorize('update', $frame);

        $data = $request->validate([
            'events' => ['nullable', 'array', 'max:200'],
            'events.*' => ['string', 'uuid'],
        ]);

        $this->manager->syncEvents($frame, $this->candidateEvents(), array_values($data['events'] ?? []));

        return redirect()->route('frames.index')->with('status', __('frames.events_updated'));
    }

    public function destroy(Frame $frame): RedirectResponse
    {
        $this->authorize('delete', $frame);

        $this->manager->delete($frame);

        return redirect()->route('frames.index')->with('status', __('frames.deleted'));
    }

    /**
     * PNG da moldura personalizada para a prévia do organizador (login + FramePolicy).
     */
    public function image(Frame $frame, string $orientation): BinaryFileResponse
    {
        $this->authorize('view', $frame);

        return self::servePng($frame, $orientation);
    }

    /**
     * Gabarito (retrato/paisagem) gerado com GD.
     */
    public function guide(FrameGuide $guide, string $orientation): Response
    {
        $this->authorize('viewAny', Frame::class);

        return response($guide->png($orientation), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="gabarito-moldura-'.$orientation.'.png"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Entrega o PNG da moldura custom, sempre de dentro da pasta do tenant dono.
     */
    public static function servePng(Frame $frame, string $orientation): BinaryFileResponse
    {
        $path = $frame->imagePath($orientation);
        $dir = $frame->storageDirectory();
        $disk = Storage::disk('local');

        abort_if(
            $path === null || $dir === null
            || ! str_starts_with($path, $dir.'/')
            || str_contains($path, '..')
            || ! $disk->exists($path),
            404,
        );

        $response = new BinaryFileResponse($disk->path($path), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$orientation.'.png"',
            'X-Content-Type-Options' => 'nosniff',
        ], false);

        return $response->setPrivate()->setMaxAge(600);
    }

    /**
     * Eventos da conta em que a moldura pode ser ligada/desligada: os que ainda não
     * terminaram. Global scope da conta: só eventos do usuário autenticado.
     *
     * @return Collection<int, Event>
     */
    private function candidateEvents(): Collection
    {
        return Event::query()
            ->orderByDesc('starts_at')
            ->limit(200)
            ->get()
            ->reject(fn (Event $event) => in_array($event->computeStatus(), [EventStatus::Ended, EventStatus::Expired], true))
            ->values();
    }
}
