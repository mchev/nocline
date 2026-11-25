# Écrire un adapter de notification

Un adapter livre un `AlertMessage` (incident opened/resolved, rappel de renouvellement, test) vers un système externe : email, Slack, Brevo, WhatsApp, Telegram, ntfy…

## En 3 étapes

1. Créer `app/Modules/Alertes/Adapters/MonAdapter.php` qui implémente `App\Modules\Alertes\Contracts\NotificationAdapter` :

```php
class NtfyAdapter implements NotificationAdapter
{
    public static function key(): string { return 'ntfy'; }          // stable, minuscules
    public static function label(): string { return 'ntfy'; }        // chaîne anglaise, traduite via __()

    public static function fields(): array
    {
        return [
            new AdapterField('topic_url', 'Topic URL', 'url', secret: true),
            new AdapterField('token', 'Access token', 'password', required: false, secret: true),
        ];
    }

    public function send(array $config, AlertMessage $message): void
    {
        Http::timeout(10)
            ->withToken($config['token'] ?? '')
            ->withBody($message->plainText())
            ->post($config['topic_url'])->throw();
    }
}
```

2. C'est tout : l'adapter est **découvert automatiquement**. Il apparaît dans _Alertes → Ajouter un canal_ avec le formulaire déclaré par `fields()` ; la validation, le chiffrement de la config, le bouton « Envoyer un test » et l'idempotence des envois sont gérés par Nocline.
3. Ajouter un test (voir `tests/Feature/Modules/NotificationAdaptersTest.php`, `Http::fake()`), les traductions (`php artisan nocline:lang:missing`) et une ligne dans `docs/user/`.

## Règles

- `send()` lève une exception en cas d'échec (le canal est marqué _failed_, les autres ne sont pas bloqués). Timeout ≤ 10 s ; pas de file d'attente à gérer.
- Champs : types `text`, `email`, `url`, `password`, `textarea`. `secret: true` = jamais renvoyé au navigateur (laisser vide à l'édition conserve la valeur). Noms réservés : `name`, `type`, `enabled`.
- `key()` est stocké en base : ne jamais le renommer après publication.
- Pas de logique métier ni d'accès à la base dans un adapter : uniquement formater et envoyer `AlertMessage` (`kind`, `subject`, `body`, `incident?`, `plainText()`).
- Adapter distribué par un package Composer : l'ajouter à `config('nocline.notification_adapters')`.
