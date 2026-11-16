@if (session('status'))
    <div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->has('event'))
    <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first('event') }}</div>
@endif
