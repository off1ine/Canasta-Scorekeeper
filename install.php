#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This installer must be run from the command line.\n";
    echo "Usage: php install.php\n";
    exit(1);
}

echo "Scorekeeper installer\n";
echo "=====================\n\n";

$errors = [];
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    $errors[] = "PHP 8.1+ required (found " . PHP_VERSION . ").";
}
foreach (['pdo', 'pdo_mysql'] as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Missing PHP extension: $ext";
    }
}
if (!is_writable(__DIR__)) {
    $errors[] = "Directory not writable: " . __DIR__;
}
if ($errors) {
    echo "Requirements failed:\n";
    foreach ($errors as $e) echo "  - $e\n";
    exit(1);
}
echo "Requirements OK.\n\n";

function prompt(string $label, string $default = ''): string {
    $suffix = $default !== '' ? " [$default]" : '';
    echo $label . $suffix . ': ';
    $line = fgets(STDIN);
    if ($line === false) { echo "\n"; exit(1); }
    $line = trim($line);
    return $line === '' ? $default : $line;
}

function prompt_secret(string $label): string {
    echo $label . ': ';
    $isTty = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
    if ($isTty) @shell_exec('stty -echo 2>/dev/null');
    $line = fgets(STDIN);
    if ($isTty) { @shell_exec('stty echo 2>/dev/null'); echo "\n"; }
    if ($line === false) exit(1);
    return rtrim($line, "\r\n");
}

echo "Enter MySQL connection details. The database must already exist.\n\n";
$host = prompt('DB host', 'localhost');
$name = prompt('DB name', 'scorekeeper');
$user = prompt('DB user', 'root');
$pass = prompt_secret('DB password');

echo "\nTesting connection...\n";
try {
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    echo "Failed: " . $e->getMessage() . "\n";
    echo "Hint: create the database first with:\n";
    echo "  mysql -u root -p -e \"CREATE DATABASE {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"\n";
    exit(1);
}
echo "Connected.\n\n";

$schemaPath = __DIR__ . '/schema.sql';
if (!is_readable($schemaPath)) {
    echo "schema.sql not readable at {$schemaPath}\n";
    exit(1);
}
echo "Applying schema.sql...\n";
$sql = (string)file_get_contents($schemaPath);
$sql = (string)preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', $sql)));
try {
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
    }
} catch (Throwable $e) {
    echo "Schema failed: " . $e->getMessage() . "\n";
    exit(1);
}
echo "Schema applied.\n\n";

$configPath = __DIR__ . '/db.local.php';
if (file_exists($configPath)) {
    echo "db.local.php already exists. Overwrite? [y/N] ";
    $answer = strtolower(trim((string)fgets(STDIN)));
    if ($answer !== 'y' && $answer !== 'yes') {
        echo "Aborted. (Schema was applied but config was not written.)\n";
        exit(1);
    }
}

$contents = "<?php\n" .
    "\$DB_HOST = " . var_export($host, true) . ";\n" .
    "\$DB_NAME = " . var_export($name, true) . ";\n" .
    "\$DB_USER = " . var_export($user, true) . ";\n" .
    "\$DB_PASS = " . var_export($pass, true) . ";\n";

if (@file_put_contents($configPath, $contents, LOCK_EX) === false) {
    echo "Could not write {$configPath}\n";
    exit(1);
}
@chmod($configPath, 0600);
echo "Wrote db.local.php (mode 0600).\n\n";

echo "Done.\n";
echo "Next: point your web server at this directory and open the app —\n";
echo "you'll be prompted to create the first admin account.\n";
