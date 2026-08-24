# ShelfVault beta 0.1.0-beta.4 - Installation PHP + MySQL

## Prerequis

- PHP 8.3 ou plus recent.
- Base MySQL ou MariaDB vide.
- Extensions PHP : ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pdo, pdo_mysql, session, tokenizer, xml.
- Dossiers `storage/`, `storage/app/public/`, `storage/framework/`, `storage/logs/`, `bootstrap/cache/` et racine du projet accessibles en ecriture pendant l installation.
- Permissions conseillees : fichiers 640/644, dossiers 755 ou 775 selon l hebergeur. Evitez 777.

## Installation o2switch / cPanel

1. Creez une base MySQL/MariaDB vide et un utilisateur MySQL dans cPanel, puis notez hote, port, base, utilisateur et mot de passe.
2. Uploadez `ShelfVault-0.1.0-beta.4.zip` ou `ShelfVault-beta.zip`, puis extrayez l archive.
3. Recommande : placez le projet hors du web, par exemple `~/shelfvault`, et pointez le document root du domaine vers `~/shelfvault/public`.
4. Fallback cPanel : si l hebergeur impose `public_html`, extrayez tout le projet dans `public_html`. Le `.htaccess` racine bloque `app/`, `config/`, `database/`, les sous-dossiers sensibles de `storage/`, `vendor/`, `.env`, `composer.*` et route vers `public/`. Cette option reste moins propre que le document root vers `public/`.
5. Ouvrez `https://votre-domaine/install.php` puis choisissez Francais ou English.
6. Corrigez les prerequis affiches, renseignez MySQL/MariaDB, puis laissez ShelfVault tester la connexion.
7. Renseignez `APP_URL` si la detection automatique n est pas correcte, puis creez le compte admin avec un mot de passe d au moins 12 caracteres.
8. Validez le recapitulatif. L installateur genere une `APP_KEY` aleatoire, ecrit `.env`, lance les migrations, cree l admin, ecrit `storage/app/shelfvault/installed.lock`, puis redirige vers `/admin/login`.

## Jaquettes et storage

ShelfVault tente de creer le lien `public/storage` pendant l'installation. Si l'hebergement refuse les liens symboliques, les jaquettes restent servies par une route Laravel de secours `/storage/...`.

## Securite apres installation

Le fichier `install.php` est bloque automatiquement par `storage/app/shelfvault/installed.lock`. Vous pouvez le supprimer manuellement apres installation si vous voulez reduire la surface visible, mais ce n est pas requis pour securiser le wizard.

## Bibliotheque publique ou privee

Dans `Admin > Parametres`, le reglage `Visibilite de la bibliotheque` propose :

- `Publique` : la consultation du frontend reste accessible sans connexion.
- `Privee` : les pages de bibliotheque redirigent vers `/admin/login`, puis reviennent a l URL demandee apres connexion. Le back-office reste protege dans tous les cas.

La valeur par defaut reste `public` pour ne pas casser les installations beta existantes.

## Mises a jour o2switch

1. Publiez le ZIP d une nouvelle beta sur une URL HTTPS.
2. Publiez un manifest JSON HTTPS avec ce format :

```json
{
  "version": "0.1.0-beta.5",
  "tag_name": "v0.1.0-beta.5",
  "name": "ShelfVault 0.1.0-beta.5",
  "html_url": "https://example.com/releases/0.1.0-beta.5",
  "zip_url": "https://example.com/ShelfVault-0.1.0-beta.5.zip",
  "sha256": "SHA256_DU_ZIP",
  "notes": "Notes de version",
  "minimum_php": "8.3.0",
  "requires_migrations": true
}
```

3. Configurez `SHELFVAULT_UPDATE_MANIFEST_URL=https://.../update-manifest.json` dans `.env`.
4. Dans `Admin > Parametres > Mise a jour`, cliquez `Verifier les mises a jour`, puis `Telecharger et installer`.

Avant remplacement, ShelfVault cree une sauvegarde dans `storage/app/shelfvault/backups`. Le ZIP est telecharge dans `storage/app/shelfvault/updates`, verifie par SHA-256, extrait en staging, puis les fichiers applicatifs sont remplaces en preservant `.env`, `storage/`, `storage/app/shelfvault/installed.lock` et `public/storage`. Les migrations sont lancees avec `--force`, les caches sont nettoyes et `storage:link` est retente. En cas d echec pendant le remplacement ou les commandes post-update, les fichiers remplaces sont restaures depuis la copie rollback.

Le script `php scripts/build-beta-package.php` genere aussi `dist/update-manifest-<version>.json` et `dist/update-manifest-beta.json`. Pour publier une beta de test, lancez le script avec `SHELFVAULT_UPDATE_ZIP_URL=https://votre-url/ShelfVault-<version>.zip`, puis uploadez le ZIP et le manifest.

## Sauvegarde et rollback

Les backups ne sont pas publics : ils restent sous `storage/app/shelfvault/backups`. Ils contiennent un dump/base SQLite ou SQL portable, `.env`, `installed.lock` et `storage/app/public`. Ne partagez jamais ces archives publiquement, car elles contiennent les secrets necessaires a une restauration fiable.
