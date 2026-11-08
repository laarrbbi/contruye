@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ __('You will no longer receive offers by e-mail.') }}</p>
    <p style="margin:0 0 24px;color:#6b7280;">{{ __('Your course and your access do not change.') }}</p>
    <x-mail.button :href="rtrim(config('app.frontend_url'), '/').'/dashboard'">{{ __('Back to my courses') }}</x-mail.button>
@endsection
