<?php
session_start();
require 'config.php';

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once 'visitor_tracking.php';

// Helper functions
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

// Check filters and parameters
$version_filter = $_GET['version'] ?? 'all'; // all, v7, v6.1, niji
$time_filter = $_GET['time'] ?? 'all'; // all, recent
$is_niji = ($version_filter === 'niji');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$per_page = 48; // Same as main gallery
$offset = ($page - 1) * $per_page;

// Select appropriate table based on filter
$table_name = $is_niji ? 'niji_images' : 'images';

// Build base WHERE clause
$where_conditions = ['votes > 0']; // Only show styles with votes
$params = [];

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
$where_sql = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total number of images with votes
$count_sql = "SELECT COUNT(*) AS total FROM " . $table_name . " " . $where_sql;
$result = $conn->query($count_sql);
$row = $result->fetch_assoc();
$total_images = $row['total'];

// Calculate pagination
$total_pages = max(1, ceil($total_images / $per_page));
$page = min($page, $total_pages);

// Main query to get images ordered by vote count
$sql = "SELECT id, filename, uploaded_at, primary_color, color_palette, votes" .
       (!$is_niji ? ", style_version" : "") .
       " FROM " . $table_name . " " .
       $where_sql .
       " ORDER BY votes DESC, uploaded_at DESC " .
       "LIMIT $per_page OFFSET $offset";

$result = $conn->query($sql);
$images = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
}

// Build pagination URL base
$base_url = "popular.php?";
$url_params = [];
if ($version_filter !== 'all') $url_params[] = "version=" . urlencode($version_filter);
if ($time_filter !== 'all') $url_params[] = "time=" . urlencode($time_filter);
$base_url .= implode('&', $url_params);
$base_url .= !empty($url_params) ? '&' : '';

// Initialize voted styles session
if (!isset($_SESSION['voted_styles'])) {
    $_SESSION['voted_styles'] = [];
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Popular Styles - StyleForger.com</title>
    <meta name="description" content="Discover the most voted AI art styles on StyleForger.com. Browse popular Midjourney style reference codes voted by the community.">
    
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
    <!-- Header - Same as main site -->
    <div class="header">
        <div class="header-content">
            <div class="header-top">
                <div class="header-left">
                    <div class="header-brand">
                        <h1><a href="/">StyleForger</a></h1>
                    </div>
                    
                    <nav class="nav-links">
                        <a href="/" class="nav-link">Browse</a>
                        <a href="curated_styles.php" class="nav-link">Curated</a>
                        <a href="popular.php" class="nav-link active">Popular</a>
                    
                    </nav>
                </div>
                
                <div class="header-right">
                    <div class="stats">
                        <i class="fas fa-heart"></i>
                        <span><?= number_format($total_images) ?></span> voted styles
                    </div>
                    
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i id="themeIcon" class="fas fa-moon"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="section-header">
            <div class="section-title">
                <h2 class="heading-lg">
                    <i class="fas fa-heart" style="color: #ff4757;"></i>
                    Most Voted Styles
                </h2>
                <div class="count"><?= number_format($total_images) ?> styles</div>
            </div>
        </div>

        <?php if (empty($images)): ?>
            <div class="empty-state">
                <i class="fas fa-heart"></i>
                <h3>No voted styles yet</h3>
                <p>Be the first to vote on your favorite styles! Browse the gallery and click the heart icon in the style modal to cast your vote.</p>
            </div>
        <?php else: ?>
            <!-- Gallery Grid -->
            <div class="gallery-grid">
                <?php foreach ($images as $image): ?>
                    <?php
                        $colors = [];
                        if ($image['primary_color']) {
                            $colors[] = $image['primary_color'];
                        }
                        if ($image['color_palette']) {
                            try {
                                $palette = json_decode($image['color_palette'], true);
                                if (is_array($palette)) {
                                    $colors = array_merge($colors, array_slice($palette, 0, 4));
                                }
                            } catch (Exception $e) {
                                // Continue without palette
                            }
                        }
                        $colors = array_slice($colors, 0, 5); // Max 5 colors
                        
                        $style_number = str_replace('.webp', '', $image['filename']);
                        $image_path = $is_niji ? '/niji6/' : '/watermarked/';
                        
                        // Determine version badge
                        $version_class = '';
                        $version_text = '';
                        if ($is_niji) {
                            $version_class = 'niji';
                            $version_text = 'Niji';
                        } else {
                            $version = $image['style_version'] ?? 'v6.1';
                            $is_v7 = ($version === 'v7.0' || $version === 'v7');
                            $version_class = $is_v7 ? 'v7' : 'v61';
                            $version_text = $is_v7 ? 'v7.0' : 'v6.1';
                        }
                    ?>
                    <div class="style-tile" 
                         data-style='<?= htmlspecialchars(json_encode($image), ENT_QUOTES, 'UTF-8') ?>'
                         data-colors='<?= htmlspecialchars(json_encode($colors), ENT_QUOTES, 'UTF-8') ?>'>
                        <img src="<?= $image_path . $image['filename'] ?>" 
                             alt="Style <?= $style_number ?>" 
                             loading="lazy">
                        
                        <!-- Version Badge -->
                        <div class="version-badge <?= $version_class ?>">
                            <?= $version_text ?>
                        </div>
                        
                        <!-- Vote Count Display -->
                        <div class="vote-display">
                            <i class="fas fa-heart"></i>
                            <span><?= $image['votes'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
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
                    ?>
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="<?= $base_url ?>page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
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
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Style Modal -->
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
                            <div class="info-label">Vote for this Style</div>
                            <button class="vote-button" id="voteButton" onclick="toggleVote()">
                                <i class="fas fa-heart heart-icon"></i>
                                <span id="voteText">Vote</span>
                                <span id="voteCount">(0)</span>
                            </button>
                        </div>

                        <div class="info-section">
                            <div class="info-label">Compatible Version</div>
                            <div id="styleVersion" style="display: inline-block; padding: 8px 16px; border-radius: 8px; font-weight: 600; color: white;"></div>
                        </div>

                        <div class="info-section">
                            <div class="info-label">Color Palette</div>
                            <div class="color-palette" id="colorPalette"></div>
                        </div>

                        <div class="info-section" id="keywordsSection" style="display: none;">
                            <div class="info-label">Keywords (AI generated)</div>
                            <div class="keywords" id="keywordsList"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('styleModal'));
        let currentStyleId = null;
        
        // Theme Toggle
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const themeIcon = document.getElementById('themeIcon');
            if (newTheme === 'dark') {
                themeIcon.className = 'fas fa-moon';
            } else {
                themeIcon.className = 'fas fa-sun';
            }
        }
        
        function initializeTheme() {
            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
            
            document.documentElement.setAttribute('data-theme', theme);
            
            const themeIcon = document.getElementById('themeIcon');
            if (theme === 'dark') {
                themeIcon.className = 'fas fa-moon';
            } else {
                themeIcon.className = 'fas fa-sun';
            }
        }
        
        // Copy style code
        function copyStyleCode() {
            const styleNumber = document.getElementById('styleNumber').textContent;
            navigator.clipboard.writeText(styleNumber).then(() => {
                // Show success feedback
                const copyIcon = document.querySelector('.copy-icon');
                const originalClass = copyIcon.className;
                copyIcon.className = 'fas fa-check';
                setTimeout(() => {
                    copyIcon.className = originalClass;
                }, 1000);
            });
        }
        
        // Voting functionality
        async function toggleVote() {
            if (!currentStyleId) return;
            
            const voteButton = document.getElementById('voteButton');
            const voteText = document.getElementById('voteText');
            const voteCount = document.getElementById('voteCount');
            const isVoted = voteButton.classList.contains('voted');
            
            try {
                voteButton.style.pointerEvents = 'none';
                
                const response = await fetch('vote_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        style_id: currentStyleId,
                        action: isVoted ? 'unvote' : 'vote'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update button state
                    if (data.user_has_voted) {
                        voteButton.classList.add('voted');
                        voteText.textContent = 'Voted';
                    } else {
                        voteButton.classList.remove('voted');
                        voteText.textContent = 'Vote';
                    }
                    
                    // Update vote count
                    voteCount.textContent = `(${data.new_vote_count})`;
                    
                    // Update gallery tile if visible
                    updateGalleryTileVoteCount(currentStyleId, data.new_vote_count);
                } else {
                    console.error('Vote failed:', data.error);
                }
            } catch (error) {
                console.error('Vote request failed:', error);
            } finally {
                voteButton.style.pointerEvents = 'auto';
            }
        }
        
        // Update vote count in gallery tile
        function updateGalleryTileVoteCount(styleId, newCount) {
            const tiles = document.querySelectorAll('.style-tile');
            tiles.forEach(tile => {
                const styleData = JSON.parse(tile.dataset.style);
                if (styleData.id == styleId) {
                    const voteDisplay = tile.querySelector('.vote-display span');
                    if (voteDisplay) {
                        voteDisplay.textContent = newCount;
                    }
                }
            });
        }
        
        // Load vote status for style
        async function loadVoteStatus(styleId) {
            try {
                const response = await fetch(`vote_api.php?style_id=${styleId}`);
                const data = await response.json();
                
                if (data.success) {
                    const voteButton = document.getElementById('voteButton');
                    const voteText = document.getElementById('voteText');
                    const voteCount = document.getElementById('voteCount');
                    
                    if (data.user_has_voted) {
                        voteButton.classList.add('voted');
                        voteText.textContent = 'Voted';
                    } else {
                        voteButton.classList.remove('voted');
                        voteText.textContent = 'Vote';
                    }
                    
                    voteCount.textContent = `(${data.vote_count})`;
                }
            } catch (error) {
                console.error('Failed to load vote status:', error);
            }
        }
        
        // Handle style tile clicks
        document.querySelectorAll('.style-tile').forEach(tile => {
            tile.addEventListener('click', function() {
                const styleData = JSON.parse(this.dataset.style);
                const colors = JSON.parse(this.dataset.colors);
                
                const styleNumber = styleData.filename.replace('.webp', '');
                currentStyleId = styleData.id;
                
                const isNiji = <?= $is_niji ? 'true' : 'false' ?>;
                const imagePath = isNiji ? '/niji6/' : '/watermarked/';
                
                document.getElementById('modalImage').src = imagePath + styleData.filename;
                document.getElementById('modalImage').alt = 'Style ' + styleNumber;
                document.getElementById('styleNumber').textContent = '--sref ' + styleNumber;
                
                // Display version
                const versionEl = document.getElementById('styleVersion');
                if (isNiji) {
                    versionEl.textContent = 'Niji';
                    versionEl.style.background = 'var(--version-niji)';
                } else {
                    const version = styleData.style_version || 'v6.1';
                    const isV7 = (version === 'v7.0' || version === 'v7');
                    versionEl.textContent = isV7 ? 'v7.0' : 'v6.1';
                    versionEl.style.background = isV7 ? 'var(--version-v7)' : 'var(--version-v61)';
                }
                
                // Color palette
                const colorPalette = document.getElementById('colorPalette');
                colorPalette.innerHTML = '';
                colors.forEach(color => {
                    const colorBox = document.createElement('div');
                    colorBox.className = 'color-box';
                    colorBox.style.backgroundColor = color;
                    colorPalette.appendChild(colorBox);
                });
                
                // Keywords
                const keywordsSection = document.getElementById('keywordsSection');
                const keywordsList = document.getElementById('keywordsList');
                
                if (styleData.ai_tags) {
                    keywordsSection.style.display = 'block';
                    keywordsList.innerHTML = '';
                    try {
                        const keywords = JSON.parse(styleData.ai_tags);
                        if (Array.isArray(keywords)) {
                            keywords.slice(0, 10).forEach(keyword => {
                                const tag = document.createElement('span');
                                tag.className = 'keyword-tag';
                                tag.textContent = keyword;
                                keywordsList.appendChild(tag);
                            });
                        }
                    } catch (e) {
                        keywordsSection.style.display = 'none';
                    }
                } else {
                    keywordsSection.style.display = 'none';
                }
                
                // Load vote status
                loadVoteStatus(styleData.id);
                
                modal.show();
            });
        });
        
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            initializeTheme();
        });
    </script>
</body>
</html>