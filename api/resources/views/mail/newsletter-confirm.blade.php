@extends('mail.layout')

@section('content')
    <p style="margin:0 0 16px;">{{ __('Someone, hopefully you, asked to receive the CONSTRUYE newsletter at this address.') }}</p>

    <p style="margin:0 0 16px;">{{ __('Confirm it with the button below. If it was not you, ignore this e-mail: without confirmation we will not write to you again.') }}</p>

    <x-mail.button :href="$confirmUrl">{{ __('Confirm my subscription') }}</x-mail.button>

    <p style="margin:16px 0 0;font-size:13px;color:#5b6472;">{{ __('Every newsletter carries a link to unsubscribe in one click.') }}</p>
@endsection
