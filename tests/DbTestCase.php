<?php

declare(strict_types=1);

namespace Tests;

use App\Helpers\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests gegen eine eigene Datenbank `ct_orderlauf_test`, frisch aus schema.sql.
 * Die lokale Prod-Kopie (`ct_orderlauf`) wird nie angefasst.
 * Läuft im Web-Container: docker compose exec web vendor/bin/phpunit
 */
abstract class DbTestCase extends TestCase
{
    private const DB = 'ct_orderlauf_test';

    private static bool $schemaLoaded = false;

    protected PDO $pdo;

    protected function setUp(): void
    {
        if (!defined('APP_DEBUG')) {
            define('APP_DEBUG', true);
        }
        $host = getenv('DB_HOST') ?: 'db';
        if (!self::$schemaLoaded) {
            $root = new PDO("mysql:host={$host};charset=utf8mb4", 'root', 'root_secret', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $root->exec('DROP DATABASE IF EXISTS ' . self::DB);
            $root->exec('CREATE DATABASE ' . self::DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $root->exec('USE ' . self::DB);
            $root->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
            Database::configure(['host' => $host, 'port' => 3306, 'name' => self::DB, 'user' => 'root', 'pass' => 'root_secret']);
            self::$schemaLoaded = true;
        }
        $this->pdo = Database::pdo();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['supplier_articles', 'item_supplier', 'items', 'suppliers', 'locations'] as $t) {
            $this->pdo->exec("TRUNCATE TABLE {$t}");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $this->pdo->exec("INSERT INTO locations (id, name) VALUES (1, 'TL')");
        $this->pdo->exec("INSERT INTO suppliers (id, name) VALUES (1, 'OGA'), (2, 'Pütz'), (3, 'Transgourmet')");
    }

    protected function insertItem(string $name, string $unit = 'Dose'): int
    {
        $this->pdo->prepare('INSERT INTO items (name, unit, location_id) VALUES (?, ?, 1)')->execute([$name, $unit]);

        return (int) $this->pdo->lastInsertId();
    }
}
