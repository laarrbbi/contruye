@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>

    <div lang="{{ $textLang }}" dir="{{ $textDir }}" style="text-align:{{ $textDir === 'rtl' ? 'right' : 'left' }};">
        <h2 style="font-size:18px;margin:0 0 16px;color:#141A22;">{{ $title }}</h2>

        {{-- Markdown rendered on the server, raw HTML stripped (App\Support\Markdown). --}}
        <div style="margin:0 0 16px;line-height:1.6;">{!! $bodyHtml !!}</div>
    </div>

    @if ($price)
        <p style="margin:0 0 16px;"><strong>{{ $price }}</strong></p>
    @endif

    @if ($closesAt)
        <p style="margin:0 0 24px;color:#6b7280;">{{ __('Available until :date.', ['date' => $closesAt]) }}</p>
    @endif

    <x-mail.button :href="$ctaUrl">{{ $ctaLabel }}</x-mail.button>
@endsection

@section('footer')
    <p style="margin:24px 0 0;color:#6b7280;font-size:13px;">
        {{ __('You receive this e-mail because you are a student of Construye Academy.') }}
        <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">{{ __('Stop receiving offers by e-mail') }}</a>
    </p>
@endsection
