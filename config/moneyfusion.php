<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MoneyFusion API URL
    |--------------------------------------------------------------------------
    |
    | L'URL de base de l'API MoneyFusion pour créer des paiements.
    | Par défaut: https://api.moneyfusion.net/api/create-payment
    |
    */
    'api_url' => env('MONEYFUSION_API_URL', 'https://api.moneyfusion.net/api/create-payment'),

    /*
    |--------------------------------------------------------------------------
    | MoneyFusion App Key
    |--------------------------------------------------------------------------
    |
    | Votre clé API au format: VotreAppName/VotreCléAPI
    | Obtenue depuis le dashboard MoneyFusion: https://moneyfusion.net/dashboard/fusionpay
    |
    */
    'app_key' => env('MONEYFUSION_APP_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | URL où MoneyFusion enverra les notifications de paiement en temps réel.
    | IMPORTANT: Doit être en HTTPS avec un domaine valide en production.
    | Pour tests locaux: Utilisez ngrok (ex: https://xxx.ngrok-free.app/api/moneyfusion/webhook)
    |
    */
    'webhook_url' => env('MONEYFUSION_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Return URL
    |--------------------------------------------------------------------------
    |
    | URL où l'utilisateur sera redirigé après le paiement.
    | IMPORTANT: Doit être en HTTPS avec un domaine valide en production.
    | Pour tests locaux: Utilisez ngrok (ex: https://xxx.ngrok-free.app/payment/callback)
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
];