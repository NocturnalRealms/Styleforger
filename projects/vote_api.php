<?php
/**
 * Fixed Vote API for StyleForger
 * Handles session-based voting for images (not niji)
 */

session_start();
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Initialize session votes tracking
if (!isset($_SESSION['voted_styles'])) {
    $_SESSION['voted_styles'] = [];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['style_id']) || !isset($input['action'])) {
            echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            exit;
        }
        
        $style_id = (int)$input['style_id'];
        $action = $input['action']; // 'vote' or 'unvote'
        $session_id = session_id();
        
        // Validate style exists
        $check_stmt = $conn->prepare("SELECT id, votes FROM images WHERE id = ?");
        $check_stmt->bind_param("i", $style_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Style not found']);
            exit;
        }
        
        $style_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($action === 'vote') {
            // Check if already voted in this session
            if (in_array($style_id, $_SESSION['voted_styles'])) {
                echo json_encode(['success' => false, 'error' => 'Already voted in this session']);
                exit;
            }
            
            // Add vote to database
            $stmt = $conn->prepare("UPDATE images SET votes = votes + 1 WHERE id = ?");
            $stmt->bind_param("i", $style_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                // Track vote in session-based votes table
                $vote_stmt = $conn->prepare("INSERT INTO session_votes (session_id, style_id, voted_at) VALUES (?, ?, NOW())");
                $vote_stmt->bind_param("si", $session_id, $style_id);
                $vote_stmt->execute();
                $vote_stmt->close();
                
                // Add to session tracking
                $_SESSION['voted_styles'][] = $style_id;
                
                $new_vote_count = $style_data['votes'] + 1;
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update vote count']);
                exit;
            }
            
            $stmt->close();
            
        } else if ($action === 'unvote') {
            // Check if voted in this session
            if (!in_array($style_id, $_SESSION['voted_styles'])) {
                echo json_encode(['success' => false, 'error' => 'Not voted in this session']);
                exit;
            }
            
            // Remove vote from database
            $stmt = $conn->prepare("UPDATE images SET votes = GREATEST(0, votes - 1) WHERE id = ?");
            $stmt->bind_param("i", $style_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                // Remove from session votes table
                $vote_stmt = $conn->prepare("DELETE FROM session_votes WHERE session_id = ? AND style_id = ?");
                $vote_stmt->bind_param("si", $session_id, $style_id);
                $vote_stmt->execute();
                $vote_stmt->close();
                
                // Remove from session tracking
                $_SESSION['voted_styles'] = array_diff($_SESSION['voted_styles'], [$style_id]);
                $_SESSION['voted_styles'] = array_values($_SESSION['voted_styles']); // Re-index
                
                $new_vote_count = max(0, $style_data['votes'] - 1);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update vote count']);
                exit;
            }
            
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
        }
        
        // Get updated vote count to be sure
        $final_stmt = $conn->prepare("SELECT votes FROM images WHERE id = ?");
        $final_stmt->bind_param("i", $style_id);
        $final_stmt->execute();
        $final_result = $final_stmt->get_result();
        $final_data = $final_result->fetch_assoc();
        $final_stmt->close();
        
        echo json_encode([
            'success' => true,
            'new_vote_count' => (int)$final_data['votes'],
            'user_has_voted' => in_array($style_id, $_SESSION['voted_styles']),
            'action' => $action
        ]);
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get vote status for a style
        if (!isset($_GET['style_id'])) {
            echo json_encode(['success' => false, 'error' => 'Missing style_id parameter']);
            exit;
        }
        
        $style_id = (int)$_GET['style_id'];
        
        $stmt = $conn->prepare("SELECT votes FROM images WHERE id = ?");
        $stmt->bind_param("i", $style_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Style not found']);
            exit;
        }
        
        $data = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'vote_count' => (int)$data['votes'],
            'user_has_voted' => in_array($style_id, $_SESSION['voted_styles'])
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Vote API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>