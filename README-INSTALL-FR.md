# ShelfVault beta.6 - Guide d'installation PHP/MySQL

Ce guide concerne le paquet beta pour un hébergement PHP classique, notamment o2switch/cPanel.

## Prérequis

- PHP 8.3 ou plus récent.
- Une base MySQL ou MariaDB vide.
- Extensions PHP : `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`.
- Chemins accessibles en écriture pendant l'installation : `storage/`, `storage/app/public/`, `storage/framework/`, `storage/logs/`, `bootstrap/cache/` et racine du projet.
- Permissions conseillées : fichiers `640`/`644`, dossiers `755` ou `775` selon l'hébergeur. Éviter `777`.

## Organisation recommandée

Meilleure option :

```text
~/shelfvault          fichiers de l'application
~/shelfvault/public  document root du domaine
```

Cela garde les fichiers internes de Laravel hors du web public.

Fallback cPanel :

Si l'hébergeur impose `public_html`, extraire tout le projet dans `public_html`. Le `.htaccess` racine bloque les chemins sensibles comme `app/`, `config/`, `database/`, les dossiers privés de `storage/`, `vendor/`, `.env`, `composer.*`, puis redirige les requêtes vers `public/`.

Ce fallback est utile sur les hébergements partagés contraints, mais le document root vers `public/` reste la configuration la plus propre.

## Nouvelle installation

1. Créer une base MySQL/MariaDB vide et un utilisateur dans cPanel.
2. Uploader `ShelfVault-0.1.0-beta.6.zip` ou `ShelfVault-beta.zip`.
3. Extraire l'archive.
4. Ouvrir `https://votre-domaine/install.php`.
5. Choisir français ou anglais.
6. Corriger les prérequis signalés par l'installateur.
7. Renseigner l'hôte, le port, le nom de base, l'utilisateur et le mot de passe MySQL/MariaDB.
8. Vérifier `APP_URL`; le corriger si la détection automatique n'est pas bonne.
9. Créer le compte admin avec un mot de passe d'au moins 12 caractères.
10. Valider la dernière étape.

L'installateur écrit `.env`, génère une nouvelle `APP_KEY`, lance les migrations, crée l'utilisateur admin, écrit `storage/app/shelfvault/installed.lock`, puis redirige vers `/admin/login`.

## Jaquettes et stockage public

ShelfVault tente de créer `public/storage` pendant l'installation. Si l'hébergement bloque les liens symboliques, les jaquettes restent servies par une route Laravel de secours sous `/storage/...`.

## Après installation

`install.php` est bloqué automatiquement dès que `storage/app/shelfvault/installed.lock` existe.

Vous pouvez supprimer `install.php` après l'installation pour réduire la surface visible, mais c'est le fichier de verrouillage qui protège réellement l'installateur.

## Bibliothèque publique ou privée

Dans `Admin > Paramètres`, le réglage `Visibilité de la bibliothèque` contrôle le frontend en lecture seule :

- `Publique` : les visiteurs peuvent consulter la bibliothèque sans connexion.
- `Privée` : les pages de bibliothèque redirigent vers `/admin/login`, puis reviennent à la page demandée après connexion.

Le back-office reste toujours protégé.

## Fournisseurs de métadonnées

Les fournisseurs se configurent après installation dans `Admin > Paramètres` :

- TMDb pour les films et séries.
- IGDB pour les jeux vidéo.
- BoardGameGeek pour les jeux de société.

Les ajouts manuels fonctionnent même sans fournisseur configuré.

## Mises à jour depuis l'admin

1. Publier le ZIP de la prochaine beta sur une URL HTTPS.
2. Publier un manifest JSON HTTPS correspondant :

```json
{
  "version": "0.1.0-beta.6",
  "tag_name": "v0.1.0-beta.6",
  "name": "ShelfVault 0.1.0-beta.6",
  "html_url": "https://example.com/releases/0.1.0-beta.6",
  "zip_url": "https://example.com/ShelfVault-0.1.0-beta.6.zip",
  "sha256": "SHA256_DU_ZIP",
  "notes": "Notes de version",
  "minimum_php": "8.3.0",
  "requires_migrations": true
}
```

3. Configurer `SHELFVAULT_UPDATE_MANIFEST_URL=https://.../update-manifest.json` dans `.env`.
4. Aller dans `Admin > Paramètres > Mises à jour`.
5. Cliquer sur `Vérifier les mises à jour`, puis `Télécharger et installer`.

Avant de remplacer les fichiers, ShelfVault crée une sauvegarde dans `storage/app/shelfvault/backups`, télécharge le ZIP dans `storage/app/shelfvault/updates`, vérifie le SHA-256, extrait dans un dossier privé, remplace les fichiers applicatifs, lance les migrations avec `--force`, vide les caches et retente `storage:link`.

Chemins préservés :

- `.env`
- `storage/`
- `storage/app/shelfvault/installed.lock`
- `public/storage`

En cas d'échec pendant le remplacement ou les commandes post-update, ShelfVault restaure les fichiers remplacés depuis la copie de rollback. La restauration de base de données reste manuelle dans cette beta.

## Construire un paquet beta

```bash
SHELFVAULT_UPDATE_ZIP_URL=https://votre-url/ShelfVault-0.1.0-beta.6.zip php scripts/build-beta-package.php
```

Le script écrit le ZIP et les manifests dans `dist/`.

Ne commitez pas et ne publiez pas les sauvegardes générées. Elles peuvent contenir `.env`, des dumps de base de données, des jaquettes uploadées et d'autres données privées.
