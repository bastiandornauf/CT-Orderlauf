<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use PDO;

final class SettingsRepository
{
    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT value FROM settings WHERE key_name = ?');
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (string) $v : $default;
    }

    public function set(string $key, ?string $value): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (key_name, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $stmt->execute([$key, $value]);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $rows = Database::pdo()->query('SELECT key_name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        return $rows ?: [];
    }
}
