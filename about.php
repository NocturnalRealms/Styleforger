<?php
session_start();
require 'config.php';

// Get the total number of images for the navigation bar
$result = $conn->query("SELECT COUNT(*) AS total FROM images");
$row = $result->fetch_assoc();
$total_images = $row['total'];

// Get parameter for the archive view (if any)
$archive_id = isset($_GET['archive']) ? (int)$_GET['archive'] : 0;

// Get pagination parameters
$imagesPerPage = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $imagesPerPage;

// Count the total archived images
$countResult = $conn->query("SELECT COUNT(*) AS total FROM about_images");
$totalImagesRow = $countResult->fetch_assoc();
$totalArchivedImages = $totalImagesRow['total'];
$totalPages = ceil($totalArchivedImages / $imagesPerPage);

// Determine if we're viewing an archive or the latest image
if ($archive_id > 0) {
    // Fetch the specific archived image
    $stmt = $conn->prepare("SELECT * FROM about_images WHERE id = ?");
    $stmt->bind_param("i", $archive_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $image_data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$image_data) {
        // If the archive ID is invalid, redirect to the main about page
        header("Location: about.php");
        exit();
    }
} else {
    // Fetch the latest uploaded image
    $result = $conn->query("SELECT * FROM about_images ORDER BY upload_date DESC LIMIT 1");
    $image_data = $result->fetch_assoc();
}

if (!$image_data) {
    die('No image found');
}

// Get the style numbers entered during the image upload (comma-separated)
$style_numbers = explode(',', $image_data['styles_used']); 

// Fetch the archive images with pagination
$archiveResult = $conn->query("SELECT * FROM about_images ORDER BY upload_date DESC LIMIT $offset, $imagesPerPage");

// Count the Niji styles
$nijiResult = $conn->query("SELECT COUNT(*) AS total FROM niji_images");
$nijiRow = $nijiResult->fetch_assoc();
$total_niji = $nijiRow['total'];

// Random taglines array for the header banner
$taglines = [
    "Transform Your Vision with the Perfect Style",
    "Where Creativity Meets Unlimited Styles",
    "Your Imagination, Our Styles",
    "Discover the Art of Style Exploration",
    "Crafting Digital Dreams with Precision",
    "Forge Your Path, Style by Style",
    "Creating Without Boundaries",
    "Unlock Your Creative Potential"
];
$randomTagline = $taglines[array_rand($taglines)];

// Get random stat for animation
$randomStat = rand(85, 99);

$v7Result = $conn->query("SELECT COUNT(*) AS v7_total FROM images WHERE style_version = 'v7.0'");
$v7Row = $v7Result->fetch_assoc();
$v7_total = $v7Row['v7_total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About - StyleForger.com</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Custom CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <style>
    /* Define theme variables with glassmorphism enhancement */
:root {
    --transition-speed: 0.3s;
    --glass-blur: 12px;
    --glass-border-opacity: 0.2;
    --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
    --glass-hover-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.5);
    --animation-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

/* Dark theme variables with glass enhancement */
.dark-theme {
    --background-color: #222;
    --background-gradient: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #16213e 100%);
    --text-color: #ffffff;
    --site-orange: #d36115;
    --link-color: #d36115;
    --link-hover-color: #ff7733;
    --card-background-color: rgba(51, 51, 51, 0.3);
    --card-glass-bg: rgba(255, 255, 255, 0.05);
    --navbar-background-color: rgba(34, 34, 34, 0.8);
    --navbar-glass-bg: rgba(255, 255, 255, 0.03);
    --navbar-text-color: #bbb;
    --input-background-color: rgba(51, 51, 51, 0.4);
    --input-glass-bg: rgba(255, 255, 255, 0.08);
    --input-text-color: #fff;
    --button-background-color: #d36115;
    --button-hover-background-color: #ff7733;
    --placeholder-color: #ccc;
    --invalid-feedback-color: #d36115;
    --accordion-active-background-color: rgba(73, 80, 87, 0.6);
    --success-color: #28a745;
    --danger-color: #dc3545;
    --table-background-color: rgba(52, 58, 64, 0.3);
    --table-text-color: #fff;
    --table-border-color: rgba(69, 77, 85, 0.5);
    --table-header-background-color: rgba(69, 77, 85, 0.6);
    --table-header-text-color: #fff;
    --table-row-background-color: rgba(60, 65, 71, 0.3);
    --table-hover-background-color: rgba(75, 82, 89, 0.4);
    --glass-highlight: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, var(--glass-border-opacity));
    --icon-glow: 0 0 20px rgba(211, 97, 21, 0.6);
}

/* Light theme variables with glass enhancement */
.light-theme {
    --background-color: #f8fafc;
    --background-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e1 100%);
    --text-color: #1a202c;
    --site-orange: #e07b39;
    --link-color: #d36115;
    --link-hover-color: #b25012;
    --card-background-color: rgba(248, 250, 252, 0.4);
    --card-glass-bg: rgba(255, 255, 255, 0.3);
    --navbar-background-color: rgba(248, 249, 250, 0.8);
    --navbar-glass-bg: rgba(255, 255, 255, 0.4);
    --navbar-text-color: #4a5568;
    --input-background-color: rgba(255, 255, 255, 0.6);
    --input-glass-bg: rgba(255, 255, 255, 0.5);
    --input-text-color: #2d3748;
    --button-background-color: #e07b39;
    --button-hover-background-color: #d36115;
    --placeholder-color: #718096;
    --invalid-feedback-color: #e07b39;
    --accordion-active-background-color: rgba(224, 224, 224, 0.6);
    --success-color: #28a745;
    --danger-color: #dc3545;
    --table-background-color: rgba(255, 255, 255, 0.4);
    --table-text-color: #2d3748;
    --table-border-color: rgba(222, 226, 230, 0.6);
    --table-header-background-color: rgba(233, 236, 239, 0.6);
    --table-header-text-color: #4a5568;
    --table-row-background-color: rgba(248, 249, 250, 0.4);
    --table-hover-background-color: rgba(233, 236, 239, 0.5);
    --glass-highlight: rgba(255, 255, 255, 0.6);
    --glass-border: rgba(255, 255, 255, var(--glass-border-opacity));
    --icon-glow: 0 0 15px rgba(224, 123, 57, 0.4);
}
        /* Hero Section */
        .hero-banner {
            position: relative;
            height: 400px;
            overflow: hidden;
            border-radius: 20px;
            margin-bottom: 60px;
        }
        
        .hero-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.4);
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            height: 100%;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        
        .hero-badge {
            background-color: var(--site-orange);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            font-weight: 400;
            color: white;
            text-shadow: 1px 1px 4px rgba(0,0,0,0.5);
            max-width: 800px;
        }
        
        /* Featured Styles Section */
        .styles-container {
            position: relative;
            z-index: 10;
            margin-top: -30px;
            background-color: var(--card-background-color);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -10px;
            width: 50px;
            height: 3px;
            background-color: var(--site-orange);
        }
        
        .style-thumbnail {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .style-thumbnail:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .style-thumbnail img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            transition: all 0.5s ease;
        }
        
        .style-thumbnail:hover img {
            transform: scale(1.1);
        }
        
        .style-number {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 5px;
            background: rgba(0,0,0,0.7);
            color: white;
            font-size: 12px;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Content Sections */
        .content-section {
            margin-bottom: 60px;
        }
        
        .content-card {
            background-color: var(--card-background-color);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            height: 100%;
            transition: all 0.3s ease;
            border-top: 4px solid var(--site-orange);
            
            background: var(--card-glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
        }
        
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .content-card h3 {
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        
        .content-card h3::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -10px;
            width: 40px;
            height: 2px;
            background-color: var(--site-orange);
        }
        
        .content-card p {
            line-height: 1.8;
        }
        
        .content-card-icon {
            font-size: 2.5rem;
            color: var(--site-orange);
            margin-bottom: 20px;
        }
        
        /* Stats Cards */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 60px;
        }
        
        .stat-card {
            background-color: var(--card-background-color);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            flex: 1;
            margin: 0 10px 20px;
            min-width: 200px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--site-orange);
            margin-bottom: 10px;
            line-height: 1;
            font-family: monospace;
        }
        
        .stat-label {
            font-size: 1rem;
            font-weight: 500;
        }
        
        /* Archive Section */
        .archive-section {
            padding: 40px;
            border-radius: 20px;
            background-color: var(--card-background-color);
            margin-bottom: 60px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            background: var(--card-glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
        }
        
        .archive-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .archive-item {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .archive-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }
        
        .archive-item.active {
            border: 3px solid var(--site-orange);
        }
        
        .archive-image {
            width: 100%;
            aspect-ratio: 2/1;
            object-fit: cover;
        }
        
        .archive-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
        }
        
        .archive-date {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .archive-styles {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .archive-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--site-orange);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .timeline-indicator {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 2px;
            background-color: var(--site-orange);
            transform: translateX(-50%);
            z-index: 0;
        }
        
        /* Quote Section */
        .quote-section {
            position: relative;
            padding: 60px 40px;
            margin: 80px 0;
            border-radius: 20px;
            overflow: hidden;
        }
        
        .quote-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.3);
            z-index: 0;
        }
        
        .quote-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }
        
        .quote-icon {
            font-size: 3rem;
            color: var(--site-orange);
            margin-bottom: 20px;
            opacity: 0.7;
        }
        
        .quote-text {
            font-size: 1.8rem;
            font-style: italic;
            margin-bottom: 20px;
            color: white;
            line-height: 1.5;
        }
        
        .quote-author {
            font-size: 1.1rem;
            color: var(--site-orange);
        }
        
        /* CTA Section */
        .cta-section {
            text-align: center;
            padding: 60px 40px;
            margin: 60px 0;
            border-radius: 20px;
            background-color: var(--card-background-color);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            background: var(--card-glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
        }
        
        .cta-title {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--site-orange);
        }
        
        .cta-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .cta-btn {
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .cta-btn-primary {
            background-color: var(--site-orange);
            color: white;
            border: none;
        }
        
        .cta-btn-primary:hover {
            background-color: var(--link-hover-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(211, 97, 21, 0.3);
        }
        
        .cta-btn-secondary {
            background-color: transparent;
            color: var(--text-color);
            border: 2px solid var(--site-orange);
        }
        
        .cta-btn-secondary:hover {
            background-color: var(--site-orange);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(211, 97, 21, 0.3);
        }
        
        /* Footer styling */
        .custom-footer {
            margin-top: 80px;
            padding: 40px 0;
            /*background-color: var(--card-background-color);*/
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .footer-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--site-orange);
            margin-bottom: 20px;
        }
        
        .footer-socials {
            display: flex;
            gap: 15px;
        }
        
        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            background-color: var(--site-orange);
            color: white;
            transform: translateY(-3px);
        }
        
        .footer-nav {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .footer-nav a {
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-nav a:hover {
            color: var(--site-orange);
        }
        
        .footer-copyright {
            margin-top: 20px;
            font-size: 0.9rem;
            opacity: 0.7;
        }
        
        /* Custom animation for stats */
        @keyframes countUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-count {
            animation: countUp 1s ease forwards;
        }
        
        /* Media queries for responsiveness */
        @media (max-width: 992px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .quote-text {
                font-size: 1.5rem;
            }
            
            .stats-container {
                flex-direction: column;
            }
            
            .stat-card {
                width: 100%;
                margin: 0 0 20px;
            }
        }
        
        @media (max-width: 768px) {
            .hero-banner {
                height: 350px;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .content-card {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .quote-text {
                font-size: 1.2rem;
            }
            
            .cta-title {
                font-size: 1.5rem;
            }
            
            .cta-subtitle {
                font-size: 1rem;
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
            
            .footer-nav {
                justify-content: center;
            }
        }
    </style>
</head>
<body class="dark-theme">

<!-- Navigation Bar -->
<?php include 'global-navigation.php'; ?>

<!-- Hero Banner Section with Featured Image -->
<div class="container mt-5">
    <div class="text-center mb-5">
        <h1 class="site-orange mb-4">About StyleForger.com</h1>
        
        <!-- Display the main about image larger -->
        <div class="position-relative mb-4">
            <?php if ($archive_id > 0): ?>
                <div class="badge bg-secondary position-absolute top-0 start-0 m-3">
                    Archived Image - <?= date('M d, Y', strtotime($image_data['upload_date'])); ?>
                </div>
            <?php else: ?>
                <div class="badge bg-secondary position-absolute top-0 start-0 m-3">
                    Current Featured Image
                </div>
            <?php endif; ?>
            <center><img src="uploads/about_images/<?= htmlspecialchars($image_data['filename']); ?>" 
                 alt="Featured Image" 
                 class="img-fluid rounded-4 shadow" 
                 style="max-height:600px; width:auto;"></center>
        </div>
        
        <!-- Display Thumbnails of Styles Used -->
        <div class="mb-5">
            <div class="d-flex justify-content-center align-items-center mb-3">
                <h5 class="site-orange mb-0">
                    <i class="fa-solid fa-palette me-2"></i> Styles Featured in this Image
                </h5>
                <?php if ($archive_id > 0): ?>
                    <a href="about.php" class="btn btn-outline-primary btn-sm ms-3">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Current
                    </a>
                <?php endif; ?>
            </div>
            <div class="row justify-content-center" style="max-width: 800px; margin: 0 auto;">
                <?php 
                // Generate style thumbnails
                foreach ($style_numbers as $style_number):
                    $style_number = trim($style_number);
                    if (!empty($style_number)):
                        $style_filename = $style_number . '.webp';
                ?>
                    <div class="col-xl-2 col-lg-3 col-md-3 col-sm-4 col-4">
                        <a href="#" class="text-decoration-none image-link" data-filename="<?= htmlspecialchars($style_filename); ?>">
                            <div class="style-thumbnail">
                                <img src="watermarked/<?= htmlspecialchars($style_filename); ?>" 
                                     alt="Style <?= htmlspecialchars($style_number); ?>"
                                     style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:8px;"
                                     onerror="this.onerror=null; this.src='assets/images/style-placeholder.jpg';">
                                <div class="style-number">
                                    <?= htmlspecialchars($style_number); ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards Section -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number"><?= number_format($total_images); ?></div>
            <div class="stat-label">Total Styles</div>
        </div>
        
        <div class="stat-card">
    <div class="stat-number"><?= number_format($v7_total); ?></div>
    <div class="stat-label">V7 Styles</div>
</div>
        
        <div class="stat-card">
            <div class="stat-number"> <?= number_format($total_niji); ?></div>
            <div class="stat-label">--niji styles</div>
        </div>
        
     <?php
// Get the total number of styles added this week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$weekResult = $conn->query("SELECT COUNT(*) AS week_total FROM images WHERE DATE(uploaded_at) BETWEEN '$weekStart' AND '$weekEnd'");
$weekRow = $weekResult->fetch_assoc();
$week_total = $weekRow['week_total'];
?>
<div class="stat-card">
    <div class="stat-number"><?= number_format($week_total); ?></div>
    <div class="stat-label">Added this week</div>
</div>
        
        <?php
        // Get the total number of moodboard codes
        $moodResult = $conn->query("SELECT COUNT(*) AS mood_total FROM moodboard_images");
        $moodRow = $moodResult->fetch_assoc();
        $mood_total = $moodRow['mood_total'];
        ?>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($mood_total); ?></div>
            <div class="stat-label">--profile  codes</div>
        </div>
    </div>
    
    <!-- Content Cards Section -->
    <div class="content-section">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="content-card">
                    <div class="content-card-icon">
                        <i class="fa-solid fa-lightbulb"></i>
                    </div>
                    <h3>Creating a Space for Easy Creativity</h3>
                    <p>
                        I built StyleForger.com with a simple idea: creativity should be free and available to everyone. I've always felt that art connects us all, breaking down barriers and bringing people together. This platform is a reflection of that belief.
                    </p>
                    <p>
                        There are no exclusives or hidden hoops to jump through. Instead, you'll find <?= $total_images; ?> unique AI-generated --sref codes ready for you to explore because I believe inspiration should be easy to find. More are added daily. 
                    </p>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="content-card">
                    <div class="content-card-icon">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <h3>What's Next</h3>
                    <p>
                        As the site grows, I'm excited to add features that make your creative process even better. Already, you can log in and build your own personal library—a place to save and organize your favorite styles, try out new ones, and keep track of what inspires you.
                    </p>
                    <p>
                        It's not just about having a bunch of styles to choose from; it's about giving you the tools to bring your ideas to life effortlessly.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quote Section -->
    <div class="quote-section">
        <img src="uploads/about_images/<?= htmlspecialchars($image_data['filename']); ?>" alt="Background" class="quote-bg">
        <div class="quote-content">
            <div class="quote-icon">
                <i class="fa-solid fa-quote-left"></i>
            </div>
            <div class="quote-text">
                "AI doesn't replace the artist; it gives us a new language to dream in."
            </div>
            <div class="quote-author">StyleForger Philosophy</div>
        </div>
    </div>
    
    <!-- More Content Section -->
    <div class="content-section">
        <div class="row">
            <div class="col-lg-12 mb-4">
                <div class="content-card">
                    <div class="content-card-icon">
                        <i class="fa-solid fa-palette"></i>
                    </div>
                    <h3>My Approach to Art</h3>
                    <p>
                        Working with AI-generated art, I believe the real magic happens when technology and creativity come together. AI isn't here to take over human creativity—it's a tool that opens up new possibilities. It lets me dive into new creative areas and make ideas come to life in ways I couldn't before.
                    </p>
                    <p>
                        With StyleForger.com, I want to create a space where every artist can discover and explore just like I do. The platform is designed to be intuitive and accessible, whether you're a seasoned digital artist or just beginning your creative journey.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Archive Section -->
    <div class="archive-section">
        <h2 class="section-title">
            <i class="fa-solid fa-clock-rotate-left me-2"></i> Style Evolution Timeline
        </h2>
        <p>
            Explore how our featured styles have evolved over time. Each image showcases a unique collection of styles that represent different creative periods. Browse through the archive to see the progression of our style offerings and how they've transformed.
        </p>
        
        <div class="archive-grid">
            <?php 
            while ($archive = $archiveResult->fetch_assoc()): 
                $isActive = ($archive_id > 0 && $archive_id == $archive['id']);
            ?>
                <a href="about.php?archive=<?= $archive['id']; ?>" class="text-decoration-none">
                    <div class="archive-item <?= $isActive ? 'active' : ''; ?>">
                        <img src="uploads/about_images/<?= htmlspecialchars($archive['filename']); ?>" 
                             alt="Archive image <?= date('M Y', strtotime($archive['upload_date'])); ?>"
                             class="archive-image"
                             loading="lazy">
                        <div class="archive-info">
                            <div class="archive-date">
                                <?= date('F d, Y', strtotime($archive['upload_date'])); ?>
                            </div>
                            <div class="archive-styles">
                                <?php
                                $archiveStyles = explode(',', $archive['styles_used']);
                                echo count(array_filter($archiveStyles, 'trim')) . ' unique styles';
                                ?>
                            </div>
                        </div>
                        <?php if ($isActive): ?>
                            <div class="archive-badge">Current View</div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Archive pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?= $page-1; ?><?= $archive_id ? '&archive='.$archive_id : ''; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?= $i; ?><?= $archive_id ? '&archive='.$archive_id : ''; ?>"><?= $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?= $page+1; ?><?= $archive_id ? '&archive='.$archive_id : ''; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    
    <!-- CTA Section -->
    <div class="cta-section">
        <h2 class="cta-title">Ready to Start Creating?</h2>
        <p class="cta-subtitle">
            Join our community of creators and unlock your full creative potential with our collection of unique styles.
        </p>
        <div class="cta-buttons">
            <a href="portal.php" class="btn cta-btn cta-btn-primary">
                <i class="fa-solid fa-user me-2"></i> Create Account
            </a>
            <a href="index.php" class="btn cta-btn cta-btn-secondary">
                <i class="fa-solid fa-palette me-2"></i> Explore Styles
            </a>
            <a href="contact.php" class="btn cta-btn cta-btn-secondary">
                <i class="fa-solid fa-envelope me-2"></i> Get in Touch
            </a>
        </div>
    </div>
    
    <!-- Get in Touch Section -->
    <div class="content-section">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="content-card">
                    <div class="content-card-icon">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                    <h3>Get in Touch</h3>
                    <p>
                        If you want to see more of my work or follow my journey, check out my artist page on DeviantArt: 
                        <a href="http://nocturnalreal.ms" target="_blank">nocturnalreal.ms</a>.
                    </p>
                    <p>
                        I'm always up for connecting with other creators, sharing ideas, and learning together. After all, 
                        this is more than just a site—it's a community of like-minded creatives.
                    </p>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="content-card">
                    <div class="content-card-icon">
                        <i class="fa-solid fa-newspaper"></i>
                    </div>
                    <h3>Latest Updates</h3>
                    <p>
                        Want to know what's new behind the scenes? StyleForger.com is constantly evolving with new features, 
                        styles, and improvements. 
                    </p>
                    <p>
                        Take a look at the latest updates in the Logs section:
                        <a href="/progress.php" class="btn btn-sm" style="background-color: var(--site-orange); color: white;">
                            <i class="fa-solid fa-rss me-1"></i> (B)LOGS
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Custom Footer -->


<!-- Bootstrap Modal for Style Preview -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-body p-0 position-relative">
        <!-- Close button -->
        <button type="button" class="btn-close position-absolute top-0 end-0 m-3 bg-white rounded-circle" data-bs-dismiss="modal" aria-label="Close"></button>
        
        <!-- Modal Image -->
        <img id="modal-image" src="" class="img-fluid w-100 rounded-top" alt="">
        
        <!-- Style Number and Download Button -->
        <div class="d-flex justify-content-between align-items-center p-3">
            <div id="style-code-container" class="d-flex align-items-center">
                <div id="style-number" class="fw-bold fs-5 site-orange"></div>
                <button id="copy-style-btn" class="btn btn-sm ms-3" style="background-color: var(--card-background-color);">
                    <i class="fa-regular fa-clipboard me-1"></i> Copy
                </button>
            </div>
            <div>
                <a href="#" id="download-button" class="btn" style="background-color: var(--site-orange); color: white;">
                    <i class="fa-solid fa-cloud-arrow-down me-1"></i> Download
                </a>
                <a href="#" id="view-style-btn" class="btn btn-outline-secondary ms-2">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> View in Gallery
                </a>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container-fluid">
   <footer class="text-center py-3 mt-5" style="min-width:100%;">
        <a class="text-light text-decoration-none" href="/section/admin/login.php">&copy;</a> 2024 StyleForger.com. All rights reserved.
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
  $(document).ready(function() {
    // Image modal handler
    $('.image-link').on('click', function(e) {
        e.preventDefault();
        var filename = $(this).data('filename');
        // Remove the file extension
        var filenameWithoutExt = filename.substring(0, filename.lastIndexOf('.')) || filename;
        // Set the modal image source and alt text
        $('#modal-image').attr('src', 'watermarked/' + filename);
        $('#modal-image').attr('alt', filenameWithoutExt);
        // Update the style number without the file extension
        $('#style-number').text('--sref ' + filenameWithoutExt);
        // Update the download button link
        $('#download-button').attr('href', 'download.php?file=' + encodeURIComponent(filename));
        // Update the view in gallery link
        $('#view-style-btn').attr('href', 'index.php?search=' + filenameWithoutExt);
        // Show the modal
        $('#imageModal').modal('show');
    });
    
    // Copy style code to clipboard
    $('#copy-style-btn').on('click', function() {
        var styleCode = $('#style-number').text();
        navigator.clipboard.writeText(styleCode).then(function() {
            var originalIcon = $('#copy-style-btn i').attr('class');
            var originalText = $('#copy-style-btn').text().trim();
            
            $('#copy-style-btn').html('<i class="fa-solid fa-check me-1"></i> Copied!');
            setTimeout(function() {
                $('#copy-style-btn').html('<i class="' + originalIcon + ' me-1"></i> ' + originalText);
            }, 2000);
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
            alert('Failed to copy. Please copy manually.');
        });
    });
    
    // Animate the stats counters - removed as we're displaying the stat codes directly now
    /*
    $('.animate-stat').each(function() {
        var $this = $(this);
        var countTo = parseInt($this.attr('data-count'));
        
        $({ Counter: 0 }).animate({
            Counter: countTo
        }, {
            duration: 2000,
            easing: 'swing',
            step: function() {
                $this.text(Math.ceil(this.Counter));
            },
            complete: function() {
                $this.text(countTo);
                $this.addClass('animate-count');
            }
        });
    });
    */
    
    // Intersection Observer for animation triggers
    if (IntersectionObserver) {
        const observerOptions = {
            threshold: 0.2
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-count');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.stat-card, .content-card, .style-thumbnail').forEach(el => {
            observer.observe(el);
        });
    }
  });
</script>
</body>
</html>