@php
    $fcfa = fn ($n) => number_format((float) $n, 0, ',', ' ') . ' FCFA';
    $titre = $rapport->periode === 'semaine'
        ? 'Rapport de la semaine du ' . $rapport->debut->format('d/m/Y') . ' au ' . $rapport->fin->format('d/m/Y')
        : 'Rapport du ' . $rapport->debut->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0b0f19; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #e11d48; text-transform: uppercase; letter-spacing: .5px; }
        .sous-titre { color: #6b6e70; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        th { background: #0b0f19; color: #fff; font-size: 9.5px; }
        .num { text-align: right; white-space: nowrap; }
        .kpis td { width: 25%; border: 1px solid #e5e7eb; padding: 8px; }
        .kpis .val { font-size: 14px; font-weight: bold; }
        .kpis .lib { color: #6b6e70; font-size: 9px; text-transform: uppercase; }
        .vide { color: #8a8d8f; font-style: italic; }
        .alerte { color: #be123c; }
    </style>
</head>
<body>
    <table style="border: 0;">
        <tr>
            <td style="border: 0;">
                <h1>Garage Kagnan — {{ $titre }}</h1>
                <div class="sous-titre">Généré le {{ $rapport->created_at->format('d/m/Y à H:i') }}</div>
            </td>
        </tr>
    </table>

    <h2>Argent</h2>
    <table class="kpis">
        <tr>
            <td><div class="lib">CA facturé</div><div class="val">{{ $fcfa($d['argent']['ca']) }}</div><div class="lib">{{ $d['argent']['nb_factures'] }} facture(s)</div></td>
            <td><div class="lib">Encaissé</div><div class="val">{{ $fcfa($d['argent']['encaisse']) }}</div></td>
            <td><div class="lib">Impayés à ce jour</div><div class="val">{{ $fcfa($d['argent']['impayes']) }}</div></td>
            <td><div class="lib">Véhicules reçus</div><div class="val">{{ $d['activite']['vehicules_recus'] }}</div><div class="lib">{{ $d['activite']['en_cours'] }} en cours</div></td>
        </tr>
    </table>

    <h2>Par siège</h2>
    <table>
        <tr><th>Siège</th><th class="num">CA facturé</th><th class="num">Encaissé</th><th class="num">Véhicules reçus</th><th class="num">Ruptures de stock</th></tr>
        @foreach ($d['sieges'] as $s)
            <tr>
                <td>{{ $s['code'] }} — {{ $sieges[$s['code']] ?? '' }}</td>
                <td class="num">{{ $fcfa($s['ca']) }}</td>
                <td class="num">{{ $fcfa($s['encaisse']) }}</td>
                <td class="num">{{ $s['interventions'] }}</td>
                <td class="num">{{ $s['ruptures'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Actions sensibles ({{ $d['actions_sensibles']['total'] }})</h2>
    @if (empty($d['actions_sensibles']['liste']))
        <p class="vide">Aucune action sensible sur la période.</p>
    @else
        <table>
            <tr><th>Date</th><th>Alerte</th><th>Auteur</th><th>Siège</th></tr>
            @foreach ($d['actions_sensibles']['liste'] as $a)
                <tr>
                    <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($a['date'])->format('d/m H:i') }}</td>
                    <td class="alerte">{{ $a['alerte'] }}</td>
                    <td>{{ $a['auteur'] }}</td>
                    <td>{{ $a['siege'] ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Sécurité</h2>
    <table>
        <tr><th>Connexions</th><th>Échecs de connexion</th><th>Connexions hors horaires</th><th>Comptes suspendus</th></tr>
        <tr>
            <td>{{ $d['securite']['connexions'] }}</td>
            <td>{{ $d['securite']['echecs'] }}</td>
            <td>{{ $d['securite']['hors_horaires'] }}</td>
            <td>{{ $d['securite']['comptes_suspendus'] }}</td>
        </tr>
    </table>

    <h2>Erreurs de l'application</h2>
    <p>{{ $d['erreurs']['nouvelles'] }} nouvelle(s) erreur(s) · {{ $d['erreurs']['ouvertes'] }} ouverte(s) au total.</p>
    @if (! empty($d['erreurs']['plus_frequentes']))
        <table>
            <tr><th>Type</th><th>Message</th><th class="num">Occurrences</th></tr>
            @foreach ($d['erreurs']['plus_frequentes'] as $e)
                <tr><td>{{ $e['type'] }}</td><td>{{ $e['message'] }}</td><td class="num">{{ $e['occurrences'] }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Stocks bas (seuil : {{ $d['stocks_bas']['seuil'] }})</h2>
    @if (empty($d['stocks_bas']['liste']))
        <p class="vide">Aucune pièce sous le seuil.</p>
    @else
        <table>
            <tr><th>Pièce</th><th>Référence</th><th>Siège</th><th class="num">Reste</th></tr>
            @foreach ($d['stocks_bas']['liste'] as $s)
                <tr><td>{{ $s['piece'] }}</td><td>{{ $s['reference'] ?? '—' }}</td><td>{{ $s['siege'] }}</td><td class="num">{{ $s['reste'] }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
