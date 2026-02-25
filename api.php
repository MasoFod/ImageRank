<?php
// api.php - 负责所有后端逻辑: 随机抽取、投票处理、排行榜
header('Content-Type: application/json; charset=utf-8');

// 配置数据库连接
$dsn = 'mysql:host=localhost;dbname=imagerank;charset=utf8mb4';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'pair') {
    $sql = "
        (SELECT id, path FROM images ORDER BY plays +(RAND() * 2000) ASC LIMIT 1)
        UNION ALL
        (SELECT id, path FROM images ORDER BY plays + (RAND() * 2000) DESC LIMIT 1)
    ";
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 如果选到了两张一样的（例如数据库只有一张图），尝试修正
    if (count($rows) === 2 && $rows[0]['id'] === $rows[1]['id']) {
        // 尝试获取一张不同的图
        $stmt = $pdo->prepare('SELECT id, path FROM images WHERE id != ? ORDER BY RAND() LIMIT 1');
        $stmt->execute([$rows[0]['id']]);
        $diff = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($diff) {
            $rows[1] = $diff;
        }
        // 如果没有不同的图，保持两张一样的（前端应能处理或用户需添加图片）
    }
    
    echo json_encode($rows);
    exit;
}

if ($action === 'leaderboard') {
    $stmt = $pdo->query('SELECT id, path, elo, wins, plays FROM images ORDER BY elo DESC LIMIT 10');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}

if ($action === 'vote') {
    // 投票接口，需要 POST 参数 winner 和 loser
    $winner = isset($_POST['winner']) ? intval($_POST['winner']) : 0;
    $loser  = isset($_POST['loser']) ? intval($_POST['loser']) : 0;
    if (!$winner || !$loser || $winner === $loser) {
        http_response_code(400);
        echo json_encode(['error' => '参数错误']);
        exit;
    }

    // 获取客户端 IP，支持反向代理
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // 限流检查：同 IP 60 秒内只能投一次
    $stmt = $pdo->prepare('SELECT last_vote FROM ip_votes WHERE ip = ?');
    $stmt->execute([$ip]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $last = strtotime($row['last_vote']);
        $elapsed = time() - $last;
        // 只有当时间差在0~60秒之间才视为限流；负值表示数据库时钟在未来，允许投票并更新
        if ($elapsed >= 0 && $elapsed < 60) {
            http_response_code(429);
            echo json_encode(['error' => '请稍候再投票']);
            exit;
        }
        // 此时要么超时，要么时钟异常，更新为当前时间
        $up = $pdo->prepare('UPDATE ip_votes SET last_vote = CURRENT_TIMESTAMP WHERE ip = ?');
        $up->execute([$ip]);
    } else {
        $ins = $pdo->prepare('INSERT INTO ip_votes(ip) VALUES(?)');
        $ins->execute([$ip]);
    }

    // 获取当前分数
    $stmt = $pdo->prepare('SELECT elo FROM images WHERE id = ?');
    $stmt->execute([$winner]);
    $elo_w = $stmt->fetchColumn();
    $stmt->execute([$loser]);
    $elo_l = $stmt->fetchColumn();

    if ($elo_w === false || $elo_l === false) {
        http_response_code(400);
        echo json_encode(['error' => '图片不存在']);
        exit;
    }

    // ELO 计算函数
    function calc_new_elo($Ra, $Rb, $k = 20) {
        $Ea = 1 / (1 + pow(10, ($Rb - $Ra) / 400));
        return $Ra + $k * (1 - $Ea);
    }

    // 计算胜者与败者的新分数
    $Ea = 1 / (1 + pow(10, ($elo_l - $elo_w) / 400));
    $Eb = 1 - $Ea;
    $new_w = round($elo_w + 20 * (1 - $Ea));
    $new_l = round($elo_l + 20 * (0 - $Eb));

    $upd = $pdo->prepare('UPDATE images SET elo = ?, wins = wins + 1, plays = plays + 1 WHERE id = ?');
    $upd->execute([$new_w, $winner]);
    $upd = $pdo->prepare('UPDATE images SET elo = ?, plays = plays + 1 WHERE id = ?');
    $upd->execute([$new_l, $loser]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => '未知操作']);
exit;
