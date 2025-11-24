<?php
session_start();
require_once 'db.php';   // PDO $pdo 사용

// -------------------------------
// 1) 로그인 체크
// -------------------------------
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

// 현재 페이지 판별 (네비 active용)
$current_page = basename($_SERVER['PHP_SELF']);

$user_id = (int)$_SESSION['user_id'];
$team_id = (int)$_SESSION['team_id'];

// -------------------------------
// 2) 입력값 준비
// -------------------------------
$position_id   = $_POST['position'] ?? '0';     // 0 = 전체
$team_id_input = $_POST['team'] ?? $team_id;   // 기본값: 로그인한 감독 팀

$rows = [];
$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {
        // Window Function 기반 누적 득점 분석
        $sql = "
            SELECT DISTINCT
                p.player_name,
                pp.position_Name,

                -- 1-2R 합계
                SUM(CASE WHEN g.round_ID IN (1, 2)
                        THEN (s.open_suc + s.backquick_suc)
                        ELSE 0 END)
                OVER (PARTITION BY p.player_ID) AS points_1_2,

                -- 3-4R 합계
                SUM(CASE WHEN g.round_ID IN (3, 4)
                        THEN (s.open_suc + s.backquick_suc)
                        ELSE 0 END)
                OVER (PARTITION BY p.player_ID) AS points_3_4,

                -- 5-6R 합계
                SUM(CASE WHEN g.round_ID IN (5, 6)
                        THEN (s.open_suc + s.backquick_suc)
                        ELSE 0 END)
                OVER (PARTITION BY p.player_ID) AS points_5_6,

                -- 누계 ~2R
                SUM(CASE WHEN g.round_ID <= 2
                        THEN (s.open_suc + s.backquick_suc)
                        ELSE 0 END)
                OVER (PARTITION BY p.player_ID) AS cumulative_2,

                -- 누계 ~4R
                SUM(CASE WHEN g.round_ID <= 4
                        THEN (s.open_suc + s.backquick_suc)
                        ELSE 0 END)
                OVER (PARTITION BY p.player_ID) AS cumulative_4,

                -- 누계 ~6R (총 득점)
                SUM(s.open_suc + s.backquick_suc)
                OVER (PARTITION BY p.player_ID) AS cumulative_6

            FROM Player p
            JOIN Player_Position pp ON p.position_ID = pp.position_ID
            JOIN Att_Stats s ON p.player_ID = s.player_ID
            JOIN Game g ON s.game_ID = g.game_ID
            WHERE
                p.current_team_ID = :team_id
                AND (:position = 0 OR p.position_ID = :position)
            ORDER BY
                cumulative_6 DESC, player_name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':team_id'  => $team_id_input,
            ':position' => $position_id
        ]);

        $rows = $stmt->fetchAll();

    } catch (PDOException $e) {
        $error_msg = "쿼리 오류: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>라운드별 누적 득점 (Windowing)</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Inter 폰트 -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            padding: 0;              
            font-family: 'Inter', sans-serif;
            background: #fff;
        }

        .navbar-nav .nav-link {
            white-space: nowrap !important;
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        .content-container {
            max-width: 960px;
            margin: auto;
            padding: 20px;
        }

        .info-box, .filter-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        table thead th,
        table td {
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>
<body>

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


<div class="content-container">

    <div class="p-4 mb-4 info-box text-center">
        <h2 class="fw-bold mb-1 text-primary">라운드별 누적 득점</h2>
        <p class="text-muted mb-0">
            라운드 구간별 득점 및 누적 득점을 분석합니다.
        </p>
    </div>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- 입력 폼 박스 -->
    <div class="p-4 mb-5 filter-box">
        <h5 class="mb-3 fw-bold">포지션 / 팀 선택</h5>

        <form method="POST" action="">
            <div class="row g-3">

                <!-- 포지션 선택 -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">1. 포지션 선택</label>

                    <?php
                    $positions = [
                        '0'  => '전체',
                        '10' => '아웃사이드 히터 (OH)',
                        '20' => '아포짓 스파이커 (OP)',
                        '30' => '미들 블로커 (MB)'
                    ];
                    foreach ($positions as $val => $label):
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="position"
                               value="<?= $val ?>" <?= ($position_id == $val) ? 'checked' : '' ?>>
                        <label class="form-check-label"><?= $label ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- 팀 선택 -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">2. 팀 선택</label>

                    <?php
                    $teams = [
                        '1000' => 'GS칼텍스',
                        '2000' => '정관장',
                        '3000' => '현대건설',
                        '4000' => 'IBK기업은행',
                        '5000' => '한국도로공사',
                        '6000' => '페퍼저축은행',
                        '7000' => '흥국생명'
                    ];
                    foreach ($teams as $tid => $name):
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="team"
                               value="<?= $tid ?>" <?= ($team_id_input == $tid) ? 'checked' : '' ?>>
                        <label class="form-check-label"><?= $name ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-4">분석 실행</button>
            </div>
        </form>
    </div>

    <!-- 결과표 -->
    <h4 class="mb-3 text-secondary">분석 결과</h4>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle shadow-sm rounded-3 overflow-hidden">
            <thead class="table-dark">
                <tr>
                    <th>선수이름</th>
                    <th>포지션</th>
                    <th>1-2R</th>
                    <th>3-4R</th>
                    <th>5-6R</th>
                    <th>~2R 누계</th>
                    <th>~4R 누계</th>
                    <th>~6R 누계</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows): ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['player_name']) ?></td>
                        <td><?= htmlspecialchars($r['position_Name']) ?></td>
                        <td><?= number_format($r['points_1_2']) ?></td>
                        <td><?= number_format($r['points_3_4']) ?></td>
                        <td><?= number_format($r['points_5_6']) ?></td>
                        <td><?= number_format($r['cumulative_2']) ?></td>
                        <td><?= number_format($r['cumulative_4']) ?></td>
                        <td><?= number_format($r['cumulative_6']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            데이터가 없습니다. 분석 실행 버튼을 눌러주세요.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
