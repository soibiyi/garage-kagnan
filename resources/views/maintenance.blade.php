<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Maintenance — Garage Kagnan</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #0b0f19;
            background: radial-gradient(900px 500px at 100% 0%, rgba(225, 29, 72, .08), transparent 60%), linear-gradient(180deg, #f4f4f6, #eeeef1);
        }
        .carte {
            width: 100%; max-width: 460px; padding: 36px 28px; text-align: center; border-radius: 24px;
            background: #fff; box-shadow: 0 1px 2px rgba(11, 15, 25, .05), 0 24px 48px -24px rgba(11, 15, 25, .35);
        }
        img { height: 64px; width: auto; }
        .icone { width: 56px; height: 56px; margin: 20px auto 12px; display: grid; place-items: center; border-radius: 16px; background: #fff1f2; color: #e11d48; font-size: 26px; }
        h1 { margin: 0 0 8px; font-size: 1.4rem; font-weight: 900; }
        p { margin: 0; color: #4b4e52; line-height: 1.55; }
        .petit { margin-top: 18px; font-size: .78rem; color: #8a8d8f; }
        button { margin-top: 20px; border: 0; border-radius: 12px; padding: 10px 18px; background: #0b0f19; color: #fff; font-weight: 700; cursor: pointer; }
        button:hover { background: #e11d48; }
    </style>
</head>
<body>
    <main class="carte">
        <img src="/images/logo-kagnan.png" alt="Garage Kagnan">
        <div class="icone" aria-hidden="true">&#9881;</div>
        <h1>Maintenance en cours</h1>
        <p>{{ $message }}</p>
        <p class="petit">Cette page se recharge toute seule chaque minute.</p>
        @if ($connecte)
            <form method="POST" action="{{ url('/logout') }}">
                @csrf
                <button type="submit">Se déconnecter</button>
            </form>
        @endif
    </main>
</body>
</html>
