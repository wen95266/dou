// frontend/js/game.js
const Game = {
    playerId: null, // Will be set by server or generated locally
    gameId: null,
    gameState: {}, // To store the full game state from backend

    init: function() {
        // Generate a simple player ID for this session
        this.playerId = 'player_' + Math.random().toString(36).substr(2, 9);
        UIManager.logMessage(`本地玩家ID: ${this.playerId}`);
    },

    setGameId: function(id) {
        this.gameId = id;
        UIManager.updateGameId(this.gameId);
    },

    updateGameState: function(newState) {
        this.gameState = newState;
        UIManager.logMessage("游戏状态已更新。");

        // Example: Update UI based on new state
        if (newState.players && newState.players[this.playerId]) {
            UIManager.renderPlayerHand(newState.players[this.playerId].hand);
        } else if (newState.hand) { // If backend directly sends current player's hand
             UIManager.renderPlayerHand(newState.hand);
        }

        if (newState.landlord_cards) {
            UIManager.renderLandlordCards(newState.landlord_cards);
        }
        if (newState.last_played_cards) {
            UIManager.renderDiscardPile(newState.last_played_cards);
        }
        // ... update other UI elements: current turn, scores, other players' card counts etc.
    },

    // Add more game-related client-side logic if needed
    // For example, basic validation of selected cards before sending to server
    // But remember, server MUST do the authoritative validation.
};

Game.init(); // Initialize the game object when script loads
