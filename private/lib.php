<?php
declare(strict_types=1);

function cfg(): array { static $c; return $c ??= require __DIR__ . '/config.php'; }

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('sqlite:' . cfg()['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
            id TEXT PRIMARY KEY, token TEXT NOT NULL, pack TEXT NOT NULL, amount INTEGER NOT NULL,
            email TEXT NOT NULL, whatsapp TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending",
            provider_ref TEXT, created_at INTEGER NOT NULL, paid_at INTEGER, downloads INTEGER NOT NULL DEFAULT 0,
            mailed INTEGER NOT NULL DEFAULT 0)');
    }
    return $pdo;
}

function price_for(string $pack): int {
    $p = cfg()['packs'][$pack];
    $promo = (new DateTimeImmutable('now', new DateTimeZone('Africa/Abidjan'))) < new DateTimeImmutable(cfg()['promo_until'], new DateTimeZone('Africa/Abidjan'));
    return $promo ? $p['promo'] : $p['normal'];
}

function order_get(string $id): ?array {
    $s = db()->prepare('SELECT * FROM orders WHERE id = ?'); $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

function order_for_buyer(string $id, string $token): ?array {
    $o = order_get($id);
    return ($o && hash_equals($o['token'], $token)) ? $o : null;
}

/** Interroge l'agregateur (serveur a serveur) et met la commande a jour. Seule source de verite. */
function order_refresh(array $o): array {
    if ($o['status'] !== 'pending') return $o;
    require_once __DIR__ . '/provider.php';
    $status = provider_verify($o);
    if ($status === 'paid') {
        $u = db()->prepare('UPDATE orders SET status="paid", paid_at=? WHERE id=? AND status="pending"');
        $u->execute([time(), $o['id']]);
        if ($u->rowCount() === 1) order_notify(order_get($o['id']));
    } elseif ($status === 'failed') {
        db()->prepare('UPDATE orders SET status="failed" WHERE id=? AND status="pending"')->execute([$o['id']]);
    }
    return order_get($o['id']);
}

function order_notify(array $o): void {
    $c = cfg(); $label = $c['packs'][$o['pack']]['label'];
    $link = $c['site_url'] . '/merci.php?o=' . $o['id'] . '&t=' . $o['token'];
    $h = "From: MJ7 Plugins <{$c['mail_from']}>\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($o['email'], 'Votre commande MJ7 : ' . $label,
        "Merci pour votre achat !\n\nPack : $label\nMontant : " . number_format($o['amount'], 0, '', ' ') .
        " FCFA\n\nTéléchargez vos installateurs ici :\n$link\n\nVotre clé de licence vous sera envoyée par email très bientôt.\n\nMJ7 Plugins",
        $h);
    @mail($c['admin_email'], 'NOUVELLE VENTE : ' . $label,
        "Pack : $label\nMontant : {$o['amount']} FCFA\nEmail : {$o['email']}\nWhatsApp : {$o['whatsapp']}\nCommande : {$o['id']}\n\n=> Envoyez la clé de licence à cet email.",
        $h);
}

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
