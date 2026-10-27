<header class="border-b bg-white">
    <div class="container flex h-16 items-center justify-between gap-4">
        <a href="{{ $header['home'] }}" class="text-lg font-bold text-primary">{{ $header['name'] }}</a>
        <nav class="flex items-center gap-4 text-sm">
            @foreach ($header['languages'] as $language)
                <a href="{{ $language['href'] }}" hreflang="{{ $language['locale'] }}" lang="{{ $language['locale'] }}">{{ $language['name'] }}</a>
            @endforeach
        </nav>
    </div>
</header>
