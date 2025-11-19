<?php
/**
 * Search API for finding similar colors and keywords
 * Handles both color similarity and keyword matching
 */

session_start();
require 'config.php';

header('Content-Type: application/json');

// Get search parameters
$search_type = $_GET['type'] ?? '';
$search_value = $_GET['value'] ?? '';
$version_filter = $_GET['version'] ?? 'all';
$limit = min(60, max(1, intval($_GET['limit'] ?? 60)));
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Session-based random seed for consistent API results (same as main gallery)
$current_filter_key = md5($version_filter . '|api|' . $search_type . ':' . $search_value);
if (!isset($_SESSION['api_seed']) || !isset($_SESSION['api_filter_key']) || $_SESSION['api_filter_key'] !== $current_filter_key) {
    $_SESSION['api_seed'] = mt_rand(1, 1000000);
    $_SESSION['api_filter_key'] = $current_filter_key;
}
$random_seed = $_SESSION['api_seed'];

// Select appropriate table based on version filter
$is_niji = ($version_filter === 'niji');
$table_name = $is_niji ? 'niji_images' : 'images';

try {
    if ($search_type === 'color') {
        // Search for similar colors with intelligent matching
        $search_color = $search_value;
        
        // Get all images with colors for similarity calculation
        $sql = "SELECT *, 
                CASE 
                    WHEN primary_color = ? THEN 0
                    WHEN color_palette LIKE ? THEN 1
                    ELSE 2
                END as match_type
                FROM " . $table_name . " 
                WHERE primary_color IS NOT NULL OR color_palette IS NOT NULL
                ORDER BY match_type, RAND(?)
                LIMIT ?";
        
        $search_pattern = '%' . $search_color . '%';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssii', $search_color, $search_pattern, $random_seed, $limit * 3); // Get more results for filtering
        
    } elseif ($search_type === 'keyword') {
        // Search for similar keywords using JSON functions
        $search_keyword = $search_value;
        
        $sql = "SELECT *
                FROM " . $table_name . " 
                WHERE ai_tags IS NOT NULL 
                AND JSON_SEARCH(ai_tags, 'one', ?, NULL, '$[*].name') IS NOT NULL
                ORDER BY RAND(?)
                LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('siii', $search_keyword, $random_seed, $limit, $offset);
        
    } else {
        throw new Exception('Invalid search type');
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process color similarity for flexible matching
    if ($search_type === 'color') {
        $exact_matches = [];
        $palette_matches = [];
        $similar_colors = [];
        $target_rgb = hex2rgb($search_color);
        
        while ($row = $result->fetch_assoc()) {
            // Parse ai_tags for keywords
            if (!empty($row['ai_tags'])) {
                $tags = json_decode($row['ai_tags'], true);
                if (is_array($tags)) {
                    $keywords = array_column($tags, 'name');
                    $row['keywords'] = implode(', ', array_slice($keywords, 0, 5));
                }
            }
            
            if ($row['match_type'] == 0) {
                // Exact primary color match
                $exact_matches[] = $row;
            } elseif ($row['match_type'] == 1) {
                // Color appears in palette
                $palette_matches[] = $row;
            } else {
                // Check for color similarity
                $is_similar = false;
                
                // Check primary color similarity
                if (!empty($row['primary_color'])) {
                    $distance = calculateColorDistance($search_color, $row['primary_color']);
                    if ($distance < 80) { // Similarity threshold - adjust as needed
                        $row['color_distance'] = $distance;
                        $is_similar = true;
                    }
                }
                
                // Check palette colors for similarity
                if (!$is_similar && !empty($row['color_palette'])) {
                    $palette = json_decode($row['color_palette'], true);
                    if (is_array($palette)) {
                        foreach ($palette as $palette_color) {
                            $distance = calculateColorDistance($search_color, $palette_color);
                            if ($distance < 80) {
                                $row['color_distance'] = $distance;
                                $is_similar = true;
                                break;
                            }
                        }
                    }
                }
                
                if ($is_similar) {
                    $similar_colors[] = $row;
                }
            }
        }
        
        // Sort similar colors by distance
        usort($similar_colors, function($a, $b) {
            return ($a['color_distance'] ?? 999) <=> ($b['color_distance'] ?? 999);
        });
        
        // Combine results: exact matches first, then palette matches, then similar
        $all_results = array_merge($exact_matches, $palette_matches, $similar_colors);
        
        // Apply pagination to final results
        $styles = array_slice($all_results, $offset, $limit);
        $search_total = count($all_results);
        
    } else {
        // For keyword search, process normally
        $styles = [];
        while ($row = $result->fetch_assoc()) {
            // Parse ai_tags for keywords if available
            if (!empty($row['ai_tags'])) {
                $tags = json_decode($row['ai_tags'], true);
                if (is_array($tags)) {
                    $keywords = array_column($tags, 'name');
                    $row['keywords'] = implode(', ', array_slice($keywords, 0, 5));
                }
            }
            $styles[] = $row;
        }
        
        // Get total count for keyword search
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM " . $table_name . " 
                                     WHERE ai_tags IS NOT NULL 
                                     AND JSON_SEARCH(ai_tags, 'one', ?, NULL, '$[*].name') IS NOT NULL");
        $count_stmt->bind_param('s', $search_keyword);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $search_total = $count_result->fetch_assoc()['total'];
    }
    
    $total_pages = max(1, ceil($search_total / $limit));
    
    // Fetch results are already processed above based on search type
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'styles' => $styles,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_count' => $search_total,
                'limit' => $limit
            ],
            'search' => [
                'type' => $search_type,
                'value' => $search_value
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate color distance using RGB color space
 * Returns a value between 0 (identical) and ~441 (maximum difference)
 */
function calculateColorDistance($color1, $color2) {
    $rgb1 = hex2rgb($color1);
    $rgb2 = hex2rgb($color2);
    
    if (!$rgb1 || !$rgb2) return 999; // Invalid colors get high distance
    
    // Calculate Euclidean distance in RGB space
    return sqrt(
        pow($rgb1['r'] - $rgb2['r'], 2) +
        pow($rgb1['g'] - $rgb2['g'], 2) +
        pow($rgb1['b'] - $rgb2['b'], 2)
    );
}

/**
 * Convert hex color to RGB array (for potential future use)
 */
function hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) .
               str_repeat(substr($hex, 1, 1), 2) .
               str_repeat(substr($hex, 2, 1), 2);
    }
    
    if (strlen($hex) != 6) {
        return false;
    }
    
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}
?>