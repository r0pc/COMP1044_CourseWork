<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require dirname(__DIR__) . '/config.php';

/** Return the singleton PDO connection, opening it on first call. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $config;
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

/** Return the configured application name for page titles and headings. */
function app_name(): string
{
    global $config;
    return $config['app']['name'];
}

/** Send an HTTP redirect to the given relative path and stop execution. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Return the logged-in user session payload, or null when no user is logged in. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Store a one-time message (success or error) to display on the next page load. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/** Read and clear the pending flash message, if any. */
function consume_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/** Escape untrusted values before rendering them inside HTML. */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** True when the current session belongs to an Admin user. */
function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'Admin';
}

/** True when the current session belongs to an Assessor user. */
function is_assessor(): bool
{
    return (current_user()['role'] ?? '') === 'Assessor';
}

/** Enforce that a user is logged in, otherwise redirect to the login page. */
function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please log in first.');
        redirect('index.php');
    }
}

/** Enforce that the logged-in user has the Admin role. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Admin access is required for that page.');
        redirect('student_management.php');
    }
}

/**
 * Return the per-session CSRF token, creating one on first access.
 * Used to protect every POST form against cross-site request forgery.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden input carrying the CSRF token; call inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '" />';
}

/** Abort the current POST request if the submitted CSRF token does not match the session token. */
function require_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($submitted) || $expected === '' || !hash_equals($expected, $submitted)) {
        flash('error', 'Your session has expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

function fetch_assessors(): array
{
    $stmt = db()->query("SELECT user_id, full_name, username FROM users WHERE role = 'Assessor' ORDER BY full_name");
    return $stmt->fetchAll();
}

function fetch_dashboard_stats(?int $assessorId = null): array
{
    $pdo = db();
    $params = [];
    $where = '';

    if ($assessorId !== null) {
        $where = ' WHERE i.assessor_id = :assessor_id ';
        $params['assessor_id'] = $assessorId;
    }

    $studentSql = 'SELECT COUNT(*) FROM internships i' . $where;
    $studentStmt = $pdo->prepare($studentSql);
    $studentStmt->execute($params);
    $totalStudents = (int) $studentStmt->fetchColumn();

    $markSql = 'SELECT COUNT(a.assessment_id) AS assessed_count, AVG(a.final_mark) AS average_mark, MAX(a.final_mark) AS highest_mark
        FROM internships i
        LEFT JOIN assessments a ON a.internship_id = i.internship_id' . $where;
    $markStmt = $pdo->prepare($markSql);
    $markStmt->execute($params);
    $result = $markStmt->fetch() ?: [];

    return [
        'total_students' => $totalStudents,
        'assessed_count' => (int) ($result['assessed_count'] ?? 0),
        'average_mark' => $result['average_mark'] !== null ? number_format((float) $result['average_mark'], 2) : '0.00',
        'highest_mark' => $result['highest_mark'] !== null ? number_format((float) $result['highest_mark'], 2) : '0.00',
    ];
}
