<?php
declare(strict_types=1);
/**
 * ADAPTATEUR KADEV PAY (https://pay.kadev.ci/developer-documentation/)
 * - Paiement : pop-up JavaScript KadevPay.checkout (voir api/pay.php)
 * - Verification : GET /transactions/verify/{reference}  (Bearer cle secrete)
 * - Webhook : signature HMAC-SHA512 du corps brut dans l'en-tete X-KADEVPAY-SIGNATURE
 */

/** Associe la reference de transaction de l'agregateur a la commande (une reference = une seule commande). */
function order_bind_ref(array $o, string $ref): ?array {
    if (!preg_match('/^[A-Za-z0-9_-]{4,64}$/', $ref)) return null;
    if ($o['provider_ref'] !== null && $o['provider_ref'] !== '') return $o['provider_ref'] === $ref ? $o : null;
    $s = db()->prepare('SELECT 1 FROM orders WHERE provider_ref=?'); $s->execute([$ref]);
    if ($s->fetch()) return null;                      // reference deja utilisee par une autre commande
    db()->prepare('UPDATE orders SET provider_ref=? WHERE id=? AND provider_ref IS NULL')->execute([$ref, $o['id']]);
    return order_get($o['id']);
}

/** Etat reel du paiement, demande a l'agregateur. Retourne 'paid' | 'pending' | 'failed'. */
function provider_verify(array $o): string {
    $c = cfg();
    if ($c['provider'] === 'mock') {
        return $o['provider_ref'] === 'mock-paid' ? 'paid' : ($o['provider_ref'] === 'mock-failed' ? 'failed' : 'pending');
    }
    if (!$o['provider_ref']) return 'pending';         // le client n'a pas encore paye
    $k = $c['kadevpay'];
    $res = http_json('GET', $k['base_url'] . '/transactions/verify/' . rawurlencode($o['provider_ref']),
        ['Authorization: Bearer ' . $k['secret_key']]);
    $d = $res['data'] ?? $res;
    $st = strtolower((string)($d['status'] ?? ''));
    if ($st === 'paid') {
        if ((int)($d['amount'] ?? -1) !== (int)$o['amount']) return 'pending';           // montant different : refuse
        $meta = $d['metadata'] ?? [];
        if (isset($meta['order_id']) && $meta['order_id'] !== $o['id']) return 'pending'; // transaction d'une autre commande
        if (isset($d['email']) && strcasecmp((string)$d['email'], $o['email']) !== 0) return 'pending';
        return 'paid';
    }
    return in_array($st, ['failed', 'cancelled', 'canceled', 'expired'], true) ? 'failed' : 'pending';
}

/** Controle la signature du webhook et retourne [reference, order_id|null], ou null si invalide. */
function provider_webhook(string $raw, array $server): ?array {
    $c = cfg();
    $ev = json_decode($raw, true);
    if (!is_array($ev)) return null;
    if ($c['provider'] === 'mock') return [(string)($ev['reference'] ?? ''), $ev['order_id'] ?? null];
    $sig = $server['HTTP_X_KADEVPAY_SIGNATURE'] ?? '';
    if (!hash_equals(hash_hmac('sha512', $raw, $c['kadevpay']['webhook_secret']), $sig)) return null;
    $d = $ev['data'] ?? [];
    return [(string)($d['reference'] ?? ''), $d['metadata']['order_id'] ?? null];
}

function http_json(string $method, string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers)]);
    $out = curl_exec($ch);
    curl_close($ch);
    return $out === false ? [] : (json_decode($out, true) ?: []);
}
