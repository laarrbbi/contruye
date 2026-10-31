{{--
    The frame every e-mail shares: the site's primary blue, and the language
    direction set on the content itself. Gmail drops the attributes of <html>,
    so the direction is repeated on the card.
--}}
@php
    $rtl = in_array(app()->getLocale(), config('locales.rtl', []), true);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:24px;background-color:#f5f6fa;font-family:Tahoma,Arial,sans-serif;color:#1f2937;">
    <div dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="max-width:560px;margin:0 auto;background-color:#ffffff;border-radius:12px;padding:32px;text-align:{{ $rtl ? 'right' : 'left' }};">
        <h1 style="font-size:20px;margin:0 0 24px;color:#1F3A5F;">Construye Academy</h1>

        @yield('content')

        @hasSection('footer')
            @yield('footer')
        @else
            <p style="margin:0;color:#6b7280;font-size:13px;">{{ __('Thank you for learning with Construye Academy.') }}</p>
        @endif
    </div>
</body>
</html>
