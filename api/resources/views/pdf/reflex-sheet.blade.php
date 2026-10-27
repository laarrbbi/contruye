<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #1c2434; line-height: 1.45; }
        .header { border-bottom: 3px solid #223A5E; padding-bottom: 8px; margin-bottom: 14px; }
        .kicker { font-size: 8.5pt; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; }
        h1 { font-size: 16pt; margin: 4px 0 0; color: #223A5E; }
        .intro { background: #f4f6fa; border-left: 4px solid #223A5E; padding: 8px 10px; margin-bottom: 14px; font-size: 10pt; }
        ol { padding-left: 16px; margin: 0; }
        li { margin-bottom: 9px; }
        .mantra { margin-top: 16px; background: #fff8e6; border: 1px solid #e8c56a; padding: 10px 12px; font-size: 9.8pt; }
        .mantra strong { color: #7a5a11; }
        .footer { margin-top: 18px; border-top: 1px solid #d5d9e2; padding-top: 7px; font-size: 8pt; color: #6b7280; }
        .legal { margin-top: 10px; font-size: 8.5pt; color: #4b5563; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <div class="kicker">{{ $courseTitle }} — Module {{ $moduleOrder }}</div>
        <h1>{{ $title }}</h1>
    </div>

    @if ($intro)
        <div class="intro">{{ $intro }}</div>
    @endif

    <ol>
        @foreach ($steps as $step)
            <li>{{ $step }}</li>
        @endforeach
    </ol>

    @foreach ($tips as $tip)
        <div class="mantra"><strong>À retenir —</strong> {{ $tip }}</div>
    @endforeach

    @foreach ($legal as $reference)
        <div class="legal">{{ $reference }}</div>
    @endforeach

    <div class="footer">
        {{ $courseTitle }} — fiche à imprimer et afficher dans la base de vie du chantier.
        Document pédagogique : il ne remplace pas les obligations légales de l'employeur.
    </div>
</body>
</html>
