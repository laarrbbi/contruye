@props(['href'])
<p style="margin:0 0 32px;">
    <a href="{{ $href }}"
       style="display:inline-block;background-color:#1F3A5F;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;">{{ $slot }}</a>
</p>
