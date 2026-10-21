<?php
// CONFIGURATION : ne jamais publier ce fichier. Il est dans private/ (acces web interdit).
return [
    // URL publique du site, sans slash final
    'site_url'    => 'http://localhost:8000',

    // 'mock' = faux agregateur pour tester en local. Passer a 'kadevpay' en production.
    'provider'    => 'mock',

    'kadevpay' => [
        'base_url'       => 'https://pay.kadev.ci/api/v1',
        'public_key'     => 'kdvp_test_CHANGER',   // cle publique (affichee dans la page de paiement)
        'secret_key'     => 'kdvs_test_CHANGER',   // cle secrete (serveur uniquement)
        'webhook_secret' => 'CHANGER',             // secret webhook du Dashboard
    ],

    // Email qui recoit une alerte a chaque paiement (pour envoyer la cle de licence)
    'admin_email' => 'adeniangelot@gmail.com',
    // Expediteur des emails clients (une adresse de votre domaine, sinon les mails finissent en spam)
    'mail_from'   => 'contact@mj7plugins.com',

    // Guide PDF vendu en option (deposer le fichier dans private/guide/)
    'guide' => ['label' => 'Guide Vocal Chain', 'price' => 7000, 'file' => 'MJ7-Guide-Vocal-Chain.pdf', 'dir' => __DIR__ . '/guide'],

    'db_path'     => __DIR__ . '/data/orders.sqlite',
    'files_dir'   => __DIR__ . '/installateur',
    'max_downloads' => 20,

    // Prix en FCFA : [prix lancement, prix normal]. Bascule le 1er novembre 2026.
    'promo_until' => '2026-11-01',
    'packs' => [
        'vocal' => ['label' => 'MJ7 Vocal Pack',    'promo' => 100,    'normal' => 100,   
                    'files' => ['win' => 'MJ7-Vocal-Pack-Windows-Installateur.exe',
                                'mac' => 'MJ7-Vocal-Pack-Mac-Installateur.pkg']],
        'suite' => ['label' => 'MJ7 Suite complète', 'promo' => 130000, 'normal' => 150000,
                    'files' => ['win' => 'MJ7-Suite-Windows-Installateur.exe',
                                'mac' => 'MJ7-Suite-Mac-Installateur.pkg']],
    ],
];
