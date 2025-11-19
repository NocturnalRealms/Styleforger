<?php
session_start();
require 'config.php';

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once 'visitor_tracking.php';

// Helper functions first!
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

function calculateColorDistance($color1, $color2) {
    $rgb1 = hex2rgb($color1);
    $rgb2 = hex2rgb($color2);
    
    if (!$rgb1 || !$rgb2) return 999;
    
    return sqrt(
        pow($rgb1['r'] - $rgb2['r'], 2) +
        pow($rgb1['g'] - $rgb2['g'], 2) +
        pow($rgb1['b'] - $rgb2['b'], 2)
    );
}

// Check filters and search parameters
$search_type = $_GET['search_type'] ?? '';
$search_value = $_GET['search_value'] ?? '';
$version_filter = $_GET['version'] ?? 'all'; // all, v7, v6.1, niji
$time_filter = $_GET['time'] ?? 'all'; // all, recent
$is_search = !empty($search_type) && !empty($search_value);
$is_niji = ($version_filter === 'niji');

// Session-based random seed for consistent pagination
// Reset seed when filters change, but keep it consistent for pagination
$current_filter_key = md5($version_filter . '|' . $time_filter . '|' . ($is_search ? $search_type . ':' . $search_value : 'browse'));
if (!isset($_SESSION['browse_seed']) || !isset($_SESSION['filter_key']) || $_SESSION['filter_key'] !== $current_filter_key || isset($_GET['reshuffle'])) {
    $_SESSION['browse_seed'] = mt_rand(1, 1000000);
    $_SESSION['filter_key'] = $current_filter_key;
}
$random_seed = $_SESSION['browse_seed'];

// Select appropriate table based on filter
$table_name = $is_niji ? 'niji_images' : 'images';

// Build base WHERE clause
$where_conditions = [];
$params = [];
$param_types = '';

// Version filtering (only apply to regular images, not niji)
if (!$is_niji) {
    if ($version_filter === 'v7') {
        $where_conditions[] = "(style_version = 'v7.0' OR style_version = 'v7')";
    } elseif ($version_filter === 'v6.1') {
        $where_conditions[] = "(style_version = 'v6.1' OR style_version IS NULL OR style_version = '')";
    }
}

// Time filtering (recent = last week)
if ($time_filter === 'recent') {
    $where_conditions[] = "uploaded_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
}

// Build WHERE clause
$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total number of images with current filters
$count_sql = "SELECT COUNT(*) AS total FROM " . $table_name . " " . $where_sql;
$result = $conn->query($count_sql);
$row = $result->fetch_assoc();
$total_images = $row['total'];

// Get version-specific totals for headers when filtering by version
$version_totals = [];
if ($version_filter !== 'all') {
    if ($is_niji) {
        // Total for niji
        $niji_count_sql = "SELECT COUNT(*) AS total FROM niji_images";
        $niji_result = $conn->query($niji_count_sql);
        $version_totals['niji'] = $niji_result->fetch_assoc()['total'];
    } else {
        if ($version_filter === 'v7') {
            // Total for v7
            $v7_count_sql = "SELECT COUNT(*) AS total FROM images WHERE (style_version = 'v7.0' OR style_version = 'v7')";
            $v7_result = $conn->query($v7_count_sql);
            $version_totals['v7'] = $v7_result->fetch_assoc()['total'];
        } elseif ($version_filter === 'v6.1') {
            // Total for v6.1
            $v61_count_sql = "SELECT COUNT(*) AS total FROM images WHERE (style_version = 'v6.1' OR style_version IS NULL OR style_version = '')";
            $v61_result = $conn->query($v61_count_sql);
            $version_totals['v61'] = $v61_result->fetch_assoc()['total'];
        }
    }
}

// Pagination settings
$limit = 60;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$styles = [];
$search_total = 0;

if ($is_search) {
    try {
        if ($search_type === 'color') {
            $search_color = $search_value;
            
            // Build search SQL with filters
            $search_where = [];
            $search_params = [];
            $search_param_types = 'sss';
            
            // Add color search conditions
            $search_where[] = "(primary_color = ? OR color_palette LIKE ?)";
            $search_params[] = $search_color;
            $search_params[] = '%' . $search_color . '%';
            
            // Add version filter (only for regular images)
            if (!$is_niji) {
                if ($version_filter === 'v7') {
                    $search_where[] = "(style_version = 'v7.0' OR style_version = 'v7')";
                } elseif ($version_filter === 'v6.1') {
                    $search_where[] = "(style_version = 'v6.1' OR style_version IS NULL OR style_version = '')";
                }
            }
            
            // Add time filter
            if ($time_filter === 'recent') {
                $search_where[] = "uploaded_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            }
            
            $search_where_sql = 'WHERE ' . implode(' AND ', $search_where);
            
            $sql = "SELECT * FROM " . $table_name . " 
                    $search_where_sql
                    ORDER BY 
                        CASE WHEN primary_color = ? THEN 0 ELSE 1 END,
                        RAND(?)";
            
            $search_params[] = $search_color;
            $search_params[] = $random_seed;
            $search_param_types .= 'i';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($search_param_types, ...$search_params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $exact_matches = [];
            $palette_matches = [];
            $all_styles = [];
            
            if ($is_niji) {
                // For niji images, no version separation needed
                while ($row = $result->fetch_assoc()) {
                    // Parse ai_tags for keywords if available
                    if (!empty($row['ai_tags'])) {
                        $tags = json_decode($row['ai_tags'], true);
                        if (is_array($tags)) {
                            $keywords = array_column($tags, 'name');
                            $row['keywords'] = implode(', ', $keywords);
                        }
                    }
                    
                    if ($row['primary_color'] === $search_color) {
                        $exact_matches[] = $row;
                    } else {
                        $palette_matches[] = $row;
                    }
                    $all_styles[] = $row;
                }
                
                // Combine results: exact matches first, then palette matches
                $all_styles = array_merge($exact_matches, $palette_matches);
            } else {
                // Separate arrays for version organization (regular images)
                $v7_exact = [];
                $v7_palette = [];
                $v61_exact = [];
                $v61_palette = [];
                
                // Process exact and palette matches, separating by version
                while ($row = $result->fetch_assoc()) {
                    // Parse ai_tags for keywords if available
                    if (!empty($row['ai_tags'])) {
                        $tags = json_decode($row['ai_tags'], true);
                        if (is_array($tags)) {
                            $keywords = array_column($tags, 'name');
                            $row['keywords'] = implode(', ', $keywords);
                        }
                    }
                    
                    // Determine version
                    $version = (!empty($row['style_version'])) ? $row['style_version'] : 'v6.1';
                    $is_v7 = ($version === 'v7.0' || $version === 'v7');
                    
                    if ($row['primary_color'] === $search_color) {
                        if ($is_v7) {
                            $v7_exact[] = $row;
                        } else {
                            $v61_exact[] = $row;
                        }
                    } else {
                        if ($is_v7) {
                            $v7_palette[] = $row;
                        } else {
                            $v61_palette[] = $row;
                        }
                    }
                    $all_styles[] = $row;
                }
            }
            
            // If we need more results, find similar colors
            if (count($all_styles) < $limit) {
                $similar_where = [];
                $similar_params = [];
                $similar_param_types = 'ss';
                
                $similar_where[] = "primary_color IS NOT NULL";
                $similar_where[] = "primary_color != ?";
                $similar_where[] = "color_palette NOT LIKE ?";
                $similar_params[] = $search_color;
                $similar_params[] = '%' . $search_color . '%';
                
                // Add version filter for similar search (only for regular images)
                if (!$is_niji) {
                    if ($version_filter === 'v7') {
                        $similar_where[] = "(style_version = 'v7.0' OR style_version = 'v7')";
                    } elseif ($version_filter === 'v6.1') {
                        $similar_where[] = "(style_version = 'v6.1' OR style_version IS NULL OR style_version = '')";
                    }
                }
                
                // Add time filter for similar search
                if ($time_filter === 'recent') {
                    $similar_where[] = "uploaded_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                }
                
                $similar_where_sql = 'WHERE ' . implode(' AND ', $similar_where);
                
                $sql2 = "SELECT * FROM " . $table_name . " 
                        $similar_where_sql
                        ORDER BY RAND(?)";
                
                $similar_params[] = $random_seed;
                $similar_param_types .= 'i';
                
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param($similar_param_types, ...$similar_params);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                if ($is_niji) {
                    // For niji images, no version separation
                    $niji_similar = [];
                    
                    while ($row = $result2->fetch_assoc()) {
                        if (!empty($row['primary_color'])) {
                            $distance = calculateColorDistance($search_color, $row['primary_color']);
                            if ($distance < 80) {
                                // Parse ai_tags for keywords if available
                                if (!empty($row['ai_tags'])) {
                                    $tags = json_decode($row['ai_tags'], true);
                                    if (is_array($tags)) {
                                        $keywords = array_column($tags, 'name');
                                        $row['keywords'] = implode(', ', $keywords);
                                    }
                                }
                                $row['color_distance'] = $distance;
                                $niji_similar[] = $row;
                            }
                        }
                    }
                    
                    // Sort similar colors by distance
                    usort($niji_similar, function($a, $b) {
                        return $a['color_distance'] <=> $b['color_distance'];
                    });
                    
                    // Combine results: exact, palette, similar
                    $all_styles = array_merge($exact_matches, $palette_matches, $niji_similar);
                } else {
                    // For regular images with version separation
                    $v7_similar = [];
                    $v61_similar = [];
                    
                    while ($row = $result2->fetch_assoc()) {
                        if (!empty($row['primary_color'])) {
                            $distance = calculateColorDistance($search_color, $row['primary_color']);
                            if ($distance < 80) {
                                // Parse ai_tags for keywords if available
                                if (!empty($row['ai_tags'])) {
                                    $tags = json_decode($row['ai_tags'], true);
                                    if (is_array($tags)) {
                                        $keywords = array_column($tags, 'name');
                                        $row['keywords'] = implode(', ', $keywords);
                                    }
                                }
                                $row['color_distance'] = $distance;
                                
                                // Determine version
                                $version = (!empty($row['style_version'])) ? $row['style_version'] : 'v6.1';
                                $is_v7 = ($version === 'v7.0' || $version === 'v7');
                                
                                if ($is_v7) {
                                    $v7_similar[] = $row;
                                } else {
                                    $v61_similar[] = $row;
                                }
                            }
                        }
                    }
                    
                    // Sort similar colors by distance within each version
                    usort($v7_similar, function($a, $b) {
                        return $a['color_distance'] <=> $b['color_distance'];
                    });
                    
                    usort($v61_similar, function($a, $b) {
                        return $a['color_distance'] <=> $b['color_distance'];
                    });
                    
                    // Combine results: V7 first (exact, palette, similar), then V6.1 (exact, palette, similar)
                    $all_styles = array_merge(
                        $v7_exact, $v7_palette, $v7_similar,
                        $v61_exact, $v61_palette, $v61_similar
                    );
                }
            }
            
            $search_total = count($all_styles);
            $styles = array_slice($all_styles, $offset, $limit);
            
        } elseif ($search_type === 'keyword') {
            // Search for keywords with filters
            $keyword_where = [];
            $keyword_params = [];
            $keyword_param_types = 's';
            
            $keyword_where[] = "ai_tags IS NOT NULL";
            $keyword_where[] = "JSON_SEARCH(ai_tags, 'one', ?, NULL, '$[*].name') IS NOT NULL";
            $keyword_params[] = $search_value;
            
            // Add version filter (only for regular images)
            if (!$is_niji) {
                if ($version_filter === 'v7') {
                    $keyword_where[] = "(style_version = 'v7.0' OR style_version = 'v7')";
                } elseif ($version_filter === 'v6.1') {
                    $keyword_where[] = "(style_version = 'v6.1' OR style_version IS NULL OR style_version = '')";
                }
            }
            
            // Add time filter
            if ($time_filter === 'recent') {
                $keyword_where[] = "uploaded_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            }
            
            $keyword_where_sql = 'WHERE ' . implode(' AND ', $keyword_where);
            
            $sql = "SELECT * FROM " . $table_name . " 
                    $keyword_where_sql
                    ORDER BY RAND(?)";
            
            $keyword_params[] = $random_seed;
            $keyword_param_types .= 'i';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($keyword_param_types, ...$keyword_params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($is_niji) {
                // For niji images, no version separation
                $niji_keywords = [];
                
                while ($row = $result->fetch_assoc()) {
                    // Parse ai_tags for keywords if available
                    if (!empty($row['ai_tags'])) {
                        $tags = json_decode($row['ai_tags'], true);
                        if (is_array($tags)) {
                            $keywords = array_column($tags, 'name');
                            $row['keywords'] = implode(', ', $keywords);
                        }
                    }
                    
                    $niji_keywords[] = $row;
                }
                
                $all_styles = $niji_keywords;
            } else {
                // For regular images with version separation
                $v7_keywords = [];
                $v61_keywords = [];
                
                while ($row = $result->fetch_assoc()) {
                    // Parse ai_tags for keywords if available
                    if (!empty($row['ai_tags'])) {
                        $tags = json_decode($row['ai_tags'], true);
                        if (is_array($tags)) {
                            $keywords = array_column($tags, 'name');
                            $row['keywords'] = implode(', ', $keywords);
                        }
                    }
                    
                    // Determine version
                    $version = (!empty($row['style_version'])) ? $row['style_version'] : 'v6.1';
                    $is_v7 = ($version === 'v7.0' || $version === 'v7');
                    
                    if ($is_v7) {
                        $v7_keywords[] = $row;
                    } else {
                        $v61_keywords[] = $row;
                    }
                }
                
                // Combine: V7 first, then V6.1
                $all_styles = array_merge($v7_keywords, $v61_keywords);
            }
            
            $search_total = count($all_styles);
            $styles = array_slice($all_styles, $offset, $limit);
        }
    } catch (Exception $e) {
        // If search fails, fall back to regular browsing
        $is_search = false;
        error_log("Search error: " . $e->getMessage());
    }
}

if (!$is_search || empty($styles)) {
    // Regular browsing or fallback with filters
    if ($is_search) {
        $search_total = 0; // No results found
    }
    
    $total_pages = max(1, ceil($total_images / $limit));
    
    $sql = "SELECT * FROM " . $table_name . " " . $where_sql . " ORDER BY RAND(?) LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $random_seed, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$is_search) {
        $styles = [];
        while ($row = $result->fetch_assoc()) {
            // Parse ai_tags for keywords if available
            if (!empty($row['ai_tags'])) {
                $tags = json_decode($row['ai_tags'], true);
                if (is_array($tags)) {
                    $keywords = array_column($tags, 'name');
                    $row['keywords'] = implode(', ', $keywords);
                }
            }
            $styles[] = $row;
        }
    }
}

if ($is_search && $search_total > 0) {
    $total_pages = max(1, ceil($search_total / $limit));
} else if (!$is_search) {
    $total_pages = max(1, ceil($total_images / $limit));
} else {
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php
// Enhanced SEO descriptions
if ($is_search) {
    $page_title = 'Search Results - ' . ucfirst($search_type) . ': ' . htmlspecialchars($search_value) . ' - StyleForger.com';
    $meta_desc = 'AI art styles for "' . htmlspecialchars($search_value) . '". Browse ' . ucfirst($search_type) . ' Midjourney style references and creative prompts on StyleForger.';
} else {
    $page_title = 'AI Art Style Gallery - Midjourney Prompts & References - StyleForger.com';
    $meta_desc = 'Discover thousands of AI art styles and Midjourney prompts. Browse curated collections for creative inspiration and AI image generation.';
}
?>

<title><?= $page_title ?></title>
<meta name="description" content="<?= $meta_desc ?>">
<meta name="keywords" content="AI art, Midjourney styles, AI prompts, style references, generative art, digital art gallery">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://styleforger.com<?= $_SERVER['REQUEST_URI'] ?>">

<!-- Social Media Sharing -->
<meta property="og:title" content="<?= $page_title ?>">
<meta property="og:description" content="<?= $meta_desc ?>">
<meta property="og:image" content="https://styleforger.com/assets/images/og-preview.jpg">
<meta property="og:url" content="https://styleforger.com<?= $_SERVER['REQUEST_URI'] ?>">
<meta property="og:type" content="website">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $page_title ?>">
<meta name="twitter:description" content="<?= $meta_desc ?>">

<!-- Performance & Icons -->
<link rel="icon" href="/favicon.ico">
<meta name="theme-color" content="#9575CD">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://cdnjs.cloudflare.com">

<!-- Google Rich Snippets -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "StyleForger",
    "description": "AI art style gallery and Midjourney prompt collection",
    "url": "https://styleforger.com",
    "potentialAction": {
        "@type": "SearchAction",
        "target": "https://styleforger.com/search?q={search_term_string}",
        "query-input": "required name=search_term_string"
    }
}
</script>

<!-- Your existing CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    
    <style>
        /* Design System Variables */
        :root {
            --primary-blue: #2861ae;
            --primary-blue-dark: #1f4a8a;
            --primary-blue-light: #4080ce;
            --secondary-orange: #ff6b35;
            --version-v7: #2861ae;
            --version-v61: #64748b;
            --version-niji: #9b59b6;
            --success-green: #10b981;
            --warning-yellow: #f59e0b;
            --error-red: #ef4444;
            
            /* Light Theme */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-tertiary: #94a3b8;
            --border-primary: #e2e8f0;
            --border-secondary: #cbd5e1;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --overlay-light: rgba(255, 255, 255, 0.95);
            --overlay-dark: rgba(0, 0, 0, 0.1);
        }

        /* Dark Theme */
        [data-theme="dark"] {
            --bg-primary: #202020;
            --bg-secondary: #2a2a2a;
            --bg-tertiary: #363636;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-tertiary: #8b949e;
            --border-primary: #3d3d3d;
            --border-secondary: #4a4a4a;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.3);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.4), 0 2px 4px -2px rgb(0 0 0 / 0.4);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.5), 0 4px 6px -4px rgb(0 0 0 / 0.5);
            --overlay-light: rgba(32, 32, 32, 0.95);
            --overlay-dark: rgba(0, 0, 0, 0.6);
        }

        /* Base Styles */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', sans-serif;
            line-height: 1.6;
            transition: background-color 0.3s ease, color 0.3s ease;
            padding-top: 70px;
        }

        /* Typography System */
        .heading-xl { font-size: 2.25rem; font-weight: 700; line-height: 1.2; }
        .heading-lg { font-size: 1.875rem; font-weight: 600; line-height: 1.3; }
        .heading-md { font-size: 1.5rem; font-weight: 600; line-height: 1.4; }
        .heading-sm { font-size: 1.25rem; font-weight: 600; line-height: 1.4; }
        .text-lg { font-size: 1.125rem; line-height: 1.6; }
        .text-base { font-size: 1rem; line-height: 1.5; }
        .text-sm { font-size: 0.875rem; line-height: 1.4; }
        .text-xs { font-size: 0.75rem; line-height: 1.3; }

        /* Header */
        .header { 
            background: var(--overlay-light);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-primary);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .header-top {
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            gap: 32px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-primary);
        }
        
        .header-left { 
            display: flex; 
            align-items: center; 
            gap: 32px;
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-brand h1 { 
            font-size: 1.5rem; 
            font-weight: 700; 
            margin: 0;
        }
        
        .header-brand h1 a {
            color: var(--primary-blue);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .header-brand h1 a:hover {
            color: var(--primary-blue-dark);
        }
        
        .nav-links {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .nav-link {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-secondary);
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        
        .nav-link:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            text-decoration: none;
        }
        
        .nav-link.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .stats {
            color: var(--text-secondary);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stats span {
            color: var(--primary-blue);
            font-weight: 600;
        }

        .reshuffle-btn {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
        }

        .reshuffle-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--primary-blue);
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        .footer-btn {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
          
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            width: 200px;
            height: 40px;
        }

        .footer-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--primary-blue);
            text-decoration: none;
            transform: translateY(-1px);
        }

        .theme-toggle {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        /* Search & History */
        .search-container {
            position: relative;
            min-width: 300px;
        }
        
        .search-input-wrapper {
            position: relative;
        }
        
        .search-input-wrapper input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .search-input-wrapper input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(40, 97, 174, 0.1);
            background: var(--bg-primary);
        }
        
        .search-input-wrapper input::placeholder {
            color: var(--text-tertiary);
        }
        
        .search-input-wrapper .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            font-size: 0.875rem;
        }
        
        .style-history {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 240px;
        }
        
        .history-label {
            color: var(--text-tertiary);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .history-thumbnails {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .history-item {
            position: relative;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid var(--border-primary);
        }
        
        .history-item:hover {
            transform: scale(1.1);
            border-color: var(--primary-blue);
            box-shadow: var(--shadow-md);
        }
        
        .history-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .history-empty {
            color: var(--text-tertiary);
            font-size: 0.75rem;
            font-style: italic;
        }
        
        /* Filter Dropdown in Navigation */
        .filter-dropdown {
            position: relative;
        }
        
        .filter-dropdown-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            padding: 12px 20px;
            color: var(--text-primary);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 200px;
            justify-content: space-between;
        }
        
        .filter-dropdown-btn.nav-style {
            background: transparent;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 8px 16px;
            color: var(--text-secondary);
            min-width: auto;
        }
        
        .filter-dropdown-btn.nav-style:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: transparent;
            transform: none;
            box-shadow: none;
        }
        
        .filter-dropdown-btn:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-blue);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .filter-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            display: none;
            overflow: hidden;
            min-width: 280px;
        }
        
        .filter-dropdown-menu.show {
            display: block;
            animation: dropdownSlide 0.2s ease;
        }
        
        @keyframes dropdownSlide {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .filter-section {
            padding: 16px 0;
        }
        
        .filter-section-label {
            color: var(--text-tertiary);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 20px 12px;
            border-bottom: 1px solid var(--border-primary);
            margin-bottom: 8px;
        }
        
        .filter-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
            justify-content: space-between;
        }
        
        .filter-option:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            text-decoration: none;
        }
        
        .filter-option.active {
           /* background: var(--primary-blue); */
            color: white;
        }
        
        .filter-option.active:hover {
            background: var(--primary-blue-dark);
        }
        
        .filter-option .fas {
            font-size: 0.75rem;
            color: currentColor;
        }
        
        .filter-divider {
            height: 1px;
            background: var(--border-primary);
            margin: 0 20px;
        }
        
        .version-badge-small {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.625rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
        }
        
        .version-badge-small.v7 {
            background: var(--version-v7);
        }
        
        .version-badge-small.v61 {
            background: var(--version-v61);
        }
        
        .version-badge-small.niji {
            background: var(--version-niji);
        }

        /* Search Info */
        .search-info { 
            background: var(--bg-secondary); 
            padding: 20px 24px; 
            border-bottom: 1px solid var(--border-primary); 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        
        .search-info .search-details { 
            display: flex; 
            align-items: center; 
            gap: 16px; 
        }
        
        .search-term { 
            background: var(--primary-blue); 
            color: white; 
            padding: 6px 16px; 
            border-radius: 20px; 
            font-size: 0.875rem; 
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link { 
            color: var(--primary-blue); 
            text-decoration: none; 
            padding: 10px 20px; 
            border: 1px solid var(--primary-blue); 
            border-radius: 8px; 
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover { 
            background: var(--primary-blue); 
            color: white; 
        }
        
        /* Grid System */
        .main-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .grid-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 1px; 
            background: var(--border-primary);
            border-radius: 12px;
            overflow: hidden;
            margin: 24px 0;
        }
        
        .style-tile { 
            position: relative; 
            aspect-ratio: 1; 
            overflow: hidden; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            background: var(--bg-secondary);
        }
        
        .style-tile img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            display: block; 
            transition: transform 0.3s ease;
        }
        
        .style-tile:hover img {
            transform: scale(1.05);
        }
        
        /* Version Badges - Consistent Color System */
        .version-badge { 
            position: absolute; 
            top: 12px; 
            right: 12px; 
            color: white; 
            padding: 4px 12px; 
            border-radius: 6px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 2;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .version-badge.v7 { 
            background: rgba(40, 97, 174, 0.9);
        }
        
        .version-badge.v61 { 
            background: rgba(100, 116, 139, 0.9);
        }
        
        .version-badge.niji { 
            background: rgba(155, 89, 182, 0.9);
        }

        /* Version Sections */
        .version-section { 
            margin: 40px 0; 
        }
        
        .version-section-header { 
            background: var(--bg-secondary); 
            padding: 20px 24px; 
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-primary);
            border-bottom: none;
        }
        
        .version-section-header h3 { 
            margin: 0; 
            color: var(--text-primary); 
            font-weight: 600;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .version-section-header .version-badge-large { 
            color: white; 
            padding: 8px 16px; 
            border-radius: 8px; 
            font-size: 0.875rem; 
            font-weight: 600;
        }
        
        .version-section-header .version-badge-large.v7 {
            background: var(--version-v7);
        }
        
        .version-section-header .version-badge-large.v61 {
            background: var(--version-v61);
        }
        
        .version-section-header .version-badge-large.niji {
            background: var(--version-niji);
        }
        
        .version-section-header .count { 
            color: var(--text-secondary); 
            font-size: 0.875rem;
            background: var(--bg-tertiary);
            padding: 6px 12px;
            border-radius: 6px;
        }

        /* Modal - Redesigned with Left/Right Layout */
        .modal-content { 
            background: var(--bg-primary); 
            border: 1px solid var(--border-primary); 
            border-radius: 16px; 
            color: var(--text-primary);
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header { 
            border-bottom: 1px solid var(--border-primary); 
            padding: 20px 24px;
            background: var(--bg-secondary);
            border-radius: 16px 16px 0 0;
        }
        
        .modal-body { 
            padding: 0; 
            display: grid;
            grid-template-columns: 1fr 400px;
            min-height: 500px;
        }
        
        .modal-image-container { 
            background: var(--bg-secondary); 
            display: flex; 
            justify-content: center; 
            align-items: center;
            border-radius: 0 0 0 16px;
            padding: 0;
        }
        
        .modal-image-container img { 
            width: 100%; 
            height: 100%; 
            max-height: 500px;
            object-fit: contain;
            display: block;
            border-radius: 0;
        }
        
        .modal-info { 
            padding: 24px;
            background: var(--bg-primary);
            border-radius: 0 0 16px 0;
            overflow-y: auto;
        }
        
        .info-section { 
            margin-bottom: 24px; 
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-primary);
        }
        
        .info-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .info-label { 
            color: var(--text-secondary); 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            margin-bottom: 12px;
            font-weight: 600;
        }

        .style-code {
            background: var(--bg-secondary);
            padding: 16px 20px;
            border-radius: 12px;
            font-family: 'SF Mono', 'Monaco', 'Cascadia Code', 'Roboto Mono', monospace;
            color: var(--primary-blue);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid var(--border-primary);
        }
        
        .style-code:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-blue);
        }
        
        .copy-icon {
            color: var(--text-tertiary);
            transition: color 0.2s;
        }
        
        .style-code:hover .copy-icon {
            color: var(--primary-blue);
        }
        
        .color-palette {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .color-box {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            border: 2px solid var(--border-primary);
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .color-box:hover {
            transform: scale(1.1);
            border-color: var(--primary-blue);
            box-shadow: var(--shadow-md);
        }
        
        .keywords {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .keyword-tag {
            background: var(--bg-secondary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            color: var(--text-secondary);
            border: 1px solid var(--border-primary);
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .keyword-tag:hover {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Search Suggestions */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            margin-top: 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
            box-shadow: var(--shadow-lg);
        }
        
        .suggestion-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-primary);
            transition: background 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .suggestion-item:last-child {
            border-bottom: none;
        }
        
        .suggestion-item:hover {
            background: var(--bg-secondary);
        }
        
        .suggestion-text {
            color: var(--text-primary);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .suggestion-count {
            color: var(--text-secondary);
            font-size: 0.75rem;
            background: var(--bg-tertiary);
            padding: 2px 8px;
            border-radius: 12px;
        }
        
        .search-loading {
            padding: 16px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Pagination */
        .pagination-container {
            background: var(--bg-secondary);
            padding: 32px 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            border-top: 1px solid var(--border-primary);
            border-radius: 0 0 12px 12px;
        }
        
        .page-btn {
            padding: 10px 16px;
            background: var(--bg-primary);
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            border: 1px solid var(--border-primary);
            font-weight: 500;
            min-width: 44px;
            text-align: center;
        }
        
        .page-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--border-secondary);
        }
        
        .page-btn.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .page-info {
            color: var(--text-secondary);
            margin: 0 16px;
            font-size: 0.875rem;
        }

        /* About Modal - Custom Styling */
        .about-modal-content {
            max-width: 600px;
            margin: 0 auto;
            text-align: left;
        }
        
        .about-modal-content p {
            margin-bottom: 16px;
            line-height: 1.7;
            text-align: justify;
        }
        
        .about-modal-content p:last-of-type {
            margin-bottom: 0;
        }
        
        .about-modal-content strong {
            font-weight: 600;
        }
        
        .about-modal-content hr {
            margin: 24px 0;
            border: none;
            border-top: 1px solid var(--border-primary);
        }

        /* Toast Notification */
        .toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--success-green);
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            display: none;
            z-index: 9999;
            box-shadow: var(--shadow-lg);
            font-weight: 500;
            align-items: center;
            gap: 8px;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }
        
        .no-results i {
            font-size: 3rem;
            color: var(--text-tertiary);
            margin-bottom: 20px;
        }
        
        .no-results h3 {
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        /* Responsive Design */
        @media (max-width: 768px) { 
            body { padding-top: 120px; }
            
            .header-content {
                padding: 0 16px;
            }
            
            .header-top { 
                flex-direction: column; 
                gap: 16px; 
                align-items: flex-start; 
                padding: 12px 0;
            }
            
            .header-left { 
                width: 100%; 
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .nav-links {
                width: 100%;
                justify-content: flex-start;
            }
            
            .filter-dropdown-btn.nav-style {
                width: 100%;
                justify-content: space-between;
            }
            
            .filter-dropdown-menu {
                min-width: 100%;
            }
            
            .header-right {
                width: 100%;
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .search-container {
                width: 100%;
                min-width: 100%;
            }
            
            .style-history {
                width: 100%;
                min-width: 100%;
            }
            
            .grid-container { 
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); 
                margin: 16px 0;
            }
            
            .search-info { 
                flex-direction: column; 
                gap: 12px; 
                align-items: flex-start;
                padding: 16px;
            }
            
            .modal-body {
                grid-template-columns: 1fr;
                min-height: auto;
            }
            
            .modal-info {
                max-height: 400px;
            }
            
            .main-content {
                padding: 0 16px;
            }
        }
        
        @media (max-width: 480px) { 
            .grid-container { 
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); 
            } 
            
            .version-section-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
                padding: 16px;
            }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-top">
                <div class="header-left">
                    <div class="header-brand">
                        <h1><a href="/">StyleForger.com</a></h1>
                        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme">
                            <i class="fas fa-moon" id="themeIcon"></i>
                        </button>
                    </div>
                    <nav class="nav-links">
                        <!-- Filter Dropdown replaces Gallery nav -->
                        <div class="filter-dropdown">
                            <button class="filter-dropdown-btn nav-style" onclick="toggleFilterDropdown()">
                                <i class="fas fa-images" style="margin-right: 8px;"></i>
                                <span id="currentFilterText">
                                    <?php 
                                    $filter_text = 'All Styles';
                                    if ($version_filter !== 'all' || $time_filter !== 'all') {
                                        $parts = [];
                                        if ($version_filter === 'v7') $parts[] = 'v7.0';
                                        if ($version_filter === 'v6.1') $parts[] = 'v6.1'; 
                                        if ($version_filter === 'niji') $parts[] = 'Niji';
                                        if ($time_filter === 'recent') $parts[] = 'Recent';
                                        $filter_text = !empty($parts) ? implode(' + ', $parts) : 'Gallery';
                                    }
                                    echo $filter_text;
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down" id="dropdownIcon" style="margin-left: 8px; font-size: 0.75rem; transition: transform 0.2s ease;"></i>
                            </button>
                            
                            <div class="filter-dropdown-menu" id="filterDropdown">
                                <div class="filter-section">
                                    <div class="filter-section-label">Version</div>
                                    <?php 
                                    $version_params = $_GET;
                                    $version_params['page'] = 1;
                                    unset($version_params['version']);
                                    $base_url = '?' . http_build_query($version_params);
                                    $base_url = $base_url === '?' ? '' : $base_url;
                                    ?>
                                    <a href="<?= $base_url ?>" class="filter-option <?= $version_filter === 'all' ? 'active' : '' ?>">
                                        <span>All Versions</span>
                                        <?= $version_filter === 'all' && $time_filter === 'all' ? '<i class="fas fa-check"></i>' : '' ?>
                                    </a>
                                    <a href="<?= $base_url . ($base_url ? '&' : '?') ?>version=v7" class="filter-option <?= $version_filter === 'v7' ? 'active' : '' ?>">
                                        <span class="version-badge-small v7">v7</span>
                                        <span>Midjourney v7.0</span>
                                        <?= $version_filter === 'v7' ? '<i class="fas fa-check"></i>' : '' ?>
                                    </a>
                                    <a href="<?= $base_url . ($base_url ? '&' : '?') ?>version=v6.1" class="filter-option <?= $version_filter === 'v6.1' ? 'active' : '' ?>">
                                        <span class="version-badge-small v61">v6.1</span>
                                        <span>Midjourney v6.1</span>
                                        <?= $version_filter === 'v6.1' ? '<i class="fas fa-check"></i>' : '' ?>
                                    </a>
                                    <a href="<?= $base_url . ($base_url ? '&' : '?') ?>version=niji" class="filter-option <?= $version_filter === 'niji' ? 'active' : '' ?>">
                                        <span class="version-badge-small niji">Niji</span>
                                        <span>Midjourney Niji</span>
                                        <?= $version_filter === 'niji' ? '<i class="fas fa-check"></i>' : '' ?>
                                    </a>
                                </div>
                                
                                <div class="filter-divider"></div>
                                
                                <div class="filter-section">
                                    <div class="filter-section-label">Time Period</div>
                                    <?php 
                                    $time_params = $_GET;
                                    $time_params['page'] = 1;
                                    unset($time_params['time']);
                                    $time_base_url = '?' . http_build_query($time_params);
                                    $time_base_url = $time_base_url === '?' ? '' : $time_base_url;
                                    ?>
                                    <a href="<?= $time_base_url ?>" class="filter-option <?= $time_filter === 'all' ? 'active' : '' ?>">
                                        <span>All Time</span>
                                        <?= $time_filter === 'all' && $version_filter === 'all' ? '<i class="fas fa-check"></i>' : '' ?>
                                    </a>
                                    <a href="<?= $time_base_url . ($time_base_url ? '&' : '?') ?>time=recent" class="filter-option <?= $time_filter === 'recent' ? 'active' : '' ?>">
                                        <span>Recently Added</span>
                                        <?= $time_filter === 'recent' ? '<i class="fas fa-check"></i>' : '' ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <a href="curated_styles.php" class="nav-link">Curated Styles</a>
                    </nav>
                    <div class="stats">
                        <?php if ($is_search): ?>
                            <?php if ($search_total > 0): ?>
                                Search: <span><?= number_format($search_total) ?></span> matches
                            <?php else: ?>
                                No matches for "<span><?= htmlspecialchars($search_value) ?></span>"
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($version_filter !== 'all' || $time_filter !== 'all'): ?>
                                Showing <span><?= number_format($total_images) ?></span> styles
                            <?php else: ?>
                                <span><?= number_format($total_images) ?></span> styles
                            <?php endif; ?>
                            <?php
                            // Build reshuffle URL preserving current filters
                            $reshuffle_params = $_GET;
                            $reshuffle_params['reshuffle'] = '1';
                            $reshuffle_params['page'] = '1';
                            $reshuffle_url = '?' . http_build_query($reshuffle_params);
                            ?>
                            <a href="<?= $reshuffle_url ?>" class="reshuffle-btn" title="Get new random order">
                                <i class="fas fa-shuffle"></i>
                                Shuffle
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="header-right">
                    <!-- Search Bar -->
                    <div class="search-container">
                        <div class="search-input-wrapper">
                            <input type="text" 
                                   id="keywordSearch" 
                                   placeholder="Search styles by keyword..." 
                                   autocomplete="off">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                        <div id="searchSuggestions" class="search-suggestions">
                            <!-- Suggestions will be populated here -->
                        </div>
                    </div>
                    <!-- Recently Viewed Styles -->
                    <div class="style-history">
                        <div class="history-label"></div>
                        <div class="history-thumbnails" id="historyThumbnails">
                            <div class="history-empty"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <?php if ($is_search): ?>
        <div class="search-info">
            <div class="search-details">
                <span>Searching for <?= htmlspecialchars($search_type) ?>:</span>
                <span class="search-term">
                    <?php if ($search_type === 'color'): ?>
                        <span style="display: inline-block; width: 12px; height: 12px; background: <?= htmlspecialchars($search_value) ?>; border-radius: 50%; margin-right: 6px; vertical-align: middle;"></span>
                    <?php endif; ?>
                    <?= htmlspecialchars($search_value) ?>
                </span>
            </div>
            <a href="/" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Styles
            </a>
        </div>
        <?php endif; ?>

        <?php if (empty($styles) && $is_search): ?>
        <div class="no-results">
            <i class="fas fa-search"></i>
            <h3>No styles found</h3>
            <p>Try a different <?= htmlspecialchars($search_type) ?> or <a href="/" style="color: var(--primary-blue);">browse all styles</a></p>
        </div>
        <?php else: ?>
        
        <?php if ($is_search): ?>
            <?php if ($is_niji): ?>
                <!-- Niji search results -->
                <div class="version-section">
                    <div class="version-section-header">
                        <h3>
                            <span class="version-badge-large niji">Niji</span>
                            Midjourney Niji Styles
                        </h3>
                        <span class="count"><?= $is_search ? count($styles) . ' results' : (isset($version_totals['niji']) ? number_format($version_totals['niji']) . ' total styles' : count($styles) . ' styles') ?></span>
                    </div>
                    <div class="grid-container">
                        <?php foreach ($styles as $style): 
                            $style_number = str_replace('.webp', '', $style['filename']);
                            $colors = [];
                            if (!empty($style['primary_color'])) {
                                $colors[] = $style['primary_color'];
                            }
                            if (!empty($style['color_palette'])) {
                                $palette = json_decode($style['color_palette'], true);
                                if (is_array($palette)) {
                                    $colors = array_merge($colors, array_slice($palette, 0, 5));
                                }
                            }
                        ?>
                        <div class="style-tile" data-style='<?= htmlspecialchars(json_encode($style)) ?>' data-colors='<?= json_encode($colors) ?>'>
                            <img src="/niji6/<?= htmlspecialchars($style['filename']) ?>" alt="Style <?= htmlspecialchars($style_number) ?>" loading="lazy">
                            <div class="version-badge niji">Niji</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Regular search results with version separation -->
                <?php 
                // Separate styles by version for display
                $v7_styles = [];
                $v61_styles = [];
                
                foreach ($styles as $style) {
                    $version = (!empty($style['style_version'])) ? $style['style_version'] : 'v6.1';
                    $is_v7 = ($version === 'v7.0' || $version === 'v7');
                    
                    if ($is_v7) {
                        $v7_styles[] = $style;
                    } else {
                        $v61_styles[] = $style;
                    }
                }
                ?>
            
            <?php if (!empty($v7_styles)): ?>
            <div class="version-section">
                <div class="version-section-header">
                    <h3>
                        <span class="version-badge-large v7">v7.0</span>
                        Midjourney Version 7 Styles
                    </h3>
                    <span class="count"><?= $is_search ? count($v7_styles) . ' results' : (isset($version_totals['v7']) ? number_format($version_totals['v7']) . ' total styles' : count($v7_styles) . ' styles') ?></span>
                </div>
                <div class="grid-container">
                    <?php foreach ($v7_styles as $style): 
                        $style_number = str_replace('.webp', '', $style['filename']);
                        $colors = [];
                        if (!empty($style['primary_color'])) {
                            $colors[] = $style['primary_color'];
                        }
                        if (!empty($style['color_palette'])) {
                            $palette = json_decode($style['color_palette'], true);
                            if (is_array($palette)) {
                                $colors = array_merge($colors, array_slice($palette, 0, 5));
                            }
                        }
                    ?>
                    <div class="style-tile" data-style='<?= htmlspecialchars(json_encode($style)) ?>' data-colors='<?= json_encode($colors) ?>'>
                        <?php $image_path = $is_niji ? '/niji6/' : '/watermarked/'; ?>
                        <img src="<?= $image_path . htmlspecialchars($style['filename']) ?>" alt="Style <?= htmlspecialchars($style_number) ?>" loading="lazy">
                        <div class="version-badge v7">v7</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($v61_styles)): ?>
            <div class="version-section">
                <div class="version-section-header">
                    <h3>
                        <span class="version-badge-large v61">v6.1</span>
                        Midjourney Version 6.1 Styles
                    </h3>
                    <span class="count"><?= $is_search ? count($v61_styles) . ' results' : (isset($version_totals['v61']) ? number_format($version_totals['v61']) . ' total styles' : count($v61_styles) . ' styles') ?></span>
                </div>
                <div class="grid-container">
                    <?php foreach ($v61_styles as $style): 
                        $style_number = str_replace('.webp', '', $style['filename']);
                        $colors = [];
                        if (!empty($style['primary_color'])) {
                            $colors[] = $style['primary_color'];
                        }
                        if (!empty($style['color_palette'])) {
                            $palette = json_decode($style['color_palette'], true);
                            if (is_array($palette)) {
                                $colors = array_merge($colors, array_slice($palette, 0, 5));
                            }
                        }
                    ?>
                    <div class="style-tile" data-style='<?= htmlspecialchars(json_encode($style)) ?>' data-colors='<?= json_encode($colors) ?>'>
                        <?php $image_path = $is_niji ? '/niji6/' : '/watermarked/'; ?>
                        <img src="<?= $image_path . htmlspecialchars($style['filename']) ?>" alt="Style <?= htmlspecialchars($style_number) ?>" loading="lazy">
                        <div class="version-badge v61">v6.1</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?> <!-- End of niji/regular conditional -->
            
        <?php else: ?>
            <!-- Regular browsing - with proper headers when filtered -->
            <?php if ($is_niji): ?>
                <div class="version-section">
                    <div class="version-section-header">
                        <h3>
                            <span class="version-badge-large niji">Niji</span>
                            Midjourney Niji Styles
                        </h3>
                        <span class="count"><?= isset($version_totals['niji']) ? number_format($version_totals['niji']) . ' total styles' : count($styles) . ' styles' ?></span>
                    </div>
                    <div class="grid-container">
                        <?php foreach ($styles as $style): 
                            $style_number = str_replace('.webp', '', $style['filename']);
                            $colors = [];
                            if (!empty($style['primary_color'])) {
                                $colors[] = $style['primary_color'];
                            }
                            if (!empty($style['color_palette'])) {
                                $palette = json_decode($style['color_palette'], true);
                                if (is_array($palette)) {
                                    $colors = array_merge($colors, array_slice($palette, 0, 5));
                                }
                            }
                        ?>
                        <div class="style-tile" data-style='<?= htmlspecialchars(json_encode($style)) ?>' data-colors='<?= json_encode($colors) ?>'>
                            <img src="/niji6/<?= htmlspecialchars($style['filename']) ?>" alt="Style <?= htmlspecialchars($style_number) ?>" loading="lazy">
                            <div class="version-badge niji">Niji</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Regular images - single grid or with headers if filtered -->
                <?php if ($version_filter !== 'all'): ?>
                    <div class="version-section">
                        <div class="version-section-header">
                            <h3>
                                <?php if ($version_filter === 'v7'): ?>
                                    <span class="version-badge-large v7">v7.0</span>
                                    Midjourney Version 7 Styles
                                <?php else: ?>
                                    <span class="version-badge-large v61">v6.1</span>
                                    Midjourney Version 6.1 Styles
                                <?php endif; ?>
                            </h3>
                            <span class="count"><?php 
                                if ($version_filter === 'v7') {
                                    echo isset($version_totals['v7']) ? number_format($version_totals['v7']) . ' total styles' : count($styles) . ' styles';
                                } else {
                                    echo isset($version_totals['v61']) ? number_format($version_totals['v61']) . ' total styles' : count($styles) . ' styles';
                                }
                            ?></span>
                        </div>
                        <div class="grid-container">
                            <?php foreach ($styles as $style): 
                                $style_number = str_replace('.webp', '', $style['filename']);
                                $colors = [];
                                if (!empty($style['primary_color'])) {
                                    $colors[] = $style['primary_color'];
                                }
                                if (!empty($style['color_palette'])) {
                                    $palette = json_decode($style['color_palette'], true);
                                    if (is_array($palette)) {
                                        $colors = array_merge($colors, array_slice($palette, 0, 5));
                                    }
                                }
                                $version = (!empty($style['style_version'])) ? $style['style_version'] : 'v6.1';
                                $isV7 = ($version === 'v7.0' || $version === 'v7');
                                $badge_text = $isV7 ? 'v7' : 'v6.1';
                                $badge_class = $isV7 ? 'v7' : 'v61';
                            ?>
                            <div class="style-tile" data-style='<?= htmlspecialchars(json_encode($style)) ?>' data-colors='<?= json_encode($colors) ?>'>
                                <img src="/watermarked/<?= htmlspecialchars($style['filename']) ?>" alt="Style <?= htmlspecialchars($style_number) ?>" loading="lazy">
                                <div class="version-badge <?= $badge_class ?>"><?= htmlspecialchars($badge_text) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Unfiltered view - simple grid -->
                    <div class="grid-container">
                        <?php foreach ($styles as $style): 
                            $style_number = str_replace('.webp', '', $style['filename']);
                            $colors = [];
                            if (!empty($style['primary_color'])) {
                                $colors[] = $style['primary_color'];
                            }
                            if (!empty($style['color_palette'])) {
                                $palette = json_decode($style['color_palette'], true);
                                if (is_array($palette)) {
                                    $colors = array_merge($colors, array_slice($palette, 0, 5));
                                }
                            }
                        ?>
                        <div class="style-tile" data-style='<?= htmlspecialchars(json_encode($style)) ?>' data-colors='<?= json_encode($colors) ?>'>
                            <?php $image_path = $is_niji ? '/niji6/' : '/watermarked/'; ?>
                            <img src="<?= $image_path . htmlspecialchars($style['filename']) ?>" alt="Style <?= htmlspecialchars($style_number) ?>" loading="lazy">
                            <?php 
                            if ($is_niji) {
                                // Niji images
                                $badge_text = 'Niji';
                                $badge_class = 'niji';
                            } else {
                                // Regular images
                                $version = (!empty($style['style_version'])) ? $style['style_version'] : 'v6.1';
                                $isV7 = ($version === 'v7.0' || $version === 'v7');
                                $badge_text = $isV7 ? 'v7' : 'v6.1';
                                $badge_class = $isV7 ? 'v7' : 'v61';
                            }
                            ?>
                            <div class="version-badge <?= $badge_class ?>"><?= htmlspecialchars($badge_text) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>

        <div class="pagination-container">
            <?php 
            // Build pagination URL preserving all current parameters
            $pagination_params = $_GET;
            unset($pagination_params['page']);
            
            $base_url = !empty($pagination_params) ? '?' . http_build_query($pagination_params) . '&' : '?';
            ?>
            
            <?php if ($page > 1): ?>
                <a href="<?= $base_url ?>page=1" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                <a href="<?= $base_url ?>page=<?= $page - 1 ?>" class="page-btn"><i class="fas fa-angle-left"></i></a>
            <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-angle-double-left"></i></span>
                <span class="page-btn disabled"><i class="fas fa-angle-left"></i></span>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <a href="<?= $base_url ?>page=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>

            <?php if ($page < $total_pages): ?>
                <a href="<?= $base_url ?>page=<?= $page + 1 ?>" class="page-btn"><i class="fas fa-angle-right"></i></a>
                <a href="<?= $base_url ?>page=<?= $total_pages ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
            <?php else: ?>
                <span class="page-btn disabled"><i class="fas fa-angle-right"></i></span>
                <span class="page-btn disabled"><i class="fas fa-angle-double-right"></i></span>
            <?php endif; ?>
        </div>
    </div>
    
    <footer style="border-top: 1px solid var(--border-primary); padding: 20px 0; text-align: center; color: var(--text-secondary);">
        <div style="max-width: 1600px; margin: 0 auto; padding: 0 24px;color:#404040">
            <a href="blog.php?section=about" class="footer-btn">About</a> <a href="blog.php?section=blog" class="footer-btn">Blog</a> <a href="blog.php?section=archive" class="footer-btn">Feature Archive</a><br><hr>
            <small><a style="color:#404040; text-decoration: none;" href="/section/admin/login.php">&copy;</a> 2024 StyleForger.com. All rights reserved.  This site uses privacy-friendly analytics to improve user experience.</small> 
        </div>
    </footer>

    <!-- Style Modal - Redesigned with Left/Right Layout -->
    <div class="modal fade" id="styleModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title heading-md">Style Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="background: var(--bg-secondary); border-radius: 8px; padding: 8px; border: 1px solid var(--border-primary);"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-image-container">
                        <img id="modalImage" src="" alt="">
                    </div>
                    <div class="modal-info">
                        <div class="info-section">
                            <div class="info-label">Style Reference</div>
                            <div class="style-code" id="styleCode" onclick="copyStyleCode()">
                                <span id="styleNumber" style="font-weight: 600;"></span>
                                <i class="fas fa-copy copy-icon"></i>
                            </div>
                        </div>

                        <div class="info-section">
                            <div class="info-label">Compatible Version</div>
                            <div id="styleVersion" style="display: inline-block; padding: 8px 16px; border-radius: 8px; font-weight: 600; color: white;"></div>
                        </div>

                        <div class="info-section">
                            <div class="info-label">Color Palette <small style="color: var(--text-tertiary);">(click to find similar)</small></div>
                            <div class="color-palette" id="colorPalette"></div>
                        </div>

                        <div class="info-section" id="keywordsSection" style="display: none;">
                            <div class="info-label">Keywords (AI generated) <small style="color: var(--text-tertiary);">(click to find similar)</small></div>
                            <div class="keywords" id="keywordsList"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-notification" id="toast">
        <i class="fas fa-check-circle"></i>
    </div>

    <!-- About Modal -->
    <div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title heading-md" id="aboutModalLabel">
                        <i class="fas fa-info-circle" style="color: var(--primary-blue); margin-right: 8px;"></i>About StyleForger
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background: var(--bg-secondary); border-radius: 8px; padding: 8px; border: 1px solid var(--border-primary);"></button>
                </div>
                <div class="modal-body" style="padding: 32px 24px;">
                    <div class="about-modal-content">
                        <p>I built StyleForger.com with a simple idea: creativity should be free and available to everyone. I've always felt that art connects us all, breaking down barriers and bringing people together. This platform is a reflection of that belief.</p>
                        
                        <p>There are no exclusives or hidden hoops to jump through. Instead, you'll find <strong style="color: var(--primary-blue);"><?= number_format($total_images) ?></strong> unique AI-generated --sref codes ready for you to explore because I believe inspiration should be easy to find.</p>
                        
                        <hr>
                        
                        <p style="color: var(--text-secondary); font-size: 0.95rem;">However, as it turns out people do not like to create accounts, free or otherwise. Due to lack of usage I have gotten rid of profile pages, style collections, curations etc. This is a very simplified version of what it once was. Browse only.</p>
                        
                        <p style="color: var(--text-secondary); font-size: 0.95rem;">In time I might bring back the features that do not require personal accounts.</p>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-primary); padding: 20px 24px; background: var(--bg-secondary); border-radius: 0 0 16px 16px;">
                    <small style="color: var(--text-secondary); width: 100%; text-align: center;">
                        Made with <i class="fas fa-heart" style="color: var(--primary-blue);"></i> for the creative community
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('styleModal'));
        
        document.querySelectorAll('.style-tile').forEach(tile => {
            tile.addEventListener('click', function() {
                const styleData = JSON.parse(this.dataset.style);
                const colors = JSON.parse(this.dataset.colors);
                
                const styleNumber = styleData.filename.replace('.webp', '');
                
                // Add to style history
                addToStyleHistory(styleData);
                
                // Determine correct image path based on current context
                const isNiji = <?= $is_niji ? 'true' : 'false' ?>;
                const imagePath = isNiji ? '/niji6/' : '/watermarked/';
                
                document.getElementById('modalImage').src = imagePath + styleData.filename;
                document.getElementById('modalImage').alt = 'Style ' + styleNumber;
                document.getElementById('styleNumber').textContent = '--sref ' + styleNumber;
                
                // Display version using new consistent color system
                updateModalVersionDisplay(styleData, <?= $is_niji ? 'true' : 'false' ?>);
                
                // Color palette with search functionality
                const colorPalette = document.getElementById('colorPalette');
                colorPalette.innerHTML = '';
                
                colors.forEach((color, index) => {
                    const colorBox = document.createElement('div');
                    colorBox.className = 'color-box';
                    colorBox.style.backgroundColor = color;
                    colorBox.setAttribute('data-color', color);
                    colorBox.onclick = (e) => {
                        e.stopPropagation();
                        searchSimilarColors(color);
                    };
                    colorPalette.appendChild(colorBox);
                });
                
                // Keywords with search functionality
                const keywordsSection = document.getElementById('keywordsSection');
                const keywordsList = document.getElementById('keywordsList');
                
                if (styleData.keywords) {
                    keywordsSection.style.display = 'block';
                    keywordsList.innerHTML = '';
                    const keywords = styleData.keywords.split(', ');
                    keywords.forEach(keyword => {
                        const tag = document.createElement('span');
                        tag.className = 'keyword-tag';
                        tag.textContent = keyword;
                        tag.onclick = (e) => {
                            e.stopPropagation();
                            searchSimilarKeywords(keyword);
                        };
                        keywordsList.appendChild(tag);
                    });
                } else {
                    keywordsSection.style.display = 'none';
                }
                
                modal.show();
            });
        });
        
        function copyStyleCode() {
            const styleCode = document.getElementById('styleNumber').textContent;
            copyToClipboard(styleCode);
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('toast');
                toast.style.display = 'flex';
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 2000);
            });
        }
        
        // Preserve current filters when searching
        function getCurrentFilters() {
            const urlParams = new URLSearchParams(window.location.search);
            const filters = {};
            
            if (urlParams.get('version')) {
                filters.version = urlParams.get('version');
            }
            if (urlParams.get('time')) {
                filters.time = urlParams.get('time');
            }
            
            return filters;
        }
        
        function searchSimilarColors(color) {
            // Close modal first
            modal.hide();
            
            // Build URL with current filters preserved
            const filters = getCurrentFilters();
            const params = new URLSearchParams({
                search_type: 'color',
                search_value: color,
                ...filters
            });
            
            // Add slight delay to ensure modal is closed
            setTimeout(() => {
                window.location.href = `/?${params.toString()}`;
            }, 300);
        }
        
        function searchSimilarKeywords(keyword) {
            // Close modal first
            modal.hide();
            
            // Build URL with current filters preserved
            const filters = getCurrentFilters();
            const params = new URLSearchParams({
                search_type: 'keyword',
                search_value: keyword,
                ...filters
            });
            
            // Add slight delay to ensure modal is closed
            setTimeout(() => {
                window.location.href = `/?${params.toString()}`;
            }, 300);
        }
        
        // Style History Management (Enhanced)
        function addToStyleHistory(styleData) {
            const maxHistory = 5; // Reduced for sticky header
            const styleNumber = styleData.filename.replace('.webp', '');
            
            // Get existing history
            let history = getStyleHistory();
            
            // Remove if already exists (to move to front)
            history = history.filter(item => item.number !== styleNumber);
            
            // Add to front with complete style data
            history.unshift({
                number: styleNumber,
                filename: styleData.filename,
                version: styleData.style_version || 'v6.1',
                timestamp: Date.now(),
                styleData: styleData,
                isNiji: <?= $is_niji ? 'true' : 'false' ?>
            });
            
            // Limit to maxHistory items
            history = history.slice(0, maxHistory);
            
            // Save to localStorage
            localStorage.setItem('styleHistory', JSON.stringify(history));
            
            // Update display
            displayStyleHistory();
        }
        
        function getStyleHistory() {
            try {
                const history = localStorage.getItem('styleHistory');
                return history ? JSON.parse(history) : [];
            } catch (e) {
                console.error('Error reading style history:', e);
                return [];
            }
        }
        
        function displayStyleHistory() {
            const container = document.getElementById('historyThumbnails');
            const history = getStyleHistory();
            
            if (history.length === 0) {
                container.innerHTML = '<div class="history-empty"></div>';
                return;
            }
            
            container.innerHTML = '';
            
            history.forEach(item => {
                const historyItem = document.createElement('div');
                historyItem.className = 'history-item';
                historyItem.title = `Style ${item.number} (${item.version}) - Click to view`;
                historyItem.setAttribute('data-style-number', item.number);
                historyItem.setAttribute('data-filename', item.filename);
                
                historyItem.innerHTML = `<img src="${item.isNiji ? '/niji6/' : '/watermarked/'}${item.filename}" alt="Style ${item.number}" loading="lazy">`;
                
                // Add click handler to open modal directly
                historyItem.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // If we have the complete style data, open modal directly
                    if (item.styleData) {
                        openStyleModalFromHistory(item.styleData, item);
                    } else {
                        // Fallback: try to find on current page or search
                        const styleFilename = this.getAttribute('data-filename');
                        const styleTile = document.querySelector(`[data-style*='"filename":"${styleFilename}"']`);
                        
                        if (styleTile) {
                            styleTile.click();
                        } else {
                            const styleNumber = this.getAttribute('data-style-number');
                            window.location.href = `/?search_type=keyword&search_value=${styleNumber}`;
                        }
                    }
                });
                
                container.appendChild(historyItem);
            });
        }
        
        function openStyleModalFromHistory(styleData, historyItem = null) {
            const styleNumber = styleData.filename.replace('.webp', '');
            
            // Determine correct image path based on stored history data
            const isFromNiji = historyItem && historyItem.isNiji;
            const imagePath = isFromNiji ? '/niji6/' : '/watermarked/';
            
            // Populate modal with style data
            document.getElementById('modalImage').src = imagePath + styleData.filename;
            document.getElementById('modalImage').alt = 'Style ' + styleNumber;
            document.getElementById('styleNumber').textContent = '--sref ' + styleNumber;
            
            // Display version using consistent color system
            updateModalVersionDisplay(styleData, historyItem && historyItem.isNiji);
            
            // Color palette with search functionality
            const colorPalette = document.getElementById('colorPalette');
            colorPalette.innerHTML = '';
            
            // Build colors array
            const colors = [];
            if (styleData.primary_color) {
                colors.push(styleData.primary_color);
            }
            if (styleData.color_palette) {
                try {
                    const palette = typeof styleData.color_palette === 'string' 
                        ? JSON.parse(styleData.color_palette) 
                        : styleData.color_palette;
                    if (Array.isArray(palette)) {
                        colors.push(...palette.slice(0, 5));
                    }
                } catch (e) {
                    console.log('Error parsing color palette:', e);
                }
            }
            
            colors.forEach((color, index) => {
                const colorBox = document.createElement('div');
                colorBox.className = 'color-box';
                colorBox.style.backgroundColor = color;
                colorBox.setAttribute('data-color', color);
                colorBox.onclick = (e) => {
                    e.stopPropagation();
                    searchSimilarColors(color);
                };
                colorPalette.appendChild(colorBox);
            });
            
            // Keywords with search functionality
            const keywordsSection = document.getElementById('keywordsSection');
            const keywordsList = document.getElementById('keywordsList');
            
            if (styleData.keywords) {
                keywordsSection.style.display = 'block';
                keywordsList.innerHTML = '';
                const keywords = styleData.keywords.split(', ');
                keywords.forEach(keyword => {
                    const tag = document.createElement('span');
                    tag.className = 'keyword-tag';
                    tag.textContent = keyword;
                    tag.onclick = (e) => {
                        e.stopPropagation();
                        searchSimilarKeywords(keyword);
                    };
                    keywordsList.appendChild(tag);
                });
            } else {
                keywordsSection.style.display = 'none';
            }
            
            // Show the modal
            modal.show();
        }
        
        // Initialize style history display on page load
        document.addEventListener('DOMContentLoaded', function() {
            displayStyleHistory();
            initializeSearch();
        });
        
        // Search functionality
        function initializeSearch() {
            const searchInput = document.getElementById('keywordSearch');
            const suggestionsContainer = document.getElementById('searchSuggestions');
            let searchTimeout;
            
            if (!searchInput || !suggestionsContainer) return;
            
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    suggestionsContainer.style.display = 'none';
                    return;
                }
                
                // Show loading state
                suggestionsContainer.innerHTML = '<div class="search-loading">Searching keywords...</div>';
                suggestionsContainer.style.display = 'block';
                
                searchTimeout = setTimeout(() => {
                    fetchKeywordSuggestions(query);
                }, 300);
            });
            
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch(this.value.trim());
                }
            });
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-container')) {
                    suggestionsContainer.style.display = 'none';
                }
            });
        }
        
        function fetchKeywordSuggestions(query) {
            const suggestionsContainer = document.getElementById('searchSuggestions');
            
            fetch(`keyword_suggestions_api.php?q=${encodeURIComponent(query)}&limit=5`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.suggestions.length > 0) {
                        displaySuggestions(data.suggestions);
                    } else {
                        suggestionsContainer.innerHTML = '<div class="search-loading">No matching keywords found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching suggestions:', error);
                    suggestionsContainer.innerHTML = '<div class="search-loading">Error loading suggestions</div>';
                });
        }
        
        function displaySuggestions(suggestions) {
            const suggestionsContainer = document.getElementById('searchSuggestions');
            
            suggestionsContainer.innerHTML = suggestions.map(suggestion => `
                <div class="suggestion-item" onclick="selectSuggestion('${suggestion.keyword}')">
                    <span class="suggestion-text">${suggestion.keyword}</span>
                    <span class="suggestion-count">${suggestion.count} styles</span>
                </div>
            `).join('');
            
            suggestionsContainer.style.display = 'block';
        }
        
        function selectSuggestion(keyword) {
            const searchInput = document.getElementById('keywordSearch');
            const suggestionsContainer = document.getElementById('searchSuggestions');
            
            searchInput.value = keyword;
            suggestionsContainer.style.display = 'none';
            
            performSearch(keyword);
        }
        
        function performSearch(keyword) {
            if (!keyword.trim()) return;
            
            // Build URL with current filters preserved (excluding niji since it has no keywords)
            const filters = getCurrentFilters();
            
            // Remove niji from search since niji doesn't have keywords
            if (filters.version === 'niji') {
                delete filters.version;
            }
            
            const params = new URLSearchParams({
                search_type: 'keyword',
                search_value: keyword.trim(),
                ...filters
            });
            
            window.location.href = `/?${params.toString()}`;
        }
        
        // Filter Dropdown Functionality
        function toggleFilterDropdown() {
            const dropdown = document.getElementById('filterDropdown');
            const icon = document.getElementById('dropdownIcon');
            
            dropdown.classList.toggle('show');
            
            if (dropdown.classList.contains('show')) {
                icon.style.transform = 'rotate(180deg)';
            } else {
                icon.style.transform = 'rotate(0deg)';
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('filterDropdown');
            const button = event.target.closest('.filter-dropdown-btn');
            
            if (!button && !event.target.closest('.filter-dropdown-menu')) {
                dropdown.classList.remove('show');
                document.getElementById('dropdownIcon').style.transform = 'rotate(0deg)';
            }
        });
        
        // Theme Toggle Functionality
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update icon
            const themeIcon = document.getElementById('themeIcon');
            if (newTheme === 'dark') {
                themeIcon.className = 'fas fa-moon';
            } else {
                themeIcon.className = 'fas fa-sun';
            }
        }
        
        // Initialize theme on page load
        function initializeTheme() {
            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
            
            document.documentElement.setAttribute('data-theme', theme);
            
            // Update icon
            const themeIcon = document.getElementById('themeIcon');
            if (theme === 'dark') {
                themeIcon.className = 'fas fa-moon';
            } else {
                themeIcon.className = 'fas fa-sun';
            }
        }
        
        // Update modal logic to use consistent version colors
        function updateModalVersionDisplay(styleData, isFromNiji = false) {
            const versionEl = document.getElementById('styleVersion');
            if (versionEl) {
                if (isFromNiji) {
                    versionEl.textContent = 'Niji';
                    versionEl.style.background = 'var(--version-niji)';
                } else {
                    const version = styleData.style_version || 'v6.1';
                    const isV7 = (version === 'v7.0' || version === 'v7');
                    versionEl.textContent = isV7 ? 'v7.0' : 'v6.1';
                    versionEl.style.background = isV7 ? 'var(--version-v7)' : 'var(--version-v61)';
                }
                versionEl.parentElement.style.display = 'block';
            }
        }
        
        // Handle back button in browser
        window.addEventListener('popstate', function(event) {
            // Reload page to handle state properly
            location.reload();
        });
        
        // Initialize theme when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeTheme();
            displayStyleHistory();
            initializeSearch();
        });
    </script>
</body>
</html>