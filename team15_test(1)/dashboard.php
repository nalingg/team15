<?php
// dashboard.php
// 메인 대시보드: 로그인한 감독(사용자)에게 팀 정보와 분석 메뉴를 보여주는 페이지

session_start();               
require_once 'db.php';         

// 1) 로그인 여부 & 팀 정보 세션 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$team_id = (int)$_SESSION['team_id'];

// 2) 소속 팀 이름 조회 (PDO PreparedStatement)
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

    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- rollup과 동일 폰트 -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            padding: 20px;
            font-family: 'Inter', sans-serif; /* rollup과 통일 */
            background-color: #ffffff;
        }
        .container {
            max-width: 960px;
        }

        /* rollup의 타이틀 톤 */
        .page-title {
            font-weight: 700;
            color: var(--bs-primary);
        }

        /* rollup의 "카드 느낌(연한 배경 + shadow + 라운드)" */
        .welcome-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        /* 분석 카드도 rollup 테이블과 같은 톤 */
        .analysis-card {
            border: 1px solid #e9ecef;
            border-radius: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            transition: transform 0.12s ease-in-out;
        }
        .analysis-card:hover {
            transform: translateY(-3px);
        }

        .analysis-card .card-title {
            font-weight: 700;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- 네비게이션바-->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm border-bottom border-primary" 
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
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>"
                        href="dashboard.php">
                            대시보드
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='analysis_value.php'?'active':'' ?>"
                        href="analysis_value.php">
                            가성비 선수 랭킹
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='score_accumulation.php'?'active':'' ?>"
                        href="score_accumulation.php">
                            라운드별 누적 득점
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page,['rollup.php', 'drilldown.php']) ?'active':'' ?>"
                        href="rollup.php?metric=score">
                            팀별 스탯 순위 분석
                        </a>
                    </li>

                     <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page, ['player_select.php','player_profile.php','mynotes.php']) ? 'active' : '' ?>"
                           href="player_select.php">스카우팅 노트</a>
                    </li>
                </ul>

                <div class="d-flex">
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        로그아웃
                    </a>
                </div>
            </div>
        </div>
    </nav>


    <!-- 메인 환영 영역(rollup 카드톤으로 변경) -->
    <div class="p-5 mb-5 welcome-box">
        <h2 class="fw-bold mb-3">감독님, 환영합니다.</h2>

        <p class="fs-5 mb-0">
            현재 로그인한 사용자 ID:
            <strong><?= htmlspecialchars((string)$user_id, ENT_QUOTES, 'UTF-8') ?></strong><br>

            소속 팀:
            <strong><?= htmlspecialchars($team_name, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    </div>

    <!-- 4대 고급 분석 기능 카드 목록 -->
    <h3 class="mb-3 fw-bold text-secondary">4대 고급 분석</h3>
    <div class="row row-cols-1 row-cols-md-2 g-4">

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body">
                    <h5 class="card-title">[Ranking] 가성비 선수 랭킹</h5>
                    <p class="card-text">
                        '연봉 대비 득점'으로 FA/신인 선수의 순위를 매깁니다.
                    </p>
                    <a href="analysis_value.php" class="btn btn-primary">
                        분석 페이지로 이동 &raquo;
                    </a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body">
                    <h5 class="card-title">[Windowing/Aggregate] 라운드별 누적 득점</h5>
                    <p class="card-text">
                        팀과 포지션을 기준으로 선수들의 라운드별 득점 및 누적 득점을 분석합니다.
                    </p>
                    <a href="score_accumulation.php" class="btn btn-primary">
                        분석 페이지로 이동 &raquo;
                    </a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body">
                    <h5 class="card-title">[OLAP] 팀별 스탯 순위 분석 </h5>
                    <p class="card-text">
                        팀별 스탯 순위 및 선수별 분석합니다.
                    </p>
                    <a href="rollup.php?metric=score" class="btn btn-primary">
                        분석 페이지로 이동 &raquo;
                    </a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 analysis-card">
                <div class="card-body">
                    <h5 class="card-title">[CRUD] 스카우팅 노트</h5>
                    <p class="card-text">
                        선수에 대한 평가를 입력/수정/삭제 및 조회합니다.
                    </p>
                    <a href="player_select.php" class="btn btn-primary">
                        팀 및 선수 선택 페이지로 이동 &raquo;
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>