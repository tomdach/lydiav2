<?php
require_once 'config.php';

// Vérification de la session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_section':
            $sectionName = $_POST['section'] ?? '';
            if ($sectionName) {
                $data = getSectionData($sectionName);
                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'Section non spécifiée']);
            }
            break;
            
        case 'save_section':
            $sectionName = $_POST['section_name'] ?? '';
            $sectionData = $_POST['section_data'] ?? [];
            
            if ($sectionName && $sectionData) {
                $success = saveSectionData($sectionName, $sectionData);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Données manquantes']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
} else {
    echo json_encode(['error' => 'Méthode non autorisée']);
}
?>
