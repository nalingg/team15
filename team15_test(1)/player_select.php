<?php
// player_select.php
session_start();
require_once 'db.php';   // PDO $pdo 사용

// 로그인 여부 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$my_team_id = (int)$_SESSION['team_id'];

// 현재 페이지 판별 (네비 active)
$current_page = basename($_SERVER['PHP_SELF']);

// 내 팀 이름 가져오기
$sql_team = "SELECT team_Name FROM team WHERE team_ID = :tid";
$stmt = $pdo->prepare($sql_team);
$stmt->execute([':tid' => $my_team_id]);
$row = $stmt->fetch();
$my_team_name = $row ? $row['team_Name'] : ($my_team_id . '번 팀');

// 전체 팀 목록 조회
$sql_teams = "SELECT team_ID, team_Name FROM team ORDER BY team_Name";
$stmt2 = $pdo->prepare($sql_teams);
$stmt2->execute();
$teams = $stmt2->fetchAll();

// 선택된 팀 처리 후 player_profile 이동
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $team_type      = $_POST['team_type'] ?? 'my';
    $target_team_id = ($team_type === 'my') ? $my_team_id : (int)($_POST['target_team_id'] ?? 0);

    if ($target_team_id <= 0) $target_team_id = $my_team_id;

    header('Location: player_profile.php?team_id=' . $target_team_id);
    exit;
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>스카우팅 대상 팀 선택</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Inter 폰트 (rollup 동일) -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
    body {
        padding: 20px;
        font-family: 'Inter', sans-serif;
    }
    .container { max-width: 960px; }

    /* rollup-style 페이지 타이틀 */
    .page-title {
        color: var(--bs-primary);
        font-weight: 700;
    }

    /* rollup-style card box */
    .select-box {
        background: #f8f9fa;
        border: 1px solid #e4e4e4;
        border-radius: 16px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.06);
    }

    /* ====== ❗ rollup 네비게이션 스타일과 동일하게 (커스텀 제거) ====== */
    .navbar-nav .nav-link {
        color: rgba(255,255,255,0.7);
        font-weight: 500;
    }
    .navbar-nav .nav-link:hover {
        color: white;
    }
    .navbar-nav .nav-link.active {
        color: #fff !important;
        font-weight: 700;
    }
    /* rollup에서는 active underline 없음 → 제거 */
</style>
</head>
<body>

<div class="container">

    <!-- 네비게이션 바 (rollup과 완전히 동일) -->
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
                        <a class="nav-link <?= $current_page=='rollup.php'?'active':'' ?>"
                           href="rollup.php?metric=score">팀별 스탯 분석</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page,['player_select.php','player_profile.php','mynotes.php'])?'active':'' ?>"
                           href="player_select.php">스카우팅 노트</a>
                    </li>

                </ul>

                <a href="logout.php" class="btn btn-outline-light btn-sm">로그아웃</a>
            </div>
        </div>
    </nav>

    <!-- 페이지 타이틀 -->
    <h1 class="mb-4 display-6 text-center page-title">스카우팅 대상 팀 선택</h1>

    <p class="text-secondary mb-4 text-center">
        내 팀 혹은 다른 팀을 선택하고 해당 팀 선수 목록으로 이동합니다.
    </p>

    <!-- 선택 박스 (rollup-style box) -->
    <form method="post" class="p-4 select-box">

        <h5 class="fw-bold mb-3">팀 유형 선택</h5>

        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="team_type"
                       id="team_type_my" value="my" checked>
                <label class="form-check-label" for="team_type_my">
                    내 팀 선수 (<?= htmlspecialchars($my_team_name) ?>)
                </label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="team_type"
                       id="team_type_other" value="other">
                <label class="form-check-label" for="team_type_other">
                    다른 팀 선수
                </label>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">다른 팀 선택</label>
            <select class="form-select bg-light text-muted"
                    id="target_team_id" name="target_team_id" disabled>
                <?php foreach ($teams as $t): ?>
                    <option value="<?= $t['team_ID'] ?>">
                        <?= htmlspecialchars($t['team_Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">
                “내 팀 선수” 선택 시 자동 무시됩니다.
            </small>
        </div>

        <div class="text-center mt-3">
            <button type="submit" class="btn btn-primary btn-lg">선수 정보 페이지로 이동</button>
        </div>

    </form>

</div>

<script>
// 라디오 버튼에 따라 드롭다운 enable/disable
function updateTeamSelectState() {
    const isOther = document.getElementById('team_type_other').checked;
    const select  = document.getElementById('target_team_id');

    select.disabled = !isOther;

    if (!isOther) {
        select.classList.add('bg-light','text-muted');
    } else {
        select.classList.remove('bg-light','text-muted');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateTeamSelectState();
    document.getElementById('team_type_my').addEventListener('change', updateTeamSelectState);
    document.getElementById('team_type_other').addEventListener('change', updateTeamSelectState);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
