<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function require_end_user(): array {
    $current = current_user();
    if (!$current) {
        redirect_to('../index.php');
    }

    $role = strtolower((string) ($current['role'] ?? ''));
    $allowed = ['user', 'student', 'faculty researcher'];
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        exit('This friendly user portal is only for end-user accounts.');
    }

    return $current;
}

function user_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = array_map(static fn($part) => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2));
    return implode('', $letters) ?: 'U';
}
