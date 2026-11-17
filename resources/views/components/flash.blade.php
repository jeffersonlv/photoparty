@if (session('status'))
    <div class="alert alert-ok mb-4" role="status">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-err mb-4" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->has('event'))
    <div class="alert alert-err mb-4" role="alert">{{ $errors->first('event') }}</div>
@endif
