<?php

declare(strict_types=1);

namespace Nano\Models;

use Nano\App;

final class User
{
    public int $id;
    public string $email;
    public string $name;
    public string $role;
    public string $createdAt;

    public static function fromRow(array $row): self
    {
        $u = new self();
        $u->id = (int) $row['id'];
        $u->email = (string) $row['email'];
        $u->name = (string) $row['name'];
        $u->role = (string) $row['role'];
        $u->createdAt = (string) $row['created_at'];
        return $u;
    }

    public static function find(int $id): ?self
    {
        $row = App::instance()->db->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
        return $row === null ? null : self::fromRow($row);
    }

    public static function findByEmail(string $email): ?self
    {
        $row = App::instance()->db->fetch(
            'SELECT * FROM users WHERE email = ? LIMIT 1',
            [strtolower(trim($email))]
        );
        return $row === null ? null : self::fromRow($row);
    }

    /**
     * @return list<self>
     */
    public static function all(int $limit = 200, int $offset = 0): array
    {
        $rows = App::instance()->db->fetchAll(
            'SELECT * FROM users ORDER BY created_at ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        return array_map(fn($r) => self::fromRow($r), $rows);
    }

    public static function count(): int
    {
        return (int) App::instance()->db->fetchColumn('SELECT COUNT(*) FROM users');
    }

    public static function create(string $email, string $password, string $name, string $role = 'admin'): self
    {
        $email = strtolower(trim($email));
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id = App::instance()->db->insert('users', [
            'email' => $email,
            'password_hash' => $hash,
            'name' => $name,
            'role' => $role,
        ]);
        return self::find($id) ?? throw new \RuntimeException('Failed to create user.');
    }

    public function save(array $data): void
    {
        $update = [];
        if (array_key_exists('name', $data))  $update['name']  = $data['name'];
        if (array_key_exists('email', $data)) $update['email'] = strtolower(trim((string) $data['email']));
        if (array_key_exists('role', $data))  $update['role']  = $data['role'];
        if ($update !== []) {
            App::instance()->db->update('users', $update, ['id' => $this->id]);
        }
    }

    public function delete(): void
    {
        App::instance()->db->delete('users', ['id' => $this->id]);
    }

    public function setPassword(string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        App::instance()->db->update('users', ['password_hash' => $hash], ['id' => $this->id]);
    }
}
