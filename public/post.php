<?php

/**
 * SEO & Open Graph Handler for Blog Posts
 * This script serves the post.html template with dynamically injected meta tags.
 */

// Disable error reporting for production
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

use App\Core\Database;

// 1. Get the slug from query parameters
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$post = null;

if ($slug) {
    try {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT title, excerpt, featured_image, og_title, og_description, og_image 
            FROM blog_posts 
            WHERE slug = :slug 
            AND (status = 'Published' OR (status = 'Scheduled' AND publish_date <= NOW()))
            AND visibility = 'public'
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        error_log("SEO Handler Database Error: " . $e->getMessage());
    }
}

// 2. Load the template
$templatePath = __DIR__ . '/orc/blog/post.html';
if (!file_exists($templatePath)) {
    http_response_code(404);
    die("Post template not found.");
}

$html = file_get_contents($templatePath);

// 3. If post exists, inject metadata
if ($post) {
    // Prep data
    $postTitle = $post['title'];
    $seoTitle = $post['og_title'] ?: $postTitle;
    $description = $post['og_description'] ?: $post['excerpt'];

    // Clean and truncate description
    $description = strip_tags($description);
    $description = str_replace(["\r", "\n", "\t"], ' ', $description);
    $description = preg_replace('/\s+/', ' ', $description);
    $description = trim($description);
    if (mb_strlen($description) > 165) {
        $description = mb_substr($description, 0, 162) . '...';
    }

    $safeTitle = htmlspecialchars($seoTitle);
    $safeDescription = htmlspecialchars($description);

    // Build absolute URLs for OG (Improved protocol detection for proxies)
    $protocol = 'http';
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) {
        $protocol = 'https';
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $protocol = 'https';
    }

    $host = $_SERVER['HTTP_HOST'];
    $siteUrl = "$protocol://$host";

    // OG Image
    $imagePath = $post['og_image'] ?: $post['featured_image'];
    if ($imagePath) {
        if (!preg_match('~^https?://~i', $imagePath)) {
            $imagePath = $siteUrl . '/' . ltrim($imagePath, '/');
        }
    } else {
        $imagePath = $siteUrl . '/orc/assets/media/logos/default-logo.png';
    }

    // Replacement logic using robust regexes

    // Title
    $html = preg_replace('/<title>.*?<\/title>/is', "<title>$safeTitle - OASIS Research Community</title>", $html);

    // Description (name="description")
    $html = preg_replace('/<meta\s+name="description"\s+content=".*?"\s*\/?>/is', '<meta name="description" content="' . $safeDescription . '">', $html);

    // OG Title (property="og:title")
    $html = preg_replace('/<meta\s+property="og:title"\s+content=".*?"\s*\/?>/is', '<meta property="og:title" content="' . $safeTitle . '">', $html);

    // OG Description (property="og:description")
    $html = preg_replace('/<meta\s+property="og:description"\s+content=".*?"\s*\/?>/is', '<meta property="og:description" content="' . $safeDescription . '">', $html);

    // OG Image (property="og:image")
    // We replace the tag and add secure_url/dimensions for better social media compatibility
    $ogImageReplacement = '<meta property="og:image" content="' . $imagePath . '">';
    if ($protocol === 'https') {
        $ogImageReplacement .= "\n  <meta property=\"og:image:secure_url\" content=\"$imagePath\">";
    }
    $ogImageReplacement .= "\n  <meta property=\"og:image:width\" content=\"1200\">\n  <meta property=\"og:image:height\" content=\"630\">";

    $html = preg_replace('/<meta\s+property="og:image"\s+content=".*?"\s*\/?>/is', $ogImageReplacement, $html);

    // Add/Update og:url and canonical if slug is present
    $currentUrl = $siteUrl . '/post?slug=' . urlencode($slug);

    // Inject og:url if not present (usually near og:type)
    if (!str_contains($html, 'og:url')) {
        $html = preg_replace('/(<meta property="og:type" content="article">)/i', "$1\n  <meta property=\"og:url\" content=\"$currentUrl\">", $html);
    } else {
        $html = preg_replace('/<meta property="og:url" content=".*?">/is', '<meta property="og:url" content="' . $currentUrl . '">', $html);
    }

    // Inject canonical link if not present
    if (!str_contains($html, 'link rel="canonical"')) {
        $html = str_replace('</head>', "  <link rel=\"canonical\" href=\"$currentUrl\">\n</head>", $html);
    } else {
        $html = preg_replace('/<link rel="canonical" href=".*?">/is', '<link rel="canonical" href="' . $currentUrl . '">', $html);
    }
}

// 4. Output the final HTML
header('Content-Type: text/html; charset=utf-8');
echo $html;
