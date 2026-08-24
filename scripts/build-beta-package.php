<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root.'/VERSION'));
$dist = $root.'/dist';
$stage = $dist.'/package/ShelfVault-'.$version;
$zipPath = $dist.'/ShelfVault-'.$version.'.zip';
$latestZipPath = $dist.'/ShelfVault-beta.zip';
$manifestPath = $dist.'/update-manifest-'.$version.'.json';
$latestManifestPath = $dist.'/update-manifest-beta.json';

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required to build the beta archive.\n");
    exit(1);
}

$excludeDirectories = [
    '.git',
    '.github',
    '.idea',
    '.nova',
    '.phpunit.cache',
    '.vscode',
    '.zed',
    'bootstrap/cache',
    'database/factories',
    'dist',
    'docker',
    'node_modules',
    'public/hot',
    'public/storage',
    'scripts',
    'storage',
    'tests',
    'tickets',
    'vendor',
];

$excludeFiles = [
    '.DS_Store',
    '.dockerignore',
    '.editorconfig',
    '.env',
    '.env.backup',
    '.env.production',
    '.gitattributes',
    '.gitignore',
    '.npmrc',
    'AGENTS.md',
    '.phpunit.result.cache',
    'auth.json',
    'docker-compose.yml',
    'package-lock.json',
    'package.json',
    'phpunit.xml',
    'phpunit.xml.dist',
    'vite.config.js',
];

removeDirectory($dist);
mkdir($stage, 0755, true);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

foreach ($iterator as $fileInfo) {
    $source = $fileInfo->getPathname();
    $relative = str_replace('\\', '/', substr($source, strlen($root) + 1));

    if (shouldExclude($relative, $fileInfo->isDir(), $excludeDirectories, $excludeFiles)) {
        continue;
    }

    $target = $stage.'/'.$relative;

    if ($fileInfo->isDir()) {
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        continue;
    }

    if (! is_dir(dirname($target))) {
        mkdir(dirname($target), 0755, true);
    }

    copy($source, $target);
}

ensureDirectory($stage.'/storage/app/public');
ensureDirectory($stage.'/storage/framework/cache');
ensureDirectory($stage.'/storage/framework/cache/data');
ensureDirectory($stage.'/storage/framework/sessions');
ensureDirectory($stage.'/storage/framework/views');
ensureDirectory($stage.'/storage/logs');
ensureDirectory($stage.'/bootstrap/cache');

writeGitignore($stage.'/storage/app/public/.gitignore');
writeGitignore($stage.'/storage/framework/cache/.gitignore');
writeGitignore($stage.'/storage/framework/cache/data/.gitignore');
writeGitignore($stage.'/storage/framework/sessions/.gitignore');
writeGitignore($stage.'/storage/framework/views/.gitignore');
writeGitignore($stage.'/storage/logs/.gitignore');
writeGitignore($stage.'/bootstrap/cache/.gitignore');

if (! is_dir($stage.'/public/build')) {
    fwrite(STDERR, "Compiled assets are missing. Run npm run build before packaging.\n");
    exit(1);
}

runCommand('composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist', $stage);
removeDevelopmentArtifacts($stage);
removeFile($stage.'/bootstrap/cache/packages.php');
removeFile($stage.'/bootstrap/cache/services.php');

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create {$zipPath}\n");
    exit(1);
}

$stageIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

foreach ($stageIterator as $fileInfo) {
    $source = $fileInfo->getPathname();
    $relative = str_replace('\\', '/', substr($source, strlen($stage) + 1));

    if ($fileInfo->isDir()) {
        $directory = rtrim($relative, '/').'/';
        $zip->addEmptyDir($directory);
        setZipUnixMode($zip, $directory, 040755);
    } else {
        $zip->addFile($source, $relative);
        setZipUnixMode($zip, $relative, $fileInfo->isExecutable() ? 0100755 : 0100644);
    }
}

$zip->close();
copy($zipPath, $latestZipPath);

$checksum = hash_file('sha256', $zipPath);
$zipUrl = trim((string) getenv('SHELFVAULT_UPDATE_ZIP_URL'));

if ($zipUrl === '') {
    $zipUrl = 'https://example.com/shelfvault/ShelfVault-'.$version.'.zip';
}

$manifest = [
    'version' => $version,
    'tag_name' => 'v'.$version,
    'name' => 'ShelfVault '.$version,
    'html_url' => 'https://github.com/willi4mbe/shelfvault/releases/tag/v'.$version,
    'zip_url' => $zipUrl,
    'sha256' => $checksum,
    'notes' => readReleaseNotes($root),
    'minimum_php' => '8.3.0',
    'requires_migrations' => true,
];

file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
copy($manifestPath, $latestManifestPath);

echo $zipPath.PHP_EOL;
echo $manifestPath.PHP_EOL;

function shouldExclude(string $relative, bool $isDirectory, array $directories, array $files): bool
{
    $name = basename($relative);

    if (! $isDirectory && in_array($name, $files, true)) {
        return true;
    }

    foreach ($directories as $directory) {
        if ($relative === $directory || str_starts_with($relative, $directory.'/')) {
            return true;
        }
    }

    return false;
}

function ensureDirectory(string $path): void
{
    if (! is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function writeGitignore(string $path): void
{
    file_put_contents($path, "*\n!.gitignore\n");
}

function setZipUnixMode(ZipArchive $zip, string $path, int $mode): void
{
    $zip->setExternalAttributesName($path, ZipArchive::OPSYS_UNIX, $mode << 16);
}

function runCommand(string $command, string $cwd): void
{
    passthru('cd '.escapeshellarg($cwd).' && '.$command, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "Command failed: {$command}\n");
        exit($exitCode);
    }
}

function removeFile(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

function removeDevelopmentArtifacts(string $stage): void
{
    removeDirectory($stage.'/vendor/bin');

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stage.'/vendor', FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $fileInfo) {
        $name = $fileInfo->getBasename();
        $path = $fileInfo->getPathname();

        if ($fileInfo->isDir() && in_array($name, ['.git', '.github', 'test', 'tests'], true)) {
            removeDirectory($path);

            continue;
        }

        if ($fileInfo->isFile() && preg_match('/^(phpunit\.xml(\.dist)?|\.phpunit\.result\.cache)$/', $name) === 1) {
            unlink($path);
        }
    }
}

function removeDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $fileInfo) {
        $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
    }

    rmdir($path);
}

function readReleaseNotes(string $root): string
{
    $path = $root.'/RELEASE-NOTES-beta.md';

    if (! is_file($path)) {
        return 'ShelfVault beta update.';
    }

    return trim((string) file_get_contents($path)) ?: 'ShelfVault beta update.';
}
