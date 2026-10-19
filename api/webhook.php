<?php
declare(strict_types=1);
require __DIR__ . '/../private/lib.php';
require __DIR__ . '/../private/provider.php';

// Le webhook sert seulement a declencher une verification : la decision vient toujours de l'API de l'agregateur.
$hook = provider_webhook(file_get_contents('php://input'), $_SERVER);
if ($hook === null) { http_response_code(401); exit('signature invalide'); }
[$ref, $orderId] = $hook;
if ($orderId && ($o = order_get((string)$orderId)) && $ref !== '') {
    $o = order_bind_ref($o, $ref);
    if ($o) order_refresh($o);
}
http_response_code(200);
echo 'ok';
