<?php
// backend/includes/functions.php
require_once __DIR__ . '/../config.php'; // For GAME_SESSIONS_DIR

// --- 游戏状态管理 (简单文件存储示例) ---
// 注意：对于生产环境或多并发，文件存储不是最佳选择，应使用数据库。
// 这里仅为演示。

function getGameFilePath($gameId) {
    // Sanitize gameId to prevent directory traversal attacks
    $gameId = basename($gameId); // Basic sanitization
    if (empty($gameId) || strpos($gameId, '.') !== false || strpos($gameId, '/') !== false) {
        return null; // Invalid game ID
    }
    return GAME_SESSIONS_DIR . '/' . $gameId . '.json';
}

function loadGameState($gameId) {
    $filePath = getGameFilePath($gameId);
    if ($filePath && file_exists($filePath)) {
        $content = file_get_contents($filePath);
        return json_decode($content, true);
    }
    return null;
}

function saveGameState($gameId, $state) {
    $filePath = getGameFilePath($gameId);
    if ($filePath) {
        return file_put_contents($filePath, json_encode($state, JSON_PRETTY_PRINT)) !== false;
    }
    return false;
}

function deleteGameStateFile($gameId) {
    $filePath = getGameFilePath($gameId);
    if ($filePath && file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}


// --- 核心游戏逻辑函数 (占位符/非常简化的示例) ---

function generateGameId() {
    return uniqid('game_');
}

/**
 * 创建新游戏
 * @param string $creatorPlayerId
 * @return array ['success' => bool, 'game_id' => string|null, 'message' => string|null]
 */
function createNewGameLogic($creatorPlayerId) {
    $gameId = generateGameId();
    $initialDeck = createDeck(); // 创建一副牌
    shuffle($initialDeck); // 洗牌

    // 初始化玩家 (这里简化为只记录ID和手牌，实际需要更多信息)
    $players = [
        $creatorPlayerId => ['id' => $creatorPlayerId, 'hand' => [], 'bid' => -1, 'is_landlord' => false, 'role' => null]
    ];

    // 游戏状态
    $gameState = [
        'game_id' => $gameId,
        'status' => 'waiting_for_players', // waiting_for_players, bidding, playing, finished
        'players' => $players,
        'deck' => $initialDeck, // 完整牌堆，发牌后会减少
        'landlord_cards' => [], // 地主底牌
        'current_turn_player_id' => null, // 当前轮到谁
        'last_played_cards' => [],
        'last_player_id' => null,
        'bid_history' => [], // [['playerId' => 'xxx', 'bid' => 1], ...]
        'current_bidder_index' => 0, // 玩家加入后，按顺序叫地主
        'highest_bid' => 0,
        'landlord_id' => null,
        'turn_order' => [], // 玩家加入后确定的出牌顺序
        'winner' => null,
        'created_at' => time(),
        'updated_at' => time()
    ];

    if (saveGameState($gameId, $gameState)) {
        return ['success' => true, 'game_id' => $gameId];
    } else {
        return ['success' => false, 'message' => '无法保存游戏状态'];
    }
}

/**
 * 玩家加入游戏
 * @param string $gameId
 * @param string $playerId
 * @return array ['success' => bool, 'message' => string|null]
 */
function joinGameLogic($gameId, $playerId) {
    $gameState = loadGameState($gameId);
    if (!$gameState) {
        return ['success' => false, 'message' => '游戏不存在'];
    }

    if (count($gameState['players']) >= MAX_PLAYERS) {
        return ['success' => false, 'message' => '房间已满'];
    }

    if (isset($gameState['players'][$playerId])) {
        return ['success' => true, 'message' => '你已在游戏中']; // Allow rejoining
    }

    $gameState['players'][$playerId] = ['id' => $playerId, 'hand' => [], 'bid' => -1, 'is_landlord' => false, 'role' => null];
    $gameState['updated_at'] = time();

    // 如果人数已满，开始发牌并进入叫分阶段
    if (count($gameState['players']) === MAX_PLAYERS) {
        $gameState = dealCards($gameState);
        $gameState['status'] = 'bidding';
        // 设定叫分顺序和第一个叫分的人
        $playerIds = array_keys($gameState['players']);
        shuffle($playerIds); //随机一个叫分开始的人，或者固定
        $gameState['turn_order'] = $playerIds; // 也可以是加入顺序
        $gameState['current_bidder_index'] = 0;
        $gameState['current_turn_player_id'] = $gameState['turn_order'][0];
    }

    if (saveGameState($gameId, $gameState)) {
        return ['success' => true, 'message' => '成功加入游戏'];
    } else {
        return ['success' => false, 'message' => '无法保存游戏状态'];
    }
}

/**
 * 创建一副斗地主牌 (54张)
 * @return array
 */
function createDeck() {
    $suits = ['H', 'D', 'C', 'S']; // 红桃,方块,梅花,黑桃
    $ranks = ['3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K', 'A', '2']; // T for 10
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($ranks as $rank) {
            $deck[] = $suit . $rank;
        }
    }
    $deck[] = 'SJOKER'; // 小王
    $deck[] = 'BJOKER'; // 大王
    return $deck;
}

/**
 * 发牌逻辑
 * @param array $gameState
 * @return array modified $gameState
 */
function dealCards($gameState) {
    $deck = $gameState['deck']; // 应该是已经洗好的牌
    $playerIds = array_keys($gameState['players']);

    // 每个玩家发17张牌
    for ($i = 0; $i < INITIAL_CARDS_COUNT; $i++) {
        foreach ($playerIds as $playerId) {
            if (count($deck) > 0) {
                $gameState['players'][$playerId]['hand'][] = array_pop($deck);
            }
        }
    }
    // 剩余的作为底牌
    $gameState['landlord_cards'] = array_slice($deck, 0, LANDLORD_EXTRA_CARDS);
    $gameState['deck'] = array_slice($deck, LANDLORD_EXTRA_CARDS); // 剩余的牌（理论上是0）

    // 对每个玩家的手牌进行排序 (可选，但推荐)
    foreach ($playerIds as $playerId) {
        $gameState['players'][$playerId]['hand'] = sortPlayerHand($gameState['players'][$playerId]['hand']);
    }
    return $gameState;
}

/**
 * 对玩家手牌进行排序 (需要定义牌的权重)
 * @param array $hand
 * @return array sorted $hand
 */
function sortPlayerHand(array $hand) {
    // 定义牌的权重，用于排序。大王 > 小王 > 2 > A > K ... > 3
    // 花色不参与比较大小，但可以用于排序时的次要依据。
    // 这是一个复杂的部分，你需要实现一个自定义的比较函数 usort()
    // 简化示例:
    // usort($hand, 'compareCards'); // 你需要实现 compareCards 函数
    // 暂时返回原样
    return $hand;
}
// function compareCards($cardA, $cardB) { /* ... 比较逻辑 ... */ return 0; }

/**
 * 处理玩家叫分
 */
function bidLogic($gameId, $playerId, $bidAmount) {
    $gameState = loadGameState($gameId);
    if (!$gameState) return ['success' => false, 'message' => '游戏不存在'];
    if ($gameState['status'] !== 'bidding') return ['success' => false, 'message' => '当前不是叫分阶段'];
    if ($gameState['current_turn_player_id'] !== $playerId) return ['success' => false, 'message' => '未轮到你叫分'];
    if (!isset($gameState['players'][$playerId])) return ['success' => false, 'message' => '玩家不在游戏中'];

    $bidAmount = intval($bidAmount);
    if ($bidAmount !== 0 && ($bidAmount < 1 || $bidAmount > 3 || $bidAmount <= $gameState['highest_bid'])) {
        return ['success' => false, 'message' => '无效的叫分值'];
    }

    $gameState['players'][$playerId]['bid'] = $bidAmount;
    $gameState['bid_history'][] = ['playerId' => $playerId, 'bid' => $bidAmount];

    if ($bidAmount > $gameState['highest_bid']) {
        $gameState['highest_bid'] = $bidAmount;
        $gameState['landlord_id'] = $playerId; // 潜在地主
    }

    // 决定下一个叫分者或结束叫分
    $currentBidderIndex = $gameState['current_bidder_index'];
    $totalPlayers = count($gameState['turn_order']);
    $nextBidderIndex = ($currentBidderIndex + 1) % $totalPlayers;
    $gameState['current_bidder_index'] = $nextBidderIndex;
    $gameState['current_turn_player_id'] = $gameState['turn_order'][$nextBidderIndex];

    // 叫分结束条件：
    // 1. 有人叫了3分
    // 2. 所有人都叫过一次，且最高叫分者是最后一个叫的人，或者他之后的人都不叫了
    // 3. 所有人都选择不叫
    $bidsMadeCount = count($gameState['bid_history']);
    $allPlayersBidOnce = $bidsMadeCount >= $totalPlayers;

    if ($bidAmount === 3 || ($allPlayersBidOnce && $gameState['highest_bid'] > 0) ) {
        // 检查是否所有叫过分的人都完成了，或者最高分者是最后一个
        $lastBidEntry = end($gameState['bid_history']);
        $isHighestBidderLastToBid = ($lastBidEntry['playerId'] === $gameState['landlord_id'] && $lastBidEntry['bid'] === $gameState['highest_bid']);

        // 一个简单的结束条件：如果有人叫了分，并且所有人都表态了
        // 或者有人叫了3分
        if ($gameState['highest_bid'] === 3 || ($allPlayersBidOnce && $gameState['highest_bid'] > 0)) {
             // 地主产生
            if ($gameState['landlord_id']) {
                $gameState['status'] = 'playing';
                $gameState['players'][$gameState['landlord_id']]['is_landlord'] = true;
                $gameState['players'][$gameState['landlord_id']]['role'] = 'landlord';
                // 将底牌给地主
                $gameState['players'][$gameState['landlord_id']]['hand'] = array_merge(
                    $gameState['players'][$gameState['landlord_id']]['hand'],
                    $gameState['landlord_cards']
                );
                $gameState['players'][$gameState['landlord_id']]['hand'] = sortPlayerHand(
                    $gameState['players'][$gameState['landlord_id']]['hand']
                );
                // 其他玩家为农民
                foreach($gameState['turn_order'] as $pid) {
                    if ($pid !== $gameState['landlord_id']) {
                        $gameState['players'][$pid]['role'] = 'farmer';
                    }
                }
                // 地主先出牌
                $gameState['current_turn_player_id'] = $gameState['landlord_id'];
                $gameState['last_played_cards'] = []; // 清空出牌区
                $gameState['last_player_id'] = null;
            } else { // 所有人都没叫，流局
                $gameState['status'] = 'finished';
                $gameState['winner'] = 'draw_no_bid'; // 流局
            }
        }
    } else if ($allPlayersBidOnce && $gameState['highest_bid'] === 0) { // 所有人都没叫
        $gameState['status'] = 'finished';
        $gameState['winner'] = 'draw_no_bid'; // 流局
    }


    $gameState['updated_at'] = time();
    if (saveGameState($gameId, $gameState)) {
        return ['success' => true, 'message' => '叫分成功'];
    }
    return ['success' => false, 'message' => '保存状态失败'];
}


/**
 * 处理玩家出牌
 * @param string $gameId
 * @param string $playerId
 * @param array $cardsPlayed
 * @return array ['success' => bool, 'message' => string|null]
 */
function playCardsLogic($gameId, $playerId, $cardsPlayed) {
    $gameState = loadGameState($gameId);
    if (!$gameState) return ['success' => false, 'message' => '游戏不存在'];
    if ($gameState['status'] !== 'playing') return ['success' => false, 'message' => '当前不是出牌阶段'];
    if ($gameState['current_turn_player_id'] !== $playerId) return ['success' => false, 'message' => '未轮到你出牌'];
    if (!isset($gameState['players'][$playerId])) return ['success' => false, 'message' => '玩家不在游戏中'];

    // 1. 验证玩家手牌中是否有这些牌
    foreach ($cardsPlayed as $card) {
        if (!in_array($card, $gameState['players'][$playerId]['hand'])) {
            return ['success' => false, 'message' => '你没有这些牌: ' . $card];
        }
    }

    // 2. 验证牌型是否合法 (isHandValid)
    // $handType = getHandType($cardsPlayed);
    // if (!$handType) return ['success' => false, 'message' => '无效的牌型'];
    // 这是最复杂的部分，需要完整的斗地主规则实现

    // 3. 验证是否能大过上家出的牌 (canBeat)
    // if ($gameState['last_player_id'] !== null && $gameState['last_player_id'] !== $playerId) {
    //     if (!canBeat($cardsPlayed, $gameState['last_played_cards'])) {
    //          return ['success' => false, 'message' => '无法大过上家的牌'];
    //     }
    // }

    // 简化：假设出牌总是合法的 (你需要实现上面的验证)
    if (empty($cardsPlayed)) {
        return ['success' => false, 'message' => '不能出空牌，请选择“过”'];
    }

    // 更新玩家手牌
    $newHand = array_diff($gameState['players'][$playerId]['hand'], $cardsPlayed);
    $gameState['players'][$playerId]['hand'] = array_values($newHand); // Re-index array

    $gameState['last_played_cards'] = $cardsPlayed;
    $gameState['last_player_id'] = $playerId;

    // 检查是否获胜
    if (empty($gameState['players'][$playerId]['hand'])) {
        $gameState['status'] = 'finished';
        $gameState['winner'] = $gameState['players'][$playerId]['role']; // 'landlord' or 'farmer'
        // 农民获胜时，所有农民都赢
    } else {
        // 轮到下一个玩家
        $currentIndex = array_search($playerId, $gameState['turn_order']);
        $nextIndex = ($currentIndex + 1) % count($gameState['turn_order']);
        $gameState['current_turn_player_id'] = $gameState['turn_order'][$nextIndex];
    }

    $gameState['updated_at'] = time();
    if (saveGameState($gameId, $gameState)) {
        return ['success' => true, 'message' => '出牌成功'];
    }
    return ['success' => false, 'message' => '保存状态失败'];
}

/**
 * 处理玩家过牌
 */
function passTurnLogic($gameId, $playerId) {
    $gameState = loadGameState($gameId);
    if (!$gameState) return ['success' => false, 'message' => '游戏不存在'];
    if ($gameState['status'] !== 'playing') return ['success' => false, 'message' => '当前不是出牌阶段'];
    if ($gameState['current_turn_player_id'] !== $playerId) return ['success' => false, 'message' => '未轮到你操作'];

    // 过牌的条件：不是自己新起一手牌（即 last_player_id 不是自己，也不是null）
    if ($gameState['last_player_id'] === null || $gameState['last_player_id'] === $playerId) {
        return ['success' => false, 'message' => '你必须出牌，不能过'];
    }

    // 轮到下一个玩家
    $currentIndex = array_search($playerId, $gameState['turn_order']);
    $nextIndex = ($currentIndex + 1) % count($gameState['turn_order']);
    $gameState['current_turn_player_id'] = $gameState['turn_order'][$nextIndex];

    // 如果又轮到了上一个出牌的人，他可以出新的牌型
    if ($gameState['current_turn_player_id'] === $gameState['last_player_id']) {
        $gameState['last_played_cards'] = []; // 清空牌桌，新一轮开始
        // $gameState['last_player_id'] = null; // 可选，根据规则
    }


    $gameState['updated_at'] = time();
    if (saveGameState($gameId, $gameState)) {
        return ['success' => true, 'message' => '过牌成功'];
    }
    return ['success' => false, 'message' => '保存状态失败'];
}


/**
 * 获取游戏状态 (为特定玩家过滤信息)
 * @param string $gameId
 * @param string $playerId
 * @return array|null
 */
function getGameStateForPlayer($gameId, $playerId) {
    $fullState = loadGameState($gameId);
    if (!$fullState) return null;

    if (!isset($fullState['players'][$playerId])) { // Player not in this game, or game state corrupted
        return null;
    }

    // 为当前玩家构建视图，隐藏其他玩家手牌等敏感信息
    $playerView = [
        'game_id' => $fullState['game_id'],
        'status' => $fullState['status'],
        'my_id' => $playerId,
        'my_hand' => $fullState['players'][$playerId]['hand'],
        'my_role' => $fullState['players'][$playerId]['role'],
        'landlord_id' => $fullState['landlord_id'],
        'landlord_cards_count' => count($fullState['landlord_cards']), // 只显示数量，内容在确定地主后给地主
        'landlord_cards' => ($fullState['status'] !== 'bidding' && $fullState['landlord_id']) ? $fullState['landlord_cards'] : [], // 叫分结束后才显示
        'current_turn_player_id' => $fullState['current_turn_player_id'],
        'last_played_cards' => $fullState['last_played_cards'],
        'last_player_id' => $fullState['last_player_id'],
        'highest_bid' => $fullState['highest_bid'],
        'winner' => $fullState['winner'],
        'players_info' => [],
        'bid_history' => $fullState['bid_history'], // 让前端知道叫分过程
    ];

    // 添加其他玩家信息 (不含手牌)
    foreach ($fullState['players'] as $pid => $playerData) {
        $playerView['players_info'][$pid] = [
            'id' => $pid,
            'card_count' => count($playerData['hand']),
            'is_landlord' => $playerData['is_landlord'],
            'role' => $playerData['role'],
            'bid' => $playerData['bid']
        ];
    }
    // 如果是地主，并且是地主本人请求，可以看到地主牌
    if ($fullState['landlord_id'] === $playerId && $fullState['status'] !== 'bidding') {
        $playerView['landlord_cards'] = $fullState['landlord_cards'];
    }


    return $playerView;
}

?>
