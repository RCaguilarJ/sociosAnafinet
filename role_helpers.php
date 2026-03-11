<?php
function normalize_text_value(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $lower);
    if ($normalized === false) {
        $normalized = $lower;
    }
    $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);
    return $normalized ?? '';
}

function is_admin_role(string $role): bool
{
    $normalized = normalize_text_value($role);
    if ($normalized === '') {
        return false;
    }
    if ($normalized === 'admin' || $normalized === 'administrador') {
        return true;
    }
    return str_contains($normalized, 'admin');
}

function fetch_user_role(?PDO $pdo, ?int $userId): ?string
{
    if (!$pdo || !$userId) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (is_array($row) && isset($row['rol'])) {
            return (string)$row['rol'];
        }
    } catch (Throwable $e) {
        return null;
    }

    return null;
}
