@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>

    <p style="margin:0 0 16px;">
        {{ __('You are receiving this email because we received a password reset request for your account.') }}
    </p>

    <x-mail.button :href="$url">{{ __('Reset my password') }}</x-mail.button>
@endsection

@section('footer')
    <p style="margin:0 0 16px;color:#6b7280;font-size:13px;">
        {{ __('This password reset link will expire in :count minutes.', ['count' => $expireMinutes]) }}
    </p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        {{ __('If you did not request a password reset, no further action is required.') }}
    </p>
@endsection
