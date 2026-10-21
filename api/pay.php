<?php
declare(strict_types=1);
require __DIR__ . '/../private/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /#prix'); exit; }

$pack = (string)($_POST['pack'] ?? '');
$email = trim((string)($_POST['email'] ?? ''));
$guide = (($_POST['guide'] ?? '') === '1') ? 1 : 0;
$wa = preg_replace('/[^\d+]/', '', (string)($_POST['whatsapp'] ?? ''));
if (!isset(cfg()['packs'][$pack]) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($wa) < 8 || strlen($wa) > 16) {
    http_response_code(400);
    exit('Formulaire invalide. Revenez en arrière et vérifiez votre email et votre numéro WhatsApp.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$s = db()->prepare('SELECT COUNT(*) FROM orders WHERE ip=? AND created_at > ?'); $s->execute([$ip, time() - 3600]);
if ((int)$s->fetchColumn() >= 15) { http_response_code(429); exit('Trop de tentatives. Réessayez dans une heure.'); }

$o = ['id' => bin2hex(random_bytes(8)), 'token' => bin2hex(random_bytes(16)), 'pack' => $pack,
      'amount' => price_for($pack) + ($guide ? guide_cfg()['price'] : 0), 'guide' => $guide, 'email' => $email, 'whatsapp' => $wa];   // prix fixe cote serveur
db()->prepare('INSERT INTO orders (id,token,pack,amount,email,whatsapp,created_at,ip,guide) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute([$o['id'], $o['token'], $o['pack'], $o['amount'], $o['email'], $o['whatsapp'], time(), $ip, $guide]);

if (cfg()['provider'] === 'mock') { header('Location: /api/mock_checkout.php?o=' . $o['id']); exit; }

function jenc($v): string { return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); }
$k = cfg()['kadevpay'];
$cb = cfg()['site_url'] . '/merci.php?o=' . $o['id'] . '&t=' . $o['token'];
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$nonce' https://pay.kadev.ci https://*.paystack.co https://*.paystack.com; connect-src https://pay.kadev.ci https://*.paystack.co https://*.paystack.com; frame-src https://pay.kadev.ci https://*.paystack.co https://*.paystack.com; style-src 'unsafe-inline' https:; img-src https: data:; font-src https: data:; base-uri 'none'; form-action 'none'");
header('Cache-Control: no-store');
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Paiement | MJ7 Plugins</title>
<style>body{margin:0;background:#000;color:#fff;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:24px;text-align:center}
.btn{display:inline-block;margin-top:14px;padding:14px 26px;border-radius:999px;background:#ff6a1a;color:#fff;font-weight:700;border:0;font-size:16px;cursor:pointer;text-decoration:none}</style></head>
<body><div><h1>Paiement sécurisé</h1>
<p><?= esc(cfg()['packs'][$pack]['label'] . ($guide ? ' + ' . guide_cfg()['label'] : '')) ?> : <?= number_format($o['amount'], 0, '', ' ') ?> FCFA</p>
<button class="btn" id="go" type="button">Payer maintenant</button><br><a class="btn" style="background:transparent;border:1px solid #555" href="/#prix">Annuler</a></div>
<script nonce="<?= $nonce ?>" src="https://pay.kadev.ci/js/v1/kadev-pay.js"></script>
<script nonce="<?= $nonce ?>">
function pay(){
  KadevPay.checkout({
    public_key: <?= jenc($k['public_key']) ?>,
    amount: <?= (int)$o['amount'] ?>,
    email: <?= jenc($o['email']) ?>,
    phone: <?= jenc($o['whatsapp']) ?>,
    callback_url: <?= jenc($cb) ?>,
    metadata: { order_id: <?= jenc($o['id']) ?> },
    onSuccess: function (r) { location.href = <?= jenc($cb) ?> + "&ref=" + encodeURIComponent(r.reference); },
    onClose: function () {}
  });
}
document.getElementById("go").addEventListener("click", pay);
window.addEventListener("load", function(){ if (window.KadevPay) pay(); });
</script></body></html>
