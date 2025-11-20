<?php
// 1. 가장 강력한 에러 출력 설정 (500 에러 원인 파악용)
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 디버깅용 박스 출력 (화면 상단에 빨간 박스로 상태 표시)
echo "<div style='background: #ffebee; border: 2px solid #f44336; padding: 10px; margin: 10px; color: #333;'>";
echo "<strong>[디버깅 상태]</strong><br>";
echo "PHP 버전: " . phpversion() . "<br>";
echo "POST 데이터: "; print_r($_POST); echo "<br>";
if (isset($_SESSION['user_id'])) {
    echo "로그인 ID: " . $_SESSION['user_id'];
} else {
    echo "로그인 상태: <span style='color:red'>로그인 안됨 (리다이렉트 예정)</span>";
}
echo "</div>";

// 3. 로그인 체크
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    // 디버깅을 위해 주석 처리함. 실제 사용시 주석 해제 필요
    // header('Location: login.php');
    // exit;
    $_SESSION['team_id'] = 1000; // 테스트용 강제 할당
}
    */

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest';
$team_id = $_SESSION['team_id'];

// DB 연결
require_once 'db.php';

// 변수 초기화 (중요: 이게 없으면 500 에러 남)
$rows = [];
$error_msg = "";

$position_id_state = isset($_POST['position']) ? $_POST['position'] : '0';
$team_id_state = isset($_POST['team']) ? $_POST['team'] : '1000';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $position_id = $position_id_state;
    $team_id = $team_id_state;

    try {
        $sql = "SELECT
                    p.player_name,
                    pp.position_Name,
                    SUM(CASE WHEN g.round_ID IN (1, 2) THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS points_1_2,
                    SUM(CASE WHEN g.round_ID IN (3, 4) THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS points_3_4,
                    SUM(CASE WHEN g.round_ID IN (5, 6) THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS points_5_6,
                    SUM(CASE WHEN g.round_ID <= 2 THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS cumulative_2,
                    SUM(CASE WHEN g.round_ID <= 4 THEN (s.open_suc + s.backquick_suc) ELSE 0 END) AS cumulative_4,
                    SUM(s.open_suc + s.backquick_suc) AS cumulative_6
                FROM Player p
                JOIN Player_Position pp ON p.position_ID = pp.position_ID
                JOIN Att_Stats s ON p.player_ID = s.player_ID
                JOIN Game g ON s.game_ID = g.game_ID
                WHERE
                    p.current_team_ID = ?
                    AND (? = 0 OR p.position_ID = ?)
                GROUP BY
                    p.player_ID, p.player_name, pp.position_Name
                ORDER BY
                    cumulative_6 DESC, player_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$team_id, $position_id, $position_id]);
        $rows = $stmt->fetchAll();

        /*
        // 데이터가 잘 왔는지 확인용 (화면에 출력됨)
        echo "<div style='background:#e3f2fd; padding:10px; margin:10px; border:1px solid blue;'>";
        echo "<strong>[쿼리 결과 개수]</strong> " . count($rows) . "건 조회됨.<br>";
        var_dump($rows); // 상세 데이터 보고 싶으면 주석 해제
        echo "</div>"; */

    } catch (PDOException $e) {
        $error_msg = "쿼리 실행 오류: " . $e->getMessage();
        echo "<div style='color:red; font-weight:bold;'>에러 발생: " . $error_msg . "</div>";
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>라운드별 누적 득점</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .container { max-width: 960px; }
    </style>
</head>
<body>

    <div class="container">
        <!-- 네비게이션 생략 없이 유지 -->
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
        <h2> [Windowing/Aggregate] 라운드별 누적 득점</h2>
        <hr>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

<form method="POST" action="" class="card card_body bg-light mb-4 p-4">
    <div class="row g-3">

        <div class="col-6">
            <label class="form-label fw-bold">1. 포지션 선택</label>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="position" id="pos_all" value="0" <?php if ($position_id_state == '0') echo 'checked'; ?>>
                <label class="form-check-label" for="pos_all">모두 함께 보기</label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="position" id="pos_oh" value="10" <?php if ($position_id_state == '10') echo 'checked'; ?>>
                <label class="form-check-label" for="pos_oh">아웃사이드 히터(OH)</label>
            </div> <div class="form-check">
                <input class="form-check-input" type="radio" name="position" id="pos_op" value="20" <?php if ($position_id_state == '20') echo 'checked'; ?>>
                <label class="form-check-label" for="pos_op">아포짓 스파이커 (OP)</label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="position" id="pos_mb" value="30" <?php if ($position_id_state == '30') echo 'checked'; ?>>
                <label class="form-check-label" for="pos_mb">미들 블로커 (MB)</label>
            </div>
        </div>
        <div class="col-6">
            <label class="form-label fw-bold">2. 팀 선택</label>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="team" id="team_1000" value="1000" <?php if ($team_id_state == '1000') echo 'checked'; ?>>
                <label class="form-check-label" for="team_1000">GS칼텍스</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="team" id="team_2000" value="2000" <?php if ($team_id_state == '2000') echo 'checked'; ?>>
                <label class="form-check-label" for="team_2000">정관장</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="team" id="team_3000" value="3000" <?php if ($team_id_state == '3000') echo 'checked'; ?>>
                <label class="form-check-label" for="team_3000">현대건설</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="team" id="team_4000" value="4000" <?php if ($team_id_state == '4000') echo 'checked'; ?>>
                <label class="form-check-label" for="team_4000">IBK기업은행</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="team" id="team_5000" value="5000" <?php if ($team_id_state == '5000') echo 'checked'; ?>>
                <label class="form-check-label" for="team_5000">한국도로공사</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="team" id="team_6000" value="6000" <?php if ($team_id_state == '6000') echo 'checked'; ?>>
                <label class="form-check-label" for="team_6000">페퍼저축은행</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="team" id="team_7000" value="7000" <?php if ($team_id_state == '7000') echo 'checked'; ?>>
                <label class="form-check-label" for="team_7000">흥국생명</label>
            </div>
        </div>
        </div>
    <div class="text-center mt-4">
        <button type="submit" class="btn btn-primary btn-lg">분석 실행</button>
    </div>
</form>

        <h3 class="mt-5"> 분석 결과</h3>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
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
                    <?php
                    if (!empty($rows)) {
                        foreach ($rows as $row) {
                    ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['player_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['position_Name']); ?></td>
                                <td><?php echo number_format($row['points_1_2']); ?></td>
                                <td><?php echo number_format($row['points_3_4']); ?></td>
                                <td><?php echo number_format($row['points_5_6']); ?></td>
                                <td><?php echo number_format($row['cumulative_2']); ?></td>
                                <td><?php echo number_format($row['cumulative_4']); ?></td>
                                <td><?php echo number_format($row['cumulative_6']); ?></td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center'>데이터가 없습니다. (분석 실행을 눌러주세요)</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
