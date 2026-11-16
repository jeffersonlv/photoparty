<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $account = $request->user()->account;

        // Global scope: só eventos da conta do usuário.
        $events = Event::query()->orderBy('starts_at')->get();

        foreach ($events as $event) {
            $event->refreshStatus();
        }

        return view('dashboard', [
            'account' => $account,
            'live' => $events->filter(fn (Event $e) => $e->status === EventStatus::Live),
            'upcoming' => $events->filter(fn (Event $e) => in_array($e->status, [EventStatus::Draft, EventStatus::Scheduled], true)),
            'past' => $events->filter(fn (Event $e) => in_array($e->status, [EventStatus::Ended, EventStatus::Expired], true))->reverse(),
        ]);
    }
}
