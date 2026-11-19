<?php
defined('APP') || exit;

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = config('db', []);
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['host'] ?? 'localhost',
        (int) ($c['port'] ?? 3306),
        $c['name'] ?? ''
    );

    try {
        $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $ex) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit(config('debug')
            ? 'Database connection failed: ' . $ex->getMessage()
            : 'Database connection failed. Check the database settings in config.php.');
    }

    // Keep MySQL's NOW() in step with PHP's timezone.
    $pdo->exec('SET time_zone = ' . $pdo->quote((new DateTime())->format('P')));

    return $pdo;
}

/** Run a prepared statement. Every query in the app goes through here. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = [])
{
    return q($sql, $params)->fetchColumn();
}

/** Builds "?, ?, ?" for an IN (...) list. */
function placeholders(array $values): string
{
    return implode(', ', array_fill(0, count($values), '?'));
}

function table_exists(string $table): bool
{
    return (bool) q_val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
}
