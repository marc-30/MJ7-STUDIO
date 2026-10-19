<?php
declare(strict_types=1);
require __DIR__ . '/private/lib.php';
require __DIR__ . '/private/provider.php';

$id = (string)($_GET['o'] ?? ''); $tok = (string)($_GET['t'] ?? '');
$o = order_for_buyer($id, $tok);
if ($o && isset($_GET['ref'])) $o = order_bind_ref($o, (string)$_GET['ref']) ?? $o;
if ($o) $o = order_refresh($o);          // verification serveur a serveur aupres de l'agregateur
$status = $o['status'] ?? 'unknown';

header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; base-uri 'none'; form-action 'none'");
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
if ($status === 'pending') header('Refresh: 5');
$q = 'o=' . urlencode($id) . '&t=' . urlencode($tok);
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Votre commande | MJ7 Plugins</title>
<style>
body{margin:0;background:#000;color:#fff;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:24px}
.c{max-width:520px;width:100%;text-align:center}h1{font-size:28px;margin:0 0 12px}p{color:#bbb;line-height:1.5}
.btn{display:inline-block;margin:6px;padding:14px 26px;border-radius:999px;background:#ff6a1a;color:#fff;font-weight:700;text-decoration:none}
.btn.o{background:transparent;border:1px solid #555}a.l{color:#ff6a1a}
</style></head><body><div class="c">
<?php if ($status === 'paid'): ?>
  <h1>Paiement confirmé</h1>
  <p><?= esc(cfg()['packs'][$o['pack']]['label']) ?> : téléchargez votre installateur.</p>
  <a class="btn" href="/api/download.php?<?= $q ?>&os=win">Windows</a>
  <a class="btn o" href="/api/download.php?<?= $q ?>&os=mac">Mac</a>
  <p>Votre clé de licence est envoyée à <b><?= esc($o['email']) ?></b>. Gardez cette page en favori.</p>
<?php elseif ($status === 'pending'): ?>
  <h1>Vérification du paiement…</h1><p>Cette page se met à jour toute seule. Ne la fermez pas.</p>
<?php elseif ($status === 'failed'): ?>
  <h1>Paiement non abouti</h1><p>Aucun montant n'a été débité.</p><a class="btn" href="/#prix">Réessayer</a>
<?php else: ?>
  <h1>Commande introuvable</h1><p>Le lien est invalide. <a class="l" href="https://wa.me/2250758616073">Écrivez-nous sur WhatsApp</a>.</p>
<?php endif; ?>
</div></body></html>
