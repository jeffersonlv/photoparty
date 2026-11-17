<?php

namespace App\Http\Controllers\Moderation;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Services\Photos\PhotoVariants;
use App\Services\PhotoModerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Moderação ao vivo (SPEC 8). Owner e moderator da conta (EventPolicy::moderate).
 * {event} é resolvido pelo uuid com o global scope da conta (outra conta => 404) e
 * scopeBindings() garante que {photo} pertença ao evento.
 */
class ModerationController extends Controller
{
    public const LIST_LIMIT = 200;

    public const BATCH_MAX = 200;

    public function __construct(private readonly PhotoModerator $moderator, private readonly PhotoVariants $variants)
    {
    }

    public function index(Event $event): Response
    {
        $this->authorize('moderate', $event);

        return response()->view('moderation.index', [
            'event' => $event,
            'config' => [
                'listUrl' => route('events.moderation.photos', $event, false),
                'batchUrl' => route('events.moderation.batch', $event, false),
                // O JS monta "{photoBase}/{uuid}/{action}".
                'photoBase' => route('events.moderation.photos', $event, false),
                'pollMs' => 5000,
                'limit' => self::LIST_LIMIT,
                'defaultTab' => $event->moderation === 'post' ? 'approved' : 'pending',
                'moderation' => $event->moderation,
                'demo' => ! ($event->account?->canDownloadPhotos() ?? false),
            ],
        ]);
    }

    /**
     * JSON do polling: contadores por status + miniaturas da aba pedida.
     */
    public function photos(Request $request, Event $event): JsonResponse
    {
        $this->authorize('moderate', $event);

        $status = $request->query('status', 'pending');
        abort_unless(is_string($status) && in_array($status, ['pending', 'approved', 'rejected'], true), 422);

        $counts = $event->photos()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $query = $event->photos()->where('status', $status);

        // Pendentes: mais antiga primeiro (fila). Demais: mais recente primeiro.
        $status === 'pending'
            ? $query->orderBy('created_at')->orderBy('id')
            : $query->orderByDesc('updated_at')->orderByDesc('id');

        $rows = $query->limit(self::LIST_LIMIT + 1)->get();
        // Conta com plano pago abre o original; as demais só veem a prévia (PhotoVariants).
        $canOpenOriginal = $event->account !== null && $this->variants->resolve($event->account, 'original') !== null;

        return response()->json([
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'status' => $status,
            'has_more' => $rows->count() > self::LIST_LIMIT,
            'photos' => $rows->take(self::LIST_LIMIT)->map(fn (Photo $photo) => [
                'uuid' => $photo->uuid,
                'thumb' => $photo->mediaUrl('thumb'),
                'full' => $photo->mediaUrl($canOpenOriginal ? 'original' : 'thumb'),
                'guest_name' => $photo->guest_name,
                'orientation' => $photo->orientation,
                'at' => $photo->created_at?->toIso8601String(),
            ])->values()->all(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Event $event, Photo $photo, string $action): JsonResponse
    {
        $this->authorize('moderate', $event);

        abort_unless(in_array($action, PhotoModerator::ACTIONS, true), 404);

        return response()->json($this->moderator->apply($event, [$photo->uuid], $action));
    }

    public function batch(Request $request, Event $event): JsonResponse
    {
        $this->authorize('moderate', $event);

        $data = $request->validate([
            'action' => ['required', Rule::in(PhotoModerator::ACTIONS)],
            'photos' => ['required', 'array', 'min:1', 'max:'.self::BATCH_MAX],
            'photos.*' => ['required', 'string', 'uuid'],
        ]);

        // uuids de fotos de outro evento/conta não são encontrados: contam como "ignored".
        return response()->json($this->moderator->apply($event, $data['photos'], $data['action']));
    }
}
