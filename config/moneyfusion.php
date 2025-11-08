<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MoneyFusion API URL
    |--------------------------------------------------------------------------
    |
    | L'URL de l'API MoneyFusion pour créer des paiements.
    |
    | NOUVELLE API (Recommandée):
    | Format: https://www.pay.moneyfusion.net/{AppName}/{ApiKey}/pay/
    | Exemple: https://www.pay.moneyfusion.net/MyApp/abc123def456/pay/
    |
    | ANCIENNE API (Toujours supportée):
    | https://api.moneyfusion.net/api/create-payment
    |
    | Pour obtenir votre URL personnalisée:
    | 1. Connectez-vous à https://moneyfusion.net/dashboard
    | 2. Allez dans "FusionPay" → "Paramètres"
    | 3. Copiez votre URL API personnalisée
    |
    */
    'api_url' => env('MONEYFUSION_API_URL', 'https://api.moneyfusion.net/api/create-payment'),

    /*
    |--------------------------------------------------------------------------
    | MoneyFusion App Key
    |--------------------------------------------------------------------------
    |
    | Votre clé API au format: AppName/ApiKey
    | Exemple: MyApp/abc123def456
    |
    | ⚠️ IMPORTANT:
    | - Gardez cette clé secrète et ne la commitez jamais dans Git
    | - Utilisez des clés différentes pour développement et production
    |
    | Obtenue depuis: https://moneyfusion.net/dashboard/fusionpay
    |
    */
    'app_key' => env('MONEYFUSION_APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | URL où MoneyFusion enverra les notifications de paiement en temps réel.
    |
    | IMPORTANT:
    | - DOIT être en HTTPS avec un domaine valide en production
    | - Pour le développement local, utilisez ngrok ou expose:
    |   Exemple: https://abc123.ngrok-free.app/api/moneyfusion/webhook
    |
    | Format recommandé: {APP_URL}/api/moneyfusion/webhook
    |
    */
    'webhook_url' => env('MONEYFUSION_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Return URL
    |--------------------------------------------------------------------------
    |
    | URL où l'utilisateur sera redirigé après le paiement.
    |
    | IMPORTANT:
    | - DOIT être en HTTPS avec un domaine valide en production
    | - Pour le développement local, utilisez ngrok ou expose:
    |   Exemple: https://abc123.ngrok-free.app/payment/callback
    |
    | Format recommandé: {APP_URL}/payment/callback
    |
    */
    'return_url' => env('MONEYFUSION_RETURN_URL'),

    /*
    |--------------------------------------------------------------------------
    | API Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout en secondes pour les requêtes HTTP vers l'API MoneyFusion.
    | Par défaut: 30 secondes
    |
    */
    'timeout' => env('MONEYFUSION_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Activer/désactiver le logging des transactions MoneyFusion.
    | Recommandé: true en développement, true en production
    |
    */
    'logging' => [
        'enabled' => env('MONEYFUSION_LOGGING_ENABLED', true),
        'channel' => env('MONEYFUSION_LOGGING_CHANNEL', 'daily'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration pour les tentatives de requêtes en cas d'échec.
    |
    */
    'retry' => [
        'enabled' => env('MONEYFUSION_RETRY_ENABLED', true),
        'times' => env('MONEYFUSION_RETRY_TIMES', 3),
        'sleep' => env('MONEYFUSION_RETRY_SLEEP', 100), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Activer/désactiver la vérification SSL pour les requêtes HTTP.
    | IMPORTANT: Mettre à false UNIQUEMENT en développement local.
    | TOUJOURS à true en production pour la sécurité!
    |
    | Utilisez false si vous rencontrez:
    | "cURL error 60: SSL certificate problem"
    |
    */
    'verify_ssl' => env('MONEYFUSION_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | MoneyFusion Check Payment URL
    |--------------------------------------------------------------------------
    |
    | URL pour vérifier le statut d'un paiement.
    | Le token sera automatiquement ajouté à la fin de cette URL.
    |
    | Exemple: https://www.pay.moneyfusion.net/paiementNotif
    | Résultat: https://www.pay.moneyfusion.net/paiementNotif/{token}
    |
    | Si non spécifié, l'URL sera construite automatiquement depuis api_url.
    |
    */
    'check_payment_url' => env('MONEYFUSION_CHECK_PAYMENT_URL', null),
];