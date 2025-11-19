<?php
session_start();
require 'config.php';
include_once 'visitor_tracking.php';

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get section parameter for navigation
$section = $_GET['section'] ?? 'about';
$valid_sections = ['about', 'blog', 'archive'];
if (!in_array($section, $valid_sections)) {
    $section = 'about';
}

// Get total images for stats
$result = $conn->query("SELECT COUNT(*) AS total FROM images");
$row = $result->fetch_assoc();
$total_images = $row['total'];

// Get Niji count
$nijiResult = $conn->query("SELECT COUNT(*) AS total FROM niji_images");
$nijiRow = $nijiResult->fetch_assoc();
$total_niji = $nijiRow['total'];

// Get v7 count
$v7Result = $conn->query("SELECT COUNT(*) AS v7_total FROM images WHERE style_version = 'v7.0'");
$v7Row = $v7Result->fetch_assoc();
$v7_total = $v7Row['v7_total'];

// Get archive parameter for about images
$archive_id = isset($_GET['archive']) ? (int)$_GET['archive'] : 0;

// Get blog parameters
$category_filter = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$blog_search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Handle About Images
if ($archive_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM about_images WHERE id = ?");
    $stmt->bind_param("i", $archive_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $image_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$image_data) {
        header("Location: blog.php");
        exit();
    }
} else {
    $result = $conn->query("SELECT * FROM about_images ORDER BY upload_date DESC LIMIT 1");
    $image_data = $result->fetch_assoc();
}

// Get style numbers for the current about image and fetch thumbnails
$style_numbers = [];
$style_thumbnails = [];
if ($image_data && !empty($image_data['styles_used'])) {
    $style_numbers = array_map('trim', explode(',', $image_data['styles_used']));
    $style_numbers = array_filter($style_numbers, function($num) {
        return !empty($num) && is_numeric($num);
    });
    
    if (!empty($style_numbers)) {
        $placeholders = implode(',', array_fill(0, count($style_numbers), '?'));
        $thumbnail_sql = "SELECT filename, style_number FROM images WHERE style_number IN ($placeholders) LIMIT 20";
        
        $stmt = $conn->prepare($thumbnail_sql);
        if ($stmt) {
            $types = str_repeat('s', count($style_numbers));
            $stmt->bind_param($types, ...$style_numbers);
            $stmt->execute();
            $thumbnail_result = $stmt->get_result();
            
            while ($thumb = $thumbnail_result->fetch_assoc()) {
                $style_thumbnails[$thumb['style_number']] = $thumb['filename'];
            }
            $stmt->close();
        }
    }
}

// Handle Blog Posts - Always load by default
$blog_posts = [];
$blog_sql = "SELECT * FROM blog WHERE 1=1";
$params = [];
$types = '';

if (!empty($category_filter)) {
    $blog_sql .= " AND category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if (!empty($blog_search)) {
    $blog_sql .= " AND (headline LIKE ? OR post_content LIKE ? OR category LIKE ?)";
    $search_term = '%' . $blog_search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$blog_sql .= " ORDER BY post_date DESC LIMIT 10";

if (!empty($params)) {
    $stmt = $conn->prepare($blog_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $blog_result = $stmt->get_result();
} else {
    $blog_result = $conn->query($blog_sql);
}

while ($row = $blog_result->fetch_assoc()) {
    $blog_posts[] = $row;
}

// Get categories for filter
$sql_categories = "SELECT DISTINCT category, COUNT(*) as count FROM blog WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY category";
$result_categories = $conn->query($sql_categories);
$categories = [];
if ($result_categories) {
    while ($cat = $result_categories->fetch_assoc()) {
        $categories[$cat['category']] = $cat['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About, Archive & Updates - StyleForger.com</title>
    
    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Use your existing design system */
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
            padding-top: 80px;
        }

        /* Simple Header */
        .simple-header {
            background: var(--overlay-light);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-primary);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 16px 24px;
        }

        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-brand h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .header-brand h1 a {
            color: var(--primary-blue);
            text-decoration: none;
        }

        .theme-toggle {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            padding: 8px;
            cursor: pointer;
            color: var(--text-secondary);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        /* Main Content */
        .main-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Segmented Control Navigation */
        .section-navigation {
            margin-bottom: 32px;
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 16px 24px;
            border: 1px solid var(--border-primary);
        }

        .nav-tabs-custom {
            display: flex;
            gap: 0;
            border-radius: 8px;
            border: 1px solid var(--border-primary);
            overflow: hidden;
            background: var(--bg-primary);
        }

        .nav-tab-btn {
            flex: 1;
            background: transparent;
            border: none;
            padding: 12px 20px;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-right: 1px solid var(--border-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .nav-tab-btn:last-child {
            border-right: none;
        }

        .nav-tab-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .nav-tab-btn.active {
            background: var(--primary-blue);
            color: white;
        }

        .nav-tab-btn.active:hover {
            background: var(--primary-blue-dark);
        }

        /* Content Sections */
        .section-content {
            display: none;
            animation: fadeInUp 0.3s ease;
        }

        .section-content.active {
            display: block;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Hero Section */
        .hero-section {
            border-radius: 16px;
            overflow: hidden;
            background: var(--bg-secondary);
            box-shadow: var(--shadow-lg);
            margin-bottom: 24px;
        }

        .hero-image {
            position: relative;
            height: 500px;
            background: var(--bg-tertiary);
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            padding: 40px;
            color: white;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .hero-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
        }

        /* Style Thumbnails */
        .style-thumbnails {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin: 24px 0;
            justify-content: flex-start;
        }

        .style-thumbnail-featured {
            position: relative;
            width: 140px;
            height: 140px;
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-primary);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .style-thumbnail-featured:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-blue);
        }

        .style-thumbnail-featured img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s ease;
        }

        .style-thumbnail-featured:hover img {
            transform: scale(1.05);
        }

        .style-badge {
            position: absolute;
            bottom: 4px;
            right: 4px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            font-size: 0.625rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }

        /* About Content */
        .about-content {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
        }

        .about-content h3 {
            color: var(--primary-blue);
            font-size: 1.25rem;
            font-weight: 600;
            margin: 32px 0 16px 0;
        }

        .about-content h3:first-child {
            margin-top: 0;
        }

        .about-content p {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .about-content a {
            color: var(--primary-blue);
            text-decoration: none;
        }

        .about-content a:hover {
            text-decoration: underline;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .stat-item {
            text-align: center;
            background: var(--bg-primary);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            padding: 20px 16px;
        }

        .stat-number {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Blog Filters */
        .blog-filters {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .search-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            padding: 10px 16px;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            min-width: 200px;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary-blue);
            background: var(--bg-primary);
        }

        .filter-item {
            background: transparent;
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            padding: 8px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .filter-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            text-decoration: none;
        }

        .filter-item.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        /* Blog Posts */
        .blog-posts {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .blog-post {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            overflow: hidden;
        }

        .blog-post-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid var(--border-primary);
        }

        .blog-post-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .blog-post-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .blog-post-content {
            padding: 0 24px 24px;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .category-badge {
            background: var(--primary-blue);
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Archive Grid */
        .archive-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 24px;
        }

        .archive-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .archive-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .archive-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .archive-item-info {
            padding: 16px;
        }

        .archive-item-date {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 12px;
            font-weight: 500;
        }

        .archive-item-styles {
            color: var(--text-tertiary);
            font-size: 0.75rem;
        }

        /* Archive Style Thumbnails */
        .archive-styles-section {
            margin-top: 12px;
        }

        .archive-styles-label {
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .archive-style-thumbnails {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .archive-style-thumbnail {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: 6px;
            overflow: hidden;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-primary);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .archive-style-thumbnail:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-blue);
        }

        .archive-style-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .archive-style-badge {
            position: absolute;
            bottom: 2px;
            right: 2px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            font-size: 0.5rem;
            padding: 1px 4px;
            border-radius: 3px;
            font-weight: 600;
        }

        /* Pagination */
        .pagination-custom {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 32px;
        }

        .pagination-custom a,
        .pagination-custom span {
            padding: 8px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .pagination-custom a:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            text-decoration: none;
        }

        .pagination-custom .active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--text-tertiary);
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-image {
                height: 250px;
            }

            .hero-title {
                font-size: 1.875rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .style-thumbnails {
                justify-content: center;
            }

            .archive-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }

            .nav-tab-btn {
                padding: 10px 12px;
                font-size: 0.8rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .blog-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .archive-style-thumbnails {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Simple Header -->
    <div class="simple-header">
        <div class="header-content">
            <div class="header-brand">
                <h1><a href="/">StyleForger.com</a></h1>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
        </div>
    </div>

    <div class="main-content">
        <!-- Navigation -->
        <div class="section-navigation">
            <div class="nav-tabs-custom">
                <button class="nav-tab-btn <?= $section === 'about' ? 'active' : '' ?>" onclick="showSection('about')">
                    <i class="fas fa-info-circle"></i>
                    About
                </button>
                <button class="nav-tab-btn <?= $section === 'blog' ? 'active' : '' ?>" onclick="showSection('blog')">
                    <i class="fas fa-edit"></i>
                    Blog & Updates
                </button>
                <button class="nav-tab-btn <?= $section === 'archive' ? 'active' : '' ?>" onclick="showSection('archive')">
                    <i class="fas fa-archive"></i>
                    Archive
                </button>
            </div>
        </div>

        <!-- About Section -->
        <div class="section-content <?= $section === 'about' ? 'active' : '' ?>" id="aboutSection">
            <?php if ($image_data): ?>
            <!-- Hero Image Section -->
            <div class="hero-section">
                <div class="hero-image">
                    <img src="uploads/about_images/<?= htmlspecialchars($image_data['filename']) ?>" 
                         alt="StyleForger About">
                    <div class="hero-overlay">
                        <div class="hero-title">StyleForger</div>
                        <div class="hero-subtitle">AI Art Style Gallery & Reference Database</div>
                    </div>
                </div>
            </div>

            <!-- Featured Styles Used -->
            <?php if (!empty($style_numbers) && !empty($style_thumbnails)): ?>
            <div style="background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                <h3 style="color: var(--text-primary); margin: 0 0 16px 0; font-size: 1.125rem;">Featured Styles Used</h3>
                <div class="style-thumbnails">
                    <?php foreach ($style_numbers as $style_num): ?>
                        <?php if (isset($style_thumbnails[$style_num])): ?>
                            <?php 
                            // Get full style data for the modal
                            $style_stmt = $conn->prepare("SELECT * FROM images WHERE style_number = ?");
                            $style_stmt->bind_param("s", $style_num);
                            $style_stmt->execute();
                            $style_result = $style_stmt->get_result();
                            $full_style_data = $style_result->fetch_assoc();
                            $style_stmt->close();
                            
                            if ($full_style_data) {
                                // Parse ai_tags for keywords if available
                                if (!empty($full_style_data['ai_tags'])) {
                                    $tags = json_decode($full_style_data['ai_tags'], true);
                                    if (is_array($tags)) {
                                        $keywords = array_column($tags, 'name');
                                        $full_style_data['keywords'] = implode(', ', $keywords);
                                    }
                                }
                                
                                // Build colors array
                                $colors = [];
                                if (!empty($full_style_data['primary_color'])) {
                                    $colors[] = $full_style_data['primary_color'];
                                }
                                if (!empty($full_style_data['color_palette'])) {
                                    $palette = json_decode($full_style_data['color_palette'], true);
                                    if (is_array($palette)) {
                                        $colors = array_merge($colors, array_slice($palette, 0, 5));
                                    }
                                }
                            ?>
                            <div class="style-thumbnail-featured" 
                                 data-style='<?= htmlspecialchars(json_encode($full_style_data)) ?>' 
                                 data-colors='<?= htmlspecialchars(json_encode($colors)) ?>'>
                                <img src="watermarked/<?= htmlspecialchars($style_thumbnails[$style_num]) ?>" 
                                     alt="Style <?= htmlspecialchars($style_num) ?>">
                                <span class="style-badge">--sref <?= htmlspecialchars($style_num) ?></span>
                            </div>
                            <?php } ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- About Content -->
            <div class="about-content">
                <h3>Welcome to StyleForger</h3>
                <p>
                    StyleForger is your premier destination for AI art style references and creative inspiration. 
                    Our curated gallery features thousands of unique Midjourney-generated styles, carefully 
                    organized to help digital artists, designers, and creators discover the perfect aesthetic 
                    for their projects.
                </p>

                <h3>Our Mission</h3>
                <p>
                    We believe that great art begins with great inspiration. StyleForger bridges the gap between 
                    imagination and creation by providing a comprehensive database of AI art styles that serve 
                    as references, starting points, and creative catalysts for your artistic endeavors.
                </p>

                <h3>What You'll Find Here</h3>
                <p>
                    Our collection spans multiple Midjourney versions (v7.0, v6.1, and Niji) and covers an 
                    extensive range of artistic styles, from photorealistic renders to abstract compositions. 
                    Each style is tagged and searchable, making it easy to find exactly what you're looking for 
                    or to stumble upon unexpected inspiration.
                </p>

                <h3>Features & Tools</h3>
                <p>
                    StyleForger offers sophisticated search capabilities, version-specific filtering, curated 
                    collections, and a clean, intuitive interface that puts the focus where it belongs - on the art. 
                    We've curated thousands of unique AI-generated styles to help you discover the perfect aesthetic for your creative projects.
                </p>

                <h3>Get in Touch</h3>
                <p>
                    Have questions, suggestions, or want to connect with fellow creators? 
                    Follow me on deviantArt <a href="https://www.deviantart.com/nocturnalrealms" target="_blank">@nocturnalrealms</a>
                </p>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($total_images) ?></div>
                        <div class="stat-label">Total Styles</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($v7_total) ?></div>
                        <div class="stat-label">v7.0 Styles</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($total_niji) ?></div>
                        <div class="stat-label">Niji Styles</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($total_images - $v7_total) ?></div>
                        <div class="stat-label">v6.1 Styles</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Blog Section -->
        <div class="section-content <?= $section === 'blog' ? 'active' : '' ?>" id="blogSection">
            <!-- Blog Filters -->
            <div class="blog-filters">
                <input type="text" class="search-box" id="blogSearch" 
                       placeholder="Search blog posts..." 
                       value="<?= htmlspecialchars($blog_search) ?>"
                       onkeypress="if(event.key==='Enter') filterBlog()">
                       
                <a href="blog.php?section=blog" class="filter-item <?= empty($category_filter) ? 'active' : '' ?>">
                    All Posts
                </a>
                <?php foreach ($categories as $category => $count): ?>
                    <a href="blog.php?section=blog&category=<?= urlencode($category) ?>" 
                       class="filter-item <?= $category_filter === $category ? 'active' : '' ?>">
                        <?= htmlspecialchars($category) ?> (<?= $count ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Blog Posts -->
            <?php if (!empty($blog_posts)): ?>
                <div class="blog-posts">
                    <?php foreach ($blog_posts as $post): ?>
                        <article class="blog-post">
                            <div class="blog-post-header">
                                <h2 class="blog-post-title"><?= htmlspecialchars($post['headline']) ?></h2>
                                <div class="blog-post-meta">
                                    <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($post['post_date'])) ?></span>
                                    <?php if (!empty($post['category'])): ?>
                                        <span class="category-badge"><?= htmlspecialchars($post['category']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="blog-post-content">
                                <?= nl2br(htmlspecialchars_decode(stripslashes($post['post_content']))) ?>
                            </div>
                            
                            <?php 
                            // Handle blog post images
                            $post_images = [];
                            for ($i = 1; $i <= 5; $i++) {
                                $img_key = 'image_' . $i;
                                if (!empty($post[$img_key])) {
                                    $img_path = 'images/blog/' . $post[$img_key];
                                    if (file_exists($img_path)) {
                                        $post_images[] = $img_path;
                                    }
                                }
                            }
                            
                            if (!empty($post_images)): ?>
                                <div style="padding: 0 24px 24px 24px;">
                                    <?php foreach ($post_images as $img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" alt="Blog Post Image" 
                                             style="width: 100%; border-radius: 12px; margin-bottom: 16px; box-shadow: var(--shadow-md);">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-edit"></i>
                    <h3>No blog posts found</h3>
                    <p>Check back later for updates!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archive Section -->
        <div class="section-content <?= $section === 'archive' ? 'active' : '' ?>" id="archiveSection">
            <h3>Feature-image Archive</h3>
            <?php 
            // Archive logic inline like the original
            $imagesPerPage = 12;
            $archive_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $archive_offset = ($archive_page - 1) * $imagesPerPage;
            
            $countResult = $conn->query("SELECT COUNT(*) AS total FROM about_images");
            $totalImagesRow = $countResult->fetch_assoc();
            $totalArchivedImages = $totalImagesRow['total'];
            $totalArchivePages = ceil($totalArchivedImages / $imagesPerPage);
            
            $archiveResult = $conn->query("SELECT * FROM about_images ORDER BY upload_date DESC LIMIT $archive_offset, $imagesPerPage");
            $archived_images = [];
            while ($row = $archiveResult->fetch_assoc()) {
                $archived_images[] = $row;
            }
            ?>
            
            <?php if (!empty($archived_images)): ?>
                <div class="archive-grid">
                    <?php foreach ($archived_images as $img): ?>
                        <div class="archive-item">
                            <img src="uploads/about_images/<?= htmlspecialchars($img['filename']) ?>" 
                                 alt="About Image Archive">
                            <div class="archive-item-info">
                                <div class="archive-item-date">
                                    <?= date('M j, Y', strtotime($img['upload_date'])) ?>
                                </div>
                                
                                <?php if (!empty($img['styles_used'])): ?>
                                    <?php 
                                    // Parse styles used for this archive image
                                    $archive_style_numbers = array_map('trim', explode(',', $img['styles_used']));
                                    $archive_style_numbers = array_filter($archive_style_numbers, function($num) {
                                        return !empty($num) && is_numeric($num);
                                    });
                                    
                                    // Get thumbnails for these styles
                                    $archive_style_thumbnails = [];
                                    if (!empty($archive_style_numbers)) {
                                        $placeholders = implode(',', array_fill(0, count($archive_style_numbers), '?'));
                                        $thumbnail_sql = "SELECT * FROM images WHERE style_number IN ($placeholders) LIMIT 8";
                                        
                                        $stmt = $conn->prepare($thumbnail_sql);
                                        if ($stmt) {
                                            $types = str_repeat('s', count($archive_style_numbers));
                                            $stmt->bind_param($types, ...$archive_style_numbers);
                                            $stmt->execute();
                                            $thumbnail_result = $stmt->get_result();
                                            
                                            while ($thumb = $thumbnail_result->fetch_assoc()) {
                                                $archive_style_thumbnails[$thumb['style_number']] = $thumb;
                                            }
                                            $stmt->close();
                                        }
                                    }
                                    ?>
                                    
                                    <?php if (!empty($archive_style_thumbnails)): ?>
                                        <div class="archive-styles-section">
                                            <div class="archive-styles-label">Styles Used:</div>
                                            <div class="archive-style-thumbnails">
                                                <?php foreach ($archive_style_numbers as $style_num): ?>
                                                    <?php if (isset($archive_style_thumbnails[$style_num])): ?>
                                                        <?php 
                                                        $style_data = $archive_style_thumbnails[$style_num];
                                                        
                                                        // Parse ai_tags for keywords if available
                                                        if (!empty($style_data['ai_tags'])) {
                                                            $tags = json_decode($style_data['ai_tags'], true);
                                                            if (is_array($tags)) {
                                                                $keywords = array_column($tags, 'name');
                                                                $style_data['keywords'] = implode(', ', $keywords);
                                                            }
                                                        }
                                                        
                                                        // Build colors array
                                                        $colors = [];
                                                        if (!empty($style_data['primary_color'])) {
                                                            $colors[] = $style_data['primary_color'];
                                                        }
                                                        if (!empty($style_data['color_palette'])) {
                                                            $palette = json_decode($style_data['color_palette'], true);
                                                            if (is_array($palette)) {
                                                                $colors = array_merge($colors, array_slice($palette, 0, 5));
                                                            }
                                                        }
                                                        ?>
                                                        <div class="archive-style-thumbnail" 
                                                             data-style='<?= htmlspecialchars(json_encode($style_data)) ?>' 
                                                             data-colors='<?= htmlspecialchars(json_encode($colors)) ?>'>
                                                            <img src="watermarked/<?= htmlspecialchars($style_data['filename']) ?>" 
                                                                 alt="Style <?= htmlspecialchars($style_num) ?>">
                                                            <!-- <span class="archive-style-badge">--sref <?= htmlspecialchars($style_num) ?></span> -->
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="archive-item-styles">
                                            Styles: <?= htmlspecialchars($img['styles_used']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalArchivePages > 1): ?>
                    <div class="pagination-custom">
                        <?php if ($archive_page > 1): ?>
                            <a href="?section=archive&page=<?= $archive_page-1 ?>">&laquo; Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalArchivePages; $i++): ?>
                            <?php if ($i == $archive_page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?section=archive&page=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($archive_page < $totalArchivePages): ?>
                            <a href="?section=archive&page=<?= $archive_page+1 ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-archive"></i>
                    <h3>No archived images found</h3>
                    <p>Check back later for more content</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Style Modal - Same as index page -->
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
        <span>Style code copied!</span>
    </div>

    <!-- Add modal CSS -->
    <style>
        /* Modal Styles - Copy from index.php */
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
            border-radius: 8px; 
            cursor: pointer; 
            transition: all 0.2s;
            border: 2px solid transparent;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1rem;
            font-family: 'Courier New', monospace;
        }
        
        .style-code:hover { 
            background: var(--bg-tertiary); 
            border-color: var(--primary-blue);
            transform: translateY(-1px);
        }
        
        .copy-icon { 
            color: var(--text-secondary); 
            font-size: 0.875rem;
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
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .color-box:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .keywords {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .keyword-tag {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid var(--border-primary);
        }

        .keyword-tag:hover {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .toast-notification {
            position: fixed;
            top: 100px;
            right: 24px;
            background: var(--success-green);
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 9999;
            box-shadow: var(--shadow-lg);
            font-weight: 500;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .modal-body {
                grid-template-columns: 1fr;
                min-height: auto;
            }
            
            .modal-image-container {
                border-radius: 0;
            }
            
            .modal-info {
                border-radius: 0 0 16px 16px;
            }
        }
    </style>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function showSection(sectionName) {
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('section', sectionName);
            window.history.pushState({}, '', url);
            
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + 'Section').classList.add('active');
            
            // Update nav buttons
            document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function filterBlog() {
            const searchTerm = document.getElementById('blogSearch').value;
            const url = new URL(window.location);
            url.searchParams.set('section', 'blog');
            if (searchTerm) {
                url.searchParams.set('search', searchTerm);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url;
        }

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
        
        // Initialize theme on page load
        initializeTheme();

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section') || 'about';
            
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(section + 'Section').classList.add('active');
            
            // Update nav buttons
            document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[onclick="showSection('${section}')"]`).classList.add('active');
        });

        // Style Modal Functionality
        const modal = new bootstrap.Modal(document.getElementById('styleModal'));
        
        // Add click handlers to featured style thumbnails
        document.querySelectorAll('.style-thumbnail-featured').forEach(tile => {
            tile.addEventListener('click', function() {
                openStyleModal(this);
            });
        });
        
        // Add click handlers to archive style thumbnails
        document.querySelectorAll('.archive-style-thumbnail').forEach(tile => {
            tile.addEventListener('click', function() {
                openStyleModal(this);
            });
        });
        
        function openStyleModal(element) {
            const styleData = JSON.parse(element.dataset.style);
            const colors = JSON.parse(element.dataset.colors);
            
            const styleNumber = styleData.filename.replace('.webp', '');
            
            document.getElementById('modalImage').src = '/watermarked/' + styleData.filename;
            document.getElementById('modalImage').alt = 'Style ' + styleNumber;
            document.getElementById('styleNumber').textContent = '--sref ' + styleNumber;
            
            // Display version using consistent color system
            updateModalVersionDisplay(styleData, false);
            
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
                    const keywordTag = document.createElement('span');
                    keywordTag.className = 'keyword-tag';
                    keywordTag.textContent = keyword;
                    keywordTag.onclick = (e) => {
                        e.stopPropagation();
                        searchSimilarKeywords(keyword);
                    };
                    keywordsList.appendChild(keywordTag);
                });
            } else {
                keywordsSection.style.display = 'none';
            }
            
            modal.show();
        }
        
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
            }).catch(err => {
                console.error('Failed to copy: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    const toast = document.getElementById('toast');
                    toast.style.display = 'flex';
                    setTimeout(() => {
                        toast.style.display = 'none';
                    }, 2000);
                } catch (err) {
                    console.error('Fallback: Oops, unable to copy', err);
                }
                document.body.removeChild(textArea);
            });
        }
        
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
            }
        }
        
        function searchSimilarColors(color) {
            // Close modal first
            modal.hide();
            
            // Navigate to main gallery with color search
            const params = new URLSearchParams({
                search_type: 'color',
                search_value: color
            });
            
            // Add a small delay for modal close animation
            setTimeout(() => {
                window.location.href = `/?${params.toString()}`;
            }, 300);
        }
        
        function searchSimilarKeywords(keyword) {
            // Close modal first
            modal.hide();
            
            // Navigate to main gallery with keyword search
            const params = new URLSearchParams({
                search_type: 'keyword',
                search_value: keyword
            });
            
            // Add a small delay for modal close animation
            setTimeout(() => {
                window.location.href = `/?${params.toString()}`;
            }, 300);
        }
    </script>
</body>
</html>