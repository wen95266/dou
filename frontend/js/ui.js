// frontend/js/ui.js
const UIManager = {
    gameIdDisplay: document.getElementById('game-id-display'),
    player0Hand: document.getElementById('player-0-hand'),
    messages: document.getElementById('messages'),
    landlordCardsDisplay: document.getElementById('landlord-cards'),
    discardPile: document.getElementById('discard-pile'),

    selectedCards: [], // 存储被选中的牌

    logMessage: function(message) {
        const p = document.createElement('p');
        p.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        this.messages.insertBefore(p, this.messages.firstChild); // Add to top
        if (this.messages.children.length > 10) { // Keep max 10 messages
            this.messages.removeChild(this.messages.lastChild);
        }
        console.log(message);
    },

    updateGameId: function(gameId) {
        this.gameIdDisplay.textContent = gameId ? `游戏ID: ${gameId}` : "游戏ID: 未创建";
    },

    renderPlayerHand: function(cards) { // cards is an array of strings like ["H3", "D4", "SJOKER"]
        this.player0Hand.innerHTML = ''; // Clear current hand
        this.selectedCards = []; // Clear selection
        if (!cards || cards.length === 0) {
            this.player0Hand.textContent = "没有手牌";
            return;
        }
        cards.forEach(cardStr => {
            const cardDiv = document.createElement('div');
            cardDiv.classList.add('card');
            cardDiv.textContent = this.formatCard(cardStr); // Make cards look nicer
            cardDiv.dataset.card = cardStr; // Store original card value

            cardDiv.addEventListener('click', () => {
                cardDiv.classList.toggle('selected');
                if (cardDiv.classList.contains('selected')) {
                    this.selectedCards.push(cardStr);
                } else {
                    this.selectedCards = this.selectedCards.filter(c => c !== cardStr);
                }
                console.log("Selected cards:", this.selectedCards);
            });
            this.player0Hand.appendChild(cardDiv);
        });
    },

    renderLandlordCards: function(cards) {
        if (cards && cards.length > 0) {
            this.landlordCardsDisplay.textContent = "底牌: " + cards.map(this.formatCard).join(', ');
        } else {
            this.landlordCardsDisplay.textContent = "底牌: ";
        }
    },

    renderDiscardPile: function(cards) {
        if (cards && cards.length > 0) {
            this.discardPile.innerHTML = cards.map(cardStr => {
                const cardDiv = document.createElement('div');
                cardDiv.classList.add('card'); // You might want a different style for discarded cards
                cardDiv.textContent = this.formatCard(cardStr);
                return cardDiv.outerHTML;
            }).join(' ');
        } else {
            this.discardPile.innerHTML = "";
        }
    },

    formatCard: function(cardStr) {
        // Basic formatting, e.g., "H3" -> "♥3", "SJOKER" -> "S.Joker"
        // This is a very simple example, you'll want to improve it.
        if (!cardStr) return '';
        const suitMap = { 'H': '♥', 'D': '♦', 'C': '♣', 'S': '♠' };
        const valueMap = { 'JOKER': 'Joker', 'SJOKER': 'S.Joker', 'BJOKER': 'B.Joker', 'A': 'A', 'K': 'K', 'Q': 'Q', 'J': 'J', '10': '10', 'T': '10'}; // T for Ten

        if (cardStr.toUpperCase() === 'SJOKER' || cardStr.toUpperCase() === 'BJOKER') {
            return valueMap[cardStr.toUpperCase()];
        }

        let suit = cardStr.charAt(0).toUpperCase();
        let value = cardStr.substring(1);

        return (suitMap[suit] || '') + (valueMap[value] || value);
    },

    getSelectedCards: function() {
        return [...this.selectedCards]; // Return a copy
    },

    clearSelectedCardsDOM: function() {
        Array.from(this.player0Hand.querySelectorAll('.card.selected')).forEach(cardDiv => {
            cardDiv.classList.remove('selected');
        });
        this.selectedCards = [];
    },

    // You can add more UI update functions here for other players, game status, etc.
};
