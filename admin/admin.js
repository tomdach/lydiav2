// Fonctions JavaScript pour l'administration
let targetCardCount = 0;

function addTargetCard() {
    const container = document.getElementById('targetCardsContainer');
    const cardIndex = targetCardCount++;
    
    const cardHtml = `
        <div class="border border-gray-200 rounded-lg p-4 mb-4" id="targetCard${cardIndex}">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-semibold">Carte ${cardIndex + 1}</h4>
                <button type="button" onclick="removeTargetCard(${cardIndex})" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Icône (classe FontAwesome)</label>
                    <input type="text" name="section_data[cards][${cardIndex}][icon]" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="fa-solid fa-compass">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titre</label>
                    <input type="text" name="section_data[cards][${cardIndex}][title]" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="section_data[cards][${cardIndex}][description]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', cardHtml);
    updatePreview();
}

function removeTargetCard(index) {
    const card = document.getElementById(`targetCard${index}`);
    if (card) {
        card.remove();
        updatePreview();
    }
}

// Fonction pour initialiser le compteur de cartes
function initTargetCardCount() {
    const cards = document.querySelectorAll('#targetCardsContainer > div');
    targetCardCount = cards.length;
}

// Initialiser quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser le compteur si on est sur la section target_audience
    if (document.getElementById('targetCardsContainer')) {
        initTargetCardCount();
    }
});
