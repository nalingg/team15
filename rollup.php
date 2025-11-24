<?php
require_once 'db.php';

//로그인
$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('DB 연결 실패: ' . $e->getMessage());
}

//db연결
$conn = new mysqli("localhost", "team15", "team15", "team15");
$conn->set_charset("utf8");

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

// 쿼리 실행
$result = $conn->query($sql);
if(!$result){
    die("SQL 오류: " . $conn->error);
}

// 팀 데이터 수집 (team NULL 제외)
$teams = [];
$total_sum = 0;
while($row = $result->fetch_assoc()){
    if(is_null($row['team'])) continue;
    $teams[] = [
        'team_id' => $row['team_ID'],
        'team_name' => $row['team'],
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
<style>
body { padding: 20px; font-family: 'Inter', sans-serif; }
.container { max-width: 960px; }
.col-rank { width: 10%; text-align: center; }
.col-team { width: 40%; text-align: center; }
.col-metric-main { width: 35%; text-align: center; }
.col-drilldown { width: 15%; text-align: center; }
.btn-check:checked + .btn-outline-primary {
    background-color: var(--bs-primary);
    color: white;
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.5);
}
</style>
</head>
<body>
<div class="container">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4">
        <div class="container-fluid">
            <!-- 좌측 로고/브랜드: 클릭 시 대시보드로 -->
            <a class="navbar-brand" href="dashboard.php">V-League 스카우팅 툴</a>

            <div class="collapse navbar-collapse">
                <!-- 좌측 메뉴 목록 -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- 현재 페이지: 대시보드 -->
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">대시보드</a>
                    </li>

                    <!-- 선수 CRUD 및 스카우팅 노트 등에서 사용할 선수 정보 관리 페이지 -->
                    <li class="nav-item">
                        <a class="nav-link" href="player_select.php">선수 정보 (CRUD)</a>
                    </li>

                    <!-- 내 스카우팅 노트 목록 페이지 (mode=mine: 내 노트만 보기) -->
                    <li class="nav-item">
                        <a class="nav-link" href="mynotes.php?mode=mine">내 스카우팅 노트</a>
                    </li>

                    //고급 분석 삭제
                </ul>

                <!-- 우측 상단 로그아웃 버튼 -->
                <a href="logout.php" class="btn btn-outline-light">로그아웃</a>
            </div>
        </div>
    </nav>

<h1 class="mb-4 display-6 fw-bold text-primary text-center">팀별 스탯 순위 분석</h1>

<div class="mb-5 p-4 bg-light border rounded shadow-sm text-center">
<h5 class="mb-3 text-dark">분석 영역</h5>
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
            // 선택한 metric으로 페이지 리로드
            window.location.href = `rollup.php?metric=${newMetric}`;
        }
    });
});
</script>


<h4 class="mb-3 text-secondary text-start"><?= "팀별 {$main_metric_name} 분석 (ROLLUP)" ?></h4>
<div class="table-responsive">
<table class="table table-striped table-hover align-middle text-center shadow-lg rounded-3 overflow-hidden">
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
<td><?= $team['team_id']=='TOTAL'?'-':"<a href='{$team['drilldown_url']}' class='btn btn-sm btn-info text-white shadow-sm'>보기</a>" ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</body>
</html>
