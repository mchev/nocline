# Coffre et renouvellements

## Coffre (admin et tech)

Stocke identifiants et secrets liés à un groupe (hébergeur, accès FTP/SSH, back-offices…).

- **Chiffrement** : libsodium (XChaCha20-Poly1305). Chaque entrée a sa propre clé de données, elle-même chiffrée par la clé maître `VAULT_MASTER_KEY` (dans `.env`, **jamais** en base, distincte de `APP_KEY`). L'assistant d'installation la génère.
- **Perte de la clé maître = secrets définitivement perdus.** Sauvegardez-la à part des sauvegardes de base. Une sauvegarde de base seule est inexploitable.
- Créer ou modifier une entrée demande votre **mot de passe** (confirmation). **Révéler** un secret le demande aussi à chaque fois ; il reste affiché 30 s puis se masque. _Copier_ vide le presse-papiers après 30 s.
- La liste n'envoie jamais les secrets au navigateur.
- **Audit** (admin, _Coffre > Journal d'audit_) : création, affichage, copie, modification, suppression avec utilisateur, date et IP — jamais le contenu. Le journal est en ajout seul et survit à la suppression des entrées.
- Les `viewer` n'ont aucun accès. Seuls les admins suppriment.

Pas de rotation automatique de la clé maître pour l'instant (prévue ; à documenter avant usage).

## Renouvellements

Menu **Renouvellements** : tout est **automatique** et en lecture seule. Il n'y a rien à saisir : ajoutez un site à surveiller (check HTTP) et l'expiration de son nom de domaine et de son certificat SSL apparaissent ici.

- **Résumé en haut** : « N éléments expirent dans les 30 prochains jours ». La liste est groupée par urgence : _Expiré_, _Sous 30 jours_, _Plus tard_, avec le site, le groupe, la date et « dans N jours ».
- **Domaines** : chaque nuit (03:00) Phare interroge RDAP (service public, sans clé) pour lire la date d'expiration du nom de domaine de chaque check HTTP actif. Un seul élément par domaine, même s'il est partagé par plusieurs sites. Si la date est introuvable (extension non prise en charge, adresse IP, `localhost`, `.test`, `.local`, service injoignable), le domaine n'est pas ajouté et l'ancienne date est conservée.
- **SSL** : chaque nuit (02:30), Phare lit le certificat de chaque check `https` (une ligne par check, sans historique).
- **Nouveau site** : à la création d'un check HTTP, le domaine et le SSL sont actualisés en arrière-plan.
- **Actualiser maintenant** (admin et tech) : relance la lecture pour tous les sites (limité à 3 fois par minute). Nécessite un worker de files actif.
- Les anciennes lignes saisies à la main restent affichées en lecture seule ; elles ne sont plus modifiables ni créables.
- **Rappels** : à 30, 14, 7 et 1 jour(s) avant l'échéance, un message est envoyé sur tous les canaux d'alerte actifs (e-mail, webhook). Un seul rappel par seuil ; si plusieurs seuils sont franchis d'un coup (installation tardive), un seul message est envoyé. Les rappels repartent si la date est repoussée.
- Les expirations dépassées sont signalées `EXPIRED`.
