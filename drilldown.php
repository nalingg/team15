<?php

require_once 'db.php';

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('DB 연결 실패: ' . $e->getMessage());
}
// ------------------------
// PHP: Ajax 데이터 처리
// ------------------------
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$conn = new mysqli("localhost", "team15", "team15", "team15");
$conn->set_charset("utf8");

// Ajax 호출인지 확인
$isAjax = isset($_GET['team']) && isset($_GET['metric']);
if($isAjax){
    $team_id = $_GET['team'];
    $metric = $_GET['metric'] ?? 'score';

    if(!$team_id){
        echo json_encode(['error'=>'팀 ID가 전달되지 않았습니다.']);
        exit;
    }

    $sql = "";
    switch($metric){
        case 'score':
            $sql = "
                SELECT p.player_name AS player,
                       pp.position_name AS position,
                       SUM(a.open_suc + a.backquick_suc + a.serve_suc) AS metric_value
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN Att_Stats a ON p.player_ID = a.player_ID
                WHERE p.current_team_ID = ?
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
                WHERE p.current_team_ID = ?
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
                WHERE p.current_team_ID = ?
                GROUP BY p.player_name, pp.position_name
            ";
            break;
        default:
            echo json_encode(['error'=>'유효하지 않은 metric 파라미터']);
            exit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $players = [];
    while($row = $result->fetch_assoc()){
        $players[] = [
            'player'=>$row['player'],
            'position'=>$row['position'],
            'metric_value'=>(int)$row['metric_value']
        ];
    }

    // metric_value 기준 내림차순 정렬
    usort($players, fn($a,$b)=>$b['metric_value'] <=> $a['metric_value']);

    // DENSE_RANK 계산
    $rank = 1;
    $prevValue = null;
    foreach($players as $index=>&$player){
        if($prevValue!==null && $player['metric_value']==$prevValue){
            $player['rank']=$rank;
        } else {
            $rank=$index+1;
            $player['rank']=$rank;
        }
        $prevValue=$player['metric_value'];
    }
    unset($player);

    echo json_encode($players, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ------------------------
// HTML 출력 (Ajax 호출 아님)
// ------------------------
$teamId = $_GET['teamId'] ?? '';
$teamName = $_GET['teamName'] ?? '팀 이름 없음';
$metric = $_GET['metric'] ?? 'score';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V-League Drilldown 분석</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { padding: 20px; font-family: 'Inter', sans-serif; }
.container { max-width: 960px; }
.btn-check:checked + .btn-outline-primary {
    background-color: var(--bs-primary);
    color: white;
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,.5);
}
</style>
</head>
<body>
<div class="container">
<!-- 네비게이션 바 -->
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

                    <!-- 고급 분석 메뉴 (가성비, 팀 킬러, 등등) -->
                    <li class="nav-item">
                        <a class="nav-link" href="analysis_value.php">고급 분석</a>
                    </li>
                </ul>

                <!-- 우측 상단 로그아웃 버튼 -->
                <a href="logout.php" class="btn btn-outline-light">로그아웃</a>
            </div>
        </div>
    </nav>

<h1 class="mb-4 display-6 fw-bold text-primary text-center" id="page-title"><?= htmlspecialchars($teamName) ?></h1>

<!-- 메트릭 선택 -->
<div class="mb-5 p-4 bg-light border rounded shadow-sm text-center">
<h5 class="mb-3 text-dark">선수별 분석 영역</h5>
<div class="btn-group" role="group" id="metric-selector">
    <input type="radio" class="btn-check" name="metric" id="metric_score" value="score" autocomplete="off" <?= $metric=='score'?'checked':'' ?>>
    <label class="btn btn-outline-primary rounded-pill px-4 me-2" for="metric_score">득점 종합</label>

    <input type="radio" class="btn-check" name="metric" id="metric_defense" value="defense" autocomplete="off" <?= $metric=='defense'?'checked':'' ?>>
    <label class="btn btn-outline-primary rounded-pill px-4 me-2" for="metric_defense">수비 도움</label>

    <input type="radio" class="btn-check" name="metric" id="metric_setting" value="setting" autocomplete="off" <?= $metric=='setting'?'checked':'' ?>>
    <label class="btn btn-outline-primary rounded-pill px-4" for="metric_setting">세팅 도움</label>
</div>
</div>

<div class="table-responsive">
<table class="table table-striped table-hover align-middle shadow-lg rounded-3 overflow-hidden">
<thead class="table-dark">
<tr>
<th scope="col" class="text-center">순위</th>
<th scope="col" class="text-center">선수</th>
<th scope="col" class="text-center">포지션</th>
<th scope="col" class="text-center" id="metric-header">득점</th>
</tr>
</thead>
<tbody id="table-body">
<tr>
<td colspan="4" class="text-center py-4 text-muted">팀을 선택하고 메트릭을 지정하면 데이터가 로드됩니다.</td>
</tr>
</tbody>
</table>
</div>

<button id="back-button" class="btn btn-secondary mt-3">Rollup으로 돌아가기</button>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let teamId = "<?= htmlspecialchars($teamId) ?>";
    let teamName = "<?= htmlspecialchars($teamName) ?>";
    let metric = "<?= $metric ?>";

    const tableBody = document.getElementById('table-body');
    const metricSelector = document.getElementById('metric-selector');
    const metricHeader = document.getElementById('metric-header');
    const pageTitle = document.getElementById('page-title');
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
        .then(res=>res.json())
        .then(data=>{
            if(!Array.isArray(data)){
                tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">데이터 형식 오류</td></tr>`;
                return;
            }
            let html='';
            data.forEach(player=>{
                html += `<tr>
                            <td class="text-center">${player.rank}</td>
                            <td class="text-center">${player.player}</td>
                            <td class="text-center">${player.position}</td>
                            <td class="text-center">${player.metric_value}</td>
                        </tr>`;
            });
            tableBody.innerHTML = html;
        })
        .catch(err=>{
            tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">데이터 로드 실패: ${err.message}</td></tr>`;
        });
    }

    // 초기 데이터 로드
    if(teamId) fetchDrilldownData(metric);

    // metric 변경 시
    metricSelector.addEventListener('change', e=>{
        const selected = e.target.value;
        if(selected){
            metric = selected;
            fetchDrilldownData(metric);
        }
    });

    backButton.addEventListener('click', ()=>{
        window.location.href = `rollup.php?metric=${metric}`;
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
