<?php
// Shared validation for Superadmin mutations. Apply migration before using pages.
function management_token(): string {
    if (empty($_SESSION['management_csrf'])) $_SESSION['management_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['management_csrf'];
}
function management_csrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(management_token(), $_POST['csrf'])) {
        throw new InvalidArgumentException('Your form expired. Reload the page and try again.');
    }
}
function management_text(string $key, int $max, bool $required = true): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException('Invalid field: ' . $key);
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (($required && $value === '') || strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        throw new InvalidArgumentException('Check ' . str_replace('_', ' ', $key) . ' (maximum ' . $max . ' bytes).');
    }
    return $value;
}
function management_id(string $key): int {
    $id = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$id) throw new InvalidArgumentException('Select a valid ' . str_replace('_', ' ', $key) . '.');
    return $id;
}
function management_capacity(PDO $pdo, int $branchId, int $exclude = 0): void {
    // Lock branch rows before user rows in every staffing mutation.
    $q = $pdo->prepare('SELECT hr_capacity FROM branches WHERE id=? AND is_deleted=0 FOR UPDATE');
    $q->execute([$branchId]); $capacity = $q->fetchColumn();
    if ($capacity === false) throw new InvalidArgumentException('Select an active branch.');
    $q = $pdo->prepare("SELECT COUNT(*) FROM user_branch_assignments a JOIN users u ON u.id=a.user_id WHERE a.branch_id=? AND u.role='hr' AND u.status='active' AND u.id<>?");
    $q->execute([$branchId,$exclude]);
    if ((int)$q->fetchColumn() >= (int)$capacity) throw new InvalidArgumentException('This branch has reached its active HR limit. Increase its capacity in Branches / Map or disable another HR account first.');
}
function management_date(string $key): string {
    $value=management_text($key,10);
    $date=DateTime::createFromFormat('!Y-m-d',$value);
    if (!$date || $date->format('Y-m-d')!==$value) throw new InvalidArgumentException('Enter a valid ' . str_replace('_',' ',$key) . '.');
    return $value;
}
function management_time(string $key, bool $required=true): ?string {
    $value=management_text($key,8,$required);
    if (!$required && $value==='') return null;
    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D',$value)) throw new InvalidArgumentException('Enter a valid ' . str_replace('_',' ',$key) . '.');
    return strlen($value)===5 ? $value.':00' : $value;
}
function management_schema(PDO $pdo): void {
    try {
        $pdo->query('SELECT first_name,last_name FROM users LIMIT 0');
        $pdo->query('SELECT hr_capacity,address_line,barangay,city,province,postal_code FROM branches LIMIT 0');
    } catch (PDOException $ex) {
        http_response_code(503);
        exit('Database upgrade required. Back up your database, then import database/migration_superadmin_management.sql once using phpMyAdmin. Do not reimport the full database on an existing installation.');
    }
}
