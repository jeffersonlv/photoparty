@props(['action', 'method' => 'POST', 'confirm'])
<form method="POST" action="{{ $action }}" x-data="confirmSubmit" x-on:submit="ask" data-confirm="{{ $confirm }}" {{ $attributes }}>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    {{ $slot }}
</form>
