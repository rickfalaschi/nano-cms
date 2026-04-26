<?php

declare(strict_types=1);

namespace Nano;

use Nano\Models\User;

final class Auth
{
    private Database $db;
    private Session $session;
    private ?User $cachedUser = null;

    public function __construct(Database $db, Session $session)
    {
        $this->db = $db;
        $this->session = $session;
    }

    public function attempt(string $email, string $password): bool
    {
        $row = $this->db->fetch(
            'SELECT * FROM users WHERE email = ? LIMIT 1',
            [strtolower(trim($email))]
        );

        if ($row === null) {
            return false;
        }

        if (!password_verify($password, $row['password_hash'])) {
            return false;
        }

        $this->session->regenerate();
        $this->session->put('user_id', (int) $row['id']);
        $this->cachedUser = User::fromRow($row);

        return true;
    }

    public function check(): bool
    {
        return $this->session->has('user_id');
    }

    public function user(): ?User
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $userId = $this->session->get('user_id');
        if (!is_int($userId) && !is_numeric($userId)) {
            return null;
        }

        $row = $this->db->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [(int) $userId]);
        if ($row === null) {
            $this->session->forget('user_id');
            return null;
        }

        $this->cachedUser = User::fromRow($row);
        return $this->cachedUser;
    }

    public function logout(): void
    {
        $this->session->forget('user_id');
        $this->session->regenerate();
        $this->cachedUser = null;
    }
}
