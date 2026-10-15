<?php
// ============================================================
// Petal Moments - Database configuration
// ------------------------------------------------------------
// LOCAL (XAMPP): leave as-is.
// HOSTING (e.g. InfinityFree): replace the 4 values below with
// the MySQL details from your hosting control panel, then
// import database.sql via phpMyAdmin.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'petal_moments');
define('DB_USER', 'root');
define('DB_PASS', '');

function db() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }
}
