<?php
declare(strict_types=1);
require __DIR__ . '/../private/lib.php';

$o = order_for_buyer((string)($_GET['o'] ?? ''), (string)($_GET['t'] ?? ''));
$os = (string)($_GET['os'] ?? '');
if ($o) $o = order_refresh($o);
if (!$o || $o['status'] !== 'paid' || !in_array($os, ['win', 'mac'], true)) { http_response_code(403); exit('Accès refusé.'); }
if ((int)$o['downloads'] >= cfg()['max_downloads']) { http_response_code(429); exit('Limite de téléchargements atteinte. Contactez-nous sur WhatsApp.'); }

$name = cfg()['packs'][$o['pack']]['files'][$os];         // seul le pack paye est accessible
$path = cfg()['files_dir'] . '/' . $name;
if (!is_file($path)) { http_response_code(404); exit('Fichier indisponible. Contactez-nous sur WhatsApp.'); }

db()->prepare('UPDATE orders SET downloads = downloads + 1 WHERE id=?')->execute([$o['id']]);
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
