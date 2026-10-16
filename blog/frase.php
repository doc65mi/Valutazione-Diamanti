<?php
declare(strict_types=1);
// Ricevitore webhook di Frase -> blog. Versione 2.1.30.
// Frase invia gli eventi content.approved / content.published firmati con HMAC-SHA256 (intestazione X-Webhook-Signature)
// usando il segreto restituito alla creazione del webhook (Admin > Blog > Collegamento Frase). Il sito poi legge
// l'articolo dall'API di Frase con la chiave salvata: il corpo del webhook serve solo a sapere quale articolo importare.
require_once __DIR__.'/../PHP di sistema/core.php';
require_once __DIR__.'/../PHP di sistema/frase.php';
security_headers(true);

/** La firma può arrivare come esadecimale o base64, con o senza prefisso "sha256=" o "t=...,v1=...". */
function frase_signature_ok(string $header, string $raw, string $secret): bool {
    if ($header === '' || $secret === '') return false;
    $candidates = [];
    foreach (preg_split('/[\s,]+/', $header) as $part) {
        if (str_contains($part, '=') && !str_ends_with($part, '=')) [$k, $v] = explode('=', $part, 2); else { $k = ''; $v = $part; }
        $candidates[] = [$k, $v];
    }
    $timestamp = '';
    foreach ($candidates as [$k, $v]) if ($k === 't') $timestamp = $v;
    $messages = [$raw]; if ($timestamp !== '') { $messages[] = $timestamp . '.' . $raw; $messages[] = $timestamp . $raw; }
    foreach ($messages as $message) {
        $bin = hash_hmac('sha256', $message, $secret, true);
        foreach ($candidates as [, $v]) {
            if (hash_equals(bin2hex($bin), strtolower($v)) || hash_equals(base64_encode($bin), $v)) return true;
        }
    }
    return false;
}
/** Cerca l'id dell'articolo nei punti in cui un webhook lo mette di solito. */
function frase_payload_id(array $p): string {
    foreach ([$p['data']['content_id'] ?? null, $p['data']['id'] ?? null, $p['data']['content']['id'] ?? null, $p['content_id'] ?? null,
        $p['content']['id'] ?? null, $p['payload']['content_id'] ?? null, $p['payload']['id'] ?? null, $p['resource_id'] ?? null, $p['object_id'] ?? null] as $v)
        if (is_scalar($v) && preg_match('/^[A-Za-z0-9_-]{1,100}$/D', (string)$v)) return (string)$v;
    return '';
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { header('Allow: POST'); fail('Usare POST.', 405); }
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') fail('HTTPS richiesto.', 400);
    rate('blog-frase-hook', 300, 3600);
    $raw = (string)file_get_contents('php://input', false, null, 0, 1000001);
    if (strlen($raw) > 1000000) fail('Contenuto troppo grande.', 413);
    $c = blog_config();
    $signature = (string)($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_X_FRASE_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '');
    if (!frase_signature_ok($signature, $raw, (string)($c['frase_webhook_secret'] ?? ''))) {
        rate('blog-frase-hook-fail', 20, 900);
        // Solo la forma della firma, mai il valore: serve a capire un formato diverso da quello atteso.
        frase_log('webhook rifiutato: firma ' . ($signature === '' ? 'assente' : 'non valida (' . strlen($signature) . ' caratteri, forma ' . preg_replace('/[A-Za-z0-9+\/]/', 'x', substr($signature, 0, 12)) . '…)'));
        fail('Firma non valida.', 401);
    }
    $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) fail('Oggetto JSON richiesto.');
    $event = (string)($payload['event'] ?? $payload['type'] ?? $payload['event_type'] ?? '');
    if ($event !== '' && !in_array($event, FRASE_EVENTS, true)) { frase_log('webhook ' . mb_substr($event, 0, 60) . ' ignorato'); respond(['ok' => true, 'ignored' => true]); }
    $id = frase_payload_id($payload);
    if ($id === '') {
        frase_log('webhook ' . ($event ?: 'senza evento') . ': id articolo non trovato, campi=' . mb_substr(implode(',', array_keys($payload)) . (is_array($payload['data'] ?? null) ? ' data.' . implode(',data.', array_keys($payload['data'])) : ''), 0, 300));
        fail('Identificativo articolo mancante.', 422);
    }
    set_time_limit(90);
    $result = frase_import($id, 'webhook ' . ($event ?: '?'));
    respond(['ok' => true, 'id' => $result['id'], 'status' => $result['status']], $result['updated'] ? 200 : 201);
} catch (JsonException | InvalidArgumentException $e) {
    frase_log('webhook: dati non validi (' . mb_substr($e->getMessage(), 0, 120) . ')');
    fail('Dati non validi.', 422);
} catch (RuntimeException $e) {
    frase_log('webhook: ' . mb_substr($e->getMessage(), 0, 200));
    fail('Importazione non riuscita.', 502);
} catch (Throwable $e) {
    error_log('Blog frase: ' . get_class($e));
    fail('Ricezione temporaneamente non disponibile.', 503);
}
