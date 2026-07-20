<?php
// Database configuration using PDO
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'gvp_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // In production, avoid echoing errors. Here we throw to help debugging on local XAMPP.
        throw new RuntimeException('Database connection failed: ' . $e->getMessage());
    }
}
