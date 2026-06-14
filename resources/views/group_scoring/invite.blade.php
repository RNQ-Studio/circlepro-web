<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $group?->title ?: 'Latihan Bersama' }} — ManahPro</title>
    <meta name="description" content="Undangan bergabung ke sesi Latihan Bersama di ManahPro.">
    {{-- Open Graph so the WhatsApp preview shows the session, not a bare URL. --}}
    <meta property="og:title" content="{{ $group?->title ?: 'Latihan Bersama' }} — ManahPro">
    <meta property="og:description" content="@if($group){{ $group->distance_m }}m · {{ $group->environment->value === 'indoor' ? 'Indoor' : 'Outdoor' }} · {{ $group->num_ends }} ronde × {{ $group->arrows_per_end }} panah. Ketuk untuk bergabung.@else Bergabung ke sesi Latihan Bersama di ManahPro.@endif">
    <meta property="og:type" content="website">
    <style>
        :root {
            --brand: #2E7D32;
            --brand-dark: #1B5E20;
            --brand-surface: #E8F5E9;
            --amber: #FFB300;
            --ink: #1A1C19;
            --muted: #5C5F58;
            --line: #E0E3DD;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #F6F8F4;
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 460px; margin: 0 auto; padding: 24px 18px 48px; }
        .brand { text-align: center; margin-bottom: 18px; }
        .brand .logo {
            display: inline-flex; align-items: center; gap: 8px;
            font-weight: 800; font-size: 20px; color: var(--brand-dark);
        }
        .card {
            background: #fff; border: 1px solid var(--line); border-radius: 18px;
            padding: 22px; box-shadow: 0 6px 24px rgba(27,94,32,0.06);
        }
        .eyebrow { color: var(--brand); font-weight: 700; font-size: 13px; letter-spacing: 0.5px; }
        h1 { font-size: 22px; margin: 6px 0 4px; line-height: 1.25; }
        .host { color: var(--muted); font-size: 14px; margin: 0 0 18px; }
        .format { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 0 0 18px; }
        .chip {
            background: var(--brand-surface); border-radius: 12px; padding: 10px 12px;
        }
        .chip .k { color: var(--muted); font-size: 12px; }
        .chip .v { font-weight: 700; font-size: 15px; }
        .codebox {
            text-align: center; border: 1px dashed var(--brand); border-radius: 14px;
            padding: 14px; margin: 0 0 18px; background: #fff;
        }
        .codebox .k { color: var(--brand); font-size: 12px; letter-spacing: 2px; font-weight: 700; }
        .codebox .v { font-size: 30px; font-weight: 800; letter-spacing: 8px; color: var(--brand-dark); font-variant-numeric: tabular-nums; }
        .btn {
            display: block; width: 100%; text-align: center; text-decoration: none;
            padding: 14px 16px; border-radius: 12px; font-weight: 700; font-size: 16px;
            border: none; cursor: pointer; margin-bottom: 10px;
        }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-secondary { background: #fff; color: var(--brand-dark); border: 1.5px solid var(--brand); }
        .stores { display: flex; gap: 10px; margin-top: 6px; }
        .stores .btn { margin-bottom: 0; }
        .note { color: var(--muted); font-size: 13px; text-align: center; margin-top: 16px; line-height: 1.5; }
        .empty { text-align: center; }
        .empty .emoji { font-size: 40px; }
        .toast {
            position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%);
            background: var(--ink); color: #fff; padding: 10px 16px; border-radius: 10px;
            font-size: 14px; opacity: 0; transition: opacity .2s; pointer-events: none;
        }
        .toast.show { opacity: 0.95; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <span class="logo">🎯 ManahPro</span>
        </div>

        @if($group)
            <div class="card">
                <div class="eyebrow">UNDANGAN LATIHAN BERSAMA</div>
                <h1>{{ $group->title ?: 'Latihan Bersama' }}</h1>
                <p class="host">Dibuat oleh {{ $group->host?->name ?? 'Host' }} · {{ $group->participants_count ?? 0 }} peserta</p>

                <div class="format">
                    <div class="chip"><div class="k">Jarak</div><div class="v">{{ $group->distance_m }} m</div></div>
                    <div class="chip"><div class="k">Lingkungan</div><div class="v">{{ $group->environment->value === 'indoor' ? 'Indoor' : 'Outdoor' }}</div></div>
                    <div class="chip"><div class="k">Format</div><div class="v">{{ $group->num_ends }} × {{ $group->arrows_per_end }}</div></div>
                    <div class="chip"><div class="k">Target Face</div><div class="v">{{ $group->target_face_cm ? $group->target_face_cm.' cm' : '—' }}</div></div>
                </div>

                <div class="codebox">
                    <div class="k">KODE GABUNG</div>
                    <div class="v">{{ $code }}</div>
                </div>

                <a class="btn btn-primary" id="openApp" href="{{ $appLink }}">Buka di Aplikasi ManahPro</a>
                <button class="btn btn-secondary" id="copyCode" type="button">Salin Kode Gabung</button>

                <p class="note">Belum punya aplikasinya? Install dulu — kodenya sudah kami simpan, kamu akan langsung kembali ke sesi ini setelah daftar.</p>
                <div class="stores">
                    <a class="btn btn-secondary" href="{{ $playStoreUrl }}">Play Store</a>
                    @if($appStoreUrl)
                        <a class="btn btn-secondary" href="{{ $appStoreUrl }}">App Store</a>
                    @endif
                </div>
            </div>
        @else
            <div class="card empty">
                <div class="emoji">🏹</div>
                <h1>Sesi tidak ditemukan</h1>
                <p class="host">Kode undangan{{ $code ? ' "'.$code.'"' : '' }} tidak valid atau sesinya sudah berakhir. Minta host membagikan tautan terbaru.</p>
                <a class="btn btn-primary" href="{{ $playStoreUrl }}">Dapatkan ManahPro</a>
                @if($appStoreUrl)
                    <a class="btn btn-secondary" href="{{ $appStoreUrl }}">App Store</a>
                @endif
            </div>
        @endif
    </div>

    <div class="toast" id="toast">Kode disalin</div>

    <script>
        (function () {
            var code = @json($code);
            var schemeLink = @json($schemeLink);
            var toast = document.getElementById('toast');

            function showToast(msg) {
                if (!toast) return;
                toast.textContent = msg;
                toast.classList.add('show');
                setTimeout(function () { toast.classList.remove('show'); }, 1600);
            }

            function copyCode(silent) {
                if (!code) return;
                // Deferred deep link (task 9.4): stash the code on the clipboard so
                // the freshly-installed app can read it on first launch and resume.
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText('manahpro:join:' + code).then(function () {
                        if (!silent) showToast('Kode disalin');
                    }).catch(function () {});
                }
            }

            // Best-effort stash on load (some browsers require a gesture; ignored if blocked).
            copyCode(true);

            var copyBtn = document.getElementById('copyCode');
            if (copyBtn) copyBtn.addEventListener('click', function () { copyCode(false); });

            // "Open in app" first tries the verified HTTPS link (default <a href>),
            // then falls back to the custom scheme if nothing handled it.
            var openBtn = document.getElementById('openApp');
            if (openBtn && code) {
                openBtn.addEventListener('click', function (e) {
                    copyCode(true);
                    // Give the HTTPS app link a moment; if still here, try the scheme.
                    setTimeout(function () {
                        window.location.href = schemeLink;
                    }, 600);
                });
            }
        })();
    </script>
</body>
</html>
