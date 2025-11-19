<?php
/**
 * Reusable Vote System Include
 * For use in modals across the StyleForger site
 * 
 * Usage: Include this file and call renderVoteSystem($style_id)
 */

function renderVoteSystem($style_id, $current_votes = 0) {
    if (!isset($_SESSION['voted_styles'])) {
        $_SESSION['voted_styles'] = [];
    }
    
    $has_voted = in_array($style_id, $_SESSION['voted_styles']);
    $heart_icon = $has_voted ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
    $vote_class = $has_voted ? 'voted' : '';
    
    ob_start();
    ?>
    <div class="vote-system" data-style-id="<?= htmlspecialchars($style_id) ?>">
        <div class="vote-container">
            <button class="vote-heart-btn <?= $vote_class ?>" onclick="toggleVoteHeart(<?= $style_id ?>)">
                <i class="<?= $heart_icon ?> vote-heart-icon"></i>
                <div class="vote-sparkles"></div>
            </button>
            <div class="vote-count"><?= number_format($current_votes) ?></div>
        </div>
    </div>
    
    <style>
        .vote-system {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .vote-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        
        .vote-heart-btn {
            position: relative;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
        }
        
        .vote-heart-btn:hover {
            background: rgba(255, 71, 87, 0.1);
            transform: scale(1.1);
        }
        
        .vote-heart-icon {
            font-size: 1.5rem;
            color: #ff4757;
            transition: all 0.3s ease;
            z-index: 2;
        }
        
        .vote-heart-btn:not(.voted) .vote-heart-icon {
            color: var(--text-secondary);
        }
        
        .vote-heart-btn:not(.voted):hover .vote-heart-icon {
            color: #ff4757;
        }
        
        .vote-heart-btn.voted .vote-heart-icon {
            animation: heartPulse 0.6s ease-in-out;
        }
        
        .vote-count {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            min-height: 20px;
            line-height: 20px;
        }
        
        .vote-heart-btn.voted + .vote-count {
            color: #ff4757;
        }
        
        /* Sparkle animation */
        .vote-sparkles {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
            z-index: 1;
        }
        
        .vote-sparkles.animate {
            animation: sparkleShow 1s ease-out forwards;
        }
        
        .vote-sparkles.animate::before,
        .vote-sparkles.animate::after {
            content: '✨';
            position: absolute;
            font-size: 0.75rem;
            color: #ff4757;
            animation: sparkleFloat 1s ease-out forwards;
        }
        
        .vote-sparkles.animate::before {
            top: -5px;
            left: -5px;
            animation-delay: 0.1s;
        }
        
        .vote-sparkles.animate::after {
            bottom: -5px;
            right: -5px;
            animation-delay: 0.3s;
        }
        
        @keyframes heartPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }
        
        @keyframes sparkleShow {
            0% { opacity: 0; }
            50% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        @keyframes sparkleFloat {
            0% {
                opacity: 0;
                transform: translateY(0) scale(0.5);
            }
            50% {
                opacity: 1;
                transform: translateY(-10px) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-20px) scale(0.5);
            }
        }
        
        /* Loading state */
        .vote-heart-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .vote-heart-btn.loading .vote-heart-icon {
            animation: pulse 1s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Get vote count for a style from database
 */
function getVoteCount($conn, $style_id) {
    $stmt = $conn->prepare("SELECT votes FROM images WHERE id = ?");
    $stmt->bind_param("i", $style_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return (int)$row['votes'];
    }
    
    return 0;
}

/**
 * Check if user has voted for a style in current session
 */
function hasUserVoted($style_id) {
    if (!isset($_SESSION['voted_styles'])) {
        $_SESSION['voted_styles'] = [];
    }
    
    return in_array($style_id, $_SESSION['voted_styles']);
}
?>