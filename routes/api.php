<?php

use Illuminate\Support\Facades\Route;
use Simonet85\LaravelMoneyFusion\Http\Controllers\PaymentController;
use Simonet85\LaravelMoneyFusion\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| MoneyFusion API Routes
|--------------------------------------------------------------------------
|
| Ces routes sont chargées automatiquement par le Service Provider
| (désactivables via config('moneyfusion.routes')). Le préfixe "api/moneyfusion"
| est appliqué par le Service Provider.
|
*/

Route::prefix('moneyfusion')->name('moneyfusion.')->group(function () {

    // Endpoints de paiement : désactivez-les (routes.payment_endpoints = false) si votre
    // application crée ses paiements via MoneyFusion::createPayment(), ou protégez-les
    // avec routes.middleware (ex. ['web', 'auth']).
    if (config('moneyfusion.routes.payment_endpoints', true)) {
        Route::middleware(config('moneyfusion.routes.middleware', []))->group(function () {
            // Créer un nouveau paiement
            Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment'])
                ->name('payments.initiate');

            // Vérifier le statut d'un paiement
            Route::get('/payments/{token}/status', [PaymentController::class, 'checkStatus'])
                ->name('payments.status');

            // Annuler un paiement (optionnel)
            Route::post('/payments/{token}/cancel', [PaymentController::class, 'cancel'])
                ->name('payments.cancel');
        });
    }

    // Webhook MoneyFusion : sans authentification (MoneyFusion ne signe pas ses notifications).
    // Le contenu n'est jamais cru : le statut est relu auprès de l'API avant toute modification.
    // Ces routes n'utilisent pas le groupe "web" : pas de CSRF à exclure.
    Route::post('/webhook', [WebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhook');
});
