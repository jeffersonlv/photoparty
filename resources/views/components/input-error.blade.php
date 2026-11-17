@props(['name'])
@error($name)
    <p class="mt-1.5 text-sm font-semibold text-red-700">{{ $message }}</p>
@enderror
