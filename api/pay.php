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
<link rel="preconnect" href="https://pay.kadev.ci"><link rel="dns-prefetch" href="https://js.paystack.co">
<style>body{margin:0;background:#000;color:#fff;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:24px;text-align:center}
.btn{display:inline-block;margin-top:14px;padding:14px 26px;border-radius:999px;background:#ff6a1a;color:#fff;font-weight:700;border:0;font-size:16px;cursor:pointer;text-decoration:none}
.btn[disabled]{opacity:.45;cursor:wait}.btn.o{background:transparent;border:1px solid #555}
#st{color:#bbb;min-height:24px;margin:16px 0 0}.sp{display:inline-block;width:16px;height:16px;border:2px solid #555;border-top-color:#ff6a1a;border-radius:50%;vertical-align:-3px;margin-right:8px;animation:r .8s linear infinite}
@keyframes r{to{transform:rotate(360deg)}}</style></head>
<body><div><h1>Paiement sécurisé</h1>
<p><?= esc(cfg()['packs'][$pack]['label'] . ($guide ? ' + ' . guide_cfg()['label'] : '')) ?> : <b><?= number_format($o['amount'], 0, '', ' ') ?> FCFA</b></p>
<p id="st"><span class="sp"></span>Chargement du paiement sécurisé…</p>
<button class="btn" id="go" type="button" disabled>Payer maintenant</button><br><a class="btn o" href="/#prix">Annuler</a></div>
<script nonce="<?= $nonce ?>" async src="https://pay.kadev.ci/js/v1/kadev-pay.js"></script>
<script nonce="<?= $nonce ?>">
(function () {
  var t0 = Date.now(), opened = false, btn = document.getElementById("go"), st = document.getElementById("st");
  function say(t, spin) { st.innerHTML = (spin ? '<span class="sp"></span>' : "") + t; }
  function ready() { return window.KadevPay && typeof window.KadevPay.checkout === "function"; }
  function open() {
    if (opened || !ready()) return;
    opened = true; btn.disabled = true; say("Ouverture du paiement…", true);
    try {
      window.KadevPay.checkout({
        public_key: <?= jenc($k['public_key']) ?>,
        amount: <?= (int)$o['amount'] ?>,
        email: <?= jenc($o['email']) ?>,
        phone: <?= jenc($o['whatsapp']) ?>,
        callback_url: <?= jenc($cb) ?>,
        metadata: { order_id: <?= jenc($o['id']) ?> },
        onSuccess: function (r) {
          say("Paiement reçu, vérification en cours…", true);
          location.href = <?= jenc($cb) ?> + "&ref=" + encodeURIComponent(r && r.reference || "");
        },
        onClose: function () { opened = false; btn.disabled = false; btn.textContent = "Réessayer le paiement"; say("Paiement non terminé. Vous pouvez réessayer."); }
      });
      say("Terminez le paiement dans la fenêtre qui s'est ouverte.");
    } catch (e) {
      opened = false; btn.disabled = false; btn.textContent = "Réessayer le paiement";
      say("Impossible d'ouvrir le paiement. Réessayez.");
      console.error("MJ7 pay: checkout a échoué", e);
    }
  }
  btn.addEventListener("click", open);
  var poll = setInterval(function () {
    if (ready()) {
      clearInterval(poll); btn.disabled = false;
      console.info("MJ7 pay: SDK Kadev Pay prêt en " + (Date.now() - t0) + " ms");
      open();
    } else if (Date.now() - t0 > 15000) {
      clearInterval(poll);
      say("Le service de paiement met trop de temps à répondre. Vérifiez votre connexion, puis revenez en arrière et réessayez.");
      console.error("MJ7 pay: kadev-pay.js non chargé après 15 s (réseau lent, bloqueur de publicités ou domaine bloqué ?)");
    }
  }, 200);
})();
</script></body></html>
