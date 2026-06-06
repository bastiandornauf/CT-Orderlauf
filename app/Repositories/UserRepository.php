<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Helpers\UserRole;
use PDO;

final class UserRepository
{
    public function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, username, display_name, email, role, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array{id:int,username:string,email:?string,role:string,created_at:string}> */
    public function allOrdered(): array
    {
        $rows = Database::pdo()->query(
            'SELECT id, username, display_name, email, role, created_at FROM users ORDER BY username ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE username = ?';
        $params = [$username];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function countAdmins(?int $excludeUserId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE role = ?';
        $params = [UserRole::ADMIN];
        if ($excludeUserId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeUserId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function create(
        string $username,
        ?string $displayName,
        ?string $email,
        string $role,
        string $passwordHash
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (username, display_name, password_hash, email, role) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $username,
            self::normalizeDisplayName($displayName),
            $passwordHash,
            $email ?: null,
            $role,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(
        int $id,
        ?string $displayName,
        ?string $email,
        string $role,
        ?string $passwordHash
    ): void {
        $dn = self::normalizeDisplayName($displayName);
        if ($passwordHash !== null) {
            $stmt = Database::pdo()->prepare(
                'UPDATE users SET display_name = ?, email = ?, role = ?, password_hash = ? WHERE id = ?'
            );
            $stmt->execute([$dn, $email ?: null, $role, $passwordHash, $id]);
            return;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET display_name = ?, email = ?, role = ? WHERE id = ?'
        );
        $stmt->execute([$dn, $email ?: null, $role, $id]);
    }

    public function updateDisplayName(int $id, ?string $displayName): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET display_name = ? WHERE id = ?');
        $stmt->execute([self::normalizeDisplayName($displayName), $id]);
    }

    private static function normalizeDisplayName(?string $displayName): ?string
    {
        $s = trim((string) $displayName);
        if ($s === '') {
            return null;
        }
        if (strlen($s) > 128) {
            $s = substr($s, 0, 128);
        }

        return $s;
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
}
