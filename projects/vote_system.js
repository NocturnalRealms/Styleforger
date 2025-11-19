/**
 * Vote System JavaScript
 * Handles heart voting with cute animations and sparkles
 * For use across StyleForger modals
 */

// Global variable to track current style for voting
let currentVoteStyleId = null;

/**
 * Toggle vote for a style with heart animation
 */
async function toggleVoteHeart(styleId) {
    const voteContainer = document.querySelector(`[data-style-id="${styleId}"]`);
    if (!voteContainer) return;
    
    const heartBtn = voteContainer.querySelector('.vote-heart-btn');
    const heartIcon = voteContainer.querySelector('.vote-heart-icon');
    const voteCountEl = voteContainer.querySelector('.vote-count');
    const sparkles = voteContainer.querySelector('.vote-sparkles');
    
    const isVoted = heartBtn.classList.contains('voted');
    const action = isVoted ? 'unvote' : 'vote';
    
    // Add loading state
    heartBtn.classList.add('loading');
    
    try {
        const response = await fetch('vote_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                style_id: styleId,
                action: action
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update the UI based on new state
            if (data.user_has_voted) {
                // User just voted - fill the heart and sparkle!
                heartBtn.classList.add('voted');
                heartIcon.className = 'fa-solid fa-heart vote-heart-icon';
                
                // Trigger sparkle animation
                sparkles.classList.add('animate');
                setTimeout(() => {
                    sparkles.classList.remove('animate');
                }, 1000);
                
                // Heart pulse animation is handled by CSS
                
            } else {
                // User just unvoted - hollow the heart
                heartBtn.classList.remove('voted');
                heartIcon.className = 'fa-regular fa-heart vote-heart-icon';
            }
            
            // Update vote count with animation
            animateVoteCount(voteCountEl, data.new_vote_count);
            
            // Update any gallery tiles if visible
            updateGalleryTileVoteCount(styleId, data.new_vote_count);
            
        } else {
            console.error('Vote failed:', data.error);
            showVoteError(data.error || 'Failed to process vote');
        }
        
    } catch (error) {
        console.error('Vote request failed:', error);
        showVoteError('Network error occurred');
    } finally {
        // Remove loading state
        heartBtn.classList.remove('loading');
    }
}

/**
 * Animate vote count change
 */
function animateVoteCount(element, newCount) {
    element.style.transform = 'scale(1.2)';
    element.style.transition = 'all 0.3s ease';
    
    setTimeout(() => {
        element.textContent = formatVoteCount(newCount);
        element.style.transform = 'scale(1)';
    }, 150);
}

/**
 * Format vote count for display
 */
function formatVoteCount(count) {
    if (count >= 1000000) {
        return Math.floor(count / 1000000) + 'M';
    } else if (count >= 1000) {
        return Math.floor(count / 1000) + 'K';
    }
    return count.toString();
}

/**
 * Update vote count in gallery tiles (if visible)
 */
function updateGalleryTileVoteCount(styleId, newCount) {
    const galleryTiles = document.querySelectorAll('.style-tile');
    galleryTiles.forEach(tile => {
        try {
            const styleData = JSON.parse(tile.dataset.style);
            if (styleData.id == styleId) {
                let voteDisplay = tile.querySelector('.vote-display');
                
                if (newCount > 0) {
                    if (!voteDisplay) {
                        // Create vote display if it doesn't exist
                        voteDisplay = document.createElement('div');
                        voteDisplay.className = 'vote-display';
                        voteDisplay.innerHTML = '<i class="fas fa-heart"></i><span></span>';
                        tile.appendChild(voteDisplay);
                    }
                    
                    const voteSpan = voteDisplay.querySelector('span');
                    if (voteSpan) {
                        voteSpan.textContent = formatVoteCount(newCount);
                    }
                    voteDisplay.style.display = 'flex';
                } else if (voteDisplay) {
                    // Hide vote display if count is 0
                    voteDisplay.style.display = 'none';
                }
            }
        } catch (e) {
            // Continue if JSON parsing fails
        }
    });
}

/**
 * Load initial vote status when modal opens
 */
async function loadVoteStatus(styleId) {
    const voteContainer = document.querySelector(`[data-style-id="${styleId}"]`);
    if (!voteContainer) return;
    
    try {
        const response = await fetch(`vote_api.php?style_id=${styleId}`);
        const data = await response.json();
        
        if (data.success) {
            const heartBtn = voteContainer.querySelector('.vote-heart-btn');
            const heartIcon = voteContainer.querySelector('.vote-heart-icon');
            const voteCountEl = voteContainer.querySelector('.vote-count');
            
            // Set initial state
            if (data.user_has_voted) {
                heartBtn.classList.add('voted');
                heartIcon.className = 'fa-solid fa-heart vote-heart-icon';
            } else {
                heartBtn.classList.remove('voted');
                heartIcon.className = 'fa-regular fa-heart vote-heart-icon';
            }
            
            voteCountEl.textContent = formatVoteCount(data.vote_count);
            
            // Store current style ID for global access
            currentVoteStyleId = styleId;
            
        } else {
            console.error('Failed to load vote status:', data.error);
        }
    } catch (error) {
        console.error('Failed to load vote status:', error);
    }
}

/**
 * Show vote error message
 */
function showVoteError(message) {
    // Create or update error toast
    let errorToast = document.getElementById('voteErrorToast');
    
    if (!errorToast) {
        errorToast = document.createElement('div');
        errorToast.id = 'voteErrorToast';
        errorToast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #ef4444;
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            z-index: 9999;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 8px;
        `;
        document.body.appendChild(errorToast);
    }
    
    errorToast.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    errorToast.style.display = 'flex';
    
    // Auto hide after 3 seconds
    setTimeout(() => {
        errorToast.style.display = 'none';
    }, 3000);
}

/**
 * Initialize vote system for modal
 * Call this when opening a modal with voting
 */
function initializeVoteSystem(styleId, currentVotes = 0) {
    // Set up the vote container if it doesn't exist
    let voteSystem = document.querySelector(`[data-style-id="${styleId}"]`);
    
    if (voteSystem) {
        // Load current vote status
        loadVoteStatus(styleId);
    }
}

/**
 * Clean up vote system when modal closes
 */
function cleanupVoteSystem() {
    currentVoteStyleId = null;
    
    // Hide any error toasts
    const errorToast = document.getElementById('voteErrorToast');
    if (errorToast) {
        errorToast.style.display = 'none';
    }
}

// Auto-initialize vote systems when DOM loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any vote systems already in the page
    const voteSystems = document.querySelectorAll('.vote-system');
    voteSystems.forEach(system => {
        const styleId = system.dataset.styleId;
        if (styleId) {
            loadVoteStatus(parseInt(styleId));
        }
    });
});

// Export functions for global use
window.toggleVoteHeart = toggleVoteHeart;
window.loadVoteStatus = loadVoteStatus;
window.initializeVoteSystem = initializeVoteSystem;
window.cleanupVoteSystem = cleanupVoteSystem;