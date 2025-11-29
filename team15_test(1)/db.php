<?php

// 김남령

// db.php
$host   = 'localhost';
$dbname = 'team15';
$user   = 'team15';
$pass   = 'team15';

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('DB 연결 실패: ' . $e->getMessage());
}
