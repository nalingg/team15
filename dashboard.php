<?php
// dashboard.php
// 메인 대시보드: 로그인한 감독(사용자)에게 팀 정보와 분석 메뉴를 보여주는 페이지

session_start();                 // 세션 시작 (로그인 정보 사용)
require_once 'db_connect.php';   // DB 연결 설정 포함, $conn (mysqli 객체) 생성

// 1) 로그인 여부 & 팀 정보 세션 체크
//    user_id와 team_id가 세션에 없으면 로그인 안 한 상태로 보고 로그인 페이지로 보냄
if (!isset($_SESSION['user_id'], $_SESSION['team_id'])) {
    header("Location: login.php");  // 로그인 페이지로 리다이렉트
    exit;                           // 이후 코드 실행 방지
}

// 세션에 저장된 값들을 정수형으로 변환해 사용
$user_id = (int)$_SESSION['user_id'];
$team_id = (int)$_SESSION['team_id'];

// 2) 소속 팀 이름 조회 (Prepared Statement 사용 → SQL Injection 방지)
//    team_ID를 조건으로 team_Name을 가져온다.
$sql  = "SELECT team_Name FROM team WHERE team_ID = ?";  // ? 자리에 파라미터 바인딩 예정
$stmt = $conn->prepare($sql);                            // SQL 컴파일/준비

// prepare 실패 시 에러 메시지 출력 후 종료
if (!$stmt) {
    die('팀 쿼리 준비 실패: ' . $conn->error);
}

// 정수형 파라미터("i")로 team_id 바인딩 → SQL Injection 방지
$stmt->bind_param("i", $team_id);

// 쿼리 실행
$stmt->execute();

// 실행 결과를 result 객체로 받아옴
$result = $stmt->get_result();

// 한 행(row)을 연관 배열로 가져오기
$row = $result->fetch_assoc();

// 결과가 있으면 해당 team_Name 사용, 없으면 "N번 팀" 형태의 기본 문자열 사용
$team_name = $row ? $row['team_Name'] : ($team_id . "번 팀");

// 사용이 끝난 statement 정리
$stmt->close();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <!-- 반응형 웹을 위한 viewport 설정 -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>메인 대시보드</title>

    <!-- Bootstrap 5 CSS CDN -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- 간단한 페이지 스타일 -->
    <style>
        body { padding: 20px; }          /* 전체 여백 */
        .container { max-width: 960px; } /* 본문 폭 제한 */
    </style>
</head>
<body>

<div class="container">
    <!-- 상단 네비게이션 바 -->
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

    <!-- 메인 환영 영역 (Jumbotron 스타일) -->
    <div class="p-5 mb-4 bg-light rounded-3">
        <h1 class="display-5 fw-bold">감독님, 환영합니다.</h1>

        <p class="fs-4">
            현재 로그인한 사용자 ID:
            <!-- XSS 방지를 위한 htmlspecialchars 적용 -->
            <strong><?= htmlspecialchars((string)$user_id, ENT_QUOTES, 'UTF-8') ?></strong><br>

            소속 팀:
            <!-- 팀 이름도 htmlspecialchars로 이스케이프 -->
            <strong><?= htmlspecialchars($team_name, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    </div>

    <!-- 4대 고급 분석 기능 카드 목록 -->
    <h3>4대 고급 분석</h3>
    <!-- row-cols-1: 모바일에서 1열, row-cols-md-2: md 이상에서 2열, g-4: 카드 사이 여백 -->
    <div class="row row-cols-1 row-cols-md-2 g-4">

        <!-- (1) [Ranking] 가성비 선수 랭킹 -->
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">[Ranking] 가성비 선수 랭킹</h5>
                    <p class="card-text">
                        '연봉 대비 득점'으로 FA/신인 선수의 순위를 매깁니다.
                    </p>
                    <!-- 가성비 분석 페이지로 이동 -->
                    <a href="analysis_value.php" class="btn btn-primary">
                        분석 페이지로 이동 &raquo;
                    </a>
                </div>
            </div>
        </div>

        <!-- (2) [Windowing/Aggregate] 라운드별 누적 득점 -->
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">[Windowing/Aggregate] 라운드별 누적 득점</h5>
                    <p class="card-text">
                        팀과 포지션을 기준으로 선수들의 라운드별 득점 및 누적 득점을 분석합니다.
                    </p>
                    <!-- 라운드별 누적 득점 분석 페이지로 이동 -->
                    <a href="score_accumulation.php" class="btn btn-primary">
                        분석 페이지로 이동 &raquo;
                    </a>
                </div>
            </div>
        </div>

        <!-- (3) [OLAP] 선수 폼 분석 -->
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">[OLAP] 선수 폼 분석</h5>
                    <p class="card-text">
                        '선수 A' vs '선수 B' vs '리그 평균'을 비교합니다.
                    </p>
                    <!-- metric=score를 쿼리스트링으로 넘겨 점수 기준 OLAP 분석 -->
                    <a href="rollup.php?metric=score" class="btn btn-primary">
                        분석 페이지로 이동 &raquo;
                    </a>
                </div>
            </div>
        </div>

        <!-- (4) [CRUD] 스카우팅 노트 -->
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">[CRUD] 스카우팅 노트</h5>
                    <p class="card-text">
                        선수에 대한 평가를 입력/수정/삭제 및 조회합니다.
                    </p>
                    <!-- 팀과 선수를 먼저 선택하는 페이지로 이동 -->
                    <a href="player_select.php" class="btn btn-primary">
                        팀 및 선수 선택 페이지로 이동 &raquo;
                    </a>
                </div>
            </div>
        </div>

    </div> <!-- row 끝 -->

</div> <!-- .container 끝 -->

<!-- Bootstrap 5 JS 번들 (팝오버, 드롭다운 등 인터랙션용) -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>
