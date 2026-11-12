<?php

/*
 * How the course material is protected from being taken whole. Each measure
 * and its reason: docs/proteccion-contenido.md.
 */
return [

    // The invisible mark of the reader in every text served (ContentFingerprint).
    'fingerprint' => (bool) env('CONTENT_FINGERPRINT', true),

    // Requests for course material per student: a person reading never comes
    // near these; a script copying the course does.
    'per_minute' => (int) env('CONTENT_PER_MINUTE', 40),
    'per_hour' => (int) env('CONTENT_PER_HOUR', 400),

    // Distinct pages of material opened in 24 hours above which the owner is
    // told (an audit entry "content.unusual_reading"). Nothing is blocked: a
    // keen student may read a lot in a day.
    'daily_alert' => (int) env('CONTENT_DAILY_ALERT', 120),

];
