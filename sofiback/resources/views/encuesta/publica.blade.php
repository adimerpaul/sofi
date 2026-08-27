@php
    use Illuminate\Support\Str;

    $opciones = [
        10 => ['label' => '¡Perfecto!', 'sub' => 'Todo excelente',        'tone' => 'good', 'face' => 'happy'],
        5  => ['label' => 'Bueno',      'sub' => 'Pudo ser mejor',        'tone' => 'mid',  'face' => 'neutral'],
        0  => ['label' => 'Malo',       'sub' => 'No quedé conforme',     'tone' => 'bad',  'face' => 'sad'],
    ];

    $fechaLarga = \Carbon\Carbon::parse($hoy)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
    $clienteNombre = trim((string) optional($cliente)->Nombres);
    $clienteDir    = trim((string) optional($cliente)->Direccion);
    $clienteZona   = trim((string) optional($cliente)->zona);
    $placa         = trim((string) optional($usuario)->placa);
    $inicial       = Str::upper(Str::substr($usuarioNombre ?: 'S', 0, 1));

    $scoreViejo = old('score', null);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#c62828">
    <title>Encuesta de satisfacción · Sofía</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        :root {
            --brand: #c62828;
            --brand-dark: #8e1f1f;
            --bg: #f4f5f7;
            --card: #ffffff;
            --text: #1f2329;
            --muted: #6b7280;
            --line: #e5e7eb;
            --soft: #f8f9fb;
            --good: #16a34a;
            --mid: #f59e0b;
            --bad: #dc2626;
            --radius: 18px;
            --shadow: 0 10px 30px rgba(16, 24, 40, .08), 0 2px 6px rgba(16, 24, 40, .04);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1115;
                --card: #171a21;
                --text: #e8eaee;
                --muted: #9aa2ae;
                --line: #262b34;
                --soft: #1d212a;
                --shadow: 0 10px 30px rgba(0, 0, 0, .45);
            }
        }

        * { box-sizing: border-box; }

        [hidden] { display: none !important; }

        html, body { margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background:
                radial-gradient(1000px 420px at 50% -180px, rgba(198, 40, 40, .16), transparent 70%),
                var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.45;
            -webkit-font-smoothing: antialiased;
            display: flex;
            justify-content: center;
            padding: 20px 14px calc(28px + env(safe-area-inset-bottom));
        }

        .wrap { width: 100%; max-width: 480px; }

        /* ---------- Marca ---------- */
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .brand img { height: 40px; width: auto; display: block; border-radius: 8px; }
        .brand .brand-txt { font-weight: 800; letter-spacing: .3px; font-size: 18px; }

        /* ---------- Card ---------- */
        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--line);
        }

        .card-head {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            color: #fff;
            padding: 20px 20px 22px;
        }
        .card-head h1 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: .2px; }
        .card-head p { margin: 6px 0 0; font-size: 13px; opacity: .9; }

        .card-body { padding: 18px 18px 22px; }

        /* ---------- Info cliente / repartidor ---------- */
        .info {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 18px;
        }
        .info .row { display: flex; align-items: center; gap: 12px; }
        .info .row + .row { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--line); }
        .avatar {
            flex: 0 0 42px; height: 42px; width: 42px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff; font-weight: 800; font-size: 17px;
        }
        .avatar.alt { background: linear-gradient(135deg, #334155, #0f172a); }
        .info .txt { min-width: 0; }
        .info .k { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); font-weight: 700; }
        .info .v { font-size: 15px; font-weight: 700; word-break: break-word; }
        .info .s { font-size: 13px; color: var(--muted); word-break: break-word; }

        /* ---------- Alertas ---------- */
        .alert {
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 14px;
            margin-bottom: 16px;
            display: flex; gap: 10px; align-items: flex-start;
            border: 1px solid transparent;
        }
        .alert svg { flex: 0 0 18px; margin-top: 1px; }
        .alert-ok   { background: rgba(22, 163, 74, .12); border-color: rgba(22, 163, 74, .3); color: #15803d; }
        .alert-err  { background: rgba(220, 38, 38, .12); border-color: rgba(220, 38, 38, .3); color: #b91c1c; }
        .alert-info { background: rgba(59, 130, 246, .1); border-color: rgba(59, 130, 246, .28); color: #1d4ed8; }
        @media (prefers-color-scheme: dark) {
            .alert-ok { color: #4ade80; }
            .alert-err { color: #fca5a5; }
            .alert-info { color: #93c5fd; }
        }

        /* ---------- Pregunta ---------- */
        .q { font-size: 16px; font-weight: 800; margin: 0 0 4px; }
        .q-sub { font-size: 13px; color: var(--muted); margin: 0 0 14px; }

        .options { display: grid; gap: 10px; margin-bottom: 18px; }
        .opt { position: relative; display: block; cursor: pointer; }
        .opt input { position: absolute; opacity: 0; width: 0; height: 0; }
        .opt-in {
            display: flex; align-items: center; gap: 12px;
            border: 2px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
            background: var(--card);
            transition: border-color .16s ease, background .16s ease, transform .12s ease;
        }
        .opt:active .opt-in { transform: scale(.99); }
        .opt-face { flex: 0 0 40px; height: 40px; width: 40px; }
        .opt-face svg { width: 100%; height: 100%; display: block; }
        .opt-txt { flex: 1 1 auto; min-width: 0; }
        .opt-txt .t { font-weight: 700; font-size: 15px; }
        .opt-txt .d { font-size: 12.5px; color: var(--muted); }
        .opt-score { font-weight: 800; font-size: 20px; color: var(--muted); transition: color .16s ease; }

        .opt[data-tone="good"] input:checked + .opt-in { border-color: var(--good); background: rgba(22, 163, 74, .08); }
        .opt[data-tone="mid"]  input:checked + .opt-in { border-color: var(--mid);  background: rgba(245, 158, 11, .08); }
        .opt[data-tone="bad"]  input:checked + .opt-in { border-color: var(--bad);  background: rgba(220, 38, 38, .08); }
        .opt[data-tone="good"] input:checked + .opt-in .opt-score { color: var(--good); }
        .opt[data-tone="mid"]  input:checked + .opt-in .opt-score { color: var(--mid); }
        .opt[data-tone="bad"]  input:checked + .opt-in .opt-score { color: var(--bad); }
        .opt input:focus-visible + .opt-in { outline: 3px solid rgba(59, 130, 246, .45); outline-offset: 2px; }

        /* ---------- Campos ---------- */
        label.fld { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; }
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--text);
            border-radius: 12px;
            padding: 12px;
            font: inherit;
            font-size: 14.5px;
            resize: vertical;
            min-height: 88px;
        }
        textarea:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(198, 40, 40, .14); }
        .counter { font-size: 11.5px; color: var(--muted); text-align: right; margin-top: 4px; }

        .check {
            display: flex; gap: 10px; align-items: flex-start;
            margin: 14px 0 4px;
            font-size: 13.5px;
            cursor: pointer;
        }
        .check input { margin: 2px 0 0; width: 18px; height: 18px; accent-color: var(--brand); flex: 0 0 18px; }

        .err { color: #dc2626; font-size: 12.5px; margin-top: 6px; }

        /* ---------- Botones ---------- */
        .btn {
            width: 100%;
            border: 0;
            border-radius: 999px;
            padding: 14px 18px;
            font: inherit;
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 6px 18px rgba(198, 40, 40, .28);
            transition: opacity .16s ease, transform .12s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn[disabled] { opacity: .45; cursor: not-allowed; box-shadow: none; }

        .btn-google {
            width: 100%;
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 11px 14px;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-link {
            background: none; border: 0; padding: 0;
            color: var(--brand); font: inherit; font-size: 13px; font-weight: 700; cursor: pointer;
        }

        .google-box { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--line); }
        .google-box .hint { font-size: 12.5px; color: var(--muted); margin: 0 0 10px; }
        .signed {
            display: flex; align-items: center; gap: 10px;
            background: var(--soft); border: 1px solid var(--line);
            border-radius: 12px; padding: 10px 12px; font-size: 13.5px;
        }
        .signed .mail { flex: 1 1 auto; min-width: 0; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ---------- Estado: ya respondió ---------- */
        .done { text-align: center; padding: 8px 4px 4px; }
        .done .mark {
            width: 76px; height: 76px; margin: 0 auto 14px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: rgba(22, 163, 74, .12);
            color: var(--good);
        }
        .done h2 { margin: 0 0 6px; font-size: 19px; font-weight: 800; }
        .done p { margin: 0; color: var(--muted); font-size: 14px; }
        .done .resumen {
            margin-top: 18px; text-align: left;
            background: var(--soft); border: 1px solid var(--line);
            border-radius: 14px; padding: 14px;
        }
        .done .resumen .k { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); font-weight: 700; }
        .done .resumen .big { display: flex; align-items: center; gap: 10px; margin-top: 4px; font-size: 17px; font-weight: 800; }
        .done .resumen .big svg { width: 32px; height: 32px; }
        .done .comentario { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--line); font-size: 14px; }

        .foot { margin-top: 14px; text-align: center; font-size: 11.5px; color: var(--muted); line-height: 1.5; }

        @media (max-width: 360px) {
            .opt-txt .d { display: none; }
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="brand">
        <img src="{{ asset('logo-sofia.png') }}" alt="Sofía" onerror="this.style.display='none'">
        <span class="brand-txt">Sofía</span>
    </div>

    <div class="card">
        <div class="card-head">
            <h1>Encuesta de satisfacción</h1>
            <p>{{ ucfirst($fechaLarga) }}</p>
        </div>

        <div class="card-body">

            @if ($error)
                <div class="alert alert-err">
                    @include('encuesta.partials.icon-alert')
                    <div>{{ $error }}</div>
                </div>
                <p class="q-sub" style="margin:0">
                    Verifica el enlace o el código QR con el repartidor. Si el problema continúa,
                    comunícate con Distribuidora Sofía.
                </p>
            @else

                @if (session('ok'))
                    <div class="alert alert-ok">
                        @include('encuesta.partials.icon-check')
                        <div>{{ session('ok') }}</div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-err">
                        @include('encuesta.partials.icon-alert')
                        <div>{{ session('error') }}</div>
                    </div>
                @endif

                {{-- Datos que se leen de la BD en cada carga (F5 = datos actuales) --}}
                <div class="info">
                    <div class="row">
                        <div class="avatar">{{ Str::upper(Str::substr($clienteNombre ?: 'C', 0, 1)) }}</div>
                        <div class="txt">
                            <div class="k">Cliente</div>
                            <div class="v">{{ $clienteNombre ?: 'Cliente '.$idcliente }}</div>
                            @if ($clienteDir || $clienteZona)
                                <div class="s">{{ trim($clienteDir . ($clienteZona ? ' · Zona ' . $clienteZona : '')) }}</div>
                            @endif
                        </div>
                    </div>
                    {{-- <div class="row">
                        <div class="avatar alt">{{ $inicial }}</div>
                        <div class="txt">
                            <div class="k">Te atendió</div>
                            <div class="v">{{ $usuarioNombre ?: 'Repartidor '.$iduser }}</div>
                            @if ($placa)
                                <div class="s">Móvil {{ $placa }}</div>
                            @endif
                        </div>
                    </div> --}}
                </div>

                @if ($respuesta)
                    {{-- Ya respondió hoy: estado real traído de la BD --}}
                    @php $op = $opciones[$respuesta->score] ?? $opciones[5]; @endphp
                    <div class="done">
                        <div class="mark">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5"/>
                            </svg>
                        </div>
                        <h2>¡Gracias por tu respuesta!</h2>
                        <p>Ya registramos tu calificación de hoy. Puedes cerrar esta página.</p>

                        <div class="resumen">
                            <div class="k">Calificación registrada</div>
                            <div class="big">
                                @include('encuesta.partials.face', ['face' => $op['face']])
                                <span>{{ $op['label'] }} · {{ $respuesta->score }}/10</span>
                            </div>
                            @if (trim((string) $respuesta->comment) !== '')
                                <div class="comentario">
                                    <div class="k">Tu comentario</div>
                                    <div>{{ $respuesta->comment }}</div>
                                </div>
                            @endif
                            <div class="comentario">
                                <div class="k">Registrada</div>
                                <div>{{ optional($respuesta->created_at)->timezone('America/La_Paz')->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Formulario --}}
                    <form method="POST" action="{{ route('encuesta.publica.store', ['idcliente' => $idcliente, 'iduser' => $iduser]) }}" id="form-encuesta">
                        @csrf
                        <input type="hidden" name="email" id="email" value="{{ old('email') }}">

                        <p class="q">¿Cómo calificas la entrega de hoy?</p>
                        <p class="q-sub">Selecciona una opción. Tu respuesta es anónima frente al repartidor.</p>

                        <div class="options">
                            @foreach ($opciones as $valor => $op)
                                <label class="opt" data-tone="{{ $op['tone'] }}">
                                    <input type="radio" name="score" value="{{ $valor }}"
                                           @if ((string) $scoreViejo === (string) $valor) checked @endif>
                                    <span class="opt-in">
                                        <span class="opt-face">@include('encuesta.partials.face', ['face' => $op['face']])</span>
                                        <span class="opt-txt">
                                            <span class="t">{{ $op['label'] }}</span><br>
                                            <span class="d">{{ $op['sub'] }}</span>
                                        </span>
                                        <span class="opt-score">{{ $valor }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('score') <div class="err">{{ $message }}</div> @enderror

                        <label class="fld" for="comment">Comentario (opcional)</label>
                        <textarea id="comment" name="comment" maxlength="500"
                                  placeholder="Cuéntanos qué podemos mejorar…">{{ old('comment') }}</textarea>
                        <div class="counter"><span id="cnt">{{ mb_strlen((string) old('comment')) }}</span>/500</div>
                        @error('comment') <div class="err">{{ $message }}</div> @enderror

                        <label class="check">
                            <input type="checkbox" name="declaro" id="declaro" value="1" @if (old('declaro')) checked @endif>
                            <span>Declaro que soy el cliente que recibió el servicio.</span>
                        </label>
                        @error('declaro') <div class="err">{{ $message }}</div> @enderror

                        <div style="margin-top:16px">
                            <button type="submit" class="btn" id="btn-enviar" disabled>
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
                                </svg>
                                <span id="btn-txt">Enviar respuesta</span>
                            </button>
                        </div>

                        <div class="google-box" id="google-box" hidden>
                            <p class="hint">
                                Opcional: inicia sesión con Google para verificar tu respuesta.
                                Solo usamos tu correo para validar que no responda el repartidor.
                            </p>
                            <button type="button" class="btn-google" id="btn-google">
                                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.7 1.22 9.2 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                                    <path fill="#FBBC05" d="M10.53 28.59A14.5 14.5 0 0 1 9.77 24c0-1.6.27-3.15.76-4.59l-7.98-6.19A23.94 23.94 0 0 0 0 24c0 3.88.93 7.54 2.56 10.78l7.97-6.19z"/>
                                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                                </svg>
                                <span>Continuar con Google</span>
                            </button>
                            <div class="signed" id="signed" hidden>
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                <span class="mail" id="signed-mail"></span>
                                <button type="button" class="btn-link" id="btn-logout">Salir</button>
                            </div>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <p class="foot">
        Distribuidora Sofía · Encuesta exclusiva para clientes.<br>
        Registramos dominio, IP y navegador solo para verificar la autenticidad de la respuesta.
    </p>
</div>

<script>
    (function () {
        var form = document.getElementById('form-encuesta');
        if (!form) return;

        var btn      = document.getElementById('btn-enviar');
        var btnTxt   = document.getElementById('btn-txt');
        var declaro  = document.getElementById('declaro');
        var comment  = document.getElementById('comment');
        var cnt      = document.getElementById('cnt');
        var radios   = form.querySelectorAll('input[name="score"]');

        function scoreElegido() {
            for (var i = 0; i < radios.length; i++) { if (radios[i].checked) return true; }
            return false;
        }
        function refrescar() {
            btn.disabled = !(scoreElegido() && declaro.checked);
        }
        for (var i = 0; i < radios.length; i++) radios[i].addEventListener('change', refrescar);
        declaro.addEventListener('change', refrescar);
        refrescar();

        if (comment && cnt) {
            comment.addEventListener('input', function () { cnt.textContent = comment.value.length; });
        }

        // Evita doble envío (doble tap / conexión lenta)
        form.addEventListener('submit', function () {
            setTimeout(function () {
                btn.disabled = true;
                btnTxt.textContent = 'Enviando…';
            }, 0);
        });
    })();
</script>

@if (! $error && ! $respuesta)
    {{-- Google Sign-In opcional: si Firebase no carga, el formulario sigue funcionando --}}
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js" defer></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-auth-compat.js" defer></script>
    <script defer>
        window.addEventListener('load', function () {
            if (typeof firebase === 'undefined') return; // sin red/bloqueado: se omite

            var box    = document.getElementById('google-box');
            var btnG   = document.getElementById('btn-google');
            var signed = document.getElementById('signed');
            var mailEl = document.getElementById('signed-mail');
            var input  = document.getElementById('email');

            try {
                if (!firebase.apps.length) {
                    firebase.initializeApp({
                        apiKey:     @json(config('services.firebase.api_key')),
                        authDomain: @json(config('services.firebase.auth_domain')),
                        projectId:  @json(config('services.firebase.project_id')),
                        appId:      @json(config('services.firebase.app_id'))
                    });
                }
            } catch (e) { return; }

            box.hidden = false;

            function pintar(user) {
                if (user && user.email) {
                    input.value = user.email;
                    mailEl.textContent = user.email;
                    signed.hidden = false;
                    btnG.hidden = true;
                } else {
                    input.value = '';
                    signed.hidden = true;
                    btnG.hidden = false;
                }
            }

            firebase.auth().onAuthStateChanged(pintar);

            btnG.addEventListener('click', function () {
                var provider = new firebase.auth.GoogleAuthProvider();
                firebase.auth().signInWithPopup(provider).catch(function (err) {
                    console.warn('Google Sign-In no disponible:', err && err.code);
                    box.hidden = true; // no bloqueamos la encuesta
                });
            });

            document.getElementById('btn-logout').addEventListener('click', function () {
                firebase.auth().signOut();
            });
        });
    </script>
@endif
</body>
</html>
