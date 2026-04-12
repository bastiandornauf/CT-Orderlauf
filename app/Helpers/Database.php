<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    /** @var array{host:string,port:int,name:string,user:string,pass:string} */
    private static array $config = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'ct_orderlauf',
        'user' => 'root',
        'pass' => '',
    ];

    /** @param array{host?:string,port?:int,name?:string,user?:string,pass?:string} $config */
    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                self::$config['host'],
                self::$config['port'],
                self::$config['name']
            );
            try {
                self::$pdo = new PDO($dsn, self::$config['user'], self::$config['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    throw $e;
                }
                throw new PDOException('Database connection failed.');
            }
        }
        return self::$pdo;
    }
}
