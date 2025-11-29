<?php

// 오지송

// player_profile.php
session_start();
require_once 'db.php';   // PDO $pdo 사용

// 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];    // 현재 로그인한 사용자
$my_team_id = (int)$_SESSION['team_id'];    // 내가 감독하는 팀 (작성자 소속팀)

// 현재 페이지명 (네비 active용)
$current_page = basename($_SERVER['PHP_SELF']);

// 대상 팀 ID 결정 (GET/POST → 없으면 내 팀)
$target_team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : $my_team_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['team_id'])) {
        $target_team_id = (int)$_POST['team_id'];
    }
}

// 대상 팀 이름 조회
$sql_team = "SELECT team_Name FROM Team WHERE team_ID = :tid";
$stmt = $pdo->prepare($sql_team);
$stmt->execute([':tid' => $target_team_id]);
$row = $stmt->fetch();

if (!$row) {
    $target_team_id = $my_team_id;
    $stmt->execute([':tid' => $target_team_id]);
    $row = $stmt->fetch();
}

$target_team_name = $row ? $row['team_Name'] : ($target_team_id . '번 팀');

// 대상 팀 선수 목록
$sql_players = "
    SELECT player_ID, player_name
    FROM Player
    WHERE current_team_ID = :tid
    ORDER BY player_name
";
$stmt = $pdo->prepare($sql_players);
$stmt->execute([':tid' => $target_team_id]);
$team_players = $stmt->fetchAll();

if (empty($team_players)) {
    die('선택한 팀에 등록된 선수가 없습니다.');
}

// 어떤 선수 볼지 결정
$player_id = $_GET['player_id'] ?? ($_POST['player_id'] ?? null);
if ($player_id === null) {
    $player_id = $team_players[0]['player_ID'];
}
$player_id = (int)$player_id;

// 노트 작성/수정/삭제
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $player_id = (int)($_POST['player_id'] ?? $player_id);

    if ($action === 'save_note') {
        $note_content = trim($_POST['note_content'] ?? '');

        if ($note_content === '') {
            $message = '메모 내용을 입력해 주세요.';
        } else {
            $sql_check = "
                SELECT report_ID
                FROM Scouting_Report
                WHERE user_ID = :uid
                  AND team_ID = :tid
                  AND player_ID = :pid
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql_check);
            $stmt->execute([
                ':uid' => $user_id,
                ':tid' => $my_team_id,
                ':pid' => $player_id
            ]);
            $existing = $stmt->fetch();

            if ($existing) {
                $sql_update = "
                    UPDATE Scouting_Report
                    SET note_content = :content,
                        note_date    = NOW()
                    WHERE report_ID = :rid
                ";
                $stmt = $pdo->prepare($sql_update);
                $stmt->execute([
                    ':content' => $note_content,
                    ':rid'     => (int)$existing['report_ID']
                ]);
                $message = '기존 노트가 수정되었습니다.';
            } else {
                $sql_insert = "
                    INSERT INTO Scouting_Report (user_ID, team_ID, player_ID, note_date, note_content)
                    VALUES (:uid, :tid, :pid, NOW(), :content)
                ";
                $stmt = $pdo->prepare($sql_insert);
                $stmt->execute([
                    ':uid'     => $user_id,
                    ':tid'     => $my_team_id,
                    ':pid'     => $player_id,
                    ':content' => $note_content
                ]);
                $message = '새 노트가 저장되었습니다.';
            }
        }

    } elseif ($action === 'delete_note') {
        $report_id = (int)($_POST['report_id'] ?? 0);
        if ($report_id > 0) {
            $sql_delete = "
                DELETE FROM Scouting_Report
                WHERE report_ID = :rid
                  AND user_ID   = :uid
                  AND team_ID   = :tid
            ";
            $stmt = $pdo->prepare($sql_delete);
            $stmt->execute([
                ':rid' => $report_id,
                ':uid' => $user_id,
                ':tid' => $my_team_id
            ]);
            $message = '노트가 삭제되었습니다.';
        }
    }
}

// 선택된 선수 기본 정보
$sql_player = "
    SELECT P.player_ID,
           P.player_name,
           P.salary,
           T.team_Name,
           PP.position_Name
    FROM Player P
    JOIN Team T ON P.current_team_ID = T.team_ID
    JOIN Player_Position PP ON P.position_ID = PP.position_ID
    WHERE P.player_ID = :pid
      AND P.current_team_ID = :tid
    LIMIT 1
";
$stmt = $pdo->prepare($sql_player);
$stmt->execute([
    ':pid' => $player_id,
    ':tid' => $target_team_id
]);
$player = $stmt->fetch();

if (!$player) {
    die('해당 선수 정보를 찾을 수 없습니다. (선택한 팀 소속 선수가 아닐 수 있음)');
}

// 이 선수에 대한 우리 팀 노트
$sql_notes = "
    SELECT report_ID, user_ID, note_date, note_content
    FROM Scouting_Report
    WHERE player_ID = :pid
      AND team_ID   = :tid
    ORDER BY note_date DESC
";
$stmt = $pdo->prepare($sql_notes);
$stmt->execute([
    ':pid' => $player_id,
    ':tid' => $my_team_id
]);
$player_notes = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>선수 상세 정보</title>

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


        .info-box, .section-box {
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

                <!-- Aggregate 포함 -->
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
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    로그아웃
                </a>
            </div>
        </div>
    </div>
</nav>



<div class="content-container">

    <!-- 타이틀/설명 박스 -->
    <div class="p-4 mb-4 info-box text-center">
        <h2 class="fw-bold mb-1 text-primary">
            <?= htmlspecialchars($player['player_name']) ?> 선수 상세 정보 
        </h2>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($target_team_name) ?>팀 내 선수의 정보를 확인하고 스카우팅 노트를 관리합니다.
        </p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info py-2"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- 1) 대상 선수 선택 -->
    <form class="p-4 section-box mb-4" method="get" action="player_profile.php">
        <h5 class="fw-bold mb-3">대상 선수 선택</h5>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">대상 팀</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($target_team_name) ?>" readonly>
            </div>
            <div class="col-md-5">
                <label for="player_select" class="form-label fw-bold">선수</label>
                <select class="form-select" id="player_select" name="player_id">
                    <?php foreach ($team_players as $p): ?>
                        <option value="<?= (int)$p['player_ID'] ?>"
                            <?= ((int)$p['player_ID'] === $player_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['player_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-grid">
                <input type="hidden" name="team_id" value="<?= $target_team_id ?>">
                <button type="submit" class="btn btn-primary">
                    선수 정보 보기
                </button>
            </div>
        </div>
    </form>

    <!-- 2) 기본 정보 -->
    <div class="p-4 section-box mb-4">
        <h5 class="fw-bold mb-3">기본 정보 (Read)</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="p-3 bg-white rounded border">
                    <div class="text-muted small mb-1">이름</div>
                    <div class="fw-semibold"><?= htmlspecialchars($player['player_name']) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-white rounded border">
                    <div class="text-muted small mb-1">포지션</div>
                    <div class="fw-semibold"><?= htmlspecialchars($player['position_Name']) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-white rounded border">
                    <div class="text-muted small mb-1">소속팀</div>
                    <div class="fw-semibold"><?= htmlspecialchars($player['team_Name']) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-white rounded border">
                    <div class="text-muted small mb-1">연봉</div>
                    <div class="fw-semibold"><?= number_format($player['salary']) ?> 만원</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3) 스카우팅 노트 작성 -->
    <form class="p-4 section-box mb-4" method="post"
          action="player_profile.php?team_id=<?= $target_team_id ?>&player_id=<?= $player_id ?>">
        <h5 class="fw-bold mb-3">스카우팅 노트 작성 (Create / Update)</h5>

        <input type="hidden" name="action" value="save_note">
        <input type="hidden" name="team_id" value="<?= $target_team_id ?>">
        <input type="hidden" name="player_id" value="<?= $player_id ?>">

        <div class="mb-3">
            <label for="note_content" class="form-label fw-bold">메모 내용</label>
            <textarea class="form-control" id="note_content" name="note_content"
                      rows="4" placeholder="이 선수에 대한 평가를 남겨주세요..."></textarea>
            <small class="text-muted">
                이미 내가 작성한 노트가 있으면 이 내용으로 수정됩니다. (작성자 팀: <?= htmlspecialchars((string)$my_team_id) ?>)
            </small>
        </div>

        <button type="submit" class="btn btn-success">
            노트 저장 (INSERT/UPDATE)
        </button>
    </form>

    <!-- 4) 노트 목록 -->
    <div class="p-4 section-box mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">이 선수에 대한 우리 팀 스카우팅 리포트</h5>
            <a href="mynotes.php?mode=mine" class="btn btn-outline-primary">
                내 모든 노트 보러가기 (수정/삭제) &raquo;
            </a>
        </div>

        <?php if (empty($player_notes)): ?>
            <p class="text-muted mb-0">아직 이 선수에 대한 스카우팅 리포트가 없습니다.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle shadow-sm">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col">작성자 ID</th>
                            <th scope="col">작성 일시</th>
                            <th scope="col">평가 내용</th>
                            <th scope="col" style="width: 120px;">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($player_notes as $n): ?>
                        <tr>
                            <td><?= htmlspecialchars($n['user_ID']) ?></td>
                            <td><?= htmlspecialchars($n['note_date']) ?></td>
                            <td class="text-start">
                                <?= nl2br(htmlspecialchars($n['note_content'])) ?>
                            </td>
                            <td>
                                <?php if ((int)$n['user_ID'] === $user_id): ?>
                                    <form method="post"
                                          action="player_profile.php?team_id=<?= $target_team_id ?>&player_id=<?= $player_id ?>"
                                          onsubmit="return confirm('이 노트를 삭제하시겠습니까?');">
                                        <input type="hidden" name="action" value="delete_note">
                                        <input type="hidden" name="team_id" value="<?= $target_team_id ?>">
                                        <input type="hidden" name="player_id" value="<?= $player_id ?>">
                                        <input type="hidden" name="report_id" value="<?= (int)$n['report_ID'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
