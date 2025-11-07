<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('moneyfusion_payments', function (Blueprint $table) {
            $table->id();
            
            // Identifiants
            $table->string('token_pay', 100)->unique()->comment('Token unique du paiement MoneyFusion');
            $table->unsignedBigInteger('user_id')->nullable()->comment('ID de l\'utilisateur');
            $table->unsignedBigInteger('order_id')->nullable()->comment('ID de la commande');
            
            // Informations client
            $table->string('numero_send', 20)->nullable()->comment('Numéro de téléphone du client');
            $table->string('nom_client', 255)->comment('Nom du client');
            
            // Montants
            $table->decimal('montant', 15, 2)->comment('Montant du paiement en FCFA');
            $table->decimal('frais', 10, 2)->default(0)->comment('Frais de transaction en FCFA');
            
            // Transaction
            $table->string('numero_transaction', 100)->nullable()->comment('Numéro de transaction MoneyFusion');
            $table->enum('statut', ['pending', 'paid', 'failed', 'cancelled'])
                  ->default('pending')
                  ->comment('Statut du paiement');
            $table->string('moyen', 50)->nullable()->comment('Moyen de paiement (card, orange_money, mtn, wave, etc.)');
            
            // URLs et données
            $table->text('payment_url')->nullable()->comment('URL de paiement MoneyFusion');
            $table->json('personal_info')->nullable()->comment('Informations personnalisées (userId, orderId, etc.)');
            $table->json('articles')->nullable()->comment('Liste des articles du paiement');
            $table->json('raw_response')->nullable()->comment('Réponse brute de l\'API MoneyFusion');
            
            // Dates
            $table->timestamp('paid_at')->nullable()->comment('Date et heure du paiement');
            $table->timestamps();
            
            // Index pour optimiser les requêtes
            $table->index('token_pay', 'idx_token_pay');
            $table->index('statut', 'idx_statut');
            $table->index('created_at', 'idx_created_at');
            $table->index(['user_id', 'statut'], 'idx_user_statut');
            $table->index('numero_transaction', 'idx_numero_transaction');
            
            // Foreign keys (si les tables existent)
            // Décommenter si vous avez une table users
            // $table->foreign('user_id')
            //       ->references('id')
            //       ->on('users')
            //       ->onDelete('cascade');
            
            // Décommenter si vous avez une table orders
            // $table->foreign('order_id')
            //       ->references('id')
            //       ->on('orders')
            //       ->onDelete('cascade');
        });

        // Commentaire de la table
        DB::statement("COMMENT ON TABLE moneyfusion_payments IS 'Table des paiements MoneyFusion'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moneyfusion_payments');
    }
};