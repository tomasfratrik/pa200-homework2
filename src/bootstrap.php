<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function appConfig(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $configPath = dirname(__DIR__) . '/config.php';

    $config = [
        'app_name' => 'Azure PaaS Todo App',
        'asset_base_url' => '',
        'db' => [
            'host' => '',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
    ];

    if (is_file($configPath)) {
        $fileConfig = require $configPath;

        if (!is_array($fileConfig) || !isset($fileConfig['db']) || !is_array($fileConfig['db'])) {
            throw new RuntimeException('Invalid config.php format.');
        }

        $config = array_replace_recursive($config, $fileConfig);
    }

    $config['app_name'] = envString('APP_NAME') ?? $config['app_name'];
    $config['asset_base_url'] = envString('ASSET_BASE_URL') ?? $config['asset_base_url'];
    $config['db']['host'] = envString('DB_HOST') ?? $config['db']['host'];
    $config['db']['port'] = envString('DB_PORT') ?? $config['db']['port'];
    $config['db']['database'] = envString('DB_NAME') ?? $config['db']['database'];
    $config['db']['username'] = envString('DB_USER') ?? $config['db']['username'];
    $config['db']['password'] = envString('DB_PASSWORD') ?? $config['db']['password'];
    $config['db']['charset'] = envString('DB_CHARSET') ?? $config['db']['charset'];
    $config['db']['ssl'] = envBool('DB_SSL') ?? $config['db']['ssl'];

    if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
        throw new RuntimeException('Invalid application configuration.');
    }

    return $config;
}

function envString(string $key): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if (!is_string($value) || $value === '') {
        return null;
    }

    return $value;
}

function envBool(string $key): ?bool
{
    $value = envString($key);

    if ($value === null) {
        return null;
    }

    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
}

function db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = appConfig();
    $db = $config['db'];

    $requiredKeys = ['host', 'port', 'database', 'username', 'password', 'charset'];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $db) || $db[$key] === '') {
            throw new RuntimeException("Missing database config key: {$key}");
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['database'],
        $db['charset']
    );

    try {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ((bool) ($db['ssl'] ?? true) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        }

        $pdo = new PDO($dsn, $db['username'], $db['password'], $options);
    } catch (\PDOException $exception) {
        throw new RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
    }

    return $pdo;
}

function assetUrl(string $path): string
{
    $config = appConfig();
    $assetBaseUrl = is_string($config['asset_base_url'] ?? null) ? trim($config['asset_base_url']) : '';
    $path = ltrim($path, '/');

    if ($assetBaseUrl !== '') {
        return rtrim($assetBaseUrl, '/') . '/' . $path;
    }

    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $publicRoot = realpath(dirname(__DIR__) . '/public');
    $prefix = ($documentRoot !== false && $publicRoot !== false && $documentRoot === $publicRoot) ? '' : '/public';

    return $prefix . '/' . $path;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $expectedToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !is_string($expectedToken) || !hash_equals($expectedToken, $submittedToken)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pullFlash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function redirectToHome(): never
{
    header('Location: /');
    exit();
}
