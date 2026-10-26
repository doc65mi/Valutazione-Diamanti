<?php
declare(strict_types=1);
// Invio diretto a Google (Indexing API, account di servizio) e Bing (Webmaster API, SubmitUrl) delle pagine e degli
// articoli nuovi o modificati, con un limite giornaliero per motore come il plugin WordPress Indexing-SC (2.1.42).
// Le credenziali restano in data/indexing.json (fuori da git, 0600) e non tornano mai al browser.
const INDEXING_CONFIG = ROOT . '/data/indexing.json';
const INDEXING_STATE = ROOT . '/data/indexing-state.json';
const INDEXING_TOKEN = ROOT . '/data/indexing-token.json';
const INDEXING_ENGINES = ['google' => 'Google', 'bing' => 'Bing'];
const INDEXING_MAX_TRIES = 3;

function indexing_config(): array {
    $v = @json_decode((string)@file_get_contents(INDEXING_CONFIG), true);
    return array_replace(['google_enabled' => false, 'google' => null, 'bing_enabled' => false, 'bing_key' => '', 'daily_limit' => 10], is_array($v) ? $v : []);
}
function indexing_state(): array {
    $v = @json_decode((string)@file_get_contents(INDEXING_STATE), true);
    $v = is_array($v) ? $v : [];
    foreach (['sent', 'failed', 'queue', 'count', 'pause', 'error'] as $k) $v[$k] = (array)($v[$k] ?? []);
    foreach (INDEXING_ENGINES as $e => $_) foreach (['sent', 'failed', 'queue'] as $k) $v[$k][$e] = (array)($v[$k][$e] ?? []);
    $v['log'] = (array)($v['log'] ?? []);
    return $v;
}
function indexing_save_state(array $state): void { atomic_write(INDEXING_STATE, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }
function indexing_active(array $c, string $engine): bool { return $engine === 'google' ? $c['google_enabled'] && is_array($c['google']) : $c['bing_enabled'] && $c['bing_key'] !== ''; }
function indexing_today(): string { return (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d'); }
function indexing_tomorrow(): int { return (new DateTimeImmutable('tomorrow 00:05', new DateTimeZone('Europe/Rome')))->getTimestamp(); }
function indexing_used(array $state, string $engine): int { return ($state['count']['day'] ?? '') === indexing_today() ? (int)($state['count'][$engine] ?? 0) : 0; }

/** Indirizzi da tenere indicizzati con una "versione": testo della pagina per le pagine, data di modifica per gli articoli. */
function indexing_entries(): array {
    $base = seo_base(); $out = [];
    if (menu_enabled('blog')) {
        require_once __DIR__ . '/blog.php';
        foreach (blog_seo_entries() as $post) $out[blog_article_url(['base_url' => $base . '/blog'], $post)] = (string)$post['updated_at'];
    }
    $hidden = hidden_page_slugs();
    foreach (PAGE_SLUGS as $slug) if ($slug !== 'grazie' && !in_array($slug, $hidden, true)) foreach (['it', 'en'] as $lang)
        // 2.1.60: nome del file pubblicato (page_name), non lo slug: le pagine EN hanno indirizzi inglesi dal 2.1.52 e i vecchi sono rimandi noindex.
        $out[$base . '/' . $lang . '/' . page_name($slug, $lang) . '.html'] = substr(hash('sha256', json_encode(content($slug, $lang), JSON_UNESCAPED_UNICODE)), 0, 16);
    return $out;
}

/** Mette in coda gli indirizzi nuovi o cambiati dall'ultimo invio riuscito; a Google segnala anche quelli tolti dal sito. */
function indexing_sync(?array $entries = null): void {
    $c = indexing_config(); if (!indexing_active($c, 'google') && !indexing_active($c, 'bing')) return;
    $entries ??= indexing_entries(); $state = indexing_state(); $changed = false;
    foreach (INDEXING_ENGINES as $engine => $_) {
        if (!indexing_active($c, $engine)) continue;
        $sent = $state['sent'][$engine]; $failed = $state['failed'][$engine]; $queue = [];
        foreach ($state['queue'][$engine] as $item) {
            $url = $item['url'];
            if ($item['type'] === 'URL_DELETED' ? !isset($entries[$url]) : ($entries[$url] ?? null) === $item['ver']) $queue[$url] = $item;
        }
        foreach ($entries as $url => $ver) {
            if (($sent[$url] ?? null) === $ver || ($failed[$url] ?? null) === $ver || isset($queue[$url])) continue;
            $queue[$url] = ['url' => $url, 'ver' => $ver, 'type' => 'URL_UPDATED', 'tries' => 0];
        }
        foreach ($sent as $url => $ver) if (!isset($entries[$url])) {
            // Solo gli articoli tolti diventano introvabili; le pagine spente dal menu restano raggiungibili.
            if ($engine === 'google' && str_contains($url, '/blog/articolo/')) { if (!isset($queue[$url])) $queue[$url] = ['url' => $url, 'ver' => null, 'type' => 'URL_DELETED', 'tries' => 0]; }
            else unset($state['sent'][$engine][$url]);
        }
        $queue = array_values($queue);
        if ($queue !== $state['queue'][$engine] || $sent !== $state['sent'][$engine]) { $state['queue'][$engine] = $queue; $changed = true; }
    }
    if ($changed) indexing_save_state($state);
}

function indexing_http(string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    return [$http, $response === false ? '' : (string)$response, $error];
}
function indexing_b64(string $v): string { return rtrim(strtr(base64_encode($v), '+/', '-_'), '='); }

/** Token OAuth dell'account di servizio (JWT firmato RS256), tenuto per 50 minuti. */
function indexing_google_token(array $sa, bool $fresh = false): string {
    $cache = @json_decode((string)@file_get_contents(INDEXING_TOKEN), true);
    if (!$fresh && is_array($cache) && ($cache['email'] ?? '') === $sa['client_email'] && (int)($cache['exp'] ?? 0) > time() + 60) return (string)$cache['token'];
    $now = time();
    $jwt = indexing_b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . indexing_b64(json_encode(['iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/indexing', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600], JSON_UNESCAPED_SLASHES));
    $key = openssl_pkey_get_private($sa['private_key']);
    if (!$key || !openssl_sign($jwt, $signature, $key, OPENSSL_ALGO_SHA256)) throw new RuntimeException('La chiave privata del file JSON non è leggibile: scarica una nuova chiave JSON in Google Cloud.');
    [$http, $body, $error] = indexing_http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . indexing_b64($signature)]));
    $v = json_decode($body, true);
    if ($http !== 200 || !is_string($v['access_token'] ?? null)) throw new RuntimeException('Google non ha accettato l’account di servizio' . ($error !== '' ? ' (' . $error . ')' : ' (codice ' . $http . ': ' . mb_substr((string)($v['error_description'] ?? $v['error'] ?? 'risposta non valida'), 0, 160) . ')') . '. Controlla di aver caricato il file JSON giusto e che la chiave non sia stata eliminata.');
    atomic_write(INDEXING_TOKEN, json_encode(['email' => $sa['client_email'], 'token' => $v['access_token'], 'exp' => $now + min(3000, (int)($v['expires_in'] ?? 3600))]));
    return $v['access_token'];
}

/** Esito di un invio: ok, quota (finita per oggi), config (credenziali o permessi da sistemare) oppure error (riprovabile). */
function indexing_send(string $engine, array $c, array $item, string $token = ''): array {
    if ($engine === 'google') {
        [$http, $body, $error] = indexing_http('POST', 'https://indexing.googleapis.com/v3/urlNotifications:publish', ['Content-Type: application/json', 'Authorization: Bearer ' . $token], json_encode(['url' => $item['url'], 'type' => $item['type']], JSON_UNESCAPED_SLASHES));
        $message = mb_substr((string)(json_decode($body, true)['error']['message'] ?? $error), 0, 200);
        if ($http === 200) return ['ok', $item['type'] === 'URL_DELETED' ? 'rimozione segnalata' : 'accettato'];
        if ($http === 429) return ['quota', 'quota giornaliera di Google esaurita'];
        if ($http === 401 || $http === 403) return ['config', 'Google ha rifiutato l’invio (' . $http . '): ' . ($message ?: 'permesso negato') . '. ' . indexing_google_owner_hint($c, $item['url'])];
        if ($item['type'] === 'URL_DELETED' && $http === 404) return ['ok', 'già assente per Google'];
        return ['error', 'codice ' . $http . ($message !== '' ? ': ' . $message : '')];
    }
    [$http, $body, $error] = indexing_http('POST', 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl?apikey=' . rawurlencode($c['bing_key']), ['Content-Type: application/json; charset=utf-8'], json_encode(['siteUrl' => seo_base(), 'url' => $item['url']], JSON_UNESCAPED_SLASHES));
    $message = mb_substr((string)(json_decode($body, true)['Message'] ?? $error), 0, 200);
    if ($http === 200) return ['ok', 'accettato'];
    if (stripos($message, 'quota') !== false || $http === 429) return ['quota', 'quota giornaliera di Bing esaurita'];
    if ($http === 401 || $http === 403 || preg_match('/InvalidApiKey|NotAuthorized|not verified|UserBlocked/i', $body)) return ['config', 'Bing ha rifiutato l’invio (' . $http . '): ' . ($message ?: 'chiave API o sito non autorizzati') . '. Controlla la chiave API e che il sito sia verificato in Bing Webmaster Tools con lo stesso indirizzo (' . seo_base() . ').'];
    return ['error', 'codice ' . $http . ($message !== '' ? ': ' . $message : '')];
}

/** Cosa sistemare in Search Console quando Google nega il permesso: ruolo Proprietario sulla proprietà che contiene l'indirizzo inviato (2.1.60). */
function indexing_google_owner_hint(array $c, string $url): string {
    $host = (string)parse_url($url, PHP_URL_HOST); $root = preg_replace('/^www\./i', '', $host);
    $prefix = parse_url($url, PHP_URL_SCHEME) . '://' . $host . '/';
    return 'In Search Console apri la proprietà «' . $root . '» (tipo Dominio) oppure «' . $prefix . '» (prefisso URL, con ' . (str_starts_with($host, 'www.') ? 'www' : 'senza www') . ' e https) → Impostazioni → Utenti e autorizzazioni e dai a ' . ($c['google']['client_email'] ?? 'l’email dell’account di servizio') . ' l’autorizzazione «Proprietario»: «Completo» o «Limitato» non bastano, e la proprietà per ' . (str_starts_with($host, 'www.') ? 'il sito senza www' : 'www') . ' non vale. Se il ruolo è già Proprietario, controlla che la Web Search Indexing API sia abilitata nello stesso progetto Google Cloud del file JSON.';
}

function indexing_log(array &$state, string $engine, string $url, bool $ok, string $message): void {
    array_unshift($state['log'], ['at' => gmdate('c'), 'engine' => $engine, 'url' => $url, 'ok' => $ok, 'msg' => $message]);
    $state['log'] = array_slice($state['log'], 0, 60);
}

/** Svuota le code fino al limite del giorno. $manual: lanciato dal pulsante, riprova anche dopo un errore di configurazione. */
function indexing_run(bool $manual = false): array {
    $c = indexing_config(); $summary = [];
    if (!function_exists('curl_init')) throw new RuntimeException('Serve l’estensione PHP cURL.');
    $lock = fopen(ROOT . '/data/indexing.lock', 'c'); if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return ['busy' => true];
    try {
        indexing_sync(); $state = indexing_state();
        if (($state['count']['day'] ?? '') !== indexing_today()) $state['count'] = ['day' => indexing_today()];
        foreach (INDEXING_ENGINES as $engine => $label) {
            if (!indexing_active($c, $engine)) continue;
            $sent = 0; $pause = $state['pause'][$engine] ?? null;
            if (is_array($pause) && $pause['until'] > time() && ($pause['why'] === 'quota' || !$manual)) { $summary[$engine] = ['sent' => 0, 'note' => $pause['why'] === 'quota' ? 'quota di oggi esaurita' : 'in pausa per un errore di configurazione']; continue; }
            unset($state['pause'][$engine]);
            try { $token = $engine === 'google' && $state['queue'][$engine] ? indexing_google_token($c['google']) : ''; }
            catch (RuntimeException $e) { $state['error'][$engine] = $e->getMessage(); $state['pause'][$engine] = ['until' => time() + 21600, 'why' => 'config']; $summary[$engine] = ['sent' => 0, 'note' => $e->getMessage()]; continue; }
            $tried = [];
            while ($state['queue'][$engine] && indexing_used($state, $engine) < $c['daily_limit']) {
                $item = $state['queue'][$engine][0];
                if (isset($tried[$item['url']])) break;
                $tried[$item['url']] = true;
                [$result, $message] = indexing_send($engine, $c, $item, $token);
                $state['count'][$engine] = indexing_used($state, $engine) + 1;
                if ($result === 'ok') {
                    array_shift($state['queue'][$engine]); unset($state['failed'][$engine][$item['url']], $state['error'][$engine]);
                    if ($item['type'] === 'URL_DELETED') unset($state['sent'][$engine][$item['url']]); else $state['sent'][$engine][$item['url']] = $item['ver'];
                    indexing_log($state, $engine, $item['url'], true, $message); $sent++; continue;
                }
                if ($result === 'quota') { $state['pause'][$engine] = ['until' => indexing_tomorrow(), 'why' => 'quota']; indexing_log($state, $engine, $item['url'], false, $message . ', riprovo domani'); break; }
                if ($result === 'config') { $state['error'][$engine] = $message; $state['pause'][$engine] = ['until' => time() + 21600, 'why' => 'config']; indexing_log($state, $engine, $item['url'], false, $message); break; }
                array_shift($state['queue'][$engine]); $item['tries']++;
                if ($item['tries'] >= INDEXING_MAX_TRIES) { if ($item['ver'] !== null) $state['failed'][$engine][$item['url']] = $item['ver']; indexing_log($state, $engine, $item['url'], false, $message . ' · abbandonato dopo ' . INDEXING_MAX_TRIES . ' tentativi'); }
                else { $state['queue'][$engine][] = $item; indexing_log($state, $engine, $item['url'], false, $message . ' · tentativo ' . $item['tries'] . ' di ' . INDEXING_MAX_TRIES); }
            }
            $summary[$engine] = ['sent' => $sent, 'left' => count($state['queue'][$engine])];
            if ($state['queue'][$engine] && indexing_used($state, $engine) >= $c['daily_limit']) $summary[$engine]['note'] = $sent . ' inviati, limite di oggi raggiunto (' . $c['daily_limit'] . '), ' . count($state['queue'][$engine]) . ' in coda per domani';
        }
        $state['last_run'] = time();
        indexing_save_state($state);
        return $summary;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

/** Invio automatico: al massimo ogni 15 minuti, dopo aver chiuso la risposta al visitatore (PHP-FPM) o per un amministratore. */
function indexing_tick(bool $admin = false): void {
    if (!is_file(INDEXING_CONFIG) || !function_exists('curl_init') || (!function_exists('fastcgi_finish_request') && !$admin)) return;
    $state = indexing_state(); if (time() - (int)($state['last_run'] ?? 0) < 900) return;
    $c = indexing_config(); $todo = false;
    foreach (INDEXING_ENGINES as $engine => $_) {
        $pause = $state['pause'][$engine] ?? null;
        if (indexing_active($c, $engine) && $state['queue'][$engine] && indexing_used($state, $engine) < $c['daily_limit'] && !(is_array($pause) && $pause['until'] > time())) $todo = true;
    }
    if (!$todo) return;
    register_shutdown_function(function (): void {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        try { indexing_run(); } catch (Throwable $e) { error_log('Invio a Google/Bing non riuscito: ' . $e->getMessage()); }
    });
}

/** Stato per il pannello: mai la chiave privata o la chiave API. */
function indexing_public(): array {
    $c = indexing_config(); $state = indexing_state(); $out = ['daily_limit' => $c['daily_limit'], 'google_enabled' => $c['google_enabled'], 'google_set' => is_array($c['google']), 'google_email' => is_array($c['google']) ? $c['google']['client_email'] : '', 'bing_enabled' => $c['bing_enabled'], 'bing_set' => $c['bing_key'] !== '', 'site_url' => seo_base(), 'engines' => [], 'log' => array_slice($state['log'], 0, 30)];
    foreach (INDEXING_ENGINES as $engine => $label) {
        $pause = $state['pause'][$engine] ?? null;
        $out['engines'][$engine] = ['label' => $label, 'active' => indexing_active($c, $engine), 'queued' => count($state['queue'][$engine]), 'next' => array_map(fn($i) => ($i['type'] === 'URL_DELETED' ? '(rimosso) ' : '') . $i['url'], array_slice($state['queue'][$engine], 0, 10)), 'today' => indexing_used($state, $engine), 'sent_total' => count($state['sent'][$engine]), 'failed' => count($state['failed'][$engine]), 'paused_until' => is_array($pause) && $pause['until'] > time() ? gmdate('c', (int)$pause['until']) : null, 'pause_why' => is_array($pause) ? $pause['why'] : null, 'error' => $state['error'][$engine] ?? null];
    }
    return $out;
}

function indexing_save(array $input): void {
    $c = indexing_config(); $googleChanged = false;
    foreach (['google_enabled', 'bing_enabled', 'google_clear', 'bing_clear'] as $k) if (!is_bool($input[$k] ?? false)) fail('Selezione non valida: ' . $k);
    $limit = $input['daily_limit'] ?? 10; if (!is_int($limit) || $limit < 1 || $limit > 200) fail('Limite giornaliero: da 1 a 200 indirizzi per motore.');
    $json = $input['google_json'] ?? ''; if (!is_string($json) || strlen($json) > 20000) fail('File JSON di Google non valido.');
    if (trim($json) !== '') {
        $sa = json_decode(trim($json), true);
        if (!is_array($sa) || ($sa['type'] ?? '') !== 'service_account' || !filter_var($sa['client_email'] ?? '', FILTER_VALIDATE_EMAIL) || !is_string($sa['private_key'] ?? null)) fail('Il file non è la chiave JSON di un account di servizio Google: in Google Cloud scegli Account di servizio → Chiavi → Aggiungi chiave → JSON.');
        if (!openssl_pkey_get_private($sa['private_key'])) fail('La chiave privata nel file JSON non è leggibile: scarica una nuova chiave JSON.');
        $c['google'] = ['client_email' => $sa['client_email'], 'private_key' => $sa['private_key'], 'project_id' => (string)($sa['project_id'] ?? '')]; $googleChanged = true;
    }
    if ($input['google_clear'] ?? false) { $c['google'] = null; $googleChanged = true; }
    $key = $input['bing_key'] ?? ''; if (!is_string($key) || strlen($key) > 200) fail('Chiave API di Bing non valida.');
    $key = trim($key); if ($key !== '' && !preg_match('/^[A-Za-z0-9]{16,64}$/', $key)) fail('Chiave API di Bing non valida: incolla solo la chiave (32 caratteri, lettere e cifre).');
    if ($key !== '') $c['bing_key'] = $key;
    if ($input['bing_clear'] ?? false) $c['bing_key'] = '';
    $c['google_enabled'] = $input['google_enabled'] ?? false; $c['bing_enabled'] = $input['bing_enabled'] ?? false; $c['daily_limit'] = $limit;
    if ($c['google_enabled'] && !is_array($c['google'])) fail('Per attivare Google carica prima il file JSON dell’account di servizio.');
    if ($c['bing_enabled'] && $c['bing_key'] === '') fail('Per attivare Bing incolla prima la chiave API.');
    atomic_write(INDEXING_CONFIG, json_encode($c, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    if ($googleChanged) @unlink(INDEXING_TOKEN);
    // Credenziali nuove: le pause per errore di configurazione ripartono subito.
    $state = indexing_state(); foreach (INDEXING_ENGINES as $engine => $_) if (($state['pause'][$engine]['why'] ?? '') === 'config') unset($state['pause'][$engine], $state['error'][$engine]);
    indexing_save_state($state); indexing_sync();
}

/** Prova le credenziali senza consumare la quota: token di Google e quota residua dichiarata da Bing. */
function indexing_test(): array {
    $c = indexing_config(); $out = [];
    if (is_array($c['google'])) { try { indexing_google_token($c['google'], true); $out['google'] = ['ok' => true, 'msg' => 'Account di servizio accettato da Google. Il permesso sul sito si verifica al primo invio: serve l’email ' . $c['google']['client_email'] . ' come Proprietario della proprietà Search Console che contiene ' . seo_base() . '/ (Dominio ' . preg_replace('/^www\./i', '', (string)parse_url(seo_base(), PHP_URL_HOST)) . ' oppure prefisso URL ' . seo_base() . '/).']; } catch (RuntimeException $e) { $out['google'] = ['ok' => false, 'msg' => $e->getMessage()]; } }
    if ($c['bing_key'] !== '') {
        [$http, $body, $error] = indexing_http('GET', 'https://ssl.bing.com/webmaster/api.svc/json/GetUrlSubmissionQuota?siteUrl=' . rawurlencode(seo_base()) . '&apikey=' . rawurlencode($c['bing_key']));
        $v = json_decode($body, true);
        $out['bing'] = $http === 200 && is_array($v['d'] ?? null) ? ['ok' => true, 'msg' => 'Chiave accettata da Bing: quota residua di oggi ' . (int)($v['d']['DailyQuota'] ?? 0) . ' indirizzi, del mese ' . (int)($v['d']['MonthlyQuota'] ?? 0) . '.'] : ['ok' => false, 'msg' => 'Bing ha risposto ' . ($error !== '' ? $error : 'con codice ' . $http . (($v['Message'] ?? '') !== '' ? ': ' . mb_substr((string)$v['Message'], 0, 200) : '')) . '. Controlla la chiave API e che il sito ' . seo_base() . ' sia verificato in Bing Webmaster Tools.'];
    }
    if (!$out) fail('Carica il file JSON di Google o la chiave API di Bing, salva e poi prova.');
    return $out;
}
