<?php
require_once 'db.php';
session_start();

// 현재 페이지 판별 (네비 active용)
$current_page = basename($_SERVER['PHP_SELF']);

// 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$my_team_id = (int)$_SESSION['team_id'];

// 선택된 metric
$metric = $_GET['metric'] ?? 'score';

// metric별 설정
$main_metric_name = "";
$sql = "";

//간단하게 rollup에만 충실한 쿼리. rank()를 여기서 정의하면 rollup의 정의를 헤치게 되어 따로 함수로 구현했음
//세 개의 테이블(공격, 수비, 세팅)을 모두 구현하느라 case문으로 나눔
switch($metric){
    case 'score':
        $main_metric_name = "총 득점";
        $sql = "
            SELECT t.team_ID, t.team_Name AS team,
                   SUM(a.open_suc + a.backquick_suc + a.serve_suc) AS metric_value
            FROM Player p
            JOIN Team t ON p.current_team_ID = t.team_ID
            JOIN Att_Stats a ON p.player_ID = a.player_ID
            GROUP BY t.team_ID, t.team_Name WITH ROLLUP
        ";
        break;

    case 'defense':
        $main_metric_name = "수비 도움";
        $sql = "
            SELECT t.team_ID, t.team_Name AS team,
                   SUM(l.dig_suc + l.receive_good) AS metric_value
            FROM Player p
            JOIN Team t ON p.current_team_ID = t.team_ID
            JOIN L_Stats l ON p.player_ID = l.player_ID
            GROUP BY t.team_ID, t.team_Name WITH ROLLUP
        ";
        break;

    case 'setting':
        $main_metric_name = "세팅 도움";
        $sql = "
            SELECT t.team_ID, t.team_Name AS team,
                   SUM(s.set_suc) AS metric_value
            FROM Player p
            JOIN Team t ON p.current_team_ID = t.team_ID
            JOIN S_Stats s ON p.player_ID = s.player_ID
            GROUP BY t.team_ID, t.team_Name WITH ROLLUP
        ";
        break;

    default:
        die("유효하지 않은 metric 파라미터");
}

// PDO로 쿼리 실행
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result_rows = $stmt->fetchAll();
} catch (PDOException $e) {
    die("SQL 오류: " . $e->getMessage());
}

// 팀 데이터 수집 (team NULL 제외)
$teams = [];
$total_sum = 0;

foreach ($result_rows as $row) {
    if (is_null($row['team'])) continue;

    $teams[] = [
        'team_id'     => $row['team_ID'],
        'team_name'   => $row['team'],
        'main_metric' => (int)$row['metric_value']
    ];

    $total_sum += (int)$row['metric_value'];
}

// 내림차순 정렬
usort($teams, function($a,$b){ return $b['main_metric'] <=> $a['main_metric']; });

// DENSE_RANK 계산하는 함수 따로 넣었음(리그 전체인 null은 순위계산에서 제외)
$rank = 1;
$prevValue = null;
foreach($teams as $index => &$team){
    if($prevValue !== null && $team['main_metric'] == $prevValue){
        $team['rank'] = $rank;
    } else {
        $rank = $index + 1;
        $team['rank'] = $rank;
    }
    $prevValue = $team['main_metric'];
}
unset($team);

// 전체 합계 추가
$teams[] = [
    'team_id' => 'TOTAL',
    'team_name' => '전체 합계',
    'main_metric' => $total_sum,
    'rank' => '-',
    'drilldown_url' => '-'
];

// drilldown_url 추가
foreach($teams as &$team){
    if($team['team_id'] !== 'TOTAL'){
        $teamNameEncoded = urlencode($team['team_name']);
        $team['drilldown_url'] = "drilldown.php?teamId={$team['team_id']}&teamName={$teamNameEncoded}&metric={$metric}";
    }
}
unset($team);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V-League Rollup 분석</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
    body {
        padding: 0; /* 네비 full-width */
        font-family: 'Inter', sans-serif;
        background: #fff;
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

    .col-rank { width: 10%; text-align: center; }
    .col-team { width: 40%; text-align: center; }
    .col-metric-main { width: 35%; text-align: center; }
    .col-drilldown { width: 15%; text-align: center; }

    .btn-check:checked + .btn-outline-primary {
        background-color: var(--bs-primary);
        color: white;
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.35);
    }
</style>
</head>

<body>

<!--(full width) -->
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
    <div class="p-4 mb-4 info-box text-center">
        <h2 class="fw-bold mb-1 text-primary">팀별 스탯 순위 분석</h2>
        <p class="text-muted mb-0">
            ROLLUP으로 팀 단위 합계를 계산하고, Drilldown으로 선수별 상세 분석이 가능합니다.
        </p>
    </div>

    <!-- 메트릭 선택 박스 -->
    <div class="metric-box p-4 mb-4 text-center">
        <h5 class="mb-3 text-dark fw-bold">분석 영역</h5>

        <div class="btn-group" role="group" id="metric-selector">
            <input type="radio" class="btn-check" name="metric" id="metric_score" value="score" autocomplete="off" <?= $metric=='score'?'checked':'' ?>>
            <label class="btn btn-outline-primary rounded-pill px-4 me-2" for="metric_score">득점 종합</label>

            <input type="radio" class="btn-check" name="metric" id="metric_defense" value="defense" autocomplete="off" <?= $metric=='defense'?'checked':'' ?>>
            <label class="btn btn-outline-primary rounded-pill px-4 me-2" for="metric_defense">수비 도움</label>

            <input type="radio" class="btn-check" name="metric" id="metric_setting" value="setting" autocomplete="off" <?= $metric=='setting'?'checked':'' ?>>
            <label class="btn btn-outline-primary rounded-pill px-4" for="metric_setting">세팅 도움</label>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const metricSelector = document.getElementById('metric-selector');
        metricSelector.addEventListener('change', (event) => {
            if(event.target.name === 'metric'){
                const newMetric = event.target.value;
                window.location.href = `rollup.php?metric=${newMetric}`;
            }
        });
    });
    </script>

    <!-- 결과 타이틀 -->
    <h4 class="mb-3 text-secondary text-start">
        <?= "팀별 {$main_metric_name} 분석 (ROLLUP)" ?>
    </h4>

    <!-- 결과 테이블 -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle text-center shadow-sm rounded-3 overflow-hidden">
            <thead class="table-dark">
                <tr>
                    <th class="col-rank">순위</th>
                    <th class="col-team">팀명</th>
                    <th class="col-metric-main"><?= $main_metric_name ?></th>
                    <th class="col-drilldown">Drilldown</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($teams as $team): ?>
                <tr class="<?= $team['team_id']=='TOTAL'?'table-secondary fw-bold':'' ?>">
                    <td><?= $team['rank'] ?></td>
                    <td><?= $team['team_name'] ?></td>
                    <td class="fw-bold text-primary"><?= number_format($team['main_metric']) ?></td>
                    <td>
                        <?= $team['team_id']=='TOTAL'
                            ? '-'
                            : "<a href='{$team['drilldown_url']}' class='btn btn-sm btn-info text-white shadow-sm'>보기</a>"
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>