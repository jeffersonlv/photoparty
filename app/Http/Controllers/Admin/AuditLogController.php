<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'user' => ['nullable', 'string', 'max:120'],
            'account' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $like = fn (string $term) => '%'.addcslashes($term, '%_\\').'%';

        $logs = AuditLog::query()
            ->with(['user', 'account'])
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', $like($v)))
            ->when($filters['user'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('email', 'like', $like($v))->orWhere('name', 'like', $like($v))))
            ->when($filters['account'] ?? null, fn ($q, $v) => $q->whereHas('account', fn ($a) => $a->where('name', 'like', $like($v))->orWhere('uuid', $v)))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
