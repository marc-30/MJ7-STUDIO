<?php
declare(strict_types=1);

ini_set('display_errors', '0');          // ne jamais afficher d'erreurs PHP au visiteur
ini_set('log_errors', '1');

function cfg(): array {
    static $c;
    if ($c === null) {
        $c = require __DIR__ . '/config.php';
        // Garde-fou : le faux agregateur permet de "payer" sans payer. Interdit hors machine locale.
        if ($c['provider'] === 'mock' && !in_array(parse_url($c['site_url'], PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
            http_response_code(500);
            error_log('MJ7: provider=mock refuse hors localhost');
            exit('Configuration invalide.');
        }
    }
    return $c;
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $new = !is_file(cfg()['db_path']);
        $pdo = new PDO('sqlite:' . cfg()['db_path']);
        if ($new) @chmod(cfg()['db_path'], 0600);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
            id TEXT PRIMARY KEY, token TEXT NOT NULL, pack TEXT NOT NULL, amount INTEGER NOT NULL,
            email TEXT NOT NULL, whatsapp TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending",
            provider_ref TEXT, created_at INTEGER NOT NULL, paid_at INTEGER, downloads INTEGER NOT NULL DEFAULT 0,
            ip TEXT, guide INTEGER NOT NULL DEFAULT 0)');
        // Migration : base creee avant l'ajout du guide
        $cols = array_column($pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('guide', $cols, true)) $pdo->exec('ALTER TABLE orders ADD COLUMN guide INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS orders_ref ON orders(provider_ref)');   // 1 reference = 1 commande
    }
    return $pdo;
}

/** Reglages du guide PDF (valeurs par defaut : marche meme si config.php ne les contient pas). */
function guide_cfg(): array {
    return (cfg()['guide'] ?? []) + ['label' => 'Guide Vocal Chain', 'price' => 7000,
        'file' => 'MJ7-Guide-Vocal-Chain.pdf', 'dir' => __DIR__ . '/guide'];
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
    $c = cfg(); $label = $c['packs'][$o['pack']]['label'] . ($o['guide'] ? ' + ' . guide_cfg()['label'] : '');
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
