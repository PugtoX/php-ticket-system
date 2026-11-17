<?php
declare(strict_types=1);
// Usage: docker compose exec app php bin/create_user.php agent@example.com demo1234 agent
require __DIR__ . '/src/Db.php';

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$role = $argv[3] ?? 'agent';

if ($email === null || $password === null) {
    fwrite(STDERR, "usage: php bin/create_user.php <email> <password> [agent|admin]\n");
    exit(1);
}
if (!in_array($role, ['agent', 'admin'], true)) {
    fwrite(STDERR, "role must be agent or admin\n");
    exit(1);
}

$pdo = App\Db::conn();
$stmt = $pdo->prepare('INSERT INTO users (email, pass_hash, role) VALUES (:e, :h, :r)');
$stmt->execute([
    ':e' => $email,
    ':h' => password_hash($password, PASSWORD_DEFAULT),
    ':r' => $role,
]);
echo "created user {$email} ({$role})\n";
