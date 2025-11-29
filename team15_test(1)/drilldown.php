<?php

// 김나현

session_start();

// 현재 페이지 판별 (네비 active용)
$current_page = basename($_SERVER['PHP_SELF']);

// 1) 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';   // $pdo 사용

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// ------------------------
// PHP: Ajax 데이터 처리
// ------------------------
$isAjax = isset($_GET['team']) && isset($_GET['metric']);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');

    $team_id = (int)$_GET['team'];
    $metric  = $_GET['metric'] ?? 'score';

    if (!$team_id) {
        echo json_encode(['error' => '팀 ID가 전달되지 않았습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // metric whitelist
    switch ($metric) {
        case 'score':
            $sql = "
                SELECT p.player_name AS player,
                       pp.position_name AS position,
                       SUM(a.open_suc + a.backquick_suc + a.serve_suc) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN Att_Stats a ON p.player_ID = a.player_ID
                WHERE p.current_team_ID = :team_id
                GROUP BY p.player_name, pp.position_name
            ";
            break;

        case 'defense':
            $sql = "
                SELECT p.player_name AS player,
                       pp.position_name AS position,
                       SUM(l.dig_suc + l.receive_good) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN L_Stats l ON p.player_ID = l.player_ID
                WHERE p.current_team_ID = :team_id
                GROUP BY p.player_name, pp.position_name
            ";
            break;

        case 'setting':
            $sql = "
                SELECT p.player_name AS player,
                       pp.position_name AS position,
                       SUM(s.set_suc) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN S_Stats s ON p.player_ID = s.player_ID
                WHERE p.current_team_ID = :team_id
                GROUP BY p.player_name, pp.position_name
            ";
            break;

        default:
            echo json_encode(['error' => '유효하지 않은 metric 파라미터'], JSON_UNESCAPED_UNICODE);
            exit;
    }

    // PDO 사용
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':team_id' => $team_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // metric_value 정수 변환
    foreach ($players as &$p) {
        $p['metric_value'] = (int)$p['metric_value'];
    }
    unset($p);

    // metric_value 기준 정렬
    usort($players, fn ($a, $b) => $b['metric_value'] <=> $a['metric_value']);

    // DENSE_RANK 계산
    $rank = 1;
    $prevValue = null;
    foreach ($players as $i => &$pl) {
        if ($prevValue !== null && $pl['metric_value'] == $prevValue) {
            $pl['rank'] = $rank;
        } else {
            $rank = $i + 1;
            $pl['rank'] = $rank;
        }
        $prevValue = $pl['metric_value'];
    }
    unset($pl);

    echo json_encode($players, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ------------------------
// HTML 출력
// ------------------------
$teamId   = $_GET['teamId'] ?? '';
$teamName = $_GET['teamName'] ?? '팀 이름 없음';
$metric   = $_GET['metric'] ?? 'score';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>팀별 Drilldown 분석</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
    body {
        padding: 0; /* 네비 full-width */
        font-family: 'Inter', sans-serif;
        background: white;
    }

    /* 네비 줄바꿈 방지 */
    .navbar-nav .nav-link {
        white-space: nowrap !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    /* 본문만 960px */
    .content-container {
        max-width: 960px;
        margin: auto;
        padding: 20px;
    }

    /* dashboard 톤 카드 */
    .info-box, .metric-box {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 16px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.06);
    }

    .btn-check:checked + .btn-outline-primary {
        background-color: var(--bs-primary);
        color: white;
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 0.25rem rgba(13,110,253,.35);
    }
</style>
</head>
<body>

<!-- full width-->
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

<!-- 본문 -->
<div class="content-container">

    <!-- 타이틀 박스 -->
    <div class="p-4 mb-4 info-box">
        <h2 class="fw-bold mb-1 text-primary text-center" id="page-title">
            <?= htmlspecialchars($teamName) ?>
        </h2>
        <p class="text-muted mb-0 text-center">
            팀 내부 선수들의 지표를 메트릭별로 Drilldown 합니다.
        </p>
    </div>

    <!-- 메트릭 선택 -->
    <div class="metric-box p-4 mb-4 text-center">
        <h5 class="mb-3 text-dark fw-bold">선수별 분석 영역</h5>

        <div class="btn-group" role="group" id="metric-selector">
            <input type="radio" class="btn-check" name="metric" id="metric_score" value="score" <?= $metric=='score'?'checked':'' ?>>
            <label class="btn btn-outline-primary rounded-pill px-4 me-2" for="metric_score">득점 종합</label>

            <input type="radio" class="btn-check" name="metric" id="metric_defense" value="defense" <?= $metric=='defense'?'checked':'' ?>>
            <label class="btn btn-outline-primary rounded-pill px-4 me-2" for="metric_defense">수비 도움</label>

            <input type="radio" class="btn-check" name="metric" id="metric_setting" value="setting" <?= $metric=='setting'?'checked':'' ?>>
            <label class="btn btn-outline-primary rounded-pill px-4" for="metric_setting">세팅 도움</label>
        </div>
    </div>

    <!-- 결과 테이블 -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle shadow-sm rounded-3 overflow-hidden">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">순위</th>
                    <th class="text-center">선수</th>
                    <th class="text-center">포지션</th>
                    <th class="text-center" id="metric-header">득점</th>
                </tr>
            </thead>
            <tbody id="table-body">
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">
                        팀을 선택하고 메트릭을 지정하면 데이터가 로드됩니다.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <button id="back-button" class="btn btn-secondary mt-3">
        Rollup으로 돌아가기
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let teamId = "<?= htmlspecialchars($teamId) ?>";
    let metric = "<?= $metric ?>";

    const tableBody = document.getElementById('table-body');
    const metricSelector = document.getElementById('metric-selector');
    const metricHeader = document.getElementById('metric-header');
    const backButton = document.getElementById('back-button');

    const metricLabelMap = {
        score: "선수별 득점 종합",
        defense: "선수별 수비 도움",
        setting: "선수별 세팅 도움"
    };

    function fetchDrilldownData(metric){
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4">데이터 로드 중...</td></tr>';
        metricHeader.textContent = metricLabelMap[metric];

        fetch(`drilldown.php?team=${teamId}&metric=${metric}`)
        .then(res => res.json())
        .then(data => {
            if(!Array.isArray(data)){
                tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">데이터 형식 오류</td></tr>`;
                return;
            }
            let html = '';
            data.forEach(player => {
                html += `<tr>
                            <td class="text-center">${player.rank}</td>
                            <td class="text-center">${player.player}</td>
                            <td class="text-center">${player.position}</td>
                            <td class="text-center">${player.metric_value}</td>
                        </tr>`;
            });
            tableBody.innerHTML = html;
        })
        .catch(err => {
            tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">데이터 로드 실패: ${err.message}</td></tr>`;
        });
    }

    if(teamId) fetchDrilldownData(metric);

    metricSelector.addEventListener('change', e => {
        const selected = e.target.value;
        if(selected){
            metric = selected;
            fetchDrilldownData(metric);
        }
    });

    backButton.addEventListener('click', () => {
        window.location.href = `rollup.php?metric=${metric}`;
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
