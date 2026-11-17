<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    public function __construct(private PDO $pdo)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare('SELECT id, email, pass_hash, role FROM users WHERE email = :e');
        $stmt->execute([':e' => trim($email)]);
        $user = $stmt->fetch();

        if ($user === false || !password_verify($password, (string) $user['pass_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /** @return array{id:int,email:string,role:string}|null */
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function requireLogin(): void
    {
        if (!$this->check()) {
            header('Location: /login');
            exit;
        }
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['csrf'];
    }

    public function verifyCsrf(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}
