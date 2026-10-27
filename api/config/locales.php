<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported interface locales
    |--------------------------------------------------------------------------
    |
    | Single source of truth for every locale the app accepts — the SetLocale
    | middleware, the register/profile validation rules and the front-end
    | language switcher all key off this list.
    |
    */

    'supported' => ['fr', 'ar', 'en'],

    /*
    | The authored locale. Course content is written in French; every other
    | locale falls back to it rather than showing a blank.
    */
    'default' => 'fr',

    /*
    | Locales that have their own translated *content* columns in the database
    | (title_ar, description_ar…). A locale outside this list still gets a
    | fully translated interface — only the course material falls back.
    |
    | Adding `en` here after the `_en` columns exist is the only change needed
    | to switch English content on.
    */
    'content' => ['fr', 'ar'],

];
