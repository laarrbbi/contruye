<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The course package
    |--------------------------------------------------------------------------
    |
    | The instructor's material, exactly as delivered: the module texts, the
    | quizzes and exam, the province directory and the financial template
    | (curso/ at the root of the repository). The seeders read it; nothing in
    | it is edited by the application. In Docker it is mounted read-only next
    | to the API, like ../web.
    |
    */

    'path' => env('CURSO_PATH', base_path('../curso')),

    /*
    |--------------------------------------------------------------------------
    | Its translations
    |--------------------------------------------------------------------------
    |
    | One folder per locale (traducciones/ru/…), files named as in curso/ and
    | cut the same way. Ours, not the instructor's: drafts until a human
    | reviews them, with the Spanish as the reference. CourseTranslationSeeder
    | loads them into the `_<locale>` columns; see traducciones/LEEME.md.
    |
    */

    'translations' => env('CURSO_TRANSLATIONS_PATH', base_path('../traducciones')),

];
