# 📦 Laravel MoneyFusion Package

[![Latest Version](https://img.shields.io/packagist/v/simonet85/laravel-moneyfusion.svg)](https://packagist.org/packages/simonet85/laravel-moneyfusion)
[![Laravel](https://img.shields.io/badge/Laravel-9.x%20--%2013.x-red.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Package Laravel pour l'intégration simplifiée de **MoneyFusion** - Solution de paiement mobile money (Orange Money, MTN, Wave, Moov) et cartes bancaires en Afrique.

## ✨ Fonctionnalités

- ✅ Installation en une commande
- ✅ Configuration automatique
- ✅ Facade Laravel intuitive
- ✅ Routes API et Web pré-configurées
- ✅ Controllers inclus
- ✅ Modèle Eloquent
- ✅ Migrations automatiques
- ✅ Gestion des webhooks
- ✅ Vues Blade incluses
- ✅ Commands Artisan
- ✅ Tests automatisés
- ✅ Support Laravel 9.x à 13.x

## 📦 Installation

```bash
composer require simonet85/laravel-moneyfusion
```

## ⚙️ Configuration

### 1. Publier la configuration

```bash
php artisan vendor:publish --tag=moneyfusion-config
```

### 2. Configurer .env

```env
MONEYFUSION_API_URL=https://api.moneyfusion.net/api/create-payment
MONEYFUSION_APP_KEY=VotreApp/VotreCle
MONEYFUSION_WEBHOOK_URL=https://votre-domaine.com/api/moneyfusion/webhook
MONEYFUSION_RETURN_URL=https://votre-domaine.com/payment/callback
```

### 3. Exécuter les migrations

```bash
php artisan migrate
```

### 4. (Optionnel) Publier les vues

```bash
php artisan vendor:publish --tag=moneyfusion-views
```

## 🚀 Utilisation

### Avec la Facade

```php
use Simonet85\LaravelMoneyFusion\Facades\MoneyFusion;

// Créer un paiement
$result = MoneyFusion::createPayment([
    'total_price' => 5000,
    'articles' => [
        [
            'name' => 'Produit A',
            'price' => 5000,
            'quantity' => 1
        ]
    ],
    'nom_client' => 'Jean Dupont',
    'numero_send' => '0707070707',
    'user_id' => auth()->id(),
]);

// Rediriger vers la page de paiement
return redirect($result['url']);
```

### Vérifier le statut d'un paiement

```php
$status = MoneyFusion::checkPaymentStatus($token);

if ($status['data']['statut'] === 'paid') {
    // Paiement réussi
}
```

### Obtenir un paiement

```php
$payment = MoneyFusion::getPaymentByToken($token);

if ($payment->isPaid()) {
    // Traiter la commande
}
```

### Dans un Controller

```php
use Simonet85\LaravelMoneyFusion\MoneyFusionService;

class CheckoutController extends Controller
{
    public function __construct(
        protected MoneyFusionService $moneyFusion
    ) {}

    public function process(Request $request)
    {
        try {
            $result = $this->moneyFusion->createPayment([
                'total_price' => $request->total,
                'articles' => $request->articles,
                'nom_client' => $request->nom_client,
            ]);

            return redirect($result['url']);

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

## 🛣️ Routes automatiques

Le package enregistre automatiquement les routes suivantes:

### API Routes

- `POST /api/moneyfusion/payments/initiate` - Créer un paiement
- `GET /api/moneyfusion/payments/{token}/status` - Vérifier le statut
- `POST /api/moneyfusion/webhook` - Recevoir les webhooks MoneyFusion

### Web Routes

- `GET /payment/callback` - Redirection après paiement
- `GET /payment/success/{token}` - Page de succès
- `GET /payment/failed` - Page d'échec
- `GET /payment/pending/{token}` - Page en attente

## 🎮 Commands Artisan

### Tester la création d'un paiement

```bash
php artisan moneyfusion:test-payment --amount=5000 --client="Jean Test"
```

### Vérifier le statut d'un paiement

```bash
php artisan moneyfusion:check-payment {token}
```

## 📊 Modèle de données

Le package crée automatiquement la table `moneyfusion_payments` avec les champs suivants:

```php
MoneyFusionPayment {
    id
    token_pay          // Token unique du paiement
    user_id            // ID de l'utilisateur (optionnel)
    order_id           // ID de la commande (optionnel)
    numero_send        // Numéro de téléphone
    nom_client         // Nom du client
    montant            // Montant en FCFA
    frais              // Frais de transaction
    numero_transaction // Numéro de transaction
    statut             // pending, paid, failed, cancelled
    moyen              // Moyen de paiement utilisé
    payment_url        // URL de paiement
    personal_info      // Données personnalisées (JSON)
    articles           // Liste des articles (JSON)
    raw_response       // Réponse brute de l'API (JSON)
    paid_at            // Date de paiement
    created_at
    updated_at
}
```

### Méthodes disponibles

```php
$payment = MoneyFusionPayment::find(1);

// Vérifier le statut
$payment->isPaid();      // bool
$payment->isPending();   // bool
$payment->isFailed();    // bool

// Marquer comme payé
$payment->markAsPaid([
    'numeroTransaction' => 'TRX123',
    'moyen' => 'orange_money',
    'frais' => 250,
]);

// Relations
$payment->user;    // Utilisateur
$payment->order;   // Commande (si configuré)

// Scopes
MoneyFusionPayment::paid()->get();      // Tous les paiements payés
MoneyFusionPayment::pending()->get();   // En attente
MoneyFusionPayment::today()->get();     // Paiements du jour
```

## 🔒 Sécurité

### Webhooks

MoneyFusion **ne signe pas** ses notifications (aucun secret, HMAC ni liste d'IP documentés).
Le contenu d'un webhook ne peut donc pas être cru. Le package :

- ✅ n'utilise que `tokenPay` et relit le statut réel auprès de l'API (`paiementNotif/{token}`) avant toute modification ;
- ✅ ignore la notification (HTTP 503) si l'API est injoignable : MoneyFusion peut la renvoyer ;
- ✅ refuse de marquer « payé » un paiement dont le montant API (`Montant`) diffère du montant créé ;
- ✅ ne fait jamais revenir un paiement « payé » en arrière, et ignore les notifications en double ;
- ✅ reconnaît les événements documentés `payin.session.pending|completed|cancelled` (et les anciens `payment.*`) ;
- ✅ n'est pas soumis au CSRF (routes sans groupe `web`).

`MoneyFusion::checkPaymentStatus()` renvoie `verified: true` uniquement pour une vraie réponse de l'API.
En cas de repli sur la base locale, `verified` vaut `false` : **ne créditez jamais un client sur une réponse non vérifiée.**

### Statuts

| API (`data.statut`) | Statut local |
|---|---|
| `pending` | `pending` |
| `paid` | `paid` |
| `failure` | `failed` |
| `no paid` | `cancelled` |

### Routes

Les routes sont préfixées `/api` : webhook `POST /api/moneyfusion/webhook`.
Les endpoints `payments/initiate|status|cancel` sont publics par défaut ; si vous créez vos paiements côté
serveur avec `MoneyFusion::createPayment()`, désactivez-les :

```php
// config/moneyfusion.php
'routes' => [
    'enabled' => true,             // false : aucune route du package
    'web' => true,                 // pages /payment/callback, success, pending, failed
    'payment_endpoints' => false,  // initiate / status / cancel
    'middleware' => ['web', 'auth'], // si vous les gardez
],
```

Un paiement rattaché à un `user_id` n'est accessible qu'à cet utilisateur (un visiteur qui connaît le token n'y a pas accès).

### Configuration HTTPS

**Important:** En production, assurez-vous que:

- `webhook_url` utilise HTTPS
- `return_url` utilise HTTPS
- Votre domaine a un certificat SSL valide

## 🧪 Tests

```bash
# Les tests utilisent SQLite en mémoire par défaut ; pour une autre base :
# DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=moneyfusion_test DB_USERNAME=... DB_PASSWORD=... vendor/bin/phpunit

# Exécuter tous les tests
composer test

# Tests avec couverture
composer test-coverage
```

## 📖 Documentation complète

Pour une documentation plus détaillée, consultez:

- [Guide d'utilisation complet](docs/USAGE.md)
- [Configuration avancée](docs/CONFIGURATION.md)
- [Gestion des webhooks](docs/WEBHOOKS.md)
- [API Reference](docs/API.md)

## 🤝 Contribution

Les contributions sont les bienvenues! Consultez [CONTRIBUTING.md](CONTRIBUTING.md) pour plus de détails.

## 🐛 Signaler un bug

Si vous trouvez un bug, ouvrez une issue sur [GitHub](https://github.com/simonet85/laravel-moneyfusion/issues).

## 📄 Licence

Ce package est open-source sous licence [MIT](LICENSE).

## 🙏 Remerciements

- [MoneyFusion](https://moneyfusion.net) - Plateforme de paiement
- [Laravel](https://laravel.com) - Framework PHP

## 📞 Support

- GitHub Issues: [issues](https://github.com/simonet85/laravel-moneyfusion/issues)
- Email: support@example.com
- Documentation MoneyFusion: [docs.moneyfusion.net](https://docs.moneyfusion.net)

## 🌟 Donnez une étoile!

Si ce package vous est utile, pensez à donner une ⭐ sur GitHub!

---

**Made with ❤️ for the Laravel and African tech community**
