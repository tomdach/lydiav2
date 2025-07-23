<?php
require_once 'config.php';
requireLogin();

// API pour les opérations AJAX spécifiques
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de sécurité invalide']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_faq_item':
        $sectionData = getSectionData('faq');
        $newItem = [
            'question' => 'Nouvelle question',
            'answer' => 'Nouvelle réponse'
        ];
        
        if (!isset($sectionData['items'])) {
            $sectionData['items'] = [];
        }
        
        $sectionData['items'][] = $newItem;
        
        if (saveSectionData('faq', $sectionData)) {
            echo json_encode(['success' => true, 'data' => $sectionData]);
        } else {
            echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
        }
        break;

    case 'remove_faq_item':
        $index = intval($_POST['index'] ?? -1);
        $sectionData = getSectionData('faq');
        
        if (isset($sectionData['items'][$index])) {
            array_splice($sectionData['items'], $index, 1);
            
            if (saveSectionData('faq', $sectionData)) {
                echo json_encode(['success' => true, 'data' => $sectionData]);
            } else {
                echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
            }
        } else {
            echo json_encode(['error' => 'Index invalide']);
        }
        break;

    case 'add_benefit_card':
        $sectionData = getSectionData('benefits');
        $newCard = [
            'icon' => 'fa-solid fa-star',
            'title' => 'Nouveau bénéfice',
            'description' => 'Description du bénéfice'
        ];
        
        if (!isset($sectionData['cards'])) {
            $sectionData['cards'] = [];
        }
        
        $sectionData['cards'][] = $newCard;
        
        if (saveSectionData('benefits', $sectionData)) {
            echo json_encode(['success' => true, 'data' => $sectionData]);
        } else {
            echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
        }
        break;

    case 'remove_benefit_card':
        $index = intval($_POST['index'] ?? -1);
        $sectionData = getSectionData('benefits');
        
        if (isset($sectionData['cards'][$index])) {
            array_splice($sectionData['cards'], $index, 1);
            
            if (saveSectionData('benefits', $sectionData)) {
                echo json_encode(['success' => true, 'data' => $sectionData]);
            } else {
                echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
            }
        } else {
            echo json_encode(['error' => 'Index invalide']);
        }
        break;

    case 'upload_image':
        // Gestion de l'upload d'images (simplifié)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/';
            
            // Création du dossier si nécessaire
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $uploadPath = $uploadDir . $fileName;
            
            // Vérification du type de fichier
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    $imageUrl = 'uploads/' . $fileName;
                    echo json_encode(['success' => true, 'url' => $imageUrl]);
                } else {
                    echo json_encode(['error' => 'Erreur lors de l\'upload']);
                }
            } else {
                echo json_encode(['error' => 'Type de fichier non autorisé']);
            }
        } else {
            echo json_encode(['error' => 'Aucun fichier reçu']);
        }
        break;

    case 'get_section_preview':
        $sectionName = $_POST['section_name'] ?? '';
        $sectionData = getSectionData($sectionName);
        
        // Génération du HTML de prévisualisation
        $html = generateSectionPreview($sectionName, $sectionData);
        
        echo json_encode(['success' => true, 'html' => $html]);
        break;

    default:
        echo json_encode(['error' => 'Action non reconnue']);
}

function generateSectionPreview($sectionName, $data) {
    switch ($sectionName) {
        case 'hero':
            return sprintf(
                '<div class="text-center p-8 bg-gray-100 rounded-lg" style="background-image: url(\'%s\'); background-size: cover; background-position: center;">
                    <div class="bg-white bg-opacity-90 p-6 rounded-lg">
                        <h1 class="text-3xl font-bold mb-4">%s</h1>
                        <p class="text-lg mb-6">%s</p>
                        <button class="bg-blue-600 text-white px-6 py-3 rounded-lg">%s</button>
                    </div>
                </div>',
                htmlspecialchars($data['background_image'] ?? ''),
                $data['title'] ?? 'Titre',
                htmlspecialchars($data['subtitle'] ?? 'Sous-titre'),
                htmlspecialchars($data['cta_text'] ?? 'Bouton')
            );

        case 'about':
            return sprintf(
                '<div class="p-6">
                    <h2 class="text-2xl font-bold mb-2">%s</h2>
                    <h3 class="text-lg font-semibold mb-4">%s</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="mb-4">%s</p>
                            <p>%s</p>
                        </div>
                        <div>
                            <img src="%s" alt="Image" class="w-full rounded-lg">
                        </div>
                    </div>
                </div>',
                htmlspecialchars($data['title'] ?? 'Titre'),
                htmlspecialchars($data['subtitle'] ?? 'Sous-titre'),
                htmlspecialchars($data['description1'] ?? 'Premier paragraphe'),
                htmlspecialchars($data['description2'] ?? 'Deuxième paragraphe'),
                htmlspecialchars($data['image'] ?? 'https://via.placeholder.com/300x200')
            );

        case 'faq':
            $html = '<div class="p-6"><h3 class="text-lg font-semibold mb-4">Questions fréquentes</h3>';
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $html .= sprintf(
                        '<div class="mb-3 p-3 bg-gray-50 rounded">
                            <div class="font-semibold">%s</div>
                            <div class="text-sm text-gray-600 mt-1">%s</div>
                        </div>',
                        htmlspecialchars($item['question'] ?? ''),
                        htmlspecialchars($item['answer'] ?? '')
                    );
                }
            }
            $html .= '</div>';
            return $html;

        default:
            return '<p class="text-gray-500 text-center py-8">Prévisualisation à venir pour cette section</p>';
    }
}
?>
