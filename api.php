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
    // 1. 获取所有图片并按分数 (elo) 排序
    $stmt = $pdo->query('SELECT id, path, elo, plays FROM images ORDER BY elo ASC');
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($images);

    // 如果图片不足两张，直接返回
    if ($total < 2) {
        echo json_encode($images);
        exit;
    }

    // 创建一个索引数组，用于按 plays 排序以找到最少被展示的图片
    $indices = range(0, $total - 1);
    usort($indices, function($a, $b) use ($images) {
        return $images[$a]['plays'] <=> $images[$b]['plays'];
    });

    // --- 选择第一张图片 ---
    // 逻辑：80% 概率从对决次数最少的 50% 图片中选，20% 概率完全随机
    $idx1 = 0;
    if (mt_rand(0, 99) < 20) {
        // 完全随机
        $idx1 = mt_rand(0, $total - 1);
    } else {
        // 从对决次数最少的一半图片中随机选一张
        $poolSize = (int)($total * 0.5);
        if ($poolSize < 1) $poolSize = 1;
        $randPos = mt_rand(0, $poolSize - 1);
        $idx1 = $indices[$randPos];
    }

    // --- 选择第二张图片 ---
    // 逻辑：90% 概率从第一张图的分数邻近范围选，10% 概率完全随机
    $idx2 = 0;
    if (mt_rand(0, 99) < 10) {
        // 完全随机，确保不与第一张相同
        do {
            $idx2 = mt_rand(0, $total - 1);
        } while ($idx2 === $idx1);
    } else {
        // 邻近范围选择：前后 10% 或至少 5 个位置
        $range = max(5, (int)($total * 0.1));
        $min = max(0, $idx1 - $range);
        $max = min($total - 1, $idx1 + $range);

        // 在范围内随机选择，确保不与第一张相同
        // 如果范围内只有一张图（即第一张图本身），则回退到完全随机
        if ($max > $min) {
            do {
                $idx2 = mt_rand($min, $max);
            } while ($idx2 === $idx1);
        } else {
            do {
                $idx2 = mt_rand(0, $total - 1);
            } while ($idx2 === $idx1);
        }
    }

    // 返回选中的两张图
    echo json_encode([$images[$idx1], $images[$idx2]]);
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
