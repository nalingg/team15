<?php

// 안지은, 오지송

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header("Location: login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_id = (int)$_SESSION['user_id'];
$team_id = (int)$_SESSION['team_id'];

$sql = "SELECT team_Name FROM team WHERE team_ID = :team_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':team_id' => $team_id]);
$row = $stmt->fetch();
$team_name = $row ? $row['team_Name'] : ($team_id . "번 팀");
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>메인 대시보드</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
    body {
        padding: 0;
        font-family: 'Inter', sans-serif;
        background-color: #ffffff;
    }

    /* 네비바 글자 줄바꿈 방지 */
    .navbar-nav .nav-link {
        white-space: nowrap !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    /* 본문 영역만 너비 제한 */
    .content-container {
        max-width: 960px;
        margin: auto;
        padding: 20px;
    }

    .welcome-box {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 16px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.06);
    }

    .analysis-card {
        border: 1px solid #e9ecef;
        border-radius: 14px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        transition: .12s;
    }
    .analysis-card:hover {
        transform: translateY(-3px);
    }
</style>
</head>
<body>

<!-- 네비게이션바 (full width) -->
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
                    <a class="nav-link <?= $current_page=='dashboard.php'?'active':'' ?>"
                       href="dashboard.php">대시보드</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $current_page=='analysis_value.php'?'active':'' ?>"
                       href="analysis_value.php">가성비 선수 랭킹</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $current_page=='score_accumulation.php'?'active':'' ?>"
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

<!-- 본문 전체 wrapper -->
<div class="content-container">

    <!-- 환영 박스 -->
    <div class="p-5 mb-5 welcome-box">
        <h2 class="fw-bold mb-3">감독님, 환영합니다.</h2>

        <p class="fs-5 mb-0">
            현재 로그인한 사용자 ID:
            <strong><?= htmlspecialchars((string)$user_id) ?></strong><br>

            소속 팀:
            <strong><?= htmlspecialchars($team_name) ?></strong>
        </p>
    </div>

    <!-- 고급 분석 카드 -->
    <h3 class="mb-3 fw-bold text-secondary">고급 분석</h3>
    <div class="row row-cols-1 row-cols-md-2 g-4">

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body">
                    <h5 class="card-title">[Ranking] 가성비 선수 랭킹</h5>
                    <p class="card-text">연봉 대비 득점으로 선수 순위를 매깁니다.</p>
                    <a href="analysis_value.php" class="btn btn-primary">분석 페이지로 이동 →</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body">
                    <h5 class="card-title">[Windowing/Aggregate] 라운드별 누적 득점</h5>
                    <p class="card-text">팀·포지션 기준 누적 득점을 분석합니다.</p>
                    <a href="score_accumulation.php" class="btn btn-primary">분석 페이지로 이동 →</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body">
                    <h5 class="card-title">[Aggregate] 범실 총합 분석</h5>
                    <p class="card-text">공격·리베로·세터 범실 누적을 비교합니다.</p>
                    <a href="aggregate.php" class="btn btn-primary">분석 페이지로 이동 →</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body">
                    <h5 class="card-title">[OLAP] 팀별 스탯 순위 분석</h5>
                    <p class="card-text">팀별 스탯 순위를 분석합니다.</p>
                    <a href="rollup.php?metric=score" class="btn btn-primary">분석 페이지로 이동 →</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body"> 
                    <h5 class="card-title">[CRUD] 스카우팅 노트</h5>
                    <p class="card-text">선수 평가에 대해 작성/수정/삭제합니다.</p>
                    <a href="player_select.php" class="btn btn-primary">선수 선택 페이지 →</a>
                </div>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


