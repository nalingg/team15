<?php
session_start();
require_once 'db.php';   // PDO $pdo 사용

// 이미 로그인 돼 있으면 바로 대시보드로
if (isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// 로그인 폼 제출 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input_id = trim($_POST['user_id'] ?? '');
    $input_pw = trim($_POST['user_pw'] ?? '');

    if ($input_id === '' || $input_pw === '') {
        $error = '아이디와 비밀번호를 모두 입력해주세요.';
    } else {

        // 아이디가 숫자라고 가정
        $user_id_int = (int)$input_id;

        try {
            // Prepared Statement (PDO)
            $sql = "
                SELECT U.user_ID, U.team_ID, T.team_Name
                FROM user AS U
                LEFT JOIN team AS T ON U.team_ID = T.team_ID
                WHERE U.user_ID = :uid
                  AND U.user_PW = :upw
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':uid' => $user_id_int,
                ':upw' => $input_pw
            ]);

            $row = $stmt->fetch();

            if ($row) {
                // 로그인 성공 → 세션 저장
                $_SESSION['user_id']   = $row['user_ID'];
                $_SESSION['team_id']   = $row['team_ID'];
                $_SESSION['team_name'] = $row['team_Name'];

                header('Location: dashboard.php');
                exit;
            } else {
                $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
            }

        } catch (PDOException $e) {
            die("로그인 쿼리 오류: " . $e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>V-League 스카우팅 툴 로그인</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Inter 폰트 (rollup 동일) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        html, body {
            height: 100%;
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        .login-container {
            max-width: 380px;
            padding: 30px;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        h1 {
            font-weight: 700;
            font-size: 28px;
            color: #3b82f6;
            margin-bottom: 10px;
        }

        p.subtitle {
            color: #6b7280;
            font-size: 15px;
            margin-bottom: 25px;
        }

        .form-control {
            border-radius: 12px;
        }

        .btn-primary {
            background-color: #3b82f6;
            border-radius: 12px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #2563eb;
        }

        .error-box {
            border-radius: 10px;
            font-size: 14px;
        }

        .footer-text {
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>

<body class="d-flex justify-content-center align-items-center">

    <div class="login-container">

        <form action="login.php" method="post">

            <h1 class="text-center">V-League 스카우팅 툴</h1>
            <p class="subtitle text-center">감독 / 스카우터 전용 로그인</p>

            <?php if ($error): ?>
                <div class="alert alert-danger error-box py-2">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="form-floating mb-3">
                <input type="text"
                       class="form-control"
                       id="floatingInput"
                       name="user_id"
                       placeholder="User ID">
                <label for="floatingInput">User ID</label>
            </div>

            <div class="form-floating mb-2">
                <input type="password"
                       class="form-control"
                       id="floatingPassword"
                       name="user_pw"
                       placeholder="Password">
                <label for="floatingPassword">Password</label>
            </div>

            <button class="w-100 btn btn-lg btn-primary mt-2" type="submit">
                로그인
            </button>

            <p class="mt-4 text-center footer-text">© Team 15 Project</p>
        </form>

    </div>

</body>
</html>