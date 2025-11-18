<?php
session_start();                    
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header("Location: login.php");
    exit;
}

error_reporting(E_ALL);
ini_set("display_errors", 1);

$conn = new mysqli("localhost","root","","testdb");
$conn->set_charset("utf8");

$team_id = isset($_GET["team_id"]) ? $_GET["team_id"] : 0;

$teamListSql = "SELECT team_ID, team_Name FROM team ORDER BY team_Name";
$teamListRes = $conn->query($teamListSql);

//  Aggregate SQL
$sql = "SELECT 
p.player_ID,
p.player_name,
t.team_Name,
(
    COALESCE(a.att_sum,0) +
    COALESCE(l.l_sum,0) +
    COALESCE(s.s_sum,0)
) AS total_mistakes
FROM player p
LEFT JOIN team t ON p.current_team_ID=t.team_ID

LEFT JOIN (
    SELECT 
        player_ID,
        SUM(open_failmis + backquick_failmis + serve_mis) AS att_sum
    FROM att_stats
    GROUP BY player_ID
) a ON p.player_ID = a.player_ID

LEFT JOIN (
    SELECT 
        player_ID,
        SUM(dig_failmis + receive_fail) AS l_sum
    FROM l_stats
    GROUP BY player_ID
) l ON p.player_ID = l.player_ID

LEFT JOIN (
    SELECT 
        player_ID,
        SUM(serve_mis) AS s_sum
    FROM s_stats
    GROUP BY player_ID
) s ON p.player_ID = s.player_ID
";

if ($team_id > 0){
    $sql .= " WHERE p.current_team_ID=? ";
}

$sql .= " GROUP BY p.player_ID ORDER BY total_mistakes DESC";

// prepared statement
$stmt = $conn->prepare($sql);
if ($team_id > 0){
    $stmt->bind_param("i",$team_id);
}

$stmt->execute();
$res = $stmt->get_result();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>범실 통계</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .container { max-width: 960px; }
        th { white-space: nowrap; }
    </style>
</head>

<body>

<div class="container">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.html">V-League 스카우팅 툴</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.html">대시보드</a></li>
                    <li class="nav-item"><a class="nav-link" href="player_profile.html">선수 정보</a></li>
                    <li class="nav-item"><a class="nav-link active" href="aggregate.php">고급 분석</a></li>
                </ul>
                <a href="login.html" class="btn btn-outline-light">로그아웃</a>
            </div>
        </div>
    </nav>

    <h2>범실 통계 (Aggregate)</h2>
    <p>Att_Stats / S_Stats / L_Stats의 mis/fail을 합산하여 선수별 범실을 비교합니다.</p>
    <hr>

    <form method="GET" action="aggregate.php" class="card bg-light p-4 mb-4">

        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label fw-bold">팀 선택</label>
                <select name="team_id" class="form-select">
                    <option value="0" <?= $team_id == 0 ? "selected" : "" ?>>전체</option>

                    <?php
                    if($teamListRes){
                        while($t = $teamListRes->fetch_assoc()){
                            $sel = ($team_id == $t["team_ID"]) ? "selected" : "";
                            echo "<option value='".$t["team_ID"]."' $sel>".$t["team_Name"]."</option>";
                        }
                    }
                    ?>
                </select>
            </div>

        </div>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg">조회</button>
        </div>

    </form>

    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead class="table-dark">
                <tr>
                    <th>순위</th>
                    <th>선수명</th>
                    <th>팀</th>
                    <th>범실 합계</th>
                </tr>
            </thead>

            <tbody>
            <?php
            if ($res && $res->num_rows > 0) {
                $rank = 1;
                while($row = $res->fetch_assoc()){
                    echo "<tr>
                            <td>".$rank++."</td>
                            <td>".htmlspecialchars($row["player_name"])."</td>
                            <td>".htmlspecialchars($row["team_Name"])."</td>
                            <td>".$row["total_mistakes"]."</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='4' class='text-center'>데이터가 없습니다.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
