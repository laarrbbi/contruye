@use('App\Support\Bidi')
@use('App\Support\Money')
@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>

    <p style="margin:0 0 16px;">
        {!! __('Your payment for :course has been confirmed. You are now enrolled.', ['course' => Bidi::isolate($course->localizedTitle())]) !!}
    </p>

    <p style="margin:0 0 24px;">
        {{ __('Amount paid:') }} <strong>{{ Money::format($order->amount, $order->currency) }}</strong>
    </p>

    {{-- Into the course, not its sales page: they have just paid for it. --}}
    <x-mail.button :href="rtrim(config('app.frontend_url'), '/').'/learn/'.$course->slug">{{ __('Start learning') }}</x-mail.button>
@endsection
