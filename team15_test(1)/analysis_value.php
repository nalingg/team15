<?php

// 김남령

// analysis_value.php
// 가성비 선수 랭킹 분석 페이지

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

// 네비 active 용
$current_page = basename($_SERVER['PHP_SELF']);

$position_id = isset($_POST['position']) ? $_POST['position'] : '10';
$min_salary_display = isset($_POST['min_salary']) ? htmlspecialchars($_POST['min_salary']) : '';

$rows = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $position_id_query = $_POST['position'];
    $min_salary_query = ($_POST['min_salary'] === '') ? 0 : $_POST['min_salary'];

    $sql = "SELECT
                ROW_NUMBER() OVER (
                    ORDER BY p.salary / 4 / NULLIF(SUM(s.open_suc + s.backquick_suc), 0) ASC
                ) AS ranking,
                p.player_name,
                pp.position_Name,
                p.salary,
                SUM(s.open_suc + s.backquick_suc) AS total_points,
                (p.salary / 4 / NULLIF(SUM(s.open_suc + s.backquick_suc), 0)) AS cost_per_point
            FROM
                Player p
            JOIN
                Att_Stats s ON p.player_ID = s.player_ID
            JOIN
                Player_Position pp ON p.position_ID = pp.position_ID
            WHERE
                p.position_ID = ? AND p.salary >= ?
            GROUP BY
                p.player_ID, p.player_name, pp.position_Name, p.salary
            HAVING
                total_points > 0
            ORDER BY
                cost_per_point ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$position_id_query, $min_salary_query]);
        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        die("쿼리 실행 오류: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>가성비 선수 랭킹</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: white;
        }

        /* 네비 줄바꿈 방지 */
        .navbar-nav .nav-link {
            white-space: nowrap !important;
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        /* 본문 너비 */
        .content-container {
            max-width: 960px;
            margin: auto;
            padding: 20px;
        }

        /* 공통 카드 스타일 */
        .info-box, .filter-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        .filter-box {
            border-radius: 14px;
        }

        .page-title {
            font-weight: 700;
            color: var(--bs-primary);
        }

        table thead th { text-align: center; }
        table td { text-align: center; }
    </style>
</head>
<body>

<!-- 네비게이션 ) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm border-bottom border-primary"
     style="border-width: 3px !important;">
    <div class="container-fluid">

        <a class="navbar-brand fw-bold px-2" href="dashboard.php">
            V-League 스카우팅
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link <?= $current_page=='dashboard.php' ? 'active':'' ?>"
                       href="dashboard.php">대시보드</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $current_page=='analysis_value.php' ? 'active':'' ?>"
                       href="analysis_value.php">가성비 선수 랭킹</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $current_page=='score_accumulation.php' ? 'active':'' ?>"
                       href="score_accumulation.php">라운드별 누적 득점</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $current_page=='aggregate.php'?'active':'' ?>"
                       href="aggregate.php">범실 총합 분석</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page,['rollup.php','drilldown.php'])?'active':'' ?>"
                       href="rollup.php?metric=score">팀별 스탯 순위 분석</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page,['player_select.php','player_profile.php','mynotes.php'])?'active':'' ?>"
                       href="player_select.php">스카우팅 노트</a>
                </li>

            </ul>

            <div class="d-flex">
                <a href="logout.php" class="btn btn-outline-light btn-sm">로그아웃</a>
            </div>
        </div>
    </div>
</nav>


<!-- 본문 -->
<div class="content-container">

    <!-- 타이틀 박스 -->
    <div class="p-4 mb-5 info-box">
        <h2 class="page-title mb-2">가성비 선수 랭킹 분석</h2>
        <p class="text-muted mb-0">연봉 대비 득점 효율을 기준으로 선수 순위를 계산합니다.</p>
    </div>

    <!-- 필터 박스 -->
    <div class="p-4 mb-5 filter-box">
        <h5 class="mb-3 fw-bold">포지션 / 최소 연봉 기준 필터링</h5>

        <form method="POST" action="">
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label fw-bold">1. 포지션 선택</label>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_atk" value="10"
                            <?= $position_id == '10' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pos_atk">아웃사이드 히터 (OH)</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_op" value="20"
                            <?= $position_id == '20' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pos_op">아포짓 스파이커 (OP)</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position" id="pos_mb" value="30"
                            <?= $position_id == '30' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pos_mb">미들 블로커 (MB)</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="min_salary" class="form-label fw-bold">2. 최소 연봉 (백만원)</label>
                    <input type="text" class="form-control" id="min_salary" name="min_salary"
                           placeholder="예: 50 (미입력 시 0부터)"
                           value="<?= $min_salary_display ?>">
                </div>

            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-4">분석 실행</button>
            </div>
        </form>
    </div>


    <!-- 결과 테이블 -->
    <h4 class="mb-3 text-secondary">분석 결과</h4>

    <table class="table table-striped table-hover shadow-sm align-middle">
        <thead class="table-dark">
            <tr>
                <th>순위</th>
                <th>선수명</th>
                <th>포지션</th>
                <th>연봉 (백만원)</th>
                <th>24-25 총 득점</th>
                <th>가성비 (1점당 연봉)</th>
            </tr>
        </thead>

        <tbody>
        <?php
        if (!empty($rows)) {
            foreach ($rows as $row) {
        ?>
            <tr>
                <td><?= (int)$row['ranking'] ?></td>
                <td><?= htmlspecialchars($row['player_name']) ?></td>
                <td><?= htmlspecialchars($row['position_Name']) ?></td>
                <td><?= number_format($row['salary']) ?></td>
                <td><?= number_format($row['total_points']) ?></td>
                <td><?= number_format($row['cost_per_point'], 1) ?> 백만원</td>
            </tr>
        <?php
            }
        } else {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                echo "<tr><td colspan='6' class='text-center'>조건에 맞는 선수가 없습니다.</td></tr>";
            } else {
                echo "<tr><td colspan='6' class='text-center'>조건을 입력하고 '분석 실행' 버튼을 눌러주세요.</td></tr>";
            }
        }
        ?>
        </tbody>
    </table>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
