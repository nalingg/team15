<?php
// mynotes.php
session_start();

// 디버깅용(개발할 때만 켜 두기)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';   // PDO $pdo 사용

// 1) 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$team_id = (int)$_SESSION['team_id'];

// -----------------------
// 1) 팀 이름 조회 (Prepared Statement)
// -----------------------
$sql_team = "SELECT team_Name FROM Team WHERE team_ID = :tid";
$stmt = $pdo->prepare($sql_team);
$stmt->execute([':tid' => $team_id]);
$row = $stmt->fetch();
$team_name = $row ? $row['team_Name'] : ($team_id . "번 팀");

// -----------------------
// 2) 모드 설정 (mine / team)
// -----------------------
$mode = $_GET['mode'] ?? 'mine';
$mode = ($mode === 'team') ? 'team' : 'mine';

$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$message = "";

// -----------------------
// 3) POST 처리 (update / delete)
// -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action    = $_POST['action'] ?? '';
    $mode      = $_POST['mode'] ?? $mode;
    $mode      = ($mode === 'team') ? 'team' : 'mine';
    $report_id = (int)($_POST['report_id'] ?? 0);

    // ===== UPDATE (내 노트만) =====
    if ($action === 'update' && $report_id > 0) {

        $note_content = trim($_POST['note_content'] ?? '');
        if ($note_content === '') {
            $message = "내용을 비울 수 없습니다.";
        } else {
            $sql = "
                UPDATE Scouting_Report
                SET note_content = :content, note_date = NOW()
                WHERE report_ID = :rid AND user_ID = :uid
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':content' => $note_content,
                ':rid'     => $report_id,
                ':uid'     => $user_id
            ]);

            $message = "노트가 수정되었습니다.";
            $edit_id = 0;
        }

    // ===== DELETE (트랜잭션 사용) =====
    } elseif ($action === 'delete' && $report_id > 0) {

        $author_pw = $_POST['author_pw'] ?? '';

        try {
            $pdo->beginTransaction();

            $sql_info = "
                SELECT SR.report_ID, SR.user_ID, U.team_ID
                FROM Scouting_Report SR
                JOIN user U ON SR.user_ID = U.user_ID
                WHERE SR.report_ID = :rid
                FOR UPDATE
            ";
            $stmt = $pdo->prepare($sql_info);
            $stmt->execute([':rid' => $report_id]);
            $info = $stmt->fetch();

            if (!$info) {
                throw new Exception("해당 리포트가 존재하지 않습니다.");
            }

            $author_id  = (int)$info['user_ID'];
            $author_tid = (int)$info['team_ID'];

            if ($author_tid !== $team_id) {
                throw new Exception("다른 팀이 작성한 노트는 삭제할 수 없습니다.");
            }

            if ($author_id === $user_id) {
                $sql_del = "DELETE FROM Scouting_Report WHERE report_ID = :rid";
                $stmt = $pdo->prepare($sql_del);
                $stmt->execute([':rid' => $report_id]);
            } else {
                if ($author_pw === '') {
                    throw new Exception("비밀번호를 입력해야 삭제할 수 있습니다.");
                }

                $sql_pw = "SELECT user_PW FROM user WHERE user_ID = :aid";
                $stmt = $pdo->prepare($sql_pw);
                $stmt->execute([':aid' => $author_id]);
                $pwres = $stmt->fetch();

                if (!$pwres || $pwres['user_PW'] !== $author_pw) {
                    throw new Exception("비밀번호가 일치하지 않아 삭제할 수 없습니다.");
                }

                $sql_del = "DELETE FROM Scouting_Report WHERE report_ID = :rid";
                $stmt = $pdo->prepare($sql_del);
                $stmt->execute([':rid' => $report_id]);
            }

            $pdo->commit();
            $message = "노트가 삭제되었습니다.";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $e->getMessage() ?: "삭제 처리 중 오류가 발생했습니다.";
        }

        $edit_id = 0;
    }
}

// -----------------------
// 4) 노트 목록 조회 (mine / team)
// -----------------------
if ($mode === 'mine') {
    $sql = "
        SELECT SR.report_ID, SR.user_ID, SR.note_date, SR.note_content, P.player_name
        FROM Scouting_Report SR
        JOIN Player P ON SR.player_ID = P.player_ID
        WHERE SR.user_ID = :uid
        ORDER BY SR.note_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $user_id]);

} else {
    $sql = "
        SELECT SR.report_ID, SR.user_ID, SR.note_date, SR.note_content, P.player_name
        FROM Scouting_Report SR
        JOIN Player P ON SR.player_ID = P.player_ID
        JOIN user U   ON SR.user_ID = U.user_ID
        WHERE U.team_ID = :tid
        ORDER BY SR.note_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tid' => $team_id]);
}

$notes = $stmt->fetchAll();

// -----------------------
// 5) 편집 모드용 데이터
// -----------------------
$edit_note = null;
if ($edit_id > 0) {
    $sql_edit = "
        SELECT report_ID, note_content
        FROM Scouting_Report
        WHERE report_ID = :rid AND user_ID = :uid
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql_edit);
    $stmt->execute([
        ':rid' => $edit_id,
        ':uid' => $user_id
    ]);
    $edit_note = $stmt->fetch();

    if (!$edit_note) {
        $edit_id = 0;
    }
}

// 현재 페이지명
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>내 스카우팅 노트</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Inter 폰트 (rollup 동일) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        .container { max-width: 1100px; }

        /* rollup-style 페이지 타이틀 */
        .page-title {
            color: var(--bs-primary);
            font-weight: 700;
        }

        /* rollup-style box/card */
        .section-box {
            background: #f8f9fa;
            border: 1px solid #e4e4e4;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        /* rollup 네비 톤 */
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.7);
            font-weight: 500;
        }
        .navbar-nav .nav-link:hover { color: #fff; }
        .navbar-nav .nav-link.active {
            color: #fff !important;
            font-weight: 700;
        }

        table thead th,
        table td {
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- 상단 네비게이션 바 (rollup과 동일) -->
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
                           href="rollup.php?metric=score">팀별 스탯 순위 분석</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page, ['player_select.php','player_profile.php','mynotes.php']) ? 'active' : '' ?>"
                           href="player_select.php">스카우팅 노트</a>
                    </li>

                </ul>

                <a href="logout.php" class="btn btn-outline-light btn-sm">로그아웃</a>
            </div>
        </div>
    </nav>

    <!-- 페이지 타이틀 -->
    <h1 class="mb-2 display-6 text-center page-title">
        내 스카우팅 노트
        <small class="text-muted fs-6">
            (<?= $mode === 'mine' ? '내가 작성한 것만' : '팀 전체' ?>)
        </small>
    </h1>

    <p class="text-secondary mb-4 text-center">
        <?php if ($mode === 'mine'): ?>
            내가 작성한 스카우팅 노트를 확인하고 수정/삭제할 수 있습니다.
        <?php else: ?>
            <?= htmlspecialchars($team_name) ?> 소속 감독들이 작성한 모든 스카우팅 노트를 확인할 수 있습니다.
            (다른 사람이 작성한 노트를 삭제하려면 작성자 계정 비밀번호를 알아야 합니다.)
        <?php endif; ?>
    </p>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- 모드 버튼 -->
    <div class="d-flex justify-content-end mb-3">
        <a href="mynotes.php?mode=mine"
           class="btn btn-sm <?= $mode === 'mine' ? 'btn-primary' : 'btn-outline-primary' ?> me-2">
            내 노트만
        </a>
        <a href="mynotes.php?mode=team"
           class="btn btn-sm <?= $mode === 'team' ? 'btn-primary' : 'btn-outline-primary' ?>">
            팀 전체 노트
        </a>
    </div>

    <!-- 편집 폼 -->
    <?php if ($edit_note): ?>
        <div class="p-4 section-box mb-4">
            <h5 class="fw-bold mb-3">선택한 노트 수정 (리포트 ID: <?= $edit_note['report_ID'] ?>)</h5>
            <form method="post" action="mynotes.php?mode=<?= $mode ?>&edit_id=<?= $edit_note['report_ID'] ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="mode" value="<?= $mode ?>">
                <input type="hidden" name="report_id" value="<?= $edit_note['report_ID'] ?>">

                <div class="mb-3">
                    <textarea name="note_content" class="form-control" rows="4"
                              placeholder="수정할 내용을 입력하세요."><?= htmlspecialchars($edit_note['note_content']) ?></textarea>
                </div>

                <button type="submit" class="btn btn-success">저장</button>
                <a href="mynotes.php?mode=<?= $mode ?>" class="btn btn-secondary">취소</a>
            </form>
        </div>
    <?php endif; ?>

    <!-- 노트 리스트 -->
    <div class="p-0 section-box">
        <div class="table-responsive">
            <table class="table mb-0 table-striped table-hover align-middle">
                <thead class="table-dark">
                <tr>
                    <th scope="col">리포트 ID</th>
                    <th scope="col">선수 이름</th>
                    <th scope="col">작성자 ID</th>
                    <th scope="col">작성 일시</th>
                    <th scope="col">내용</th>
                    <th scope="col" style="width: 150px;">관리</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($notes)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            등록된 노트가 없습니다.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($notes as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['report_ID']) ?></td>
                            <td><?= htmlspecialchars($row['player_name']) ?></td>
                            <td><?= htmlspecialchars($row['user_ID']) ?></td>
                            <td><?= htmlspecialchars($row['note_date']) ?></td>
                            <td class="text-start"><?= nl2br(htmlspecialchars($row['note_content'])) ?></td>
                            <td>
                                <?php if ($mode === 'mine' || (int)$row['user_ID'] === $user_id): ?>
                                    <!-- 내가 쓴 노트 -->
                                    <a href="mynotes.php?mode=<?= $mode ?>&edit_id=<?= $row['report_ID'] ?>"
                                       class="btn btn-sm btn-warning mb-1">
                                        수정
                                    </a>
                                    <form method="post"
                                          action="mynotes.php?mode=<?= $mode ?>"
                                          onsubmit="return confirm('이 노트를 삭제하시겠습니까?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="mode" value="<?= $mode ?>">
                                        <input type="hidden" name="report_id" value="<?= $row['report_ID'] ?>">
                                        <input type="hidden" name="author_pw" value="">
                                        <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                                    </form>

                                <?php elseif ($mode === 'team'): ?>
                                    <!-- 팀 모드 + 타인 노트 (비밀번호 필요) -->
                                    <form method="post"
                                          action="mynotes.php?mode=<?= $mode ?>"
                                          onsubmit="return deleteWithPassword(this);">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="mode" value="<?= $mode ?>">
                                        <input type="hidden" name="report_id" value="<?= $row['report_ID'] ?>">
                                        <input type="hidden" name="author_pw" value="">
                                        <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function deleteWithPassword(form) {
    if (!confirm('정말 이 노트를 삭제하시겠습니까?')) {
        return false;
    }
    const pw = prompt('삭제하시려면 작성자 계정 비밀번호를 입력하세요.');
    if (pw === null) return false;
    if (pw.trim() === '') {
        alert('비밀번호를 입력해야 삭제할 수 있습니다.');
        return false;
    }
    form.querySelector('input[name="author_pw"]').value = pw;
    return true;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>