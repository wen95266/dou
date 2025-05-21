<?php
// backend/api/game.php
require_once __DIR__ . '/../includes/db.php'; // 包含数据库连接（如果使用数据库）
require_once __DIR__ . '/../includes/functions.php'; // 包含游戏逻辑函数
require_once __DIR__ . '/../config.php'; // 包含配置，如 ALLOWED_ORIGIN

// CORS Headers - 非常重要！
// 确保 ALLOWED_ORIGIN 在 config.php 中已正确配置
header("Access-Control-Allow-Origin: " . ALLOWED_ORIGIN);
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// 处理 OPTIONS 预检请求 (CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// $conn = getDBConnection(); // 获取数据库连接 (如果使用数据库)

$action = $_POST['action'] ?? $_GET['action'] ?? null; // 优先 POST，兼容 GET
$response = ['success' => false, 'message' => '无效的操作'];

// 从请求中获取数据 (用 $_POST 因为前端使用 x-www-form-urlencoded)
$gameId = $_POST['game_id'] ?? null;
$playerId = $_POST['playerId'] ?? null; // 在前端 Game.init() 中生成
$cards = $_POST['cards'] ?? []; // 对于 playCards, cards 参数名应与前端一致
$bidAmount = isset($_POST['amount']) ? intval($_POST['amount']) : null;


switch ($action) {
    case 'createGame':
        if (!$playerId) {
            $response = ['success' => false, 'message' => 'Player ID is required.'];
            break;
        }
        $result = createNewGameLogic($playerId); // 使用文件存储的逻辑
        if ($result['success']) {
            $response = ['success' => true, 'game_id' => $result['game_id']];
        } else {
            $response = ['success' => false, 'message' => $result['message'] ?? '创建游戏失败'];
        }
        break;

    case 'joinGame':
        if (!$gameId || !$playerId) {
            $response = ['success' => false, 'message' => 'Game ID and Player ID are required.'];
            break;
        }
        $result = joinGameLogic($gameId, $playerId); // 使用文件存储的逻辑
        $response = $result;
        break;

    case 'getGameState':
        if (!$gameId || !$playerId) {
            $response = ['success' => false, 'message' => 'Game ID and Player ID are required.'];
            break;
        }
        $gameState = getGameStateForPlayer($gameId, $playerId); // 使用文件存储的逻辑
        if ($gameState) {
            $response = ['success' => true, 'state' => $gameState];
        } else {
            // 此处需要区分是游戏不存在还是玩家不在此游戏中
            if (!getGameFilePath($gameId) || !file_exists(getGameFilePath($gameId))) {
                $response = ['success' => false, 'message' => 'Game not found.'];
            } else {
                $response = ['success' => false, 'message' => 'Game not found or player not in game.'];
            }
        }
        break;

    case 'playCards':
        if (!$gameId || !$playerId || empty($cards)) {
            $response = ['success' => false, 'message' => 'Game ID, Player ID, and Cards are required.'];
            break;
        }
        // $cards 参数已经在 switch 外面通过 $_POST['cards'] 获取
        $result = playCardsLogic($gameId, $playerId, $cards); // 使用文件存储的逻辑
        $response = $result;
        break;

    case 'passTurn':
        if (!$gameId || !$playerId) {
            $response = ['success' => false, 'message' => 'Game ID and Player ID are required.'];
            break;
        }
        $result = passTurnLogic($gameId, $playerId);
        $response = $result;
        break;

    case 'bid':
        if (!$gameId || !$playerId || $bidAmount === null) {
            $response = ['success' => false, 'message' => 'Game ID, Player ID, and Bid Amount are required.'];
            break;
        }
        $result = bidLogic($gameId, $playerId, $bidAmount);
        $response = $result;
        break;


    // ... 可以添加更多 case, 例如 'bid', 'startGame' (如果不是自动开始) 等

    default:
        $response = ['success' => false, 'message' => "未知操作: {$action}"];
        break;
}

// if ($conn) { // 如果使用了数据库连接
//    $conn->close();
// }

echo json_encode($response);
exit();
?>
