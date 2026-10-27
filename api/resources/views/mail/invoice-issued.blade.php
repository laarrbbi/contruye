@use('App\Support\Bidi')
@use('App\Support\Money')
@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>

    {{-- The number stays whole and left to right inside an Arabic sentence. --}}
    <p style="margin:0 0 16px;">
        {!! __('Please find attached your invoice :number for :course.', [
            'number' => '<span dir="ltr" style="white-space:nowrap;">'.e($invoice->number).'</span>',
            'course' => Bidi::isolate($order->course->localizedTitle()),
        ]) !!}
    </p>

    <p style="margin:0 0 24px;">
        {{ __('Amount:') }} <strong>{{ Money::format($invoice->total_ttc_c / 100, $invoice->currency) }}</strong>
    </p>

    <x-mail.button :href="rtrim(config('app.frontend_url'), '/').'/orders'">{{ __('My orders') }}</x-mail.button>
@endsection
