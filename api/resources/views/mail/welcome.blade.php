@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>

    <p style="margin:0 0 16px;">
        {{ __('Your Ebni Academy account has been created. You can browse the program and start with the free preview lessons right away.') }}
    </p>

    <x-mail.button :href="rtrim(config('app.frontend_url'), '/')">{{ __('Discover the program') }}</x-mail.button>
@endsection
