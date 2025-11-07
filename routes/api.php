<?php

use Illuminate\Support\Facades\Route;
use Simonet85\LaravelMoneyFusion\Http\Controllers\PaymentController;
use Simonet85\LaravelMoneyFusion\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| MoneyFusion API Routes
|--------------------------------------------------------------------------
|
| Ces routes sont chargées automatiquement par le Service Provider.
| Le préfixe "api/moneyfusion" est appliqué automatiquement.
|
*/

// Routes de paiement (nécessitent l'authentification si middleware activé)
Route::prefix('moneyfusion')->name('moneyfusion.')->group(function () {

    // Créer un nouveau paiement
    Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment'])
        ->name('payments.initiate');

    // Vérifier le statut d'un paiement
    Route::get('/payments/{token}/status', [PaymentController::class, 'checkStatus'])
        ->name('payments.status');

    // Annuler un paiement (optionnel)
    Route::post('/payments/{token}/cancel', [PaymentController::class, 'cancel'])
        ->name('payments.cancel');

    // Webhook MoneyFusion (SANS authentification, SANS CSRF)
    // Note: Le CSRF est désactivé dans VerifyCsrfToken middleware
    Route::post('/webhook', [WebhookController::class, 'handle'])
        ->name('webhook')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
});
