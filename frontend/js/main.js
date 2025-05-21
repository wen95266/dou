// frontend/js/main.js

// !!! IMPORTANT: Configure this to your backend API URL !!!
// Example for Serv00: "https://yourusername.serv00.net/doudizhu_backend/api/game.php"
// Example for local dev: "http://localhost/path/to/your/backend/api/game.php"
const BACKEND_API_URL = 'https://YOUR_SERV00_SUBDOMAIN.serv00.net/your_backend_path/api/game.php';
// For Cloudflare Pages, ensure backend allows CORS from your .pages.dev domain or custom domain.

let pollingInterval = null; // For game state polling

// --- Event Listeners ---
document.getElementById('btn-create-game').addEventListener('click', createGame);
document.getElementById('btn-join-game').addEventListener('click', joinGame);
document.getElementById('btn-get-state').addEventListener('click', () => {
    if (Game.gameId) getGameState(Game.gameId);
    else UIManager.logMessage("请先创建或加入游戏。");
});
document.getElementById('btn-play-cards').addEventListener('click', playSelectedCards);
document.getElementById('btn-pass').addEventListener('click', passTurn);

// Bidding buttons (example, you'll need more logic)
document.getElementById('btn-bid-1').addEventListener('click', () => bid(1));
document.getElementById('btn-bid-2').addEventListener('click', () => bid(2));
document.getElementById('btn-bid-3').addEventListener('click', () => bid(3));
document.getElementById('btn-no-bid').addEventListener('click', () => bid(0));


// --- API Call Functions ---
async function apiRequest(action, data = {}) {
    UIManager.logMessage(`请求: ${action} - 数据: ${JSON.stringify(data)}`);
    try {
        const params = new URLSearchParams();
        params.append('action', action);
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                // If data[key] is an array, append each element separately for PHP to receive as an array
                if (Array.isArray(data[key])) {
                    data[key].forEach(item => params.append(key + '[]', item));
                } else {
                    params.append(key, data[key]);
                }
            }
        }

        const response = await fetch(BACKEND_API_URL, {
            method: 'POST', // Using POST for all actions for simplicity here
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded', // PHP default for $_POST
            },
            body: params.toString()
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
        }
        const result = await response.json();
        UIManager.logMessage(`响应: ${action} - ${JSON.stringify(result)}`);
        return result;
    } catch (error) {
        UIManager.logMessage(`API请求错误 (${action}): ${error.message}`);
        console.error(`API请求错误 (${action}):`, error);
        return { success: false, message: error.message };
    }
}

async function createGame() {
    const result = await apiRequest('createGame', { playerId: Game.playerId });
    if (result.success && result.game_id) {
        Game.setGameId(result.game_id);
        UIManager.logMessage(`游戏已创建，ID: ${result.game_id}`);
        startPollingGameState();
        // Optionally, get initial state immediately
        getGameState(result.game_id);
    } else {
        UIManager.logMessage(`创建游戏失败: ${result.message || '未知错误'}`);
    }
}

async function joinGame() {
    const gameIdToJoin = document.getElementById('input-join-game-id').value.trim();
    if (!gameIdToJoin) {
        UIManager.logMessage("请输入要加入的游戏ID。");
        return;
    }
    const result = await apiRequest('joinGame', { game_id: gameIdToJoin, playerId: Game.playerId });
    if (result.success) {
        Game.setGameId(gameIdToJoin);
        UIManager.logMessage(`已加入游戏: ${gameIdToJoin}`);
        startPollingGameState();
        getGameState(gameIdToJoin); // Get state immediately after joining
    } else {
        UIManager.logMessage(`加入游戏失败: ${result.message || '未知错误'}`);
    }
}

async function getGameState(gameId) {
    if (!gameId) return;
    const result = await apiRequest('getGameState', { game_id: gameId, playerId: Game.playerId });
    if (result.success && result.state) {
        Game.updateGameState(result.state);
    } else if (result.message === "Game not found or player not in game.") {
        UIManager.logMessage(`无法获取游戏状态: ${result.message}. 可能游戏已结束或您未加入.`);
        stopPollingGameState(); // Stop polling if game is gone
    } else {
        UIManager.logMessage(`获取游戏状态失败: ${result.message || '未知错误'}`);
    }
}

async function playSelectedCards() {
    if (!Game.gameId) {
        UIManager.logMessage("请先创建或加入游戏。");
        return;
    }
    const selectedCards = UIManager.getSelectedCards();
    if (selectedCards.length === 0) {
        UIManager.logMessage("请选择要出的牌。");
        return;
    }

    UIManager.logMessage(`尝试出牌: ${selectedCards.join(', ')}`);
    const result = await apiRequest('playCards', {
        game_id: Game.gameId,
        playerId: Game.playerId,
        cards: selectedCards // Pass as array
    });

    if (result.success) {
        UIManager.logMessage("出牌成功!");
        // The game state update should ideally come from the regular polling or a specific push from server
        // For now, let's re-fetch state.
        UIManager.clearSelectedCardsDOM(); // Clear selection in UI
        getGameState(Game.gameId);
    } else {
        UIManager.logMessage(`出牌失败: ${result.message || '未知错误'}`);
    }
}

async function passTurn() {
    if (!Game.gameId) {
        UIManager.logMessage("请先创建或加入游戏。");
        return;
    }
    UIManager.logMessage("选择过牌。");
    const result = await apiRequest('passTurn', {
        game_id: Game.gameId,
        playerId: Game.playerId
    });
    if (result.success) {
        UIManager.logMessage("操作成功：过牌。");
        getGameState(Game.gameId); // Refresh state
    } else {
        UIManager.logMessage(`过牌失败: ${result.message || '未知错误'}`);
    }
}

async function bid(amount) {
    if (!Game.gameId) {
        UIManager.logMessage("请先创建或加入游戏。");
        return;
    }
    UIManager.logMessage(`叫分: ${amount}`);
    const result = await apiRequest('bid', {
        game_id: Game.gameId,
        playerId: Game.playerId,
        amount: amount
    });
    if (result.success) {
        UIManager.logMessage(`叫分成功: ${amount}分。`);
        getGameState(Game.gameId);
    } else {
        UIManager.logMessage(`叫分失败: ${result.message || '未知错误'}`);
    }
}


// --- Game State Polling ---
function startPollingGameState() {
    if (pollingInterval) clearInterval(pollingInterval); // Clear existing interval
    if (!Game.gameId) return;

    pollingInterval = setInterval(() => {
        getGameState(Game.gameId);
    }, 3000); // Poll every 3 seconds
    UIManager.logMessage("开始轮询游戏状态...");
}

function stopPollingGameState() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
        UIManager.logMessage("停止轮询游戏状态。");
    }
}

// Initialize
UIManager.logMessage("前端脚本已加载。请配置 BACKEND_API_URL。");
if (BACKEND_API_URL.includes('YOUR_SERV00_SUBDOMAIN') || BACKEND_API_URL.includes('localhost/path/to/your/backend')) {
    UIManager.logMessage("警告: 后端 API URL 似乎未正确配置。请编辑 frontend/js/main.js");
}
