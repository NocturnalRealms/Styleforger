<?php
/**
 * Keyword Suggestions API for Search Autocomplete
 * Returns matching keywords as user types - Now using your exact database config!
 */

session_start();

// Enhanced error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection using your exact configuration
$conn = null;
$db_error = null;

try {
    // First, try to include config.php if it exists
    if (file_exists('config.php')) {
        require 'config.php';
    } elseif (file_exists('../config.php')) {
        require '../config.php';
    } else {
        // Use your exact database configuration from the uploaded config.php
        $servername = "localhost";
        $username = "u849566657_zambicho";
        $password = "Val3r1a2024";
        $dbname = "u849566657_midstyles";

        // Create connection exactly like your config.php
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
    }
    
    // Ensure we have a connection
    if (!$conn || $conn->connect_error) {
        throw new Exception("No database connection available");
    }
    
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

// Get search query
$query = trim($_GET['q'] ?? '');
$limit = min(20, max(5, intval($_GET['limit'] ?? 10)));

try {
    // If database connection failed, return helpful error
    if ($db_error || !$conn) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed',
            'debug' => $db_error ?? 'No database connection available',
            'suggestions' => [],
            'help' => 'Please check your database configuration'
        ]);
        exit;
    }
    
    if (empty($query) || strlen($query) < 2) {
        echo json_encode([
            'status' => 'success',
            'suggestions' => []
        ]);
        exit;
    }
    
    $suggestions = [];
    $search_pattern = '%' . mysqli_real_escape_string($conn, $query) . '%';
    
    // Method 1: Try master_tags table if it exists
    $tables_result = $conn->query("SHOW TABLES LIKE 'master_tags'");
    if ($tables_result && $tables_result->num_rows > 0) {
        $sql = "SELECT DISTINCT tag_name as keyword, usage_count
                FROM master_tags 
                WHERE tag_name LIKE ? 
                AND is_active = 1
                ORDER BY usage_count DESC, tag_name ASC
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $search_pattern, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = [
                    'keyword' => $row['keyword'],
                    'count' => $row['usage_count'] ?? 0,
                    'source' => 'master'
                ];
            }
            $stmt->close();
        }
    }
    
    // Method 2: Try style_tags table if we need more suggestions
    if (count($suggestions) < $limit) {
        $remaining_limit = $limit - count($suggestions);
        
        // Check if style_tags table exists
        $tables_result = $conn->query("SHOW TABLES LIKE 'style_tags'");
        if ($tables_result && $tables_result->num_rows > 0) {
            // Get existing keywords to avoid duplicates
            $existing_keywords = array_column($suggestions, 'keyword');
            $exclude_condition = !empty($existing_keywords) 
                ? "AND st.tag NOT IN ('" . implode("','", array_map([$conn, 'real_escape_string'], $existing_keywords)) . "')"
                : "";
            
            // Count using the SAME logic as main search - join with images table for filtering
            $sql2 = "SELECT DISTINCT st.tag as keyword, COUNT(*) as usage_count
                    FROM style_tags st
                    INNER JOIN images img ON FIND_IN_SET(st.tag, REPLACE(REPLACE(REPLACE(JSON_EXTRACT(img.ai_tags, '$[*].name'), '[\"', ''), '\"]', ''), '\",\"', ','))
                    WHERE st.tag LIKE ? 
                    $exclude_condition
                    GROUP BY st.tag
                    ORDER BY usage_count DESC, st.tag ASC
                    LIMIT ?";
            
            $stmt2 = $conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param('si', $search_pattern, $remaining_limit);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                while ($row = $result2->fetch_assoc()) {
                    $suggestions[] = [
                        'keyword' => $row['keyword'],
                        'count' => $row['usage_count'] ?? 0,
                        'source' => 'tags'
                    ];
                }
                $stmt2->close();
            }
        }
    }
    
    // Method 3: Use EXACT same logic as main search to count keywords in ai_tags JSON
    if (count($suggestions) < 5) {
        $remaining_limit = max(5, $limit - count($suggestions));
        $existing_keywords = array_column($suggestions, 'keyword');
        
        // Check if images table exists and has ai_tags column
        $tables_result = $conn->query("SHOW TABLES LIKE 'images'");
        if ($tables_result && $tables_result->num_rows > 0) {
            $columns_result = $conn->query("SHOW COLUMNS FROM images LIKE 'ai_tags'");
            if ($columns_result && $columns_result->num_rows > 0) {
                
                // Get a list of potential keywords first by scanning a sample
                $sample_sql = "SELECT DISTINCT ai_tags
                              FROM images 
                              WHERE ai_tags IS NOT NULL 
                              AND ai_tags LIKE ?
                              LIMIT 500"; // Get a reasonable sample
                
                $json_search_pattern = '%"name":"' . mysqli_real_escape_string($conn, $query) . '%';
                $sample_stmt = $conn->prepare($sample_sql);
                if ($sample_stmt) {
                    $sample_stmt->bind_param('s', $json_search_pattern);
                    $sample_stmt->execute();
                    $sample_result = $sample_stmt->get_result();
                    
                    $potential_keywords = [];
                    while ($row = $sample_result->fetch_assoc()) {
                        $tags = json_decode($row['ai_tags'], true);
                        if (is_array($tags)) {
                            foreach ($tags as $tag) {
                                if (isset($tag['name']) && 
                                    stripos($tag['name'], $query) !== false &&
                                    !in_array($tag['name'], $existing_keywords)) {
                                    $potential_keywords[$tag['name']] = true;
                                }
                            }
                        }
                    }
                    $sample_stmt->close();
                    
                    // Now count each potential keyword using EXACT same logic as main search
                    foreach ($potential_keywords as $keyword => $dummy) {
                        if (count($suggestions) >= $limit) break;
                        
                        // Use the EXACT same JSON_SEARCH query as main search
                        $count_sql = "SELECT COUNT(*) as total 
                                     FROM images 
                                     WHERE ai_tags IS NOT NULL 
                                     AND JSON_SEARCH(ai_tags, 'one', ?, NULL, '$[*].name') IS NOT NULL";
                        
                        $count_stmt = $conn->prepare($count_sql);
                        if ($count_stmt) {
                            $count_stmt->bind_param('s', $keyword);
                            $count_stmt->execute();
                            $count_result = $count_stmt->get_result();
                            $count = $count_result->fetch_assoc()['total'];
                            $count_stmt->close();
                            
                            if ($count > 0) {
                                $suggestions[] = [
                                    'keyword' => $keyword,
                                    'count' => $count,
                                    'source' => 'ai_exact'
                                ];
                            }
                        }
                    }
                    
                    // Sort by count descending
                    usort($suggestions, function($a, $b) {
                        return $b['count'] - $a['count'];
                    });
                }
            }
        }
    }
    
    $conn->close();
    
    echo json_encode([
        'status' => 'success',
        'suggestions' => $suggestions,
        'query' => $query,
        'total_found' => count($suggestions),
        'connection' => 'Database connected successfully!'
    ]);
    
} catch (Exception $e) {
    if ($conn) {
        $conn->close();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch suggestions',
        'debug' => $e->getMessage(),
        'query' => $query ?? 'undefined',
        'suggestions' => []
    ]);
}
?>
