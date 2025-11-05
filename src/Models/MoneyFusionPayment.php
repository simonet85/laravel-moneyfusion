<?php

namespace Simonet85\LaravelMoneyFusion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyFusionPayment extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function isPaid(): bool
    {
        return $this->statut === 'paid';
    }

    public function isPending(): bool
    {
        return $this->statut === 'pending';
    }

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
}
