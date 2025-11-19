<?php
session_start();
require 'config.php';

// Get the total number of images for the navigation bar
$result = $conn->query("SELECT COUNT(*) AS total FROM images");
$row = $result->fetch_assoc();
$total_images = $row['total'];

// Array of taglines with dynamic $total_images
$taglines = [
    "$total_images Styles — Crafted for the Bold, Created by You",
    "$total_images Styles, Infinite Visions — Discover Yours",
    "Forge Your Path with $total_images Unique Styles",
    "Where $total_images Styles Meet Limitless Imagination",
    "Your Creativity, Our Palette — $total_images Styles Await",
    "$total_images Styles, Endless Possibilities — Design Beyond Limits",
    "Unleash Your Artistry with $total_images Curated Styles",
    "Shape Your World with $total_images Styles at Your Fingertips",
    "$total_images Styles, One Platform — Unleash Your Imagination",
    "Create, Forge, Inspire — $total_images Styles and Counting"
];

// Randomly select a tagline
$random_tagline = $taglines[array_rand($taglines)];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Data - StyleForger</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body class="dark-theme">

<?php include 'global-navigation.php'; ?>

<div class="container mt-5"> <h1 class="text-center mb-4 site-orange">Your Privacy Matters at StyleForger</h1>

<p class="text-center">
    At StyleForger, we take your privacy seriously. We are committed to safeguarding your personal information and ensuring a secure experience on our platform.
</p>

<h5 class="site-orange">What Data We Collect</h5>
<p>
    To provide you with seamless access to your account and the features we offer, we only collect the most essential information. This includes:
</p>
<ul>
    <li><strong>Email Address:</strong> Collected solely for password recovery purposes. You won’t receive any newsletters or promotional content—just the essentials for managing your account.</li>
    <li><strong>Username:</strong> Your unique identifier, chosen during registration, used for accessing your account.</li>
</ul>

<h5 class="site-orange">Your Data Stays Private</h5>
<p>
    We understand the importance of privacy. That’s why we guarantee that your email and username will never be shared with third parties. Your data is used exclusively for managing your account on StyleForger and nothing more.
</p>

<h5 class="site-orange">Stay Informed</h5>
<p>
    If you’re curious about the latest updates or want to stay informed about new features, check out our <a href="/progress.php">blog</a>. That’s where we post important news and progress updates related to the site.
</p>

<h5 class="site-orange">No Cookies, No Tracking</h5>
<p>
    At StyleForger, we respect your browsing experience. We do not use cookies to track or collect any personal data while you navigate the site. Your privacy is our priority... and styles, we really love styles!
</p>

</div>

<!-- Footer -->
<footer class="text-white text-center py-3 mt-5">
    &copy; <?= date('Y'); ?> StyleForger.com. All rights reserved.
</footer>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/scripts.js"></script>

</body>
</html>
