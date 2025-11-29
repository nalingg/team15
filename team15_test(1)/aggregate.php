<?php

// 안지은

// aggregate.php
// 공격수/리베로/세터 범실(mis/fail) 총합 비교 Aggregate 분석 페이지

session_start();
require_once 'db.php';  

// 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header("Location: login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);

error_reporting(E_ALL);
ini_set("display_errors", 1);

$team_id = isset($_GET["team_id"]) ? (int)$_GET["team_id"] : 0;


$teamListSql = "SELECT team_ID, team_Name FROM team ORDER BY team_Name";
$teamListStmt = $pdo->prepare($teamListSql);
$teamListStmt->execute();
$teamListRes = $teamListStmt->fetchAll();


$sql = "
    SELECT 
        p.player_ID,
        p.player_name,
        t.team_Name,
        (
            COALESCE(a.att_sum,0) +
            COALESCE(l.l_sum,0) +
            COALESCE(s.s_sum,0)
        ) AS total_mistakes
    FROM player p
    LEFT JOIN team t 
        ON p.current_team_ID = t.team_ID

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

// 팀 필터 조건 유지
$params = [];
if ($team_id > 0) {
    $sql .= " WHERE p.current_team_ID = :team_id ";
    $params[':team_id'] = $team_id;
}

$sql .= "
    GROUP BY p.player_ID
    ORDER BY total_mistakes DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$res = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>범실 총합 분석 </title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- dashboard와 동일 폰트 -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            padding: 0; 
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
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

        .info-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        .filter-card {
            border: 1px solid #e9ecef;
            border-radius: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        th { white-space: nowrap; }
    </style>
</head>

<body>

<!--  네비게이션 (full width) -->
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

<!-- 본문 wrapper -->


<div class="content-container">

    <!-- 타이틀/설명 -->
    <div class="p-4 mb-4 info-box">
        <h2 class="fw-bold mb-2 text-primary">범실 총합 분석</h2>
        <p class="mb-0 text-muted">
            공격수 / 리베로 / 세터의 범실을 합산하여 선수별 범실 총합을 비교합니다.
        </p>
    </div>

    <!-- 필터 카드 -->
    <form method="GET" action="aggregate.php" class="filter-card bg-light p-4 mb-4">
        <div class="row g-3 align-items-end">

            <div class="col-md-6">
                <label class="form-label fw-bold">팀 선택</label>
                <select name="team_id" class="form-select">
                    <option value="0" <?= $team_id == 0 ? "selected" : "" ?>>전체</option>
                    <?php foreach ($teamListRes as $t): ?>
                        <option value="<?= (int)$t['team_ID'] ?>"
                            <?= $team_id == (int)$t['team_ID'] ? "selected" : "" ?>>
                            <?= htmlspecialchars($t['team_Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 text-md-end">
                <button type="submit" class="btn btn-primary btn-lg px-4">
                    조회
                </button>
            </div>

        </div>
    </form>

    <!-- 결과 테이블 -->
    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm align-middle">
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
            if (!empty($res)) {
                $rank = 1;
                foreach ($res as $row) {
                    echo "<tr>
                            <td>{$rank}</td>
                            <td>" . htmlspecialchars($row["player_name"]) . "</td>
                            <td>" . htmlspecialchars($row["team_Name"]) . "</td>
                            <td>{$row["total_mistakes"]}</td>
                          </tr>";
                    $rank++;
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

