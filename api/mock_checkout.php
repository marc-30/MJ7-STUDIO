<?php
declare(strict_types=1);
// Faux ecran de paiement pour tests locaux. Inactif en production.
require __DIR__ . '/../private/lib.php';
if (cfg()['provider'] !== 'mock') { http_response_code(404); exit; }
$o = order_get((string)($_GET['o'] ?? '')) or exit('Commande inconnue');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('UPDATE orders SET provider_ref=? WHERE id=?')->execute([(isset($_POST['ok']) ? 'mock-paid-' : 'mock-failed-') . $o['id'], $o['id']]);
    header('Location: /merci.php?o=' . $o['id'] . '&t=' . $o['token']); exit;
}
?><!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:420px;margin:60px auto">
<h2>Agrégateur (SIMULATION)</h2><p><?= esc($o['pack']) ?> : <?= (int)$o['amount'] ?> FCFA</p>
<form method="post"><button name="ok" value="1">Simuler un paiement réussi</button> <button name="ko" value="1">Simuler un échec</button></form>
