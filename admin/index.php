<?php
require_once 'config.php';
requireLogin();

$currentSection = $_GET['section'] ?? 'dashboard';

// Fonctions pour gérer les messages de contact
function getUnreadMessagesCount() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0");
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function getContactMessages($limit = null, $unreadOnly = false) {
    global $pdo;
    try {
        $sql = "SELECT * FROM contact_messages";
        
        if ($unreadOnly) {
            $sql .= " WHERE is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function markMessageAsRead($messageId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1, read_at = NOW() WHERE id = ?");
        return $stmt->execute([$messageId]);
    } catch (Exception $e) {
        return false;
    }
}

function saveMessageReply($messageId, $subject, $replyMessage) {
    global $pdo;
    try {
        // Créer la table des réponses si elle n'existe pas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS message_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                subject VARCHAR(255) NOT NULL,
                reply_message TEXT NOT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES contact_messages(id)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO message_replies (message_id, subject, reply_message) 
            VALUES (?, ?, ?)
        ");
        
        return $stmt->execute([$messageId, $subject, $replyMessage]);
    } catch (Exception $e) {
        return false;
    }
}

function getMessageReplies($messageId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM message_replies 
            WHERE message_id = ? 
            ORDER BY sent_at DESC
        ");
        $stmt->execute([$messageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function deleteMessage($messageId) {
    global $pdo;
    try {
        // Supprimer d'abord les réponses liées au message
        $stmt = $pdo->prepare("DELETE FROM message_replies WHERE message_id = ?");
        $stmt->execute([$messageId]);
        
        // Puis supprimer le message
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        return $stmt->execute([$messageId]);
    } catch (Exception $e) {
        return false;
    }
}

$unreadCount = getUnreadMessagesCount();
$success = '';
$error = '';

// Traitement des formulaires AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Token de sécurité invalide']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'save_section':
            $sectionName = $_POST['section_name'] ?? '';
            $sectionData = $_POST['section_data'] ?? [];
            
            if (saveSectionData($sectionName, $sectionData)) {
                echo json_encode(['success' => true, 'message' => 'Section sauvegardée avec succès']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la sauvegarde']);
            }
            exit;
            
        case 'mark_message_read':
            $messageId = intval($_POST['message_id'] ?? 0);
            
            if ($messageId && markMessageAsRead($messageId)) {
                echo json_encode(['success' => true, 'message' => 'Message marqué comme lu']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
            }
            exit;
            
        case 'send_reply':
            $messageId = intval($_POST['message_id'] ?? 0);
            $subject = sanitize($_POST['subject'] ?? '');
            $replyMessage = sanitize($_POST['reply_message'] ?? '');
            
            if ($messageId && $subject && $replyMessage) {
                // En local, on simule l'envoi et on sauvegarde dans une table de réponses
                if (saveMessageReply($messageId, $subject, $replyMessage)) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Réponse envoyée avec succès (mode simulation)',
                        'timestamp' => date('d/m/Y H:i')
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Erreur lors de la sauvegarde']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Veuillez remplir tous les champs']);
            }
            exit;
            
        case 'get_reply_history':
            $messageId = intval($_POST['message_id'] ?? 0);
            
            if ($messageId) {
                $replies = getMessageReplies($messageId);
                echo json_encode(['success' => true, 'replies' => $replies]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID du message invalide']);
            }
            exit;
            
        case 'get_unread_count':
            $count = getUnreadMessagesCount();
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
            
        case 'get_recent_messages':
            $messages = getContactMessages(5); // Récupérer les 5 derniers messages
            echo json_encode(['success' => true, 'messages' => $messages]);
            exit;
            
        case 'delete_message':
            $messageId = intval($_POST['message_id'] ?? 0);
            
            if ($messageId && deleteMessage($messageId)) {
                echo json_encode(['success' => true, 'message' => 'Message supprimé avec succès']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
            }
            exit;
            
        case 'get_messages':
            $messages = getContactMessages();
            $html = '';
            
            if (empty($messages)) {
                $html = '
                    <div class="p-8 text-center">
                        <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-500 mb-2">Aucun message</h3>
                        <p class="text-gray-400">Les messages de contact apparaîtront ici.</p>
                    </div>
                ';
            } else {
                ob_start(); // Capture de la sortie HTML
                ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Réponses</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="messagesTableBody">
                            <?php foreach ($messages as $message): 
                                $replies = getMessageReplies($message['id']);
                                $replyCount = count($replies);
                            ?>
                                <tr class="<?= $message['is_read'] ? 'bg-white' : 'bg-blue-50' ?> hover:bg-gray-50 transition-colors" data-message-id="<?= $message['id'] ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($message['firstname'] . ' ' . $message['lastname']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($message['email']) ?></div>
                                                <?php if ($message['phone']): ?>
                                                    <div class="text-sm text-gray-500">
                                                        <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($message['phone']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-xs truncate" title="<?= htmlspecialchars($message['message']) ?>">
                                            <?= htmlspecialchars(substr($message['message'], 0, 100)) ?><?= strlen($message['message']) > 100 ? '...' : '' ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($message['is_read']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i>Lu
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-circle mr-1"></i>Non lu
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($replyCount > 0): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-reply mr-1"></i><?= $replyCount ?> réponse<?= $replyCount > 1 ? 's' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">
                                                <i class="fas fa-minus mr-1"></i>Aucune réponse
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewMessage(<?= $message['id'] ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i> Voir & Répondre
                                        </button>
                                        <?php if (!$message['is_read']): ?>
                                            <button onclick="markAsRead(<?= $message['id'] ?>)" class="text-green-600 hover:text-green-900 mr-3">
                                                <i class="fas fa-check"></i> Marquer lu
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteMessage(<?= $message['id'] ?>)" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $html = ob_get_clean();
            }
            
            echo json_encode(['success' => true, 'html' => $html, 'count' => count($messages)]);
            exit;
            
        case 'get_recent_messages':
            $recentMessages = getContactMessages(5);
            $html = '';
            
            if (!empty($recentMessages)) {
                ob_start();
                foreach ($recentMessages as $message): ?>
                    <div class="p-4 bg-<?= $message['is_read'] ? 'gray' : 'blue' ?>-50 border border-<?= $message['is_read'] ? 'gray' : 'blue' ?>-200 rounded-lg hover:shadow-md transition-all duration-200">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <h4 class="font-medium text-gray-900">
                                        <?= htmlspecialchars($message['firstname'] . ' ' . $message['lastname']) ?>
                                    </h4>
                                    <?php if (!$message['is_read']): ?>
                                        <span class="ml-2 w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                    <?php endif; ?>
                                    <span class="ml-auto text-xs text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 mb-1"><?= htmlspecialchars($message['email']) ?></p>
                                <p class="text-sm text-gray-700 line-clamp-2">
                                    <?= htmlspecialchars(substr($message['message'], 0, 120)) ?><?= strlen($message['message']) > 120 ? '...' : '' ?>
                                </p>
                            </div>
                            <div class="ml-4 flex space-x-2">
                                <button onclick="viewMessage(<?= $message['id'] ?>)" 
                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if (!$message['is_read']): ?>
                                    <button onclick="markAsRead(<?= $message['id'] ?>, false)" 
                                        class="text-green-600 hover:text-green-800 text-sm">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach;
                $html = ob_get_clean();
            }
            
            echo json_encode(['success' => true, 'html' => $html]);
            exit;
            
        case 'change_password':
            $newPassword = $_POST['new_password'] ?? '';
            
            if (strlen($newPassword) < 6) {
                echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 6 caractères']);
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                if (saveSetting('admin_password', $hashedPassword)) {
                    echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Erreur lors de la modification']);
                }
            }
            exit;
    }
}

// Récupération des données pour l'affichage
$sectionData = [];
if ($currentSection !== 'dashboard' && $currentSection !== 'settings' && $currentSection !== 'messages') {
    $sectionData = getSectionData($currentSection);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        .sidebar-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .preview-frame {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .editor-panel {
            max-height: 35vh;
            overflow-y: auto;
        }
        .editor-section {
            background: white;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .form-group {
            margin-bottom: 8px;
        }
        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
        }
        .form-control {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
            background-color: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        .card-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 8px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h4 {
            margin: 0;
            color: #374151;
            font-size: 13px;
            font-weight: 600;
        }
        .card-number {
            background-color: #3b82f6;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 12px;
        }
        .icon-selector-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .icon-preview {
            width: 40px;
            height: 40px;
            background-color: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        .icon-selector {
            flex: 1;
        }
        .preview-section {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 24px;
            position: sticky;
            top: 20px;
        }
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-top: 8px;
        }
        .icon-option {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid transparent;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            font-size: 18px;
            color: #374151;
        }
        .icon-option:hover {
            border-color: #3b82f6;
            background-color: #eff6ff;
            color: #3b82f6;
        }
        .icon-option.selected {
            border-color: #3b82f6;
            background-color: #3b82f6;
            color: white;
        }
        .icon-category {
            margin-bottom: 20px;
        }
        .icon-category-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            padding: 8px 12px;
            background: #e5e7eb;
            border-radius: 4px;
            font-size: 14px;
        }
        .icon-search-wrapper {
            position: relative;
            margin-bottom: 12px;
        }
        
        /* Animation pour la mise à jour des lignes */
        @keyframes rowUpdate {
            0% {
                background-color: #dbeafe;
                transform: scale(1);
            }
            50% {
                background-color: #bfdbfe;
                transform: scale(1.02);
            }
            100% {
                background-color: transparent;
                transform: scale(1);
            }
        }
        
        /* Animation pour les notifications */
        @keyframes notificationSlide {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        #notification {
            animation: notificationSlide 0.3s ease-out;
        }
        .icon-search {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .icon-search:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        .close-modal:hover {
            color: #374151;
        }
        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding: 16px;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .section-title h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .stats-number {
            font-size: 32px;
            font-weight: 700;
            color: #3b82f6;
            display: block;
        }
        .stats-label {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Styles pour les messages du dashboard */
        .message-preview {
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }

        .message-preview:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .message-preview.unread {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-left: 4px solid #3b82f6;
        }

        .message-indicator {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 1100;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        .notification.show {
            transform: translateX(0);
        }
        .notification.success {
            background-color: #10b981;
        }
        .notification.error {
            background-color: #ef4444;
        }
        .floating-save {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
        }
        .btn-floating {
            padding: 16px 24px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }
        .btn-floating:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        .preview-container {
            background: #FEFBF6;
            min-height: 45vh;
            max-height: 45vh;
            overflow-y: auto;
        }
        .preview-content {
            padding: 30px 15px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .preview-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #343A40;
            text-align: center;
            margin-bottom: 0.8rem;
            line-height: 1.2;
        }
        .preview-subtitle {
            font-size: 1rem;
            color: #666;
            text-align: center;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .preview-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-top: 1.2rem;
        }
        .preview-card {
            background: white;
            padding: 1.2rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .preview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        .preview-card-icon {
            width: 2.5rem;
            height: 2.5rem;
            background-color: #A3B18A;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.8rem;
            color: white;
            font-size: 1rem;
        }
        .preview-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #343A40;
            margin-bottom: 0.6rem;
        }
        .preview-card-description {
            color: #666;
            line-height: 1.4;
            font-size: 0.85rem;
        }
        
        /* Styles pour la FAQ de prévisualisation */
        .preview-faq-accordion {
            max-width: 700px;
            margin: 0 auto;
        }
        .preview-faq-item {
            background: white;
            margin-bottom: 8px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        .preview-faq-question {
            padding: 12px 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            color: #343a40;
            font-size: 0.9rem;
            transition: background-color 0.2s ease;
        }
        .preview-faq-question:hover {
            background: #e9ecef;
        }
        .preview-faq-question::after {
            content: '▼';
            color: #A3B18A;
            transition: transform 0.3s ease;
            font-size: 0.8rem;
        }
        .preview-faq-item.active .preview-faq-question::after {
            transform: rotate(180deg);
        }
        .preview-faq-answer {
            padding: 0 16px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            background: white;
        }
        .preview-faq-item.active .preview-faq-answer {
            max-height: 150px;
            padding: 12px 16px;
        }
        .preview-faq-answer p {
            margin: 0;
            color: #666;
            line-height: 1.4;
            font-size: 0.85rem;
        }
        
        /* Styles pour la timeline de prévisualisation */
        .preview-timeline {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-top: 1.2rem;
        }
        .preview-timeline-step {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .preview-timeline-step:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        .preview-step-icon {
            width: 3rem;
            height: 3rem;
            background-color: #A3B18A;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .preview-step-content {
            flex: 1;
        }
        .preview-step-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #343A40;
            margin-bottom: 0.5rem;
        }
        .preview-step-description {
            color: #666;
            line-height: 1.5;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation top -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-tachometer-alt mr-2 text-blue-600"></i>
                            Administration
                        </h1>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="../index.php" target="_blank" class="text-gray-600 hover:text-blue-600 transition duration-200">
                        <i class="fas fa-external-link-alt mr-1"></i>Voir le site
                    </a>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                        <i class="fas fa-sign-out-alt mr-1"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg flex-shrink-0 overflow-y-auto">
            <div class="p-4">
                <nav class="space-y-1">
                    <a href="?section=dashboard" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'dashboard' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-chart-pie mr-3"></i>Dashboard
                    </a>
                    
                    <div class="border-t border-gray-200 my-4"></div>
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide px-4 mb-2">SECTIONS DU SITE</div>
                    
                    <a href="?section=hero" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'hero' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-home mr-3"></i>Accueil / Hero
                    </a>
                    
                    <a href="?section=about" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'about' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-user mr-3"></i>À propos
                    </a>
                    
                    <a href="?section=target_audience" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'target_audience' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-users mr-3"></i>Pour qui ?
                    </a>
                    
                    <a href="?section=process" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'process' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-tasks mr-3"></i>Le Bilan
                    </a>
                    
                    <a href="?section=benefits" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'benefits' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-star mr-3"></i>Bénéfices
                    </a>
                    
                    <a href="?section=faq" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'faq' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-question-circle mr-3"></i>FAQ
                    </a>
                    
                    <a href="?section=contact" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'contact' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-envelope mr-3"></i>Contact
                    </a>
                    
                    <a href="?section=messages" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'messages' ? 'sidebar-active' : '' ?> relative">
                        <i class="fas fa-inbox mr-3"></i>Messages
                        <?php if ($unreadCount > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-1 min-w-[20px] text-center"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="?section=footer" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'footer' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-info mr-3"></i>Footer
                    </a>
                    
                    <a href="?section=design" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'design' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-palette mr-3"></i>Couleurs
                    </a>
                    
                    <div class="border-t border-gray-200 my-4"></div>
                    
                    <a href="?section=settings" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?= $currentSection === 'settings' ? 'sidebar-active' : '' ?>">
                        <i class="fas fa-cog mr-3"></i>Paramètres
                    </a>
                </nav>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Messages de notification -->
            <div id="notification" class="hidden m-4 p-4 rounded-lg"></div>
            
            <!-- Notification de nouveaux messages -->
            <div id="newMessageNotification" class="hidden m-4 p-4 bg-blue-50 border border-blue-200 rounded-lg animate-pulse">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-bell text-blue-600"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-blue-800">
                            <span id="newMessageText">Nouveau message reçu !</span>
                        </p>
                        <p class="text-xs text-blue-600 mt-1">
                            Cliquez pour voir les messages
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <button onclick="hideNewMessageNotification(); window.location.href='?section=messages';" 
                            class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                            Voir
                        </button>
                        <button onclick="hideNewMessageNotification()" 
                            class="ml-2 text-blue-400 hover:text-blue-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4">

            <?php if ($currentSection === 'dashboard'): ?>
                <!-- Dashboard Amélioré -->
                <div class="section-title">
                    <i class="fas fa-chart-pie text-2xl text-blue-600"></i>
                    <h2>Tableau de bord</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="stats-card">
                        <span class="stats-number">7</span>
                        <span class="stats-label">Sections disponibles</span>
                        <div class="mt-2">
                            <i class="fas fa-layer-group text-blue-500"></i>
                        </div>
                    </div>

                    <div class="stats-card cursor-pointer" onclick="window.location.href='?section=messages'">
                        <span class="stats-number" id="dashboardUnreadCount"><?= $unreadCount ?></span>
                        <span class="stats-label">Messages non lus</span>
                        <div class="mt-2">
                            <i class="fas fa-envelope text-red-500"></i>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                        <div class="absolute top-2 right-2">
                            <span class="animate-pulse w-3 h-3 bg-red-500 rounded-full"></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="stats-card">
                        <span class="stats-number">120+</span>
                        <span class="stats-label">Icônes disponibles</span>
                        <div class="mt-2">
                            <i class="fas fa-palette text-purple-500"></i>
                        </div>
                    </div>

                    <div class="stats-card">
                        <span class="stats-number">100%</span>
                        <span class="stats-label">Responsive design</span>
                        <div class="mt-2">
                            <i class="fas fa-mobile-alt text-orange-500"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Messages récents -->
                    <div class="editor-section">
                        <h3 class="text-xl font-semibold mb-4 flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-envelope text-blue-500 mr-2"></i>
                                Messages récents
                                <?php if ($unreadCount > 0): ?>
                                <span id="dashboardUnreadBadge" class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-1 animate-pulse"><?= $unreadCount ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="?section=messages" class="text-sm text-blue-600 hover:text-blue-800 transition-colors duration-200">
                                Voir tout →
                            </a>
                        </h3>
                        <div id="dashboardMessages" class="space-y-3">
                            <?php
                            $recentMessages = getContactMessages(5); // Récupérer les 5 derniers messages
                            if (empty($recentMessages)): ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p>Aucun message pour le moment</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentMessages as $message): ?>
                                <div class="message-preview <?= $message['is_read'] ? 'opacity-60' : 'bg-blue-50 border-l-4 border-blue-500' ?> p-3 rounded-lg cursor-pointer hover:bg-gray-50 transition-all duration-200" 
                                     onclick="viewMessage(<?= $message['id'] ?>)">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <?php if (!$message['is_read']): ?>
                                                <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                                <?php endif; ?>
                                                <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($message['firstname'] . ' ' . $message['lastname']) ?></h4>
                                                <span class="text-xs text-gray-500"><?= date('d/m H:i', strtotime($message['created_at'])) ?></span>
                                            </div>
                                            <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars(substr($message['message'], 0, 80)) ?>...</p>
                                        </div>
                                        <div class="ml-2 flex flex-col gap-1">
                                            <?php if (!$message['is_read']): ?>
                                            <button onclick="event.stopPropagation(); markAsRead(<?= $message['id'] ?>, false)" 
                                                    class="text-green-600 hover:text-green-800 text-xs" title="Marquer comme lu">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button onclick="event.stopPropagation(); viewMessage(<?= $message['id'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 text-xs" title="Voir le message">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <div class="text-center pt-3">
                                    <a href="?section=messages" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Voir tous les messages →
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="editor-section">
                        <h3 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                            Actions rapides
                        </h3>
                        <div class="space-y-3">
                            <a href="?section=target_audience" class="flex items-center p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg hover:from-blue-100 hover:to-blue-200 transition-all duration-300">
                                <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-users text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Gérer les cartes "Pour qui"</h4>
                                    <p class="text-sm text-gray-600">Section dynamique avec sélecteur d'icônes avancé</p>
                                </div>
                            </a>
                            
                            <a href="?section=benefits" class="flex items-center p-4 bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg hover:from-orange-100 hover:to-orange-200 transition-all duration-300">
                                <div class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-star text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Gérer les bénéfices</h4>
                                    <p class="text-sm text-gray-600">Section dynamique avec sélecteur d'icônes avancé</p>
                                </div>
                            </a>
                            
                            <a href="?section=faq" class="flex items-center p-4 bg-gradient-to-r from-teal-50 to-teal-100 rounded-lg hover:from-teal-100 hover:to-teal-200 transition-all duration-300">
                                <div class="w-12 h-12 bg-teal-500 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-question-circle text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Gérer la FAQ</h4>
                                    <p class="text-sm text-gray-600">Questions/réponses dynamiques</p>
                                </div>
                            </a>
                            
                            <a href="?section=hero" class="flex items-center p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg hover:from-green-100 hover:to-green-200 transition-all duration-300">
                                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-home text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Modifier l'accueil</h4>
                                    <p class="text-sm text-gray-600">Titre principal et appel à l'action</p>
                                </div>
                            </a>
                            
                            <a href="../index.php" target="_blank" class="flex items-center p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg hover:from-purple-100 hover:to-purple-200 transition-all duration-300">
                                <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-external-link-alt text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Voir le site public</h4>
                                    <p class="text-sm text-gray-600">Prévisualiser vos modifications</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Nouvelles fonctionnalités -->
                    <div class="editor-section">
                        <h3 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-star text-yellow-500 mr-2"></i>
                            Fonctionnalités avancées
                        </h3>
                        <div class="space-y-4">
                            <div class="p-4 bg-gradient-to-r from-indigo-50 to-indigo-100 rounded-lg">
                                <h4 class="font-semibold text-gray-800 mb-2">
                                    <i class="fas fa-palette mr-2 text-indigo-500"></i>
                                    Sélecteur d'icônes avancé
                                </h4>
                                <p class="text-sm text-gray-600 mb-2">Plus de 120 icônes organisées par catégories :</p>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span class="px-2 py-1 bg-white rounded">Business & Travail</span>
                                    <span class="px-2 py-1 bg-white rounded">Croissance & Évolution</span>
                                    <span class="px-2 py-1 bg-white rounded">Navigation & Direction</span>
                                    <span class="px-2 py-1 bg-white rounded">Créativité & Innovation</span>
                                </div>
                            </div>
                            
                            <div class="p-4 bg-gradient-to-r from-pink-50 to-pink-100 rounded-lg">
                                <h4 class="font-semibold text-gray-800 mb-2">
                                    <i class="fas fa-magic mr-2 text-pink-500"></i>
                                    Interface intuitive
                                </h4>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li>• Prévisualisation en temps réel</li>
                                    <li>• Glisser-déposer pour organiser</li>
                                    <li>• Sauvegarde automatique</li>
                                    <li>• Design responsive</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guide d'utilisation modernisé -->
                <div class="editor-section">
                    <h3 class="text-xl font-semibold mb-6 flex items-center">
                        <i class="fas fa-graduation-cap text-green-500 mr-2"></i>
                        Guide d'utilisation rapide
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="text-white font-bold text-xl">1</span>
                            </div>
                            <h4 class="font-semibold text-gray-800 mb-2">Sélectionnez une section</h4>
                            <p class="text-sm text-gray-600">Choisissez la section à modifier dans le menu de gauche</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="text-white font-bold text-xl">2</span>
                            </div>
                            <h4 class="font-semibold text-gray-800 mb-2">Modifiez le contenu</h4>
                            <p class="text-sm text-gray-600">Utilisez les formulaires intuitifs et voyez le résultat en temps réel</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-400 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="text-white font-bold text-xl">3</span>
                            </div>
                            <h4 class="font-semibold text-gray-800 mb-2">Sauvegardez</h4>
                            <p class="text-sm text-gray-600">Cliquez sur "Sauvegarder" pour publier vos modifications</p>
                        </div>
                    </div>
                </div>

                <!-- Section Messages récents dans le dashboard -->
                <?php 
                $recentMessages = getContactMessages(5); // Récupérer les 5 derniers messages
                if (!empty($recentMessages)): 
                ?>
                <div class="mt-8">
                    <div class="editor-section">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-semibold flex items-center">
                                <i class="fas fa-inbox text-blue-500 mr-2"></i>
                                Messages récents
                            </h3>
                            <a href="?section=messages" class="text-blue-600 hover:text-blue-800 text-sm">
                                Voir tous les messages <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        
                        <div class="space-y-3" id="dashboardRecentMessages">
                            <?php foreach ($recentMessages as $message): ?>
                                <div class="p-4 bg-<?= $message['is_read'] ? 'gray' : 'blue' ?>-50 border border-<?= $message['is_read'] ? 'gray' : 'blue' ?>-200 rounded-lg hover:shadow-md transition-all duration-200">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-2">
                                                <h4 class="font-medium text-gray-900">
                                                    <?= htmlspecialchars($message['firstname'] . ' ' . $message['lastname']) ?>
                                                </h4>
                                                <?php if (!$message['is_read']): ?>
                                                    <span class="ml-2 w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                                <?php endif; ?>
                                                <span class="ml-auto text-xs text-gray-500">
                                                    <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-1"><?= htmlspecialchars($message['email']) ?></p>
                                            <p class="text-sm text-gray-700 line-clamp-2">
                                                <?= htmlspecialchars(substr($message['message'], 0, 120)) ?><?= strlen($message['message']) > 120 ? '...' : '' ?>
                                            </p>
                                        </div>
                                        <div class="ml-4 flex space-x-2">
                                            <button onclick="viewMessage(<?= $message['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (!$message['is_read']): ?>
                                                <button onclick="markAsRead(<?= $message['id'] ?>, false)" 
                                                    class="text-green-600 hover:text-green-800 text-sm">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($currentSection === 'messages'): ?>
                <!-- Section Messages -->
                <div class="mb-6">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-inbox mr-3 text-blue-600"></i>Messages de contact
                    </h2>
                    <p class="text-gray-600">Gérez les messages reçus via le formulaire de contact</p>
                </div>

                <?php
                $messages = getContactMessages();
                ?>

                <div class="bg-white rounded-lg shadow-md overflow-hidden" id="messagesContainer">
                    <?php if (empty($messages)): ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-medium text-gray-500 mb-2">Aucun message</h3>
                            <p class="text-gray-400">Les messages de contact apparaîtront ici.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Réponses</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="messagesTableBody">
                                    <?php foreach ($messages as $message): 
                                        $replies = getMessageReplies($message['id']);
                                        $replyCount = count($replies);
                                    ?>
                                        <tr class="<?= $message['is_read'] ? 'bg-white' : 'bg-blue-50' ?> hover:bg-gray-50 transition-colors" data-message-id="<?= $message['id'] ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($message['firstname'] . ' ' . $message['lastname']) ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($message['email']) ?></div>
                                                        <?php if ($message['phone']): ?>
                                                            <div class="text-sm text-gray-500">
                                                                <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($message['phone']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900 max-w-xs truncate" title="<?= htmlspecialchars($message['message']) ?>">
                                                    <?= htmlspecialchars(substr($message['message'], 0, 100)) ?><?= strlen($message['message']) > 100 ? '...' : '' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($message['is_read']): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check mr-1"></i>Lu
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        <i class="fas fa-circle mr-1"></i>Non lu
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($replyCount > 0): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <i class="fas fa-reply mr-1"></i><?= $replyCount ?> réponse<?= $replyCount > 1 ? 's' : '' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">
                                                        <i class="fas fa-minus mr-1"></i>Aucune réponse
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="viewMessage(<?= $message['id'] ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-eye"></i> Voir & Répondre
                                                </button>
                                                <?php if (!$message['is_read']): ?>
                                                    <button onclick="markAsRead(<?= $message['id'] ?>)" class="text-green-600 hover:text-green-900 mr-3">
                                                        <i class="fas fa-check"></i> Marquer lu
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="deleteMessage(<?= $message['id'] ?>)" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i> Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Modal pour voir le message -->
                <div id="messageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
                    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
                        <div class="flex items-center justify-between p-6 border-b bg-gray-50">
                            <h3 class="text-xl font-semibold text-gray-900">
                                <i class="fas fa-envelope-open mr-2 text-blue-600"></i>
                                Détails du message
                            </h3>
                            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="flex h-[calc(90vh-80px)]">
                            <!-- Partie gauche - Détails du message -->
                            <div class="w-1/2 p-6 border-r bg-gray-50 overflow-y-auto">
                                <div id="messageContent">
                                    <!-- Contenu du message sera chargé ici -->
                                </div>
                            </div>
                            
                            <!-- Partie droite - Réponse -->
                            <div class="w-1/2 p-6 flex flex-col">
                                <div class="mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-2">
                                        <i class="fas fa-reply mr-2 text-green-600"></i>
                                        Répondre au message
                                    </h4>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        En mode local, la réponse sera simulée et affichée dans l'historique.
                                    </div>
                                </div>
                                
                                <form id="replyForm" class="flex-1 flex flex-col">
                                    <input type="hidden" id="replyMessageId" name="message_id">
                                    <input type="hidden" name="action" value="send_reply">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    
                                    <div class="mb-4">
                                        <label for="replySubject" class="block text-sm font-medium text-gray-700 mb-2">
                                            Sujet de la réponse
                                        </label>
                                        <input type="text" id="replySubject" name="subject" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="Re: Votre message de contact">
                                    </div>
                                    
                                    <div class="flex-1 mb-4">
                                        <label for="replyMessage" class="block text-sm font-medium text-gray-700 mb-2">
                                            Votre réponse
                                        </label>
                                        <textarea id="replyMessage" name="reply_message" 
                                                  class="w-full h-full min-h-[200px] px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 resize-none"
                                                  placeholder="Bonjour,

Merci pour votre message. 

Cordialement,"></textarea>
                                    </div>
                                    
                                    <div class="flex gap-3">
                                        <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                                            <i class="fas fa-paper-plane mr-2"></i>Envoyer la réponse
                                        </button>
                                        <button type="button" onclick="saveReplyDraft()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition duration-200">
                                            <i class="fas fa-save mr-2"></i>Brouillon
                                        </button>
                                    </div>
                                </form>
                                
                                <!-- Historique des réponses -->
                                <div id="replyHistory" class="mt-6 pt-4 border-t hidden">
                                    <h5 class="font-medium text-gray-700 mb-3">
                                        <i class="fas fa-history mr-2"></i>Historique des réponses
                                    </h5>
                                    <div id="replyHistoryContent" class="space-y-3 max-h-32 overflow-y-auto">
                                        <!-- Historique sera chargé ici -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($currentSection === 'settings'): ?>
                <!-- Paramètres -->
                <div class="mb-6">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Paramètres</h2>
                    <p class="text-gray-600">Configuration de l'administration</p>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Changer le mot de passe</h3>
                    <form id="passwordForm" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-sm text-gray-500 mt-1">Minimum 6 caractères</p>
                        </div>
                        
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-save mr-2"></i>Modifier le mot de passe
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- Interface d'édition ultra-compacte -->
                <div class="space-y-3">
                    <!-- En-tête de section très compact -->
                    <div class="bg-white p-3 rounded-lg shadow-sm">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <i class="fas fa-edit mr-2 text-blue-600"></i>
                                    <?php
                                    $titles = [
                                        'hero' => 'Section Accueil',
                                        'about' => 'Section À propos',
                                        'target_audience' => 'Section Pour qui ?',
                                        'process' => 'Section Le Bilan',
                                        'benefits' => 'Section Bénéfices',
                                        'faq' => 'Section FAQ',
                                        'contact' => 'Section Contact',
                                        'footer' => 'Section Footer',
                                        'design' => 'Couleurs et Design'
                                    ];
                                    echo $titles[$currentSection] ?? 'Section';
                                    ?>
                                </h3>
                            </div>
                            <button id="saveBtn" class="btn btn-primary btn-sm">
                                <i class="fas fa-save mr-1"></i>Sauvegarder
                            </button>
                        </div>
                        
                        <div class="editor-panel mt-2">
                            <form id="sectionForm">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="save_section">
                                <input type="hidden" name="section_name" value="<?= $currentSection ?>">
                                
                                <div id="formFields">
                                    <!-- Les champs seront générés par JavaScript -->
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Prévisualisation ultra-compacte -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-eye mr-2 text-green-600"></i>
                                Prévisualisation
                            </h3>
                        </div>
                        <div class="preview-container" id="previewPanel">
                            <!-- Le contenu de prévisualisation sera généré par JavaScript -->
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </div>

            <!-- Bouton de sauvegarde flottant (visible seulement dans les sections d'édition) -->
            <?php if ($currentSection !== 'dashboard' && $currentSection !== 'settings' && $currentSection !== 'messages'): ?>
                <div class="floating-save">
                    <button id="floatingSaveBtn" class="btn btn-primary btn-floating">
                        <i class="fas fa-save mr-2"></i>Sauvegarder les modifications
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Données de la section courante
        const currentSection = '<?= $currentSection ?>';
        const sectionData = <?= json_encode($sectionData) ?>;
        
        // Fonction pour afficher les notifications
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.className = `mb-6 p-4 rounded-lg ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            notification.classList.remove('hidden');

            setTimeout(() => {
                notification.classList.add('hidden');
            }, 5000);
        }

        // Fonctions pour la gestion des étapes (section process)
        function updateProcessData() {
            const steps = [];
            const stepElements = document.querySelectorAll('#steps-container .card-item');
            
            stepElements.forEach((stepEl, index) => {
                const titleInput = stepEl.querySelector('.step-title');
                const descInput = stepEl.querySelector('.step-description');
                
                if (titleInput && descInput) {
                    steps.push({
                        title: titleInput.value,
                        description: descInput.value
                    });
                }
            });
            
            const hiddenInput = document.getElementById('process-data');
            if (hiddenInput) {
                hiddenInput.value = JSON.stringify(steps);
            }
        }

        function addNewStep() {
            const container = document.getElementById('steps-container');
            if (!container) {
                console.error('Container steps-container not found');
                return;
            }
            
            const existingSteps = container.querySelectorAll('.card-item');
            const nextIndex = existingSteps.length;
            
            if (nextIndex >= 10) {
                alert('Vous ne pouvez pas avoir plus de 10 étapes.');
                return;
            }
            
            const stepHtml = `
                <div class="card-item" id="step-${nextIndex + 1}">
                    <div class="card-header">
                        <div style="display: flex; align-items: center;">
                            <span class="card-number" style="width: 18px; height: 18px; font-size: 10px;">${nextIndex + 1}</span>
                            <h4>Phase ${nextIndex + 1}</h4>
                        </div>
                        <button type="button" onclick="removeStep(${nextIndex})" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;">
                            <i class="fas fa-trash" style="font-size: 10px;"></i>
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>Titre de la phase</label>
                        <input type="text" value="Nouvelle phase" class="form-control step-title" data-index="${nextIndex}">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea rows="3" class="form-control step-description" data-index="${nextIndex}">Description de cette nouvelle phase...</textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', stepHtml);
            
            // Ajouter les event listeners aux nouveaux éléments
            const newStep = container.lastElementChild;
            newStep.querySelectorAll('input, textarea').forEach(field => {
                field.addEventListener('input', function() {
                    updateProcessData();
                    updatePreview();
                });
            });
            
            updateStepCount();
            updateProcessData();
            updatePreview();
        }

        function removeStep(index) {
            const stepToRemove = document.querySelector(`#steps-container .card-item:nth-child(${index + 1})`);
            if (stepToRemove) {
                stepToRemove.remove();
                updateStepNumbers();
                updateStepCount();
                updateProcessData();
                updatePreview();
            }
        }

        function updateStepNumbers() {
            const steps = document.querySelectorAll('#steps-container .card-item');
            steps.forEach((step, index) => {
                const numberSpan = step.querySelector('.card-number');
                const titleH4 = step.querySelector('.card-header h4');
                if (numberSpan) numberSpan.textContent = index + 1;
                if (titleH4) titleH4.textContent = `Phase ${index + 1}`;
                
                // Mettre à jour l'id
                step.id = `step-${index + 1}`;
                
                // Mettre à jour les data-index
                const titleInput = step.querySelector('.step-title');
                const descInput = step.querySelector('.step-description');
                if (titleInput) titleInput.setAttribute('data-index', index);
                if (descInput) descInput.setAttribute('data-index', index);
                
                // Mettre à jour le bouton de suppression
                const removeBtn = step.querySelector('button[onclick*="removeStep"]');
                if (removeBtn) {
                    removeBtn.setAttribute('onclick', `removeStep(${index})`);
                }
            });
        }

        function updateStepCount() {
            const countElement = document.getElementById('step-count');
            const steps = document.querySelectorAll('#steps-container .card-item');
            if (countElement) {
                countElement.textContent = steps.length;
            }
        }        // Fonction pour afficher les notifications        // Génération des champs de formulaire
        function generateFormFields(data) {
            const container = document.getElementById('formFields');
            if (!container) return;
            
            container.innerHTML = '';
            
            // Gestion spécifique par section
            switch (currentSection) {
                case 'hero':
                    generateHeroFields(data, container);
                    break;
                case 'about':
                    generateAboutFields(data, container);
                    break;
                case 'target_audience':
                    generateTargetAudienceFields(data, container);
                    break;
                case 'process':
                    generateProcessFields(data, container);
                    break;
                case 'benefits':
                    generateBenefitsFields(data, container);
                    break;
                case 'faq':
                    generateFaqFields(data, container);
                    break;
                case 'contact':
                    generateContactFields(data, container);
                    break;
                case 'footer':
                    generateFooterFields(data, container);
                    break;
                case 'design':
                    generateDesignFields(data, container);
                    break;
            }
            
            // Ajout des event listeners pour la mise à jour en temps réel
            container.querySelectorAll('input, textarea').forEach(field => {
                field.addEventListener('input', updatePreview);
            });
        }

        // Générateurs de champs spécifiques
        function generateHeroFields(data, container) {
            container.innerHTML = `
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Titre principal</label>
                        <textarea name="section_data[title]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">${data.title || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sous-titre</label>
                        <textarea name="section_data[subtitle]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">${data.subtitle || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Texte du bouton</label>
                        <input type="text" name="section_data[cta_text]" value="${data.cta_text || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Image de fond (URL)</label>
                        <input type="url" name="section_data[background_image]" value="${data.background_image || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            `;
        }

        function generateAboutFields(data, container) {
            container.innerHTML = `
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Titre</label>
                        <input type="text" name="section_data[title]" value="${data.title || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sous-titre</label>
                        <input type="text" name="section_data[subtitle]" value="${data.subtitle || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Premier paragraphe</label>
                        <textarea name="section_data[description1]" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">${data.description1 || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Deuxième paragraphe</label>
                        <textarea name="section_data[description2]" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">${data.description2 || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Image (URL)</label>
                        <input type="url" name="section_data[image]" value="${data.image || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            `;
        }

        function generateDesignFields(data, container) {
            container.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Vert Sauge</label>
                            <input type="color" name="section_data[vert_sauge]" value="${data.vert_sauge || '#A3B18A'}" class="w-full h-10 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Beige Rosé</label>
                            <input type="color" name="section_data[beige_rose]" value="${data.beige_rose || '#F2E8DF'}" class="w-full h-10 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Crème</label>
                            <input type="color" name="section_data[creme]" value="${data.creme || '#FEFBF6'}" class="w-full h-10 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Gris Anthracite</label>
                            <input type="color" name="section_data[gris_anthracite]" value="${data.gris_anthracite || '#343A40'}" class="w-full h-10 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Doré</label>
                            <input type="color" name="section_data[dore]" value="${data.dore || '#B99470'}" class="w-full h-10 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Doré Clair</label>
                            <input type="color" name="section_data[dore_clair]" value="${data.dore_clair || '#d1b59a'}" class="w-full h-10 border border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>
            `;
        }

        // Générateur pour la section target_audience
        function generateTargetAudienceFields(data, container) {
            // Récupérer le nombre de cartes existantes ou commencer avec 3
            const existingCards = [];
            for (let i = 1; i <= 10; i++) {
                if (data[`card${i}_title`] || i <= 3) {
                    existingCards.push({
                        index: i,
                        icon: data[`card${i}_icon`] || (i === 1 ? 'fa-solid fa-compass' : i === 2 ? 'fa-solid fa-arrow-trend-up' : 'fa-solid fa-seedling'),
                        title: data[`card${i}_title`] || (i === 1 ? 'En reconversion' : i === 2 ? 'En quête d\'évolution' : 'En manque de sens'),
                        description: data[`card${i}_description`] || (i === 1 ? 'Vous avez une idée mais n\'osez pas sauter le pas, ou au contraire, vous êtes dans le flou total et cherchez une nouvelle voie.' : i === 2 ? 'Vous vous sentez à l\'étroit dans votre poste actuel et souhaitez évoluer, mais ne savez pas comment valoriser vos compétences.' : 'Votre travail ne vous passionne plus. Vous cherchez à aligner votre vie professionnelle avec vos valeurs personnelles.')
                    });
                }
            }

            let cardsHtml = '';
            existingCards.forEach((card, index) => {
                cardsHtml += `
                    <div class="card-item" id="card-${card.index}">
                        <div class="card-header">
                            <div style="display: flex; align-items: center;">
                                <span class="card-number" style="width: 18px; height: 18px; font-size: 10px;">${card.index}</span>
                                <h4>Carte ${card.index}</h4>
                            </div>
                            ${existingCards.length > 3 ? `
                                <button type="button" onclick="removeCard(${card.index})" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;">
                                    <i class="fas fa-trash" style="font-size: 10px;"></i>
                                </button>
                            ` : ''}
                        </div>
                        
                        <div class="form-group">
                            <label>Icône</label>
                            <div class="icon-selector-wrapper">
                                <div class="icon-preview" style="width: 30px; height: 30px; font-size: 14px;">
                                    <i class="${card.icon}"></i>
                                </div>
                                <button type="button" onclick="openIconSelector(${card.index})" class="btn btn-secondary" style="flex: 1; padding: 4px 8px; font-size: 11px;">
                                    <i class="fas fa-palette"></i> Icône
                                </button>
                                <input type="hidden" name="section_data[card${card.index}_icon]" value="${card.icon}" id="icon-input-${card.index}">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Titre</label>
                            <input type="text" name="section_data[card${card.index}_title]" value="${card.title}" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="section_data[card${card.index}_description]" rows="2" class="form-control">${card.description}</textarea>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = `
                <div class="editor-section">
                    <h3 style="font-size: 14px; margin-bottom: 8px;"><i class="fa-solid fa-sliders" style="font-size: 12px;"></i> Configuration</h3>
                    
                    <div class="form-group">
                        <label>Titre principal</label>
                        <input type="text" name="section_data[title]" value="${data.title || ''}" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Sous-titre</label>
                        <textarea name="section_data[subtitle]" rows="2" class="form-control">${data.subtitle || ''}</textarea>
                    </div>
                </div>
                
                <div class="editor-section">
                    <div class="section-header">
                        <h3 style="font-size: 14px;"><i class="fa-solid fa-layer-group" style="font-size: 12px;"></i> Cartes (<span id="card-count">${existingCards.length}</span>)</h3>
                        <button type="button" onclick="addNewCard()" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px;">
                            <i class="fa-solid fa-plus" style="font-size: 10px;"></i> Ajouter
                        </button>
                    </div>
                    
                    <div id="cards-container">
                        ${cardsHtml}
                    </div>
                </div>
            `;
            
            // Ajouter les event listeners pour la mise à jour en temps réel
            container.querySelectorAll('input, textarea, select').forEach(field => {
                field.addEventListener('input', updatePreview);
            });
        }

        // Fonction pour ajouter une nouvelle carte
        function addNewCard() {
            const container = document.getElementById('cards-container');
            if (!container) return;
            
            // Trouver le prochain index disponible
            const existingCards = container.querySelectorAll('.card-item');
            let nextIndex = existingCards.length + 1;
            
            if (nextIndex > 10) {
                alert('Vous ne pouvez pas avoir plus de 10 cartes.');
                return;
            }
            
            const cardHtml = `
                <div class="card-item" id="card-${nextIndex}">
                    <div class="card-header">
                        <div style="display: flex; align-items: center;">
                            <span class="card-number" style="width: 18px; height: 18px; font-size: 10px;">${nextIndex}</span>
                            <h4>Carte ${nextIndex}</h4>
                        </div>
                        <button type="button" onclick="removeCard(${nextIndex})" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;">
                            <i class="fas fa-trash" style="font-size: 10px;"></i>
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>Icône</label>
                        <div class="icon-selector-wrapper">
                            <div class="icon-preview" style="width: 30px; height: 30px; font-size: 14px;">
                                <i class="fa-solid fa-star"></i>
                            </div>
                            <button type="button" onclick="openIconSelector(${nextIndex})" class="btn btn-secondary" style="flex: 1; padding: 4px 8px; font-size: 11px;">
                                <i class="fas fa-palette"></i> Icône
                            </button>
                            <input type="hidden" name="section_data[card${nextIndex}_icon]" value="fa-solid fa-star" id="icon-input-${nextIndex}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Titre</label>
                        <input type="text" name="section_data[card${nextIndex}_title]" value="Nouvelle carte" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="section_data[card${nextIndex}_description]" rows="2" class="form-control">Description de cette nouvelle carte...</textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', cardHtml);
            
            // Ajouter les event listeners aux nouveaux champs
            const newCard = container.lastElementChild;
            newCard.querySelectorAll('input, textarea, select').forEach(field => {
                field.addEventListener('input', updatePreview);
            });
            
            updateCardCount();
            updatePreview();
        }

        // Fonction pour supprimer une carte
        function removeCard(index) {
            const card = document.getElementById(`card-${index}`);
            if (card) {
                card.remove();
                updateCardCount();
                updatePreview();
            }
        }

        // Fonction pour mettre à jour le compteur de cartes
        function updateCardCount() {
            const container = document.getElementById('cards-container');
            if (container) {
                const count = container.querySelectorAll('.card-item').length;
                const countElement = document.getElementById('card-count');
                if (countElement) {
                    countElement.textContent = count;
                }
            }
        }

        // Fonctions pour gérer les cartes de bénéfices
        function addNewBenefitCard() {
            const container = document.getElementById('benefits-container');
            if (!container) return;
            
            // Trouver le prochain index disponible
            const existingCards = container.querySelectorAll('.card-item');
            let nextIndex = existingCards.length + 1;
            
            if (nextIndex > 10) {
                alert('Vous ne pouvez pas avoir plus de 10 bénéfices.');
                return;
            }
            
            const cardHtml = `
                <div class="card-item" id="benefit-card-${nextIndex}">
                    <div class="card-header">
                        <div style="display: flex; align-items: center;">
                            <span class="card-number" style="width: 18px; height: 18px; font-size: 10px;">${nextIndex}</span>
                            <h4>Bénéfice ${nextIndex}</h4>
                        </div>
                        <button type="button" onclick="removeBenefitCard(${nextIndex})" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;">
                            <i class="fas fa-trash" style="font-size: 10px;"></i>
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>Icône</label>
                        <div class="icon-selector-wrapper">
                            <div class="icon-preview" style="width: 30px; height: 30px; font-size: 14px;">
                                <i class="fa-solid fa-star"></i>
                            </div>
                            <button type="button" onclick="openIconSelectorBenefit(${nextIndex})" class="btn btn-secondary" style="flex: 1; padding: 4px 8px; font-size: 11px;">
                                <i class="fas fa-palette"></i> Icône
                            </button>
                            <input type="hidden" name="section_data[card${nextIndex}_icon]" value="fa-solid fa-star" id="benefit-icon-input-${nextIndex}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Titre</label>
                        <input type="text" name="section_data[card${nextIndex}_title]" value="Nouveau bénéfice" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="section_data[card${nextIndex}_description]" rows="2" class="form-control">Description de ce nouveau bénéfice...</textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', cardHtml);
            
            // Ajouter les event listeners aux nouveaux champs
            const newCard = container.lastElementChild;
            newCard.querySelectorAll('input, textarea').forEach(field => {
                field.addEventListener('input', updatePreview);
            });
            
            updateBenefitCardCount();
            updatePreview();
        }

        function removeBenefitCard(index) {
            const card = document.getElementById(`benefit-card-${index}`);
            if (card) {
                card.remove();
                updateBenefitCardCount();
                updatePreview();
            }
        }

        function updateBenefitCardCount() {
            const container = document.getElementById('benefits-container');
            if (container) {
                const count = container.querySelectorAll('.card-item').length;
                const countElement = document.getElementById('benefit-card-count');
                if (countElement) {
                    countElement.textContent = count;
                }
            }
        }

        function openIconSelectorBenefit(cardIndex) {
            currentCardIndex = cardIndex;
            currentMode = 'benefit'; // Nouveau mode pour les bénéfices
            const modal = document.getElementById('iconModal');
            const modalContent = document.getElementById('iconModalContent');
            
            // Générer le contenu de la modale (même code que pour les cartes)
            let modalHtml = `
                <div class="modal-header">
                    <h3>Choisir une icône</h3>
                    <button type="button" class="close-modal" onclick="closeIconSelector()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="icon-search-wrapper">
                    <input type="text" class="icon-search" placeholder="Rechercher une icône..." onkeyup="filterIcons(this.value)">
                </div>
                <div id="iconCategoriesContainer">
            `;
            
            // Ajouter chaque catégorie
            Object.entries(iconCategories).forEach(([categoryName, icons]) => {
                modalHtml += `
                    <div class="icon-category" data-category="${categoryName.toLowerCase()}">
                        <div class="icon-category-title">${categoryName}</div>
                        <div class="icon-grid">
                `;
                
                icons.forEach(iconClass => {
                    modalHtml += `
                        <div class="icon-option" data-icon="${iconClass}" onclick="selectIconBenefit('${iconClass}')">
                            <i class="${iconClass}"></i>
                        </div>
                    `;
                });
                
                modalHtml += `
                        </div>
                    </div>
                `;
            });
            
            modalHtml += `
                </div>
            `;
            
            modalContent.innerHTML = modalHtml;
            modal.style.display = 'flex';
            
            // Marquer l'icône actuellement sélectionnée
            const currentIcon = document.getElementById(`benefit-icon-input-${cardIndex}`).value;
            const currentOption = modal.querySelector(`[data-icon="${currentIcon}"]`);
            if (currentOption) {
                currentOption.classList.add('selected');
            }
        }

        function selectIconBenefit(iconClass) {
            if (currentCardIndex && currentMode === 'benefit') {
                // Mettre à jour l'input caché
                const iconInput = document.getElementById(`benefit-icon-input-${currentCardIndex}`);
                iconInput.value = iconClass;
                
                // Mettre à jour l'aperçu
                const card = document.getElementById(`benefit-card-${currentCardIndex}`);
                const previewIcon = card.querySelector('.icon-preview i');
                previewIcon.className = iconClass;
                
                // Fermer la modale
                closeIconSelector();
                
                // Mettre à jour la prévisualisation générale
                updatePreview();
            }
        }

        // Fonctions pour gérer les FAQ
        function addNewFaqItem() {
            const container = document.getElementById('faq-container');
            if (!container) return;
            
            // Trouver le prochain index disponible
            const existingFaqs = container.querySelectorAll('.card-item');
            let nextIndex = existingFaqs.length + 1;
            
            if (nextIndex > 15) {
                alert('Vous ne pouvez pas avoir plus de 15 questions.');
                return;
            }
            
            const faqHtml = `
                <div class="card-item" id="faq-item-${nextIndex}">
                    <div class="card-header">
                        <div style="display: flex; align-items: center;">
                            <span class="card-number" style="width: 18px; height: 18px; font-size: 10px;">${nextIndex}</span>
                            <h4>Question ${nextIndex}</h4>
                        </div>
                        <button type="button" onclick="removeFaqItem(${nextIndex})" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;">
                            <i class="fas fa-trash" style="font-size: 10px;"></i>
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>Question</label>
                        <input type="text" name="section_data[faq${nextIndex}_question]" value="Nouvelle question ?" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Réponse</label>
                        <textarea name="section_data[faq${nextIndex}_answer]" rows="3" class="form-control">Réponse à cette nouvelle question...</textarea>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', faqHtml);
            
            // Ajouter les event listeners aux nouveaux champs
            const newFaq = container.lastElementChild;
            newFaq.querySelectorAll('input, textarea').forEach(field => {
                field.addEventListener('input', updatePreview);
            });
            
            updateFaqCount();
            updatePreview();
        }

        function removeFaqItem(index) {
            const faq = document.getElementById(`faq-item-${index}`);
            if (faq) {
                faq.remove();
                updateFaqCount();
                updatePreview();
            }
        }

        function updateFaqCount() {
            const container = document.getElementById('faq-container');
            if (container) {
                const count = container.querySelectorAll('.card-item').length;
                const countElement = document.getElementById('faq-count');
                if (countElement) {
                    countElement.textContent = count;
                }
            }
        }

        // Fonction pour l'accordéon interactif de la prévisualisation FAQ
        function toggleFaqPreview(faqItem) {
            // Fermer tous les autres items
            const allItems = faqItem.parentNode.querySelectorAll('.preview-faq-item');
            allItems.forEach(item => {
                if (item !== faqItem) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle l'item cliqué
            faqItem.classList.toggle('active');
        }

        function generateProcessFields(data, container) {
            // Decode JSON data if it exists
            let processSteps = [];
            if (data.data) {
                try {
                    processSteps = JSON.parse(data.data);
                } catch (e) {
                    processSteps = [];
                }
            }
            
            // Si aucune donnée, commencer avec 3 étapes par défaut
            if (processSteps.length === 0) {
                processSteps = [
                    {
                        title: 'L\'Investigation',
                        description: 'Nous analysons votre parcours, vos expériences, mais surtout vos envies profondes pour comprendre qui vous êtes et ce qui vous motive.'
                    },
                    {
                        title: 'L\'Exploration', 
                        description: 'Nous explorons les pistes professionnelles possibles, nous enquêtons sur les métiers et les formations pour construire un projet réaliste.'
                    },
                    {
                        title: 'La Construction',
                        description: 'Vous repartez avec un plan d\'action clair, des étapes définies et une synthèse écrite pour mettre en œuvre votre projet en toute confiance.'
                    }
                ];
            }

            let stepsHtml = '';
            processSteps.forEach((step, index) => {
                stepsHtml += `
                    <div class="card-item" id="step-${index + 1}">
                        <div class="card-header">
                            <div style="display: flex; align-items: center;">
                                <span class="card-number" style="width: 18px; height: 18px; font-size: 10px;">${index + 1}</span>
                                <h4>Phase ${index + 1}</h4>
                            </div>
                            ${processSteps.length > 1 ? `
                                <button type="button" onclick="removeStep(${index})" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;">
                                    <i class="fas fa-trash" style="font-size: 10px;"></i>
                                </button>
                            ` : ''}
                        </div>
                        
                        <div class="form-group">
                            <label>Titre de la phase</label>
                            <input type="text" value="${step.title || ''}" class="form-control step-title" data-index="${index}">
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea rows="3" class="form-control step-description" data-index="${index}">${step.description || ''}</textarea>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = `
                <div class="editor-section">
                    <h3 style="font-size: 14px; margin-bottom: 8px;"><i class="fa-solid fa-sliders" style="font-size: 12px;"></i> Configuration</h3>
                    
                    <div class="form-group">
                        <label>Titre principal</label>
                        <input type="text" name="section_data[title]" value="${data.title || 'Un cheminement en 3 phases clés'}" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Sous-titre</label>
                        <textarea name="section_data[subtitle]" rows="2" class="form-control">${data.subtitle || 'Le bilan est un voyage structuré que nous faisons ensemble. Loin d\'être un simple test, c\'est un dialogue constructif pour co-créer votre avenir.'}</textarea>
                    </div>
                </div>
                
                <div class="editor-section">
                    <div class="section-header">
                        <h3 style="font-size: 14px;"><i class="fa-solid fa-layer-group" style="font-size: 12px;"></i> Étapes (<span id="step-count">${processSteps.length}</span>)</h3>
                        <button type="button" onclick="addNewStep()" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px;">
                            <i class="fa-solid fa-plus" style="font-size: 10px;"></i> Ajouter
                        </button>
                    </div>
                    
                    <div id="steps-container">
                        ${stepsHtml}
                    </div>
                    
                    <input type="hidden" name="section_data[data]" id="process-data" value='${JSON.stringify(processSteps)}'>
                </div>
            `;
            
            // Ajouter les event listeners pour la mise à jour en temps réel
            container.querySelectorAll('input, textarea, select').forEach(field => {
                field.addEventListener('input', function() {
                    updateProcessData();
                    updatePreview();
                });
            });
            
            // Initialiser les données
            updateProcessData();
        }

        function generateBenefitsFields(data, container) {
            // Récupérer le nombre de cartes existantes ou commencer avec 6
            const existingCards = [];
            for (let i = 1; i <= 10; i++) {
                if (data[`card${i}_title`] || i <= 6) {
                    existingCards.push({
                        index: i,
                        icon: data[`card${i}_icon`] || (i === 1 ? 'fa-solid fa-lightbulb' : i === 2 ? 'fa-solid fa-mountain-sun' : i === 3 ? 'fa-solid fa-map-signs' : i === 4 ? 'fa-solid fa-heart' : i === 5 ? 'fa-solid fa-toolbox' : 'fa-solid fa-network-wired'),
                        title: data[`card${i}_title`] || (i === 1 ? 'Clarté & Vision' : i === 2 ? 'Confiance Retrouvée' : i === 3 ? 'Plan d\'Action' : i === 4 ? 'Sens & Épanouissement' : i === 5 ? 'Outils Personnalisés' : 'Réseau & Opportunités'),
                        description: data[`card${i}_description`] || (i === 1 ? 'Repartez avec un projet professionnel clair, défini et qui vous ressemble.' : i === 2 ? 'Prenez conscience de vos forces, de vos talents et de votre valeur unique.' : i === 3 ? 'Obtenez une feuille de route précise et des étapes concrètes pour vos objectifs.' : i === 4 ? 'Alignez enfin votre carrière avec ce qui compte vraiment pour vous.' : i === 5 ? 'Disposez d\'outils sur-mesure pour continuer à piloter votre carrière en autonomie.' : 'Apprenez à développer votre réseau et à identifier les bonnes opportunités.')
                    });
                }
            }

            let cardsHtml = '';
            existingCards.forEach((card, index) => {
                cardsHtml += `
                    <div class="card-item" id="benefit-card-${card.index}">
                        <div class="card-header">
                            <div style="display: flex; align-items: center;">
                                <span class="card-number" style="width: 18px; height: 18px; font-size: 10px;">${card.index}</span>
                                <h4>Bénéfice ${card.index}</h4>
                            </div>
                            ${existingCards.length > 3 ? `
                                <button type="button" onclick="removeBenefitCard(${card.index})" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;">
                                    <i class="fas fa-trash" style="font-size: 10px;"></i>
                                </button>
                            ` : ''}
                        </div>
                        
                        <div class="form-group">
                            <label>Icône</label>
                            <div class="icon-selector-wrapper">
                                <div class="icon-preview" style="width: 30px; height: 30px; font-size: 14px;">
                                    <i class="${card.icon}"></i>
                                </div>
                                <button type="button" onclick="openIconSelectorBenefit(${card.index})" class="btn btn-secondary" style="flex: 1; padding: 4px 8px; font-size: 11px;">
                                    <i class="fas fa-palette"></i> Icône
                                </button>
                                <input type="hidden" name="section_data[card${card.index}_icon]" value="${card.icon}" id="benefit-icon-input-${card.index}">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Titre</label>
                            <input type="text" name="section_data[card${card.index}_title]" value="${card.title}" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="section_data[card${card.index}_description]" rows="2" class="form-control">${card.description}</textarea>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = `
                <div class="editor-section">
                    <h3 style="font-size: 14px; margin-bottom: 8px;"><i class="fa-solid fa-sliders" style="font-size: 12px;"></i> Configuration</h3>
                    
                    <div class="form-group">
                        <label>Titre principal</label>
                        <input type="text" name="section_data[title]" value="${data.title || 'Les bénéfices concrets de notre collaboration'}" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Sous-titre (optionnel)</label>
                        <textarea name="section_data[subtitle]" rows="2" class="form-control">${data.subtitle || ''}</textarea>
                    </div>
                </div>
                
                <div class="editor-section">
                    <div class="section-header">
                        <h3 style="font-size: 14px;"><i class="fa-solid fa-layer-group" style="font-size: 12px;"></i> Bénéfices (<span id="benefit-card-count">${existingCards.length}</span>)</h3>
                        <button type="button" onclick="addNewBenefitCard()" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px;">
                            <i class="fa-solid fa-plus" style="font-size: 10px;"></i> Ajouter
                        </button>
                    </div>
                    
                    <div id="benefits-container">
                        ${cardsHtml}
                    </div>
                </div>
            `;

            // Ajouter les event listeners
            container.querySelectorAll('input, textarea').forEach(field => {
                field.addEventListener('input', updatePreview);
            });
        }

        function generateFaqFields(data, container) {
            // Récupérer le nombre de questions existantes ou commencer avec 3
            const existingFaqs = [];
            for (let i = 1; i <= 15; i++) {
                if (data[`faq${i}_question`] || i <= 3) {
                    existingFaqs.push({
                        index: i,
                        question: data[`faq${i}_question`] || (i === 1 ? 'Combien de temps dure un bilan de compétences ?' : i === 2 ? 'Mon bilan est-il finançable par le CPF ?' : 'Les séances peuvent-elles se faire à distance ?'),
                        answer: data[`faq${i}_answer`] || (i === 1 ? 'Un bilan de compétences dure généralement jusqu\'à 24 heures, réparties sur plusieurs semaines. Cela nous laisse le temps d\'approfondir chaque étape sans se presser, avec des séances de 2 à 3 heures.' : i === 2 ? 'Oui, absolument. Le bilan de compétences est une formation éligible au Compte Personnel de Formation (CPF). Je vous accompagnerai dans les démarches pour utiliser vos droits et financer votre accompagnement.' : 'Oui, je propose des accompagnements en présentiel dans mon cabinet à Paris, mais également 100% à distance par visioconférence. Nous choisissons ensemble la formule qui vous convient le mieux.')
                    });
                }
            }

            let faqsHtml = '';
            existingFaqs.forEach((faq, index) => {
                faqsHtml += `
                    <div class="card-item" id="faq-item-${faq.index}">
                        <div class="card-header">
                            <div style="display: flex; align-items: center;">
                                <span class="card-number" style="width: 18px; height: 18px; font-size: 10px;">${faq.index}</span>
                                <h4>Question ${faq.index}</h4>
                            </div>
                            ${existingFaqs.length > 1 ? `
                                <button type="button" onclick="removeFaqItem(${faq.index})" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;">
                                    <i class="fas fa-trash" style="font-size: 10px;"></i>
                                </button>
                            ` : ''}
                        </div>
                        
                        <div class="form-group">
                            <label>Question</label>
                            <input type="text" name="section_data[faq${faq.index}_question]" value="${faq.question}" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Réponse</label>
                            <textarea name="section_data[faq${faq.index}_answer]" rows="3" class="form-control">${faq.answer}</textarea>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = `
                <div class="editor-section">
                    <h3 style="font-size: 14px; margin-bottom: 8px;"><i class="fa-solid fa-sliders" style="font-size: 12px;"></i> Configuration</h3>
                    
                    <div class="form-group">
                        <label>Titre principal</label>
                        <input type="text" name="section_data[title]" value="${data.title || 'Vos questions, nos réponses'}" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Sous-titre</label>
                        <textarea name="section_data[subtitle]" rows="2" class="form-control">${data.subtitle || 'Voici les réponses aux questions les plus fréquentes pour vous aider à y voir plus clair.'}</textarea>
                    </div>
                </div>
                
                <div class="editor-section">
                    <div class="section-header">
                        <h3 style="font-size: 14px;"><i class="fa-solid fa-question-circle" style="font-size: 12px;"></i> Questions/Réponses (<span id="faq-count">${existingFaqs.length}</span>)</h3>
                        <button type="button" onclick="addNewFaqItem()" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px;">
                            <i class="fa-solid fa-plus" style="font-size: 10px;"></i> Ajouter
                        </button>
                    </div>
                    
                    <div id="faq-container">
                        ${faqsHtml}
                    </div>
                </div>
            `;

            // Ajouter les event listeners
            container.querySelectorAll('input, textarea').forEach(field => {
                field.addEventListener('input', updatePreview);
            });
        }

        function generateContactFields(data, container) {
            container.innerHTML = '<p class="text-gray-500">Éditeur pour la section "Contact" - En développement</p>';
        }

        function generateFooterFields(data, container) {
            container.innerHTML = '<p class="text-gray-500">Éditeur pour la section "Footer" - En développement</p>';
        }

        // Mise à jour de la prévisualisation
        function updatePreview() {
            const previewPanel = document.getElementById('previewPanel');
            if (!previewPanel) return;
            
            // Récupération des données du formulaire
            const formData = new FormData(document.getElementById('sectionForm'));
            const data = {};
            
            // Parse des données du formulaire pour gérer les tableaux
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('section_data[')) {
                    const keyPath = key.replace('section_data[', '').replace(']', '');
                    data[keyPath] = value;
                }
            }
            
            // Génération de la prévisualisation selon la section
            switch (currentSection) {
                case 'hero':
                    previewPanel.innerHTML = `
                        <div class="preview-content text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 80px 20px;">
                            <h1 class="preview-title" style="color: white; font-size: 3rem; margin-bottom: 1.5rem;">${data.title || 'Votre Nouveau Départ Professionnel Commence Ici'}</h1>
                            <p class="preview-subtitle" style="color: rgba(255,255,255,0.9); font-size: 1.5rem; margin-bottom: 2.5rem;">${data.subtitle || 'Découvrez votre voie grâce à un accompagnement personnalisé'}</p>
                            <button class="btn btn-primary" style="background: white; color: #667eea; padding: 16px 32px; font-size: 1.2rem; border-radius: 50px; border: none; font-weight: 600;">
                                ${data.cta_text || 'Commencer mon bilan'}
                            </button>
                        </div>
                    `;
                    break;
                case 'about':
                    previewPanel.innerHTML = `
                        <div class="preview-content">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center;">
                                <div>
                                    <h2 class="preview-title" style="text-align: left; font-size: 2.5rem; margin-bottom: 1rem;">${data.title || 'À Propos'}</h2>
                                    <h3 style="color: #A3B18A; font-size: 1.5rem; font-weight: 600; margin-bottom: 2rem;">${data.subtitle || 'Mon approche'}</h3>
                                    <div style="color: #666; line-height: 1.8; font-size: 1.1rem;">
                                        <p style="margin-bottom: 1.5rem;">${data.description1 || 'Premier paragraphe de description...'}</p>
                                        <p>${data.description2 || 'Deuxième paragraphe de description...'}</p>
                                    </div>
                                </div>
                                <div>
                                    <img src="${data.image || 'https://via.placeholder.com/400x300'}" alt="Image" style="width: 100%; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.1);">
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'target_audience':
                    let targetHtml = `
                        <div class="preview-content">
                            <h2 class="preview-title">${data.title || 'Est-ce que cet accompagnement est fait pour vous ?'}</h2>
                            <p class="preview-subtitle">${data.subtitle || 'Si vous vous reconnaissez dans l\'une de ces situations, alors la réponse est probablement oui.'}</p>
                            <div class="preview-cards-grid">
                    `;
                    
                    // Afficher toutes les cartes créées dynamiquement
                    let cardCount = 0;
                    for (let i = 1; i <= 10; i++) {
                        if (data[`card${i}_title`]) {
                            const icon = data[`card${i}_icon`] || 'fa-solid fa-star';
                            const title = data[`card${i}_title`] || `Carte ${i}`;
                            const description = data[`card${i}_description`] || 'Description...';
                            
                            targetHtml += `
                                <div class="preview-card">
                                    <div class="preview-card-icon">
                                        <i class="${icon}"></i>
                                    </div>
                                    <h3 class="preview-card-title">${title}</h3>
                                    <p class="preview-card-description">${description}</p>
                                </div>
                            `;
                            cardCount++;
                        }
                    }
                    
                    // Si aucune carte personnalisée n'existe, afficher les cartes par défaut
                    if (cardCount === 0) {
                        const defaultCards = [
                            {
                                icon: 'fa-solid fa-compass',
                                title: 'En reconversion',
                                description: 'Vous avez une idée mais n\'osez pas sauter le pas, ou au contraire, vous êtes dans le flou total et cherchez une nouvelle voie.'
                            },
                            {
                                icon: 'fa-solid fa-arrow-trend-up',
                                title: 'En quête d\'évolution',
                                description: 'Vous vous sentez à l\'étroit dans votre poste actuel et souhaitez évoluer, mais ne savez pas comment valoriser vos compétences.'
                            },
                            {
                                icon: 'fa-solid fa-seedling',
                                title: 'En manque de sens',
                                description: 'Votre travail ne vous passionne plus. Vous cherchez à aligner votre vie professionnelle avec vos valeurs personnelles.'
                            }
                        ];
                        
                        defaultCards.forEach(card => {
                            targetHtml += `
                                <div class="preview-card">
                                    <div class="preview-card-icon">
                                        <i class="${card.icon}"></i>
                                    </div>
                                    <h3 class="preview-card-title">${card.title}</h3>
                                    <p class="preview-card-description">${card.description}</p>
                                </div>
                            `;
                        });
                    }
                    
                    targetHtml += `
                            </div>
                        </div>
                    `;
                    previewPanel.innerHTML = targetHtml;
                    break;
                case 'benefits':
                    let benefitsHtml = `
                        <div class="preview-content">
                            <h2 class="preview-title">${data.title || 'Les bénéfices concrets de notre collaboration'}</h2>
                            ${data.subtitle ? `<p class="preview-subtitle">${data.subtitle}</p>` : ''}
                            <div class="preview-cards-grid">
                    `;
                    
                    // Afficher toutes les cartes créées dynamiquement
                    let benefitCount = 0;
                    for (let i = 1; i <= 10; i++) {
                        if (data[`card${i}_title`]) {
                            const icon = data[`card${i}_icon`] || 'fa-solid fa-star';
                            const title = data[`card${i}_title`] || `Bénéfice ${i}`;
                            const description = data[`card${i}_description`] || 'Description...';
                            
                            benefitsHtml += `
                                <div class="preview-card">
                                    <div class="preview-card-icon">
                                        <i class="${icon}"></i>
                                    </div>
                                    <h3 class="preview-card-title">${title}</h3>
                                    <p class="preview-card-description">${description}</p>
                                </div>
                            `;
                            benefitCount++;
                        }
                    }
                    
                    // Si aucune carte personnalisée n'existe, afficher les cartes par défaut
                    if (benefitCount === 0) {
                        const defaultBenefits = [
                            {
                                icon: 'fa-solid fa-lightbulb',
                                title: 'Clarté & Vision',
                                description: 'Repartez avec un projet professionnel clair, défini et qui vous ressemble.'
                            },
                            {
                                icon: 'fa-solid fa-mountain-sun',
                                title: 'Confiance Retrouvée',
                                description: 'Prenez conscience de vos forces, de vos talents et de votre valeur unique.'
                            },
                            {
                                icon: 'fa-solid fa-map-signs',
                                title: 'Plan d\'Action',
                                description: 'Obtenez une feuille de route précise et des étapes concrètes pour vos objectifs.'
                            },
                            {
                                icon: 'fa-solid fa-heart',
                                title: 'Sens & Épanouissement',
                                description: 'Alignez enfin votre carrière avec ce qui compte vraiment pour vous.'
                            },
                            {
                                icon: 'fa-solid fa-toolbox',
                                title: 'Outils Personnalisés',
                                description: 'Disposez d\'outils sur-mesure pour continuer à piloter votre carrière en autonomie.'
                            },
                            {
                                icon: 'fa-solid fa-network-wired',
                                title: 'Réseau & Opportunités',
                                description: 'Apprenez à développer votre réseau et à identifier les bonnes opportunités.'
                            }
                        ];
                        
                        defaultBenefits.forEach(benefit => {
                            benefitsHtml += `
                                <div class="preview-card">
                                    <div class="preview-card-icon">
                                        <i class="${benefit.icon}"></i>
                                    </div>
                                    <h3 class="preview-card-title">${benefit.title}</h3>
                                    <p class="preview-card-description">${benefit.description}</p>
                                </div>
                            `;
                        });
                    }
                    
                    benefitsHtml += `
                            </div>
                        </div>
                    `;
                    previewPanel.innerHTML = benefitsHtml;
                    break;
                case 'faq':
                    let faqHtml = `
                        <div class="preview-content">
                            <h2 class="preview-title">${data.title || 'Vos questions, nos réponses'}</h2>
                            <p class="preview-subtitle">${data.subtitle || 'Voici les réponses aux questions les plus fréquentes pour vous aider à y voir plus clair.'}</p>
                            <div class="preview-faq-accordion">
                    `;
                    
                    // Afficher toutes les FAQ créées dynamiquement
                    let faqCount = 0;
                    for (let i = 1; i <= 15; i++) {
                        if (data[`faq${i}_question`]) {
                            const question = data[`faq${i}_question`] || `Question ${i}`;
                            const answer = data[`faq${i}_answer`] || 'Réponse...';
                            
                            faqHtml += `
                                <div class="preview-faq-item" onclick="toggleFaqPreview(this)">
                                    <div class="preview-faq-question">${question}</div>
                                    <div class="preview-faq-answer">
                                        <p>${answer}</p>
                                    </div>
                                </div>
                            `;
                            faqCount++;
                        }
                    }
                    
                    // Si aucune FAQ personnalisée n'existe, afficher les FAQ par défaut
                    if (faqCount === 0) {
                        const defaultFaqs = [
                            {
                                question: 'Combien de temps dure un bilan de compétences ?',
                                answer: 'Un bilan de compétences dure généralement jusqu\'à 24 heures, réparties sur plusieurs semaines. Cela nous laisse le temps d\'approfondir chaque étape sans se presser, avec des séances de 2 à 3 heures.'
                            },
                            {
                                question: 'Mon bilan est-il finançable par le CPF ?',
                                answer: 'Oui, absolument. Le bilan de compétences est une formation éligible au Compte Personnel de Formation (CPF). Je vous accompagnerai dans les démarches pour utiliser vos droits et financer votre accompagnement.'
                            },
                            {
                                question: 'Les séances peuvent-elles se faire à distance ?',
                                answer: 'Oui, je propose des accompagnements en présentiel dans mon cabinet à Paris, mais également 100% à distance par visioconférence. Nous choisissons ensemble la formule qui vous convient le mieux.'
                            }
                        ];
                        
                        defaultFaqs.forEach((faq, index) => {
                            faqHtml += `
                                <div class="preview-faq-item ${index === 0 ? 'active' : ''}" onclick="toggleFaqPreview(this)">
                                    <div class="preview-faq-question">${faq.question}</div>
                                    <div class="preview-faq-answer">
                                        <p>${faq.answer}</p>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    
                    faqHtml += `
                            </div>
                        </div>
                    `;
                    previewPanel.innerHTML = faqHtml;
                    break;
                case 'process':
                    // Récupérer les données des étapes depuis l'input caché ET depuis les champs du formulaire
                    let processSteps = [];
                    const processDataInput = document.getElementById('process-data');
                    
                    // D'abord essayer de lire depuis l'input caché
                    if (processDataInput && processDataInput.value) {
                        try {
                            processSteps = JSON.parse(processDataInput.value);
                        } catch (e) {
                            processSteps = [];
                        }
                    }
                    
                    // Si pas de données dans l'input caché, lire directement depuis les champs
                    if (processSteps.length === 0) {
                        const stepElements = document.querySelectorAll('#steps-container .card-item');
                        stepElements.forEach((stepEl, index) => {
                            const titleInput = stepEl.querySelector('.step-title');
                            const descInput = stepEl.querySelector('.step-description');
                            
                            if (titleInput && descInput) {
                                processSteps.push({
                                    title: titleInput.value || `Étape ${index + 1}`,
                                    description: descInput.value || 'Description...'
                                });
                            }
                        });
                    }
                    
                    let processHtml = `
                        <div class="preview-content" style="background-color: #F2E8DF; padding: 3rem 2rem; border-radius: 12px;">
                            <h2 class="preview-title" style="color: #343A40; margin-bottom: 1rem;">${data.title || 'Un cheminement en 3 phases clés'}</h2>
                            <p class="preview-subtitle" style="color: #666; margin-bottom: 2rem; text-align: center;">${data.subtitle || 'Le bilan est un voyage structuré que nous faisons ensemble.'}</p>
                            <div class="preview-timeline" style="position: relative; max-width: 700px; margin: 0 auto;">
                    `;
                    
                    // Ligne centrale
                    processHtml += `<div style="position: absolute; left: 50%; top: 0; bottom: 0; width: 4px; background: #A3B18A; opacity: 0.3; transform: translateX(-50%);"></div>`;
                    
                    if (processSteps.length > 0) {
                        processSteps.forEach((step, index) => {
                            const isLeft = index % 2 === 0;
                            processHtml += `
                                <div style="display: flex; align-items: center; margin-bottom: 2rem; position: relative;">
                                    ${!isLeft ? '<div style="flex: 1;"></div>' : ''}
                                    <div style="flex: 1; ${isLeft ? 'text-align: right; padding-right: 2rem;' : 'text-align: left; padding-left: 2rem;'}">
                                        <div style="background: #FEFBF6; padding: 1.5rem; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative;">
                                            <h3 style="color: #343A40; margin-bottom: 0.8rem; font-size: 1.1rem;">Phase ${index + 1} : ${step.title || 'Titre'}</h3>
                                            <p style="color: #666; line-height: 1.5; margin: 0; font-size: 0.9rem;">${step.description || 'Description...'}</p>
                                        </div>
                                    </div>
                                    ${isLeft ? '<div style="flex: 1;"></div>' : ''}
                                    <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 20px; height: 20px; background: white; border: 4px solid #A3B18A; border-radius: 50%; z-index: 2;"></div>
                                </div>
                            `;
                        });
                    } else {
                        // Données par défaut si aucune étape
                        const defaultSteps = [
                            { title: 'L\'Investigation', description: 'Analyse de votre parcours et de vos motivations.' },
                            { title: 'L\'Exploration', description: 'Exploration des pistes professionnelles possibles.' },
                            { title: 'La Construction', description: 'Élaboration de votre plan d\'action personnalisé.' }
                        ];
                        
                        defaultSteps.forEach((step, index) => {
                            const isLeft = index % 2 === 0;
                            processHtml += `
                                <div style="display: flex; align-items: center; margin-bottom: 2rem; position: relative;">
                                    ${!isLeft ? '<div style="flex: 1;"></div>' : ''}
                                    <div style="flex: 1; ${isLeft ? 'text-align: right; padding-right: 2rem;' : 'text-align: left; padding-left: 2rem;'}">
                                        <div style="background: #FEFBF6; padding: 1.5rem; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                            <h3 style="color: #343A40; margin-bottom: 0.8rem; font-size: 1.1rem;">Phase ${index + 1} : ${step.title}</h3>
                                            <p style="color: #666; line-height: 1.5; margin: 0; font-size: 0.9rem;">${step.description}</p>
                                        </div>
                                    </div>
                                    ${isLeft ? '<div style="flex: 1;"></div>' : ''}
                                    <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 20px; height: 20px; background: white; border: 4px solid #A3B18A; border-radius: 50%; z-index: 2;"></div>
                                </div>
                            `;
                        });
                    }
                    
                    processHtml += `
                            </div>
                        </div>
                    `;
                    previewPanel.innerHTML = processHtml;
                    break;
                case 'design':
                    previewPanel.innerHTML = `
                        <div class="preview-content">
                            <h2 class="preview-title">Palette de couleurs</h2>
                            <p class="preview-subtitle">Aperçu des couleurs de votre site</p>
                            <div class="preview-cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                                <div class="preview-card" style="background-color: ${data.vert_sauge || '#A3B18A'}; color: white;">
                                    <h3 class="preview-card-title" style="color: white;">Vert Sauge</h3>
                                    <p style="color: rgba(255,255,255,0.9);">${data.vert_sauge || '#A3B18A'}</p>
                                </div>
                                <div class="preview-card" style="background-color: ${data.beige_rose || '#F2E8DF'};">
                                    <h3 class="preview-card-title">Beige Rosé</h3>
                                    <p style="color: #666;">${data.beige_rose || '#F2E8DF'}</p>
                                </div>
                                <div class="preview-card" style="background-color: ${data.creme || '#FEFBF6'};">
                                    <h3 class="preview-card-title">Crème</h3>
                                    <p style="color: #666;">${data.creme || '#FEFBF6'}</p>
                                </div>
                                <div class="preview-card" style="background-color: ${data.gris_anthracite || '#343A40'}; color: white;">
                                    <h3 class="preview-card-title" style="color: white;">Gris Anthracite</h3>
                                    <p style="color: rgba(255,255,255,0.9);">${data.gris_anthracite || '#343A40'}</p>
                                </div>
                                <div class="preview-card" style="background-color: ${data.dore || '#B99470'}; color: white;">
                                    <h3 class="preview-card-title" style="color: white;">Doré</h3>
                                    <p style="color: rgba(255,255,255,0.9);">${data.dore || '#B99470'}</p>
                                </div>
                                <div class="preview-card" style="background-color: ${data.dore_clair || '#d1b59a'};">
                                    <h3 class="preview-card-title">Doré Clair</h3>
                                    <p style="color: #666;">${data.dore_clair || '#d1b59a'}</p>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                default:
                    previewPanel.innerHTML = `
                        <div class="preview-content text-center">
                            <div class="preview-card-icon" style="margin: 2rem auto;">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h2 class="preview-title">Section en développement</h2>
                            <p class="preview-subtitle">La prévisualisation pour cette section sera bientôt disponible.</p>
                        </div>
                    `;
            }
        }

        // Sauvegarde via AJAX
        function saveSectionData() {
            const form = document.getElementById('sectionForm');
            const formData = new FormData(form);
            
            // Ajouter un indicateur de chargement
            const saveBtn = document.getElementById('saveBtn');
            const floatingSaveBtn = document.getElementById('floatingSaveBtn');
            
            const originalBtnText = saveBtn ? saveBtn.innerHTML : '';
            const originalFloatingText = floatingSaveBtn ? floatingSaveBtn.innerHTML : '';
            
            if (saveBtn) {
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sauvegarde...';
                saveBtn.disabled = true;
            }
            if (floatingSaveBtn) {
                floatingSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sauvegarde...';
                floatingSaveBtn.disabled = true;
            }
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la sauvegarde', 'error');
            })
            .finally(() => {
                // Restaurer les boutons
                if (saveBtn) {
                    saveBtn.innerHTML = originalBtnText;
                    saveBtn.disabled = false;
                }
                if (floatingSaveBtn) {
                    floatingSaveBtn.innerHTML = originalFloatingText;
                    floatingSaveBtn.disabled = false;
                }
            });
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Génération des champs et preview initiaux
            if (currentSection !== 'dashboard' && currentSection !== 'settings') {
                generateFormFields(sectionData);
                updatePreview();
            }
            
            // Bouton de sauvegarde principal
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', saveSectionData);
            }
            
            // Bouton de sauvegarde flottant
            const floatingSaveBtn = document.getElementById('floatingSaveBtn');
            if (floatingSaveBtn) {
                floatingSaveBtn.addEventListener('click', saveSectionData);
            }
            
            // Formulaire de changement de mot de passe
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            passwordForm.reset();
                        } else {
                            showNotification(data.error, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Erreur lors de la modification', 'error');
                    });
                });
            }
        });

        // Fonction pour afficher les notifications
        function showNotification(message, type = 'success') {
            // Supprimer les notifications existantes
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => {
                notification.remove();
            });
            
            // Créer la nouvelle notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-2"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            // Afficher la notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Masquer et supprimer la notification après 4 secondes
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 4000);
        }

        // Variables globales pour le sélecteur d'icônes
        let currentCardIndex = null;
        let currentMode = 'card'; // 'card' ou 'step'
        const iconCategories = {
            'Business & Travail': [
                'fa-solid fa-briefcase', 'fa-solid fa-building', 'fa-solid fa-chart-line', 'fa-solid fa-chart-bar',
                'fa-solid fa-handshake', 'fa-solid fa-users', 'fa-solid fa-user-tie', 'fa-solid fa-cogs',
                'fa-solid fa-laptop', 'fa-solid fa-clipboard-check', 'fa-solid fa-calendar-alt', 'fa-solid fa-clock',
                'fa-solid fa-file-contract', 'fa-solid fa-tasks', 'fa-solid fa-project-diagram', 'fa-solid fa-network-wired'
            ],
            'Croissance & Évolution': [
                'fa-solid fa-arrow-trend-up', 'fa-solid fa-seedling', 'fa-solid fa-rocket', 'fa-solid fa-chart-pie',
                'fa-solid fa-level-up-alt', 'fa-solid fa-trophy', 'fa-solid fa-medal', 'fa-solid fa-crown',
                'fa-solid fa-mountain', 'fa-solid fa-stairs', 'fa-solid fa-graduation-cap', 'fa-solid fa-certificate',
                'fa-solid fa-award', 'fa-solid fa-ribbon', 'fa-solid fa-star', 'fa-solid fa-fire'
            ],
            'Navigation & Direction': [
                'fa-solid fa-compass', 'fa-solid fa-map', 'fa-solid fa-route', 'fa-solid fa-location-arrow',
                'fa-solid fa-crosshairs', 'fa-solid fa-target', 'fa-solid fa-bullseye', 'fa-solid fa-direction',
                'fa-solid fa-road', 'fa-solid fa-path', 'fa-solid fa-sign-post', 'fa-solid fa-flag',
                'fa-solid fa-map-marker-alt', 'fa-solid fa-globe', 'fa-solid fa-anchor', 'fa-solid fa-bridge'
            ],
            'Créativité & Innovation': [
                'fa-solid fa-lightbulb', 'fa-solid fa-magic', 'fa-solid fa-wand-magic-sparkles', 'fa-solid fa-palette',
                'fa-solid fa-pen-fancy', 'fa-solid fa-paint-brush', 'fa-solid fa-drafting-compass', 'fa-solid fa-shapes',
                'fa-solid fa-puzzle-piece', 'fa-solid fa-cube', 'fa-solid fa-brain', 'fa-solid fa-eye',
                'fa-solid fa-diamond', 'fa-solid fa-gem', 'fa-solid fa-atom', 'fa-solid fa-flask'
            ],
            'Communication & Relations': [
                'fa-solid fa-comments', 'fa-solid fa-comment-dots', 'fa-solid fa-phone', 'fa-solid fa-envelope',
                'fa-solid fa-megaphone', 'fa-solid fa-bullhorn', 'fa-solid fa-microphone', 'fa-solid fa-broadcast-tower',
                'fa-solid fa-share-alt', 'fa-solid fa-link', 'fa-solid fa-network-wired', 'fa-solid fa-wifi',
                'fa-solid fa-rss', 'fa-solid fa-signal', 'fa-solid fa-satellite', 'fa-solid fa-globe-americas'
            ],
            'Émotions & Motivation': [
                'fa-solid fa-heart', 'fa-solid fa-smile', 'fa-solid fa-laugh', 'fa-solid fa-grin',
                'fa-solid fa-thumbs-up', 'fa-solid fa-hand-peace', 'fa-solid fa-pray', 'fa-solid fa-dove',
                'fa-solid fa-sun', 'fa-solid fa-rainbow', 'fa-solid fa-feather', 'fa-solid fa-leaf',
                'fa-solid fa-flower', 'fa-solid fa-butterfly', 'fa-solid fa-kiwi-bird', 'fa-solid fa-music'
            ],
            'Solutions & Outils': [
                'fa-solid fa-key', 'fa-solid fa-unlock', 'fa-solid fa-door-open', 'fa-solid fa-window-restore',
                'fa-solid fa-tools', 'fa-solid fa-wrench', 'fa-solid fa-hammer', 'fa-solid fa-screwdriver',
                'fa-solid fa-gear', 'fa-solid fa-cog', 'fa-solid fa-settings', 'fa-solid fa-sliders',
                'fa-solid fa-adjustments', 'fa-solid fa-filter', 'fa-solid fa-funnel-dollar', 'fa-solid fa-search'
            ],
            'Sécurité & Protection': [
                'fa-solid fa-shield', 'fa-solid fa-shield-alt', 'fa-solid fa-lock', 'fa-solid fa-user-shield',
                'fa-solid fa-home', 'fa-solid fa-umbrella', 'fa-solid fa-hard-hat', 'fa-solid fa-vest',
                'fa-solid fa-first-aid', 'fa-solid fa-medkit', 'fa-solid fa-life-ring', 'fa-solid fa-anchor',
                'fa-solid fa-fortress', 'fa-solid fa-castle', 'fa-solid fa-tower', 'fa-solid fa-wall'
            ]
        };

        // Fonction pour ouvrir le sélecteur d'icônes
        function openIconSelector(cardIndex) {
            currentCardIndex = cardIndex;
            currentMode = 'card';
            const modal = document.getElementById('iconModal');
            const modalContent = document.getElementById('iconModalContent');
            
            // Générer le contenu de la modale
            let modalHtml = `
                <div class="modal-header">
                    <h3>Choisir une icône</h3>
                    <button type="button" class="close-modal" onclick="closeIconSelector()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="icon-search-wrapper">
                    <input type="text" class="icon-search" placeholder="Rechercher une icône..." onkeyup="filterIcons(this.value)">
                </div>
                <div id="iconCategoriesContainer">
            `;
            
            // Ajouter chaque catégorie
            Object.entries(iconCategories).forEach(([categoryName, icons]) => {
                modalHtml += `
                    <div class="icon-category" data-category="${categoryName.toLowerCase()}">
                        <div class="icon-category-title">${categoryName}</div>
                        <div class="icon-grid">
                `;
                
                icons.forEach(iconClass => {
                    modalHtml += `
                        <div class="icon-option" data-icon="${iconClass}" onclick="selectIcon('${iconClass}')">
                            <i class="${iconClass}"></i>
                        </div>
                    `;
                });
                
                modalHtml += `
                        </div>
                    </div>
                `;
            });
            
            modalHtml += `
                </div>
            `;
            
            modalContent.innerHTML = modalHtml;
            modal.style.display = 'flex';
            
            // Marquer l'icône actuellement sélectionnée
            const currentIcon = document.getElementById(`icon-input-${cardIndex}`).value;
            const currentOption = modal.querySelector(`[data-icon="${currentIcon}"]`);
            if (currentOption) {
                currentOption.classList.add('selected');
            }
        }

        // Fonction pour fermer le sélecteur d'icônes
        function closeIconSelector() {
            const modal = document.getElementById('iconModal');
            modal.style.display = 'none';
            currentCardIndex = null;
        }

        // Fonction pour sélectionner une icône
        function selectIcon(iconClass) {
            if (currentCardIndex !== null && currentMode === 'card') {
                // Mode carte - target_audience seulement
                const iconInput = document.getElementById(`icon-input-${currentCardIndex}`);
                if (iconInput) {
                    iconInput.value = iconClass;
                }
                
                const card = document.getElementById(`card-${currentCardIndex}`);
                if (card) {
                    const previewIcon = card.querySelector('.icon-preview i');
                    if (previewIcon) {
                        previewIcon.className = iconClass;
                    }
                }
                
                // Fermer la modale
                closeIconSelector();
                
                // Mettre à jour la prévisualisation
                updatePreview();
            }
        }

        // Fonction pour filtrer les icônes
        function filterIcons(searchTerm) {
            const categories = document.querySelectorAll('.icon-category');
            const term = searchTerm.toLowerCase();
            
            categories.forEach(category => {
                const icons = category.querySelectorAll('.icon-option');
                let hasVisibleIcons = false;
                
                icons.forEach(icon => {
                    const iconClass = icon.getAttribute('data-icon');
                    const isVisible = iconClass.toLowerCase().includes(term) || term === '';
                    icon.style.display = isVisible ? 'flex' : 'none';
                    if (isVisible) hasVisibleIcons = true;
                });
                
                category.style.display = hasVisibleIcons ? 'block' : 'none';
            });
        }

        // Fermer la modale en cliquant sur l'overlay
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('iconModal');
            if (e.target === modal) {
                closeIconSelector();
            }
        });

        // Fonctions pour la gestion des messages
        function viewMessage(messageId) {
            // Récupérer les données du message depuis PHP
            const messages = <?= json_encode($messages ?? []) ?>;
            const message = messages.find(m => m.id == messageId);
            
            if (message) {
                const content = `
                    <div class="space-y-6">
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-user mr-2 text-blue-600"></i>
                                Informations de contact
                            </h4>
                            <div class="space-y-2 text-sm">
                                <p><span class="font-medium text-gray-700">Nom :</span> ${message.firstname} ${message.lastname}</p>
                                <p><span class="font-medium text-gray-700">Email :</span> 
                                   <a href="mailto:${message.email}" class="text-blue-600 hover:underline">${message.email}</a>
                                </p>
                                ${message.phone ? `<p><span class="font-medium text-gray-700">Téléphone :</span> 
                                   <a href="tel:${message.phone}" class="text-blue-600 hover:underline">${message.phone}</a></p>` : ''}
                                <p><span class="font-medium text-gray-700">Date :</span> ${new Date(message.created_at).toLocaleString('fr-FR')}</p>
                                <p><span class="font-medium text-gray-700">IP :</span> ${message.ip_address}</p>
                                <p><span class="font-medium text-gray-700">Statut :</span> 
                                   <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${message.is_read ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                       <i class="fas fa-${message.is_read ? 'check' : 'circle'} mr-1"></i>
                                       ${message.is_read ? 'Lu' : 'Non lu'}
                                   </span>
                                </p>
                            </div>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-comment mr-2 text-green-600"></i>
                                Message reçu
                            </h4>
                            <div class="bg-gray-50 border rounded p-3 text-sm whitespace-pre-wrap">${message.message}</div>
                        </div>
                    </div>
                `;
                
                document.getElementById('messageContent').innerHTML = content;
                document.getElementById('replyMessageId').value = messageId;
                
                // Pré-remplir le sujet de réponse
                document.getElementById('replySubject').value = `Re: Message de ${message.firstname} ${message.lastname}`;
                
                // Charger l'historique des réponses
                loadReplyHistory(messageId);
                
                document.getElementById('messageModal').classList.remove('hidden');
                document.getElementById('messageModal').classList.add('flex');
                
                // Marquer comme lu automatiquement ET mettre à jour le compteur immédiatement
                if (!message.is_read) {
                    // Mettre à jour visuellement le compteur AVANT l'appel AJAX pour une réactivité immédiate
                    const currentCount = parseInt(document.querySelector('span.bg-red-500')?.textContent || '0');
                    if (currentCount > 0) {
                        updateUnreadCountDisplay(currentCount - 1);
                        lastUnreadCount = currentCount - 1; // Mettre à jour la variable globale
                    }
                    
                    // Marquer comme lu côté serveur (sans mettre à jour le compteur car déjà fait)
                    markAsRead(messageId, false, false);
                    
                    // Rafraîchir les messages du dashboard si on est dessus
                    setTimeout(() => refreshDashboardMessages(), 500);
                }
            }
        }
        
        function loadReplyHistory(messageId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_reply_history&message_id=${messageId}&csrf_token=<?= generateCSRFToken() ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.replies.length > 0) {
                    let historyHtml = '';
                    data.replies.forEach(reply => {
                        historyHtml += `
                            <div class="bg-gray-50 border rounded p-3 text-sm">
                                <div class="font-medium text-gray-700 mb-1">${reply.subject}</div>
                                <div class="text-gray-600 text-xs mb-2">${new Date(reply.sent_at).toLocaleString('fr-FR')}</div>
                                <div class="text-gray-800">${reply.reply_message.substring(0, 100)}${reply.reply_message.length > 100 ? '...' : ''}</div>
                            </div>
                        `;
                    });
                    document.getElementById('replyHistoryContent').innerHTML = historyHtml;
                    document.getElementById('replyHistory').classList.remove('hidden');
                }
            })
            .catch(error => {
                console.log('Aucun historique trouvé');
            });
        }

        function closeModal() {
            document.getElementById('messageModal').classList.add('hidden');
            document.getElementById('messageModal').classList.remove('flex');
        }

        function markAsRead(messageId, showAlert = true, updateCounter = true) {
            const row = document.querySelector(`tr[data-message-id="${messageId}"]`);
            
            console.log('Marquage comme lu pour message:', messageId); // Debug
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_message_read&message_id=${messageId}&csrf_token=<?= generateCSRFToken() ?>`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Réponse markAsRead:', data); // Debug
                
                if (data.success) {
                    if (showAlert) {
                        alert('Message marqué comme lu');
                    }
                    
                    // Mise à jour visuelle instantanée
                    if (row) {
                        // Changer le fond de la ligne
                        row.classList.remove('bg-blue-50');
                        row.classList.add('bg-white');
                        
                        // Mettre à jour le badge de statut
                        const statusCell = row.querySelector('td:nth-child(4)');
                        if (statusCell) {
                            statusCell.innerHTML = `
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-check mr-1"></i>Lu
                                </span>
                            `;
                        }
                        
                        // Supprimer le bouton "Marquer lu"
                        const actionsCell = row.querySelector('td:nth-child(6)');
                        const markReadButton = actionsCell.querySelector('button[onclick*="markAsRead"]');
                        if (markReadButton) {
                            markReadButton.remove();
                        }
                        
                        // Animation de mise à jour
                        row.style.animation = 'rowUpdate 0.5s ease-out';
                    }
                    
                    // Mettre à jour les compteurs seulement si demandé
                    if (updateCounter) {
                        setTimeout(() => {
                            updateUnreadCount();
                            // Forcer la mise à jour immédiate
                            checkForNewMessages();
                        }, 500);
                    }
                    
                } else {
                    alert(data.error || 'Erreur lors de la mise à jour');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
            });
        }
        
        function updateUnreadCount() {
            // Mettre à jour le badge de notification dans le menu
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_unread_count&csrf_token=<?= generateCSRFToken() ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateUnreadCountDisplay(data.count);
                    // Mettre à jour la variable globale pour éviter les doublons
                    lastUnreadCount = data.count;
                    console.log('Compteur mis à jour:', data.count);
                    
                    // Rafraîchir les messages du dashboard
                    refreshDashboardMessages();
                }
            })
            .catch(error => {
                console.log('Erreur mise à jour compteur:', error);
            });
        }

        // Fermer la modale avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Gestion du formulaire de réponse
        document.addEventListener('DOMContentLoaded', function() {
            const replyForm = document.getElementById('replyForm');
            if (replyForm) {
                replyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    sendReply();
                });
            }
        });
        
        function sendReply() {
            const form = document.getElementById('replyForm');
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            const messageId = document.getElementById('replyMessageId').value;
            
            // Désactiver le bouton pendant l'envoi
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Envoi en cours...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    
                    // Ajouter à l'historique avec animation
                    const historyContent = document.getElementById('replyHistoryContent');
                    const newReply = document.createElement('div');
                    newReply.className = 'bg-green-50 border border-green-200 rounded p-3 text-sm';
                    newReply.style.opacity = '0';
                    newReply.style.transform = 'translateY(-10px)';
                    newReply.innerHTML = `
                        <div class="font-medium text-green-700 mb-1">${form.subject.value}</div>
                        <div class="text-green-600 text-xs mb-2">Envoyé le ${data.timestamp}</div>
                        <div class="text-green-800">${form.reply_message.value.substring(0, 100)}${form.reply_message.value.length > 100 ? '...' : ''}</div>
                    `;
                    
                    historyContent.insertBefore(newReply, historyContent.firstChild);
                    document.getElementById('replyHistory').classList.remove('hidden');
                    
                    // Animation d'apparition
                    setTimeout(() => {
                        newReply.style.transition = 'all 0.3s ease-out';
                        newReply.style.opacity = '1';
                        newReply.style.transform = 'translateY(0)';
                    }, 10);
                    
                    // Mettre à jour le compteur de réponses dans le tableau principal
                    updateReplyCount(messageId);
                    
                    // Réinitialiser le formulaire
                    form.reply_message.value = '';
                    
                } else {
                    showNotification(data.error || 'Erreur lors de l\'envoi', 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
            })
            .finally(() => {
                // Réactiver le bouton
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Envoyer la réponse';
            });
        }
        
        function updateReplyCount(messageId) {
            // Mettre à jour visuellement le compteur de réponses
            const row = document.querySelector(`tr[data-message-id="${messageId}"]`);
            if (row) {
                const replyCell = row.querySelector('td:nth-child(5)');
                if (replyCell) {
                    // Récupérer le nombre actuel ou initialiser à 0
                    const currentBadge = replyCell.querySelector('.bg-blue-100');
                    let currentCount = 0;
                    
                    if (currentBadge) {
                        const match = currentBadge.textContent.match(/(\d+)/);
                        currentCount = match ? parseInt(match[1]) : 0;
                    }
                    
                    const newCount = currentCount + 1;
                    const plural = newCount > 1 ? 's' : '';
                    
                    replyCell.innerHTML = `
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-reply mr-1"></i>${newCount} réponse${plural}
                        </span>
                    `;
                    
                    // Animation de mise à jour
                    replyCell.style.animation = 'rowUpdate 0.5s ease-out';
                }
            }
        }
        
        function saveReplyDraft() {
            const subject = document.getElementById('replySubject').value;
            const message = document.getElementById('replyMessage').value;
            
            if (subject || message) {
                localStorage.setItem('replyDraft', JSON.stringify({
                    subject: subject,
                    message: message,
                    timestamp: new Date().toISOString()
                }));
                showNotification('Brouillon sauvegardé');
            }
        }
        
        function loadReplyDraft() {
            const draft = localStorage.getItem('replyDraft');
            if (draft) {
                const data = JSON.parse(draft);
                document.getElementById('replySubject').value = data.subject || '';
                document.getElementById('replyMessage').value = data.message || '';
            }
        }

        // Fonction pour supprimer un message
        function deleteMessage(messageId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce message ? Cette action est irréversible.')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_message&message_id=${messageId}&csrf_token=<?= generateCSRFToken() ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Message supprimé avec succès');
                        
                        // Supprimer la ligne avec une animation
                        const row = document.querySelector(`tr[data-message-id="${messageId}"]`);
                        if (row) {
                            row.style.transition = 'all 0.3s ease-out';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(-100px)';
                            
                            setTimeout(() => {
                                row.remove();
                                
                                // Vérifier s'il reste des messages
                                const tbody = document.getElementById('messagesTableBody');
                                if (tbody && tbody.children.length === 0) {
                                    // Si plus de messages, recharger la liste
                                    refreshMessages();
                                }
                            }, 300);
                        }
                        
                        // Mettre à jour le compteur
                        updateUnreadCount();
                        
                    } else {
                        showNotification(data.error || 'Erreur lors de la suppression', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erreur de connexion', 'error');
                });
            }
        }

        // Fonction pour actualiser la liste des messages
        function refreshMessages() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_messages&csrf_token=<?= generateCSRFToken() ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('messagesContainer');
                    if (container) {
                        container.innerHTML = data.html;
                    }
                }
            })
            .catch(error => {
                console.log('Erreur lors de l\'actualisation:', error);
            });
        }

        // Variables globales pour l'auto-refresh
        let autoRefreshInterval;
        let lastUnreadCount = <?= $unreadCount ?>;
        let notificationSound;
        let totalNewMessages = 0; // Compteur de nouveaux messages accumulés
        let notificationVisible = false; // Statut de la notification
        let isCheckingMessages = false; // Protection contre les appels multiples

        // Créer le son de notification
        function createNotificationSound() {
            // Créer un son de notification simple
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            return function playSound() {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.3);
            }
        }

        // Fonction pour vérifier les nouveaux messages
        function checkForNewMessages() {
            // Éviter les appels multiples simultanés
            if (isCheckingMessages) {
                console.log('Vérification déjà en cours, skip');
                return;
            }
            
            isCheckingMessages = true;
            console.log('Vérification des nouveaux messages...');
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_unread_count&csrf_token=<?= generateCSRFToken() ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const newCount = data.count;
                    console.log('Ancien count:', lastUnreadCount, 'Nouveau count:', newCount);
                    
                    // Mettre à jour les compteurs visuels
                    updateUnreadCountDisplay(newCount);
                    
                    // Vérifier s'il y a de nouveaux messages
                    if (newCount > lastUnreadCount) {
                        const newMessages = newCount - lastUnreadCount;
                        console.log('Nouveaux messages détectés:', newMessages);
                        
                        // Accumuler les nouveaux messages
                        totalNewMessages += newMessages;
                        
                        // Afficher ou mettre à jour la notification
                        showNewMessageNotification(totalNewMessages);
                        
                        // Jouer le son de notification
                        if (notificationSound) {
                            notificationSound();
                        }
                        
                        // Mettre à jour la section messages récents du dashboard
                        refreshDashboardMessages();
                        
                        // Mettre à jour lastUnreadCount IMMÉDIATEMENT pour éviter les doublons
                        lastUnreadCount = newCount;
                    } else if (newCount !== lastUnreadCount) {
                        // Synchroniser avec le serveur même si le count a diminué
                        lastUnreadCount = newCount;
                        refreshDashboardMessages();
                    }
                    
                    // Si on est sur la page des messages, actualiser la liste
                    if (window.location.href.includes('section=messages')) {
                        refreshMessages();
                    }
                }
            })
            .catch(error => {
                console.log('Erreur vérification nouveaux messages:', error);
            })
            .finally(() => {
                isCheckingMessages = false;
            });
        }

        // Mettre à jour l'affichage du compteur
        function updateUnreadCountDisplay(count) {
            console.log('Mise à jour compteur:', count); // Debug
            
            // Compteur dans le menu sidebar
            const messagesLink = document.querySelector('a[href*="section=messages"]');
            let menuBadge = messagesLink ? messagesLink.querySelector('span.bg-red-500') : null;
            
            console.log('Messages link trouvé:', messagesLink); // Debug
            console.log('Badge trouvé:', menuBadge); // Debug
            
            if (count > 0) {
                if (menuBadge) {
                    // Mettre à jour le badge existant
                    menuBadge.textContent = count;
                    console.log('Badge mis à jour:', count); // Debug
                } else if (messagesLink) {
                    // Créer un nouveau badge
                    const newBadge = document.createElement('span');
                    newBadge.className = 'ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-1 min-w-[20px] text-center';
                    newBadge.textContent = count;
                    messagesLink.appendChild(newBadge);
                    console.log('Nouveau badge créé:', count); // Debug
                }
            } else {
                // Supprimer le badge s'il n'y a plus de messages non lus
                if (menuBadge) {
                    menuBadge.remove();
                    console.log('Badge supprimé'); // Debug
                }
            }
            
            // Compteur dans le dashboard
            const dashboardCount = document.getElementById('dashboardUnreadCount');
            if (dashboardCount) {
                dashboardCount.textContent = count;
                console.log('Dashboard mis à jour:', count); // Debug
            }
            
            // Badge dans le titre de la section messages récents
            const dashboardBadge = document.getElementById('dashboardUnreadBadge');
            if (count > 0) {
                if (dashboardBadge) {
                    dashboardBadge.textContent = count;
                    dashboardBadge.style.display = 'inline-block';
                } else {
                    // Créer le badge s'il n'existe pas
                    const messagesTitle = document.querySelector('.editor-section h3 .flex.items-center');
                    if (messagesTitle) {
                        messagesTitle.insertAdjacentHTML('beforeend', 
                            `<span id="dashboardUnreadBadge" class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-1 animate-pulse">${count}</span>`
                        );
                    }
                }
            } else if (dashboardBadge) {
                dashboardBadge.style.display = 'none';
            }
            
            // Mettre à jour aussi le point clignotant dans le dashboard
            const dashboardCard = dashboardCount ? dashboardCount.closest('.stats-card') : null;
            const pulseIndicator = dashboardCard ? dashboardCard.querySelector('.animate-pulse') : null;
            
            if (count > 0) {
                if (!pulseIndicator && dashboardCard) {
                    dashboardCard.insertAdjacentHTML('beforeend', 
                        '<div class="absolute top-2 right-2"><span class="animate-pulse w-3 h-3 bg-red-500 rounded-full"></span></div>'
                    );
                }
            } else {
                if (pulseIndicator) {
                    pulseIndicator.parentElement.remove();
                }
            }
        }

        // Afficher la notification de nouveaux messages
        function showNewMessageNotification(totalNew) {
            const notification = document.getElementById('newMessageNotification');
            const messageText = document.getElementById('newMessageText');
            
            if (notification && messageText) {
                // Mettre à jour le texte avec le total accumulé
                messageText.textContent = totalNew === 1 ? 
                    'Nouveau message reçu !' : 
                    `${totalNew} nouveaux messages reçus !`;
                
                // Afficher la notification si elle n'est pas déjà visible
                if (!notificationVisible) {
                    notification.classList.remove('hidden');
                    notification.classList.add('animate-bounce');
                    notificationVisible = true;
                    
                    // Animation de rebond temporaire
                    setTimeout(() => {
                        notification.classList.remove('animate-bounce');
                    }, 1000);
                } else {
                    // Si déjà visible, juste une petite animation pour indiquer la mise à jour
                    notification.classList.add('animate-pulse');
                    setTimeout(() => {
                        notification.classList.remove('animate-pulse');
                    }, 500);
                }
            }
        }

        // Masquer la notification (quand l'utilisateur clique)
        function hideNewMessageNotification() {
            const notification = document.getElementById('newMessageNotification');
            if (notification) {
                notification.classList.add('hidden');
                notification.classList.remove('animate-bounce', 'animate-pulse');
                notificationVisible = false;
                totalNewMessages = 0; // Remettre à zéro le compteur
            }
        }

        // Actualiser la section messages récents du dashboard
        function refreshDashboardMessages() {
            const dashboardMessages = document.getElementById('dashboardMessages');
            if (!dashboardMessages) return; // Pas sur le dashboard
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_recent_messages&csrf_token=<?= generateCSRFToken() ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages) {
                    updateDashboardMessagesHTML(data.messages);
                }
            })
            .catch(error => {
                console.error('Erreur lors du rafraîchissement des messages:', error);
            });
        }

        // Mettre à jour le HTML des messages du dashboard
        function updateDashboardMessagesHTML(messages) {
            const dashboardMessages = document.getElementById('dashboardMessages');
            if (!dashboardMessages) return;

            if (messages.length === 0) {
                dashboardMessages.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-3xl mb-2"></i>
                        <p>Aucun message pour le moment</p>
                    </div>
                `;
                return;
            }

            let html = '';
            messages.forEach(message => {
                const isUnread = message.is_read == 0;
                const date = new Date(message.created_at);
                const formattedDate = date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                html += `
                    <div class="message-preview ${isUnread ? 'unread bg-blue-50 border-l-4 border-blue-500' : 'opacity-60'} p-3 rounded-lg cursor-pointer hover:bg-gray-50 transition-all duration-200" 
                         onclick="viewMessage(${message.id})">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    ${isUnread ? '<span class="w-2 h-2 bg-blue-500 rounded-full message-indicator"></span>' : ''}
                                    <h4 class="font-semibold text-gray-800">${escapeHtml(message.firstname + ' ' + message.lastname)}</h4>
                                    <span class="text-xs text-gray-500">${formattedDate}</span>
                                </div>
                                <p class="text-sm text-gray-600 truncate">${escapeHtml(message.message.substring(0, 80))}...</p>
                            </div>
                            <div class="ml-2 flex flex-col gap-1">
                                ${isUnread ? `
                                    <button onclick="event.stopPropagation(); markAsRead(${message.id}, false)" 
                                            class="text-green-600 hover:text-green-800 text-xs transition-colors duration-200" title="Marquer comme lu">
                                        <i class="fas fa-check"></i>
                                    </button>
                                ` : ''}
                                <button onclick="event.stopPropagation(); viewMessage(${message.id})" 
                                        class="text-blue-600 hover:text-blue-800 text-xs transition-colors duration-200" title="Voir le message">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += `
                <div class="text-center pt-3">
                    <a href="?section=messages" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Voir tous les messages →
                    </a>
                </div>
            `;

            dashboardMessages.innerHTML = html;
        }

        // Fonction utilitaire pour échapper le HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Démarrer l'actualisation automatique
        function startAutoRefresh() {
            if (!autoRefreshInterval) {
                // Vérifier toutes les 20 secondes pour éviter les conflits
                autoRefreshInterval = setInterval(checkForNewMessages, 20000);
                console.log('Auto-refresh démarré (20s)');
            }
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                console.log('Auto-refresh arrêté');
            }
        }

        // Démarrer l'actualisation automatique au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Créer le son de notification
            try {
                notificationSound = createNotificationSound();
            } catch (e) {
                console.log('Impossible de créer le son de notification:', e);
            }
            
            // Si on est sur la page des messages, masquer la notification
            if (window.location.href.includes('section=messages')) {
                hideNewMessageNotification();
            }
            
            // Démarrer l'auto-refresh
            startAutoRefresh();
            
            // Première vérification après 5 secondes pour laisser le temps à la page de se charger
            setTimeout(checkForNewMessages, 5000);
        });

        // Arrêter l'actualisation quand on quitte la page
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });

        // Gérer la visibilité de la page (quand on change d'onglet)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
                // Vérifier immédiatement quand on revient sur la page
                setTimeout(checkForNewMessages, 1000);
            }
        });
    </script>

    <!-- Modale pour le sélecteur d'icônes -->
    <div id="iconModal" class="modal-overlay">
        <div class="modal-content" id="iconModalContent">
            <!-- Le contenu sera généré dynamiquement -->
        </div>
    </div>
</body>
</html>
