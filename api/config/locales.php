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

    'supported' => ['es', 'en'],

    /*
    | The authored locale. Course content is written in Spanish; every other
    | locale falls back to it rather than showing a blank.
    */
    'default' => 'es',

    /*
    | Locales that have their own translated *content* columns in the database
    | (title_en, description_en…). A locale outside this list still gets a
    | fully translated interface — only the course material falls back.
    |
    | The `_en` columns stay empty until a human translation is written:
    | English readers see the authored Spanish meanwhile.
    */
    'content' => ['es', 'en'],

];
