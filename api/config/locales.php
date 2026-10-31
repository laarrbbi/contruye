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

    'supported' => ['es', 'en', 'fr', 'ar', 'ru', 'uk'],

    /*
    | The authored locale. Course content is written in Spanish; every other
    | locale falls back to it rather than showing a blank.
    */
    'default' => 'es',

    /*
    | Locales that have their own translated *content* columns in the database
    | (title_en, description_fr…). A locale outside this list still gets a
    | fully translated interface — only the course material falls back.
    |
    | The translations of the course are drafts until a native reader has
    | reviewed them (traducciones/), and the Spanish text is the reference.
    | An empty column reads the authored Spanish.
    */
    'content' => ['es', 'en', 'fr', 'ar', 'ru', 'uk'],

    /*
    | Locales written right to left.
    */
    'rtl' => ['ar'],

];
