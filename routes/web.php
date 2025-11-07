<?php

use Illuminate\Support\Facades\Route;
use Simonet85\LaravelMoneyFusion\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| MoneyFusion Web Routes
|--------------------------------------------------------------------------
|
| Ces routes sont charg�es automatiquement par le Service Provider.
| Elles g�rent les redirections apr�s paiement et les pages de r�sultats.
|
*/

Route::prefix('payment')->name('payment.')->group(function () {

    // Callback après paiement (MoneyFusion redirige ici)
    Route::get('/callback', [PaymentController::class, 'callback'])
        ->name('callback');

    // Page de succès
    Route::get('/success/{token}', [PaymentController::class, 'success'])
        ->name('success');

    // Page d'échec
    Route::get('/failed', [PaymentController::class, 'failed'])
        ->name('failed');

    // Page en attente
    Route::get('/pending/{token}', [PaymentController::class, 'pending'])
        ->name('pending');
});
