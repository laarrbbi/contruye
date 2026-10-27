@use('App\Support\Bidi')
@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>

    <p style="margin:0 0 16px;">
        {!! __('We could not validate your payment receipt for :course.', ['course' => Bidi::isolate($course->localizedTitle())]) !!}
    </p>

    {{-- The reason is typed by the admin, often in French: in an Arabic
         e-mail it keeps its own direction on its own line. --}}
    @php($reasonDir = Bidi::direction($reason))
    <div style="margin:0 0 24px;padding:12px 16px;background-color:#fef2f2;border-radius:8px;color:#991b1b;">
        <strong>{{ __('Reason:') }}</strong>
        <div dir="{{ $reasonDir }}" style="margin-top:4px;text-align:{{ $reasonDir === 'rtl' ? 'right' : 'left' }};">
            {{ $reason }}
        </div>
    </div>

    <p style="margin:0 0 16px;">
        {{ __('Please upload a new receipt from your orders page.') }}
    </p>

    <x-mail.button :href="rtrim(config('app.frontend_url'), '/').'/orders'">{{ __('View my orders') }}</x-mail.button>
@endsection
