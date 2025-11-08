<?php

namespace Simonet85\LaravelMoneyFusion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MoneyFusionPayment extends Model
{
    use HasFactory;

    /**
     * Le nom de la table associée au modèle
     *
     * @var string
     */
    protected $table = 'moneyfusion_payments';

    protected $fillable = [
        'token_pay',
        'user_id',
        'order_id',
        'numero_send',
        'nom_client',
        'montant',
        'frais',
        'numero_transaction',
        'statut',
        'moyen',
        'payment_url',
        'personal_info',
        'articles',
        'raw_response',
        'paid_at',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'frais' => 'decimal:2',
        'personal_info' => 'array',
        'articles' => 'array',
        'raw_response' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Relation avec la commande (si table orders existe)
     */
    public function order(): BelongsTo
    {
        // Adapter selon votre modèle Order
        return $this->belongsTo('App\Models\Order');
    }

    // ============================================
    // MÉTHODES DE VÉRIFICATION DU STATUT
    // ============================================

    /**
     * Vérifier si le paiement est payé
     */
    public function isPaid(): bool
    {
        return $this->statut === 'paid';
    }

    /**
     * Vérifier si le paiement est en attente
     */
    public function isPending(): bool
    {
        return $this->statut === 'pending';
    }

    /**
     * Vérifier si le paiement a échoué
     */
    public function isFailed(): bool
    {
        return $this->statut === 'failed';
    }

    /**
     * Vérifier si le paiement est annulé
     */
    public function isCancelled(): bool
    {
        return $this->statut === 'cancelled';
    }

    // ============================================
    // MÉTHODES DE CHANGEMENT DE STATUT
    // ============================================

    /**
     * Marquer comme payé
     */
    public function markAsPaid(array $data = []): void
    {
        $this->update([
            'statut' => 'paid',
            'paid_at' => now(),
            'numero_transaction' => $data['numeroTransaction'] ?? $this->numero_transaction,
            'moyen' => $data['moyen'] ?? $this->moyen,
            'frais' => $data['frais'] ?? $this->frais,
        ]);
    }

    /**
     * Marquer comme échoué
     */
    public function markAsFailed(): void
    {
        $this->update([
            'statut' => 'failed',
        ]);
    }

    /**
     * Marquer comme annulé
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'statut' => 'cancelled',
        ]);
    }

    // ============================================
    // QUERY SCOPES
    // ============================================

    /**
     * Scope pour filtrer les paiements payés
     */
    public function scopePaid($query)
    {
        return $query->where('statut', 'paid');
    }

    /**
     * Scope pour filtrer les paiements en attente
     */
    public function scopePending($query)
    {
        return $query->where('statut', 'pending');
    }

    /**
     * Scope pour filtrer les paiements échoués
     */
    public function scopeFailed($query)
    {
        return $query->where('statut', 'failed');
    }

    /**
     * Scope pour filtrer les paiements d'aujourd'hui
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope pour filtrer les paiements de cette semaine
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope pour filtrer les paiements de ce mois
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    /**
     * Scope pour filtrer par moyen de paiement
     */
    public function scopeByPaymentMethod($query, string $method)
    {
        return $query->where('moyen', $method);
    }

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ============================================
    // ACCESSEURS ET MUTATEURS
    // ============================================

    /**
     * Obtenir le montant total (montant + frais)
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->montant + $this->frais;
    }

    /**
     * Obtenir le montant formaté
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->montant, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Obtenir les frais formatés
     */
    public function getFormattedFeesAttribute(): string
    {
        return number_format($this->frais, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Obtenir le statut traduit
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->statut) {
            'pending' => 'En attente',
            'paid' => 'Payé',
            'failed' => 'Échoué',
            'cancelled' => 'Annulé',
            default => 'Inconnu',
        };
    }

    /**
     * Obtenir la couleur du badge selon le statut
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->statut) {
            'pending' => 'warning',
            'paid' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            default => 'info',
        };
    }

    /**
     * Obtenir le nombre d'articles
     */
    public function getArticlesCountAttribute(): int
    {
        return count($this->articles ?? []);
    }
}