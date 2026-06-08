<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function student_allowed_roles(): array {
    return ['Student', 'Faculty Researcher', 'Research Coordinator', 'Extension Coordinator'];
}

function require_student(): array {
    $user = current_user();
    if (!$user) {
        redirect_to('../index.php');
    }

    if (!in_array((string) $user['role'], student_allowed_roles(), true)) {
        http_response_code(403);
        exit('You do not have access to the student portal.');
    }

    return $user;
}

function student_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = array_map(static fn($part) => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2));
    return implode('', $letters) ?: 'IR';
}
