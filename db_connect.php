<?php

$servername = "localhost";  // XAMPP는 항상 localhost(또는 127.0.0.1) 입니다.
$username = "team15";       // 본인 팀 ID (예: "team05")
$password = "team15";       // 본인 팀 패스워드 (예: "team05")
$dbname = "team15";         // 본인 팀 데이터베이스 이름 (예: "team05")

// === DB 연결 시도 ===
$conn = new mysqli($servername, $username, $password, $dbname);

// === 연결 성공/실패 확인 ===
if ($conn->connect_error) {
    die("DB 연결 실패: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
