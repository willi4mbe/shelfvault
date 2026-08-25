<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$root = dirname(__DIR__);
$lockPath = $root.'/storage/app/shelfvault/installed.lock';

installer_prepare_directories($root);

session_name('SHELFVAULT_INSTALL');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => installer_cookie_path(),
    'secure' => installer_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (! isset($_SESSION['shelfvault_install']) || ! is_array($_SESSION['shelfvault_install'])) {
    $_SESSION['shelfvault_install'] = [];
}

$state = &$_SESSION['shelfvault_install'];
$state['csrf'] ??= bin2hex(random_bytes(32));
$state['locale'] = installer_locale($_POST['locale'] ?? $_GET['locale'] ?? $state['locale'] ?? 'en');

$messages = installer_messages($state['locale']);
$errors = [];
$notice = null;

if (is_file($lockPath)) {
    installer_render($root, $state, $messages, 'installed', [], [], null, $lockPath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! hash_equals((string) $state['csrf'], (string) ($_POST['_token'] ?? ''))) {
        $errors[] = $messages['errors']['csrf'];
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'language') {
                $state['locale'] = installer_locale($_POST['locale'] ?? 'en');
                $messages = installer_messages($state['locale']);
                $state['step'] = 'requirements';
            } elseif ($action === 'requirements') {
                $checks = installer_requirement_checks($root, $messages);

                if (! installer_checks_pass($checks)) {
                    $errors[] = $messages['errors']['requirements'];
                    $state['step'] = 'requirements';
                } else {
                    $state['step'] = 'database';
                }
            } elseif ($action === 'database') {
                $database = installer_validate_database($_POST, $messages);
                $databaseError = installer_test_database($database, $messages);

                if ($databaseError !== null) {
                    $errors[] = $databaseError;
                    $state['database_form'] = installer_without_secret($database);
                    $state['step'] = 'database';
                } else {
                    $state['database'] = $database;
                    $state['database_form'] = installer_without_secret($database);
                    $state['step'] = 'admin';
                    $notice = $messages['database']['connected'];
                }
            } elseif ($action === 'admin') {
                $state['admin'] = installer_validate_admin($_POST, $messages);
                $state['settings'] = installer_validate_settings($_POST, $messages);
                $state['step'] = 'review';
            } elseif ($action === 'install') {
                installer_assert_ready($state, $messages);
                installer_install($root, $state, $messages, $lockPath);
            }
        } catch (InstallerValidationException $exception) {
            $errors = array_merge($errors, $exception->errors);
            $state['step'] = $exception->step;
        } catch (Throwable $exception) {
            installer_log($root, 'Installation failed.', [
                'type' => $exception::class,
                'message' => installer_sanitize_message($exception->getMessage(), $state),
            ]);

            $errors[] = $messages['errors']['generic'];
        }
    }
}

$step = (string) ($state['step'] ?? 'language');
$allowedSteps = ['language', 'requirements', 'database', 'admin', 'review'];

if (! in_array($step, $allowedSteps, true)) {
    $step = 'language';
}

installer_render($root, $state, $messages, $step, $errors, installer_requirement_checks($root, $messages), $notice, $lockPath);

final class InstallerValidationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(public string $step, public array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}

function installer_prepare_directories(string $root): void
{
    foreach ([
        $root.'/storage/app/public',
        $root.'/storage/framework/cache/data',
        $root.'/storage/framework/sessions',
        $root.'/storage/framework/views',
        $root.'/storage/logs',
        $root.'/bootstrap/cache',
    ] as $directory) {
        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
    }
}

function installer_cookie_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php');
    $directory = str_replace('\\', '/', dirname($script));

    return $directory === '/' || $directory === '.' ? '/' : rtrim($directory, '/').'/';
}

function installer_is_https(): bool
{
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    return $forwarded === 'https' || (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

function installer_script_url(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'));

    if (basename($script) === 'index.php') {
        $directory = dirname($script);

        return ($directory === '/' || $directory === '.') ? '/install.php' : rtrim($directory, '/').'/install.php';
    }

    return $script;
}

function installer_detect_app_url(): string
{
    $scheme = installer_is_https() ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
    $directory = basename($script) === 'install.php' ? dirname($script) : '';

    if ($directory === '/' || $directory === '.') {
        $directory = '';
    }

    return $scheme.'://'.$host.rtrim($directory, '/');
}

function installer_locale(mixed $locale): string
{
    return in_array($locale, ['en', 'fr'], true) ? (string) $locale : 'en';
}

/**
 * @return array<string, mixed>
 */
function installer_messages(string $locale): array
{
    $messages = [
        'en' => [
            'title' => 'ShelfVault setup',
            'subtitle' => 'Standalone installer for shared hosting.',
            'version' => 'Version',
            'language' => [
                'title' => 'Choose your language',
                'intro' => 'The installer runs before Laravel and does not need an application key or database yet.',
                'continue' => 'Continue',
                'english' => 'English',
                'french' => 'Francais',
            ],
            'steps' => [
                'language' => 'Language',
                'requirements' => 'Server requirements',
                'database' => 'Database',
                'admin' => 'Admin',
                'review' => 'Review',
            ],
            'requirements' => [
                'title' => 'Server requirements',
                'intro' => 'Fix failed checks before continuing. Use writable directories with 755/775 where possible, not 777.',
                'system' => 'PHP and extensions',
                'permissions' => 'Permissions',
                'name' => 'Check',
                'required' => 'Required',
                'current' => 'Current',
                'status' => 'Status',
                'path' => 'Path',
                'ok' => 'OK',
                'failed' => 'Failed',
                'continue' => 'Continue to database',
                'refresh' => 'Refresh checks',
            ],
            'database' => [
                'title' => 'Connect MySQL / MariaDB',
                'intro' => 'Use the database created in cPanel. ShelfVault tests the connection before saving anything.',
                'host' => 'Host',
                'port' => 'Port',
                'database' => 'Database name',
                'username' => 'Username',
                'password' => 'Password',
                'submit' => 'Test connection',
                'connected' => 'Database connection verified.',
            ],
            'admin' => [
                'title' => 'Application and admin',
                'intro' => 'Create the first administrator. The password is never displayed in the recap.',
                'app_url' => 'Application URL',
                'app_name' => 'Application name',
                'login' => 'Admin login',
                'email' => 'Admin email',
                'password' => 'Admin password',
                'password_confirmation' => 'Confirm password',
                'locale' => 'Admin language',
                'submit' => 'Review installation',
            ],
            'review' => [
                'title' => 'Review',
                'intro' => 'ShelfVault will write .env, generate APP_KEY, bootstrap Laravel, run migrations, create the admin, then write the install lock.',
                'database' => 'Database',
                'admin' => 'Administrator',
                'settings' => 'Settings',
                'secret_saved' => 'Saved, not displayed',
                'submit' => 'Install ShelfVault',
            ],
            'installed' => [
                'title' => 'ShelfVault is already installed',
                'intro' => 'The install lock exists, so this wizard is disabled by default.',
                'home' => 'Go to home',
                'admin' => 'Go to admin login',
                'lock' => 'Lock file',
            ],
            'errors' => [
                'csrf' => 'The installer form expired. Reload the page and try again.',
                'requirements' => 'Some server requirements are not satisfied.',
                'database_host' => 'ShelfVault could not reach the database host. Check host and port.',
                'database_credentials' => 'Database access was denied. Check the username and password.',
                'database_name' => 'The database was not found. Create it first in cPanel.',
                'database_generic' => 'ShelfVault could not connect to the database. Check the details and try again.',
                'missing_database' => 'Test the database connection before continuing.',
                'missing_admin' => 'Complete the admin step before installing.',
                'generic' => 'Installation failed. Check storage/logs/shelfvault-install.log if it exists, then retry.',
                'env_write' => 'ShelfVault could not write the .env file. Check root directory permissions.',
                'migration' => 'Database migrations failed. The install lock was not created.',
                'admin_exists' => 'This database already contains users. Use an empty database for a fresh install.',
                'admin_create' => 'The admin account could not be created. The install lock was not created.',
            ],
        ],
        'fr' => [
            'title' => 'Installation ShelfVault',
            'subtitle' => 'Installateur autonome pour hebergement mutualise.',
            'version' => 'Version',
            'language' => [
                'title' => 'Choisir la langue',
                'intro' => 'L installateur demarre avant Laravel et ne demande pas encore de cle APP_KEY ni de base de donnees.',
                'continue' => 'Continuer',
                'english' => 'English',
                'french' => 'Francais',
            ],
            'steps' => [
                'language' => 'Langue',
                'requirements' => 'Prerequis serveur',
                'database' => 'Base de donnees',
                'admin' => 'Admin',
                'review' => 'Recapitulatif',
            ],
            'requirements' => [
                'title' => 'Prerequis serveur',
                'intro' => 'Corrigez les controles en echec avant de continuer. Utilisez 755/775 si possible, pas 777.',
                'system' => 'PHP et extensions',
                'permissions' => 'Permissions',
                'name' => 'Controle',
                'required' => 'Requis',
                'current' => 'Actuel',
                'status' => 'Etat',
                'path' => 'Chemin',
                'ok' => 'OK',
                'failed' => 'Echec',
                'continue' => 'Continuer vers la base',
                'refresh' => 'Actualiser',
            ],
            'database' => [
                'title' => 'Connecter MySQL / MariaDB',
                'intro' => 'Utilisez la base creee dans cPanel. ShelfVault teste la connexion avant d enregistrer quoi que ce soit.',
                'host' => 'Hote',
                'port' => 'Port',
                'database' => 'Nom de la base',
                'username' => 'Utilisateur',
                'password' => 'Mot de passe',
                'submit' => 'Tester la connexion',
                'connected' => 'Connexion a la base verifiee.',
            ],
            'admin' => [
                'title' => 'Application et admin',
                'intro' => 'Creez le premier administrateur. Le mot de passe ne sera jamais affiche dans le recapitulatif.',
                'app_url' => 'URL de l application',
                'app_name' => 'Nom de l application',
                'login' => 'Identifiant admin',
                'email' => 'Email admin',
                'password' => 'Mot de passe admin',
                'password_confirmation' => 'Confirmer le mot de passe',
                'locale' => 'Langue admin',
                'submit' => 'Verifier l installation',
            ],
            'review' => [
                'title' => 'Recapitulatif',
                'intro' => 'ShelfVault va ecrire .env, generer APP_KEY, demarrer Laravel, lancer les migrations, creer l admin, puis ecrire le verrou d installation.',
                'database' => 'Base de donnees',
                'admin' => 'Administrateur',
                'settings' => 'Parametres',
                'secret_saved' => 'Enregistre, non affiche',
                'submit' => 'Installer ShelfVault',
            ],
            'installed' => [
                'title' => 'ShelfVault est deja installe',
                'intro' => 'Le verrou d installation existe, donc cet assistant est desactive par defaut.',
                'home' => 'Aller a l accueil',
                'admin' => 'Aller a la connexion admin',
                'lock' => 'Fichier verrou',
            ],
            'errors' => [
                'csrf' => 'Le formulaire d installation a expire. Rechargez la page puis recommencez.',
                'requirements' => 'Certains prerequis serveur ne sont pas satisfaits.',
                'database_host' => 'ShelfVault ne peut pas joindre l hote de base de donnees. Verifiez hote et port.',
                'database_credentials' => 'Acces refuse a la base. Verifiez utilisateur et mot de passe.',
                'database_name' => 'La base de donnees est introuvable. Creez la d abord dans cPanel.',
                'database_generic' => 'ShelfVault ne peut pas se connecter a la base. Verifiez les informations puis reessayez.',
                'missing_database' => 'Testez la connexion a la base avant de continuer.',
                'missing_admin' => 'Completez l etape admin avant l installation.',
                'generic' => 'Installation echouee. Consultez storage/logs/shelfvault-install.log s il existe, puis reessayez.',
                'env_write' => 'ShelfVault ne peut pas ecrire le fichier .env. Verifiez les permissions de la racine.',
                'migration' => 'Les migrations ont echoue. Le verrou d installation n a pas ete cree.',
                'admin_exists' => 'Cette base contient deja des utilisateurs. Utilisez une base vide pour une installation fraiche.',
                'admin_create' => 'Le compte admin n a pas pu etre cree. Le verrou d installation n a pas ete cree.',
            ],
        ],
    ];

    return $messages[$locale] ?? $messages['en'];
}

/**
 * @return array{system: array<int, array<string, mixed>>, permissions: array<int, array<string, mixed>>}
 */
function installer_requirement_checks(string $root, array $messages): array
{
    $phpConstraint = installer_php_constraint($root);
    $phpMinimum = installer_php_minimum($phpConstraint);
    $extensions = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'];
    $system = [[
        'name' => 'PHP',
        'required' => $phpConstraint,
        'current' => PHP_VERSION,
        'passes' => version_compare(PHP_VERSION, $phpMinimum, '>='),
    ]];

    foreach ($extensions as $extension) {
        $system[] = [
            'name' => 'PHP extension: '.$extension,
            'required' => $messages['requirements']['ok'],
            'current' => extension_loaded($extension) ? $messages['requirements']['ok'] : $messages['requirements']['failed'],
            'passes' => extension_loaded($extension),
        ];
    }

    $paths = [
        'storage' => $root.'/storage',
        'storage/app/public' => $root.'/storage/app/public',
        'storage/framework/cache/data' => $root.'/storage/framework/cache/data',
        'storage/framework/sessions' => $root.'/storage/framework/sessions',
        'storage/framework/views' => $root.'/storage/framework/views',
        'storage/logs' => $root.'/storage/logs',
        'bootstrap/cache' => $root.'/bootstrap/cache',
        '.env' => is_file($root.'/.env') ? $root.'/.env' : $root,
    ];
    $permissions = [];

    foreach ($paths as $name => $path) {
        $permissions[] = [
            'name' => $name,
            'path' => $path,
            'passes' => is_writable($path),
        ];
    }

    return ['system' => $system, 'permissions' => $permissions];
}

function installer_php_constraint(string $root): string
{
    $lock = installer_json_file($root.'/composer.lock');
    $constraint = $lock['platform']['php'] ?? null;

    if (is_string($constraint) && $constraint !== '') {
        return $constraint;
    }

    $composer = installer_json_file($root.'/composer.json');
    $constraint = $composer['require']['php'] ?? '^8.3';

    return is_string($constraint) && $constraint !== '' ? $constraint : '^8.3';
}

/**
 * @return array<string, mixed>
 */
function installer_json_file(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $json = json_decode((string) file_get_contents($path), true);

    return is_array($json) ? $json : [];
}

function installer_php_minimum(string $constraint): string
{
    if (preg_match('/(?:\^|>=|~)?\s*(\d+\.\d+(?:\.\d+)?)/', $constraint, $matches) !== 1) {
        return '8.3.0';
    }

    return substr_count($matches[1], '.') === 1 ? $matches[1].'.0' : $matches[1];
}

/**
 * @param  array{system: array<int, array<string, mixed>>, permissions: array<int, array<string, mixed>>}  $checks
 */
function installer_checks_pass(array $checks): bool
{
    foreach (array_merge($checks['system'], $checks['permissions']) as $check) {
        if (! ($check['passes'] ?? false)) {
            return false;
        }
    }

    return true;
}

/**
 * @return array<string, string>
 */
function installer_validate_database(array $input, array $messages): array
{
    $database = [
        'connection' => 'mysql',
        'host' => trim((string) ($input['host'] ?? '')),
        'port' => trim((string) ($input['port'] ?? '3306')),
        'database' => trim((string) ($input['database'] ?? '')),
        'username' => trim((string) ($input['username'] ?? '')),
        'password' => (string) ($input['password'] ?? ''),
    ];
    $errors = [];

    foreach (['host', 'port', 'database', 'username'] as $field) {
        if ($database[$field] === '') {
            $errors[] = ucfirst($field).' is required.';
        }
    }

    if ($database['port'] !== '' && (! ctype_digit($database['port']) || (int) $database['port'] < 1 || (int) $database['port'] > 65535)) {
        $errors[] = 'Port must be between 1 and 65535.';
    }

    if ($errors !== []) {
        throw new InstallerValidationException('database', $errors);
    }

    return $database;
}

function installer_test_database(array $database, array $messages): ?string
{
    if (! extension_loaded('pdo_mysql')) {
        return $messages['errors']['database_generic'];
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $database['host'],
        $database['port'],
        $database['database'],
    );

    try {
        new PDO($dsn, $database['username'], $database['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        return null;
    } catch (Throwable $exception) {
        $message = $exception->getMessage();

        if (str_contains($message, 'SQLSTATE[HY000] [2002]')) {
            return $messages['errors']['database_host'];
        }

        if (str_contains($message, 'Access denied')) {
            return $messages['errors']['database_credentials'];
        }

        if (str_contains($message, 'Unknown database')) {
            return $messages['errors']['database_name'];
        }

        return $messages['errors']['database_generic'];
    }
}

/**
 * @return array<string, string>
 */
function installer_without_secret(array $database): array
{
    $database['password'] = '';

    return $database;
}

/**
 * @return array<string, string>
 */
function installer_validate_admin(array $input, array $messages): array
{
    $admin = [
        'login' => trim((string) ($input['login'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'password' => (string) ($input['password'] ?? ''),
        'preferred_locale' => installer_locale($input['preferred_locale'] ?? 'en'),
    ];
    $confirmation = (string) ($input['password_confirmation'] ?? '');
    $errors = [];

    if (! preg_match('/^[A-Za-z0-9_-]{3,40}$/', $admin['login'])) {
        $errors[] = 'Admin login must be 3-40 characters and use only letters, numbers, dashes, and underscores.';
    }

    if (filter_var($admin['email'], FILTER_VALIDATE_EMAIL) === false || strlen($admin['email']) > 255) {
        $errors[] = 'Admin email is invalid.';
    }

    if (strlen($admin['password']) < 12 || strlen($admin['password']) > 128) {
        $errors[] = 'Admin password must be between 12 and 128 characters.';
    }

    if (! hash_equals($admin['password'], $confirmation)) {
        $errors[] = 'Admin password confirmation does not match.';
    }

    if ($errors !== []) {
        throw new InstallerValidationException('admin', $errors);
    }

    return $admin;
}

/**
 * @return array<string, string>
 */
function installer_validate_settings(array $input, array $messages): array
{
    $settings = [
        'app_name' => trim((string) ($input['app_name'] ?? 'ShelfVault')),
        'app_url' => rtrim(trim((string) ($input['app_url'] ?? installer_detect_app_url())), '/'),
        'app_locale' => installer_locale($input['preferred_locale'] ?? 'en'),
    ];
    $errors = [];

    if ($settings['app_name'] === '' || strlen($settings['app_name']) > 80) {
        $errors[] = 'Application name is required and must be 80 characters or fewer.';
    }

    if (filter_var($settings['app_url'], FILTER_VALIDATE_URL) === false || strlen($settings['app_url']) > 255) {
        $errors[] = 'Application URL is invalid.';
    }

    if ($errors !== []) {
        throw new InstallerValidationException('admin', $errors);
    }

    return $settings;
}

function installer_assert_ready(array $state, array $messages): void
{
    if (! isset($state['database']) || ! is_array($state['database'])) {
        throw new InstallerValidationException('database', [$messages['errors']['missing_database']]);
    }

    if (! isset($state['admin'], $state['settings']) || ! is_array($state['admin']) || ! is_array($state['settings'])) {
        throw new InstallerValidationException('admin', [$messages['errors']['missing_admin']]);
    }
}

function installer_install(string $root, array &$state, array $messages, string $lockPath): void
{
    $database = $state['database'];
    $admin = $state['admin'];
    $settings = $state['settings'];
    $databaseError = installer_test_database($database, $messages);

    if ($databaseError !== null) {
        throw new InstallerValidationException('database', [$databaseError]);
    }

    $appKey = 'base64:'.base64_encode(random_bytes(32));
    $env = [
        'APP_NAME' => $settings['app_name'],
        'APP_ENV' => 'production',
        'APP_KEY' => $appKey,
        'APP_DEBUG' => 'false',
        'APP_URL' => $settings['app_url'],
        'APP_LOCALE' => $settings['app_locale'],
        'APP_FALLBACK_LOCALE' => 'en',
        'LOG_CHANNEL' => 'stack',
        'LOG_STACK' => 'single',
        'LOG_LEVEL' => 'warning',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => $database['host'],
        'DB_PORT' => $database['port'],
        'DB_DATABASE' => $database['database'],
        'DB_USERNAME' => $database['username'],
        'DB_PASSWORD' => $database['password'],
        'SESSION_DRIVER' => 'file',
        'CACHE_STORE' => 'file',
        'QUEUE_CONNECTION' => 'database',
        'SHELFVAULT_VERSION' => installer_version($root),
    ];

    installer_write_env($root, $env, $messages);
    installer_clear_bootstrap_cache($root);
    installer_prime_process_env($env);

    try {
        require_once $root.'/vendor/autoload.php';

        $app = require $root.'/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $migrationStatus = Illuminate\Support\Facades\Artisan::call('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($migrationStatus !== 0) {
            installer_log($root, 'Migrations failed.', ['output' => Illuminate\Support\Facades\Artisan::output()]);

            throw new InstallerValidationException('review', [$messages['errors']['migration']]);
        }

        $userClass = App\Models\User::class;

        Illuminate\Support\Facades\DB::transaction(function () use ($userClass, $admin, $messages): void {
            if ($userClass::query()->exists()) {
                throw new InstallerValidationException('review', [$messages['errors']['admin_exists']]);
            }

            $userClass::query()->create([
                'name' => $admin['login'],
                'login' => $admin['login'],
                'email' => $admin['email'],
                'password' => $admin['password'],
                'preferred_locale' => $admin['preferred_locale'],
            ]);
        });

        if (! $userClass::query()->where('login', $admin['login'])->where('email', $admin['email'])->exists()) {
            throw new InstallerValidationException('review', [$messages['errors']['admin_create']]);
        }

        try {
            Illuminate\Support\Facades\Artisan::call('storage:link', [
                '--force' => true,
                '--no-interaction' => true,
            ]);
        } catch (Throwable $exception) {
            installer_log($root, 'Public storage link could not be created.', [
                'type' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        installer_write_lock($lockPath);
        installer_log($root, 'Installation completed.');

        unset($_SESSION['shelfvault_install']);

        session_regenerate_id(true);
        header('Location: '.rtrim($settings['app_url'], '/').'/admin/login', true, 303);
        exit;
    } catch (InstallerValidationException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        installer_log($root, 'Laravel bootstrap/install failed.', [
            'type' => $exception::class,
            'message' => installer_sanitize_message($exception->getMessage(), $state),
        ]);

        throw new InstallerValidationException('review', [$messages['errors']['generic']]);
    }
}

/**
 * @param  array<string, string>  $values
 */
function installer_write_env(string $root, array $values, array $messages): void
{
    $envPath = $root.'/.env';
    $examplePath = $root.'/.env.example';
    $contents = is_file($envPath)
        ? (string) file_get_contents($envPath)
        : (is_file($examplePath) ? (string) file_get_contents($examplePath) : '');

    foreach ($values as $key => $value) {
        $line = $key.'='.installer_env_value($value);

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents) === 1) {
            $contents = (string) preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }
    }

    if (@file_put_contents($envPath, $contents, LOCK_EX) === false) {
        throw new InstallerValidationException('review', [$messages['errors']['env_write']]);
    }

    @chmod($envPath, 0640);
}

function installer_env_value(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);

    if ($value === '' || preg_match('/\s|#|"|\'|=|\$/', $value) === 1) {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    return $value;
}

function installer_clear_bootstrap_cache(string $root): void
{
    foreach (glob($root.'/bootstrap/cache/*.php') ?: [] as $file) {
        @unlink($file);
    }
}

/**
 * @param  array<string, string>  $values
 */
function installer_prime_process_env(array $values): void
{
    foreach ($values as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function installer_write_lock(string $lockPath): void
{
    if (! is_dir(dirname($lockPath))) {
        mkdir(dirname($lockPath), 0755, true);
    }

    file_put_contents($lockPath, gmdate('c').PHP_EOL, LOCK_EX);
    @chmod($lockPath, 0644);
}

/**
 * @param  array<string, mixed>  $context
 */
function installer_log(string $root, string $message, array $context = []): void
{
    $path = $root.'/storage/logs/shelfvault-install.log';
    $line = '['.gmdate('c').'] '.$message;

    if ($context !== []) {
        $line .= ' '.json_encode($context, JSON_UNESCAPED_SLASHES);
    }

    @file_put_contents($path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function installer_sanitize_message(string $message, array $state): string
{
    $secrets = [];

    foreach (['database', 'admin'] as $group) {
        if (! isset($state[$group]) || ! is_array($state[$group])) {
            continue;
        }

        foreach (['password', 'password_confirmation'] as $key) {
            if (isset($state[$group][$key]) && is_string($state[$group][$key]) && $state[$group][$key] !== '') {
                $secrets[] = $state[$group][$key];
            }
        }
    }

    foreach ($secrets as $secret) {
        $message = str_replace($secret, '[redacted]', $message);
    }

    return $message;
}

function installer_version(string $root): string
{
    $version = is_file($root.'/VERSION') ? trim((string) file_get_contents($root.'/VERSION')) : '';

    return $version !== '' ? $version : '0.1.0-beta.5';
}

/**
 * @param  array<string, mixed>  $state
 * @param  array<int, string>  $errors
 * @param  array{system: array<int, array<string, mixed>>, permissions: array<int, array<string, mixed>>}  $checks
 */
function installer_render(string $root, array $state, array $messages, string $step, array $errors, array $checks, ?string $notice, string $lockPath): void
{
    $version = installer_version($root);
    $script = installer_script_url();
    $token = (string) ($state['csrf'] ?? '');
    $locale = installer_locale($state['locale'] ?? 'en');

    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="<?= installer_e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= installer_e($messages['title']) ?></title>
    <style>
        :root { color-scheme: dark; --ink: #ffffff; --muted: rgba(255,255,255,.62); --line: rgba(255,255,255,.1); --panel: rgba(9,10,14,.9); --soft: rgba(255,255,255,.055); --brand: #f97316; --accent: #f97316; --accent-contrast: #170d02; --bad: #fda4af; --ok: #86efac; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--ink); background: radial-gradient(circle at 80% 0%, rgba(249,115,22,.18), transparent 24rem), radial-gradient(circle at 12% 18%, rgba(14,165,233,.1), transparent 22rem), linear-gradient(180deg, #111217 0%, #08090c 42%, #050507 100%); }
        a { color: inherit; }
        .shell { min-height: 100vh; display: grid; grid-template-columns: 310px minmax(0, 1fr); }
        .side { padding: 28px; color: #fff; display: flex; flex-direction: column; gap: 28px; border-right: 1px solid var(--line); background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.025)), rgba(9,10,14,.92); backdrop-filter: blur(24px); box-shadow: 0 24px 70px rgba(0,0,0,.34); }
        .brand { display: flex; gap: 12px; align-items: center; }
        .brand img { width: 48px; height: 48px; border-radius: 16px; background: #fff; box-shadow: 0 18px 34px rgba(0,0,0,.32); }
        .brand strong { display: block; font-size: 1.1rem; }
        .brand span { display: block; margin-top: 2px; color: rgba(255,255,255,.62); font-size: .9rem; line-height: 1.35; }
        .steps { display: grid; gap: 8px; }
        .step { border: 1px solid transparent; border-radius: .85rem; padding: 10px 12px; color: rgba(255,255,255,.62); font-size: .93rem; background: rgba(255,255,255,.035); }
        .step.active { background: linear-gradient(90deg, rgba(249,115,22,.18), rgba(14,165,233,.1)), rgba(255,255,255,.08); color: #fff; border-color: rgba(255,255,255,.16); font-weight: 700; }
        .side-footer { margin-top: auto; color: #9ca3af; font-size: .82rem; }
        .main { padding: 32px; display: flex; align-items: center; }
        .panel { width: min(960px, 100%); margin: 0 auto; background: linear-gradient(135deg, rgba(255,255,255,.1), rgba(255,255,255,.035)), var(--panel); border: 1px solid var(--line); border-radius: 24px; box-shadow: 0 24px 70px rgba(0,0,0,.28); overflow: hidden; backdrop-filter: blur(24px); }
        .panel-head { padding: 24px 28px; border-bottom: 1px solid var(--line); display: flex; gap: 20px; justify-content: space-between; align-items: flex-start; }
        h1 { margin: 0; font-size: 1.55rem; line-height: 1.2; letter-spacing: 0; }
        p { margin: .55rem 0 0; color: var(--muted); line-height: 1.55; }
        .lang-switch { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .content { padding: 28px; }
        .grid { display: grid; gap: 16px; }
        .grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        .table th, .table td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        .table th { color: rgba(255,255,255,.46); font-size: .78rem; text-transform: uppercase; font-weight: 800; }
        .badge { display: inline-flex; min-width: 70px; justify-content: center; border-radius: 999px; padding: 4px 9px; font-size: .78rem; font-weight: 800; }
        .badge.ok { color: var(--ok); background: rgba(34,197,94,.12); }
        .badge.fail { color: var(--bad); background: rgba(244,63,94,.12); }
        label { display: grid; gap: 7px; font-weight: 700; color: rgba(255,255,255,.82); }
        input, select { width: 100%; min-height: 42px; border: 1px solid rgba(255,255,255,.11); border-radius: .85rem; padding: 9px 11px; font: inherit; background: rgba(5,5,7,.58); color: #fff; }
        input:focus, select:focus { outline: 4px solid rgba(249,115,22,.12); border-color: rgba(249,115,22,.58); background: rgba(5,5,7,.76); }
        .actions { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; padding-top: 22px; }
        button, .button { min-height: 42px; border: 1px solid transparent; border-radius: 999px; padding: 9px 16px; font: inherit; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: transform .16s ease, background-color .16s ease, border-color .16s ease; }
        button:hover, .button:hover { transform: translateY(-1px); }
        .primary { background: var(--brand); color: var(--accent-contrast); box-shadow: 0 16px 34px rgba(249,115,22,.18); }
        .secondary { background: rgba(255,255,255,.06); color: rgba(255,255,255,.78); border-color: rgba(255,255,255,.11); }
        .alert { border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; line-height: 1.5; }
        .alert.error { background: rgba(244,63,94,.12); color: #fecdd3; border: 1px solid rgba(244,63,94,.2); }
        .alert.notice { background: rgba(34,197,94,.12); color: #bbf7d0; border: 1px solid rgba(34,197,94,.2); }
        .summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .summary section { border: 1px solid var(--line); border-radius: 1rem; padding: 14px; background: var(--soft); }
        .summary h2 { margin: 0 0 10px; font-size: .95rem; }
        .summary dl { margin: 0; display: grid; gap: 8px; }
        .summary dt { color: var(--muted); font-size: .78rem; font-weight: 800; text-transform: uppercase; }
        .summary dd { margin: 2px 0 0; overflow-wrap: anywhere; }
        body { background: radial-gradient(circle at 78% 0%, rgba(255,255,255,.06), transparent 24rem), radial-gradient(circle at 12% 18%, rgba(255,255,255,.05), transparent 22rem), linear-gradient(180deg, #111217 0%, #08090c 42%, #050507 100%); }
        .shell { grid-template-columns: 16.5rem minmax(0, 1fr); }
        .side { padding: 1.8rem 1.25rem; border-color: rgba(255,255,255,.06); background: linear-gradient(90deg, rgba(255,255,255,.035), transparent 58%), rgba(9,12,15,.96); box-shadow: 22px 0 70px rgba(0,0,0,.38); }
        .brand { gap: .7rem; }
        .brand img { width: 48px; height: 48px; border-radius: .35rem; background: rgba(245,190,25,.08); box-shadow: none; }
        .brand strong { color: #fff; font-size: 1.3rem; font-weight: 800; }
        .brand span { margin-top: 0; color: rgba(255,255,255,.58); font-size: .82rem; }
        .steps { gap: .5rem; border-top: 1px solid rgba(255,255,255,.08); padding-top: 1.2rem; }
        .step { min-height: 3.25rem; display: flex; align-items: center; border: 1px solid transparent; border-radius: .45rem; background: transparent; padding: 0 .95rem; color: rgba(255,255,255,.82); font-weight: 750; }
        .step.active { border-color: rgba(255,255,255,.08); background: rgba(255,255,255,.035); color: var(--brand); box-shadow: none; }
        .main { align-items: flex-start; padding: 1.75rem 2.25rem; }
        .panel { width: min(96rem, 100%); border-radius: .65rem; border-color: rgba(255,255,255,.09); background: linear-gradient(180deg, rgba(255,255,255,.072), rgba(255,255,255,.04)), rgba(255,255,255,.055); box-shadow: inset 0 1px 0 rgba(255,255,255,.055), 0 18px 42px rgba(0,0,0,.22); }
        .panel-head { border-color: rgba(255,255,255,.08); }
        h1 { font-size: clamp(1.75rem, 3vw, 2.35rem); font-weight: 850; }
        input, select { border-radius: .45rem; border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.075); }
        input:focus, select:focus { outline: 3px solid rgba(249,115,22,.1); border-color: rgba(249,115,22,.42); background: rgba(255,255,255,.095); }
        .summary section { border-radius: .55rem; background: rgba(255,255,255,.055); }
        @media (max-width: 820px) { .shell { grid-template-columns: 1fr; } .side { padding: 20px; } .main { padding: 18px; align-items: stretch; } .panel-head { display: grid; } .grid.two, .summary { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="side">
            <div class="brand">
                <img src="branding/shelfvault-icon-192.png" alt="ShelfVault">
                <div>
                    <strong>ShelfVault</strong>
                    <span><?= installer_e($messages['subtitle']) ?></span>
                </div>
            </div>
            <nav class="steps" aria-label="Setup steps">
                <?php foreach (['language', 'requirements', 'database', 'admin', 'review'] as $name): ?>
                    <div class="step <?= $step === $name ? 'active' : '' ?>"><?= installer_e($messages['steps'][$name]) ?></div>
                <?php endforeach; ?>
            </nav>
            <div class="side-footer"><?= installer_e($messages['version']) ?> <?= installer_e($version) ?></div>
        </aside>
        <main class="main">
            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h1><?= installer_e(installer_step_title($messages, $step)) ?></h1>
                        <p><?= installer_e(installer_step_intro($messages, $step)) ?></p>
                    </div>
                    <?php if ($step !== 'installed'): ?>
                        <form method="post" action="<?= installer_e($script) ?>" class="lang-switch">
                            <input type="hidden" name="_token" value="<?= installer_e($token) ?>">
                            <input type="hidden" name="action" value="language">
                            <button class="<?= $locale === 'en' ? 'primary' : 'secondary' ?>" type="submit" name="locale" value="en"><?= installer_e($messages['language']['english']) ?></button>
                            <button class="<?= $locale === 'fr' ? 'primary' : 'secondary' ?>" type="submit" name="locale" value="fr"><?= installer_e($messages['language']['french']) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="content">
                    <?php foreach ($errors as $error): ?>
                        <div class="alert error"><?= installer_e($error) ?></div>
                    <?php endforeach; ?>
                    <?php if ($notice !== null): ?>
                        <div class="alert notice"><?= installer_e($notice) ?></div>
                    <?php endif; ?>

                    <?php if ($step === 'language'): ?>
                        <form method="post" action="<?= installer_e($script) ?>" class="grid">
                            <input type="hidden" name="_token" value="<?= installer_e($token) ?>">
                            <input type="hidden" name="action" value="language">
                            <div class="grid two">
                                <button class="primary" type="submit" name="locale" value="en"><?= installer_e($messages['language']['english']) ?></button>
                                <button class="secondary" type="submit" name="locale" value="fr"><?= installer_e($messages['language']['french']) ?></button>
                            </div>
                        </form>
                    <?php elseif ($step === 'requirements'): ?>
                        <?php installer_render_requirements($messages, $checks); ?>
                        <form method="post" action="<?= installer_e($script) ?>" class="actions">
                            <input type="hidden" name="_token" value="<?= installer_e($token) ?>">
                            <input type="hidden" name="action" value="requirements">
                            <a class="button secondary" href="<?= installer_e($script) ?>"><?= installer_e($messages['requirements']['refresh']) ?></a>
                            <button class="primary" type="submit"><?= installer_e($messages['requirements']['continue']) ?></button>
                        </form>
                    <?php elseif ($step === 'database'): ?>
                        <?php $database = installer_database_form($state); ?>
                        <form method="post" action="<?= installer_e($script) ?>" class="grid">
                            <input type="hidden" name="_token" value="<?= installer_e($token) ?>">
                            <input type="hidden" name="action" value="database">
                            <div class="grid two">
                                <label><?= installer_e($messages['database']['host']) ?><input name="host" value="<?= installer_e($database['host']) ?>" required autocomplete="off"></label>
                                <label><?= installer_e($messages['database']['port']) ?><input name="port" value="<?= installer_e($database['port']) ?>" required inputmode="numeric"></label>
                                <label><?= installer_e($messages['database']['database']) ?><input name="database" value="<?= installer_e($database['database']) ?>" required autocomplete="off"></label>
                                <label><?= installer_e($messages['database']['username']) ?><input name="username" value="<?= installer_e($database['username']) ?>" required autocomplete="off"></label>
                            </div>
                            <label><?= installer_e($messages['database']['password']) ?><input name="password" type="password" autocomplete="off"></label>
                            <div class="actions"><button class="primary" type="submit"><?= installer_e($messages['database']['submit']) ?></button></div>
                        </form>
                    <?php elseif ($step === 'admin'): ?>
                        <form method="post" action="<?= installer_e($script) ?>" class="grid">
                            <input type="hidden" name="_token" value="<?= installer_e($token) ?>">
                            <input type="hidden" name="action" value="admin">
                            <div class="grid two">
                                <label><?= installer_e($messages['admin']['app_name']) ?><input name="app_name" value="ShelfVault" required maxlength="80"></label>
                                <label><?= installer_e($messages['admin']['app_url']) ?><input name="app_url" value="<?= installer_e(installer_detect_app_url()) ?>" required maxlength="255"></label>
                                <label><?= installer_e($messages['admin']['login']) ?><input name="login" required minlength="3" maxlength="40" autocomplete="username"></label>
                                <label><?= installer_e($messages['admin']['email']) ?><input name="email" type="email" required maxlength="255" autocomplete="email"></label>
                                <label><?= installer_e($messages['admin']['password']) ?><input name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"></label>
                                <label><?= installer_e($messages['admin']['password_confirmation']) ?><input name="password_confirmation" type="password" required minlength="12" maxlength="128" autocomplete="new-password"></label>
                                <label><?= installer_e($messages['admin']['locale']) ?><select name="preferred_locale"><option value="en">English</option><option value="fr">Francais</option></select></label>
                            </div>
                            <div class="actions"><button class="primary" type="submit"><?= installer_e($messages['admin']['submit']) ?></button></div>
                        </form>
                    <?php elseif ($step === 'review'): ?>
                        <?php installer_render_review($state, $messages); ?>
                        <form method="post" action="<?= installer_e($script) ?>" class="actions">
                            <input type="hidden" name="_token" value="<?= installer_e($token) ?>">
                            <input type="hidden" name="action" value="install">
                            <button class="primary" type="submit"><?= installer_e($messages['review']['submit']) ?></button>
                        </form>
                    <?php elseif ($step === 'installed'): ?>
                        <div class="grid">
                            <p><?= installer_e($messages['installed']['intro']) ?></p>
                            <p><strong><?= installer_e($messages['installed']['lock']) ?>:</strong> <?= installer_e($lockPath) ?></p>
                            <div class="actions">
                                <a class="button secondary" href="/"><?= installer_e($messages['installed']['home']) ?></a>
                                <a class="button primary" href="/admin/login"><?= installer_e($messages['installed']['admin']) ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
    <?php
}

function installer_step_title(array $messages, string $step): string
{
    return match ($step) {
        'requirements' => $messages['requirements']['title'],
        'database' => $messages['database']['title'],
        'admin' => $messages['admin']['title'],
        'review' => $messages['review']['title'],
        'installed' => $messages['installed']['title'],
        default => $messages['language']['title'],
    };
}

function installer_step_intro(array $messages, string $step): string
{
    return match ($step) {
        'requirements' => $messages['requirements']['intro'],
        'database' => $messages['database']['intro'],
        'admin' => $messages['admin']['intro'],
        'review' => $messages['review']['intro'],
        'installed' => $messages['installed']['intro'],
        default => $messages['language']['intro'],
    };
}

/**
 * @param  array{system: array<int, array<string, mixed>>, permissions: array<int, array<string, mixed>>}  $checks
 */
function installer_render_requirements(array $messages, array $checks): void
{
    ?>
    <div class="grid">
        <section>
            <h2><?= installer_e($messages['requirements']['system']) ?></h2>
            <table class="table">
                <thead><tr><th><?= installer_e($messages['requirements']['name']) ?></th><th><?= installer_e($messages['requirements']['required']) ?></th><th><?= installer_e($messages['requirements']['current']) ?></th><th><?= installer_e($messages['requirements']['status']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($checks['system'] as $check): ?>
                        <tr><td><?= installer_e($check['name']) ?></td><td><?= installer_e($check['required']) ?></td><td><?= installer_e($check['current']) ?></td><td><?= installer_badge((bool) $check['passes'], $messages) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <section>
            <h2><?= installer_e($messages['requirements']['permissions']) ?></h2>
            <table class="table">
                <thead><tr><th><?= installer_e($messages['requirements']['path']) ?></th><th><?= installer_e($messages['requirements']['current']) ?></th><th><?= installer_e($messages['requirements']['status']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($checks['permissions'] as $check): ?>
                        <tr><td><?= installer_e($check['name']) ?></td><td><?= installer_e($check['path']) ?></td><td><?= installer_badge((bool) $check['passes'], $messages) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
    <?php
}

function installer_badge(bool $passes, array $messages): string
{
    $class = $passes ? 'ok' : 'fail';
    $label = $passes ? $messages['requirements']['ok'] : $messages['requirements']['failed'];

    return '<span class="badge '.$class.'">'.installer_e($label).'</span>';
}

/**
 * @return array<string, string>
 */
function installer_database_form(array $state): array
{
    return array_merge([
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'shelfvault',
        'username' => 'shelfvault',
        'password' => '',
    ], is_array($state['database_form'] ?? null) ? $state['database_form'] : []);
}

function installer_render_review(array $state, array $messages): void
{
    $database = $state['database'] ?? [];
    $admin = $state['admin'] ?? [];
    $settings = $state['settings'] ?? [];
    ?>
    <div class="summary">
        <section>
            <h2><?= installer_e($messages['review']['database']) ?></h2>
            <dl>
                <div><dt>Host</dt><dd><?= installer_e((string) ($database['host'] ?? '')) ?>:<?= installer_e((string) ($database['port'] ?? '')) ?></dd></div>
                <div><dt>Database</dt><dd><?= installer_e((string) ($database['database'] ?? '')) ?></dd></div>
                <div><dt>User</dt><dd><?= installer_e((string) ($database['username'] ?? '')) ?></dd></div>
                <div><dt>Password</dt><dd><?= installer_e($messages['review']['secret_saved']) ?></dd></div>
            </dl>
        </section>
        <section>
            <h2><?= installer_e($messages['review']['admin']) ?></h2>
            <dl>
                <div><dt>Login</dt><dd><?= installer_e((string) ($admin['login'] ?? '')) ?></dd></div>
                <div><dt>Email</dt><dd><?= installer_e((string) ($admin['email'] ?? '')) ?></dd></div>
                <div><dt>Password</dt><dd><?= installer_e($messages['review']['secret_saved']) ?></dd></div>
            </dl>
        </section>
        <section>
            <h2><?= installer_e($messages['review']['settings']) ?></h2>
            <dl>
                <div><dt>APP_NAME</dt><dd><?= installer_e((string) ($settings['app_name'] ?? '')) ?></dd></div>
                <div><dt>APP_URL</dt><dd><?= installer_e((string) ($settings['app_url'] ?? '')) ?></dd></div>
                <div><dt>Locale</dt><dd><?= installer_e((string) ($settings['app_locale'] ?? '')) ?></dd></div>
            </dl>
        </section>
    </div>
    <?php
}

function installer_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
