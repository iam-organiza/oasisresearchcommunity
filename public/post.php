<?php
/**
 * SEO & Open Graph Handler for Blog Posts
 * This script serves the post.html template with dynamically injected meta tags.
 */

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
        // Fallback to static if database connection fails
        error_log("SEO Handler Error: " . $e->getMessage());
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
    $title = htmlspecialchars($post['og_title'] ?: $post['title']);
    $description = $post['og_description'] ?: $post['excerpt'];
    
    // Clean and truncate description
    $description = strip_tags($description);
    $description = str_replace(["\r", "\n"], ' ', $description);
    if (mb_strlen($description) > 165) {
        $description = mb_substr($description, 0, 162) . '...';
    }
    $description = htmlspecialchars($description);

    // Build absolute URLs for OG
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $siteUrl = "$protocol://$host";
    
    // OG Image
    $imagePath = $post['og_image'] ?: $post['featured_image'];
    if ($imagePath) {
        if (!str_starts_with($imagePath, 'http')) {
            $imagePath = $siteUrl . '/' . ltrim($imagePath, '/');
        }
    } else {
        $imagePath = $siteUrl . '/orc/assets/media/logos/default-logo.png';
    }

    // Replacement logic
    // Title
    $html = preg_replace('/<title>.*?<\/title>/i', "<title>$title - OASIS Research Community</title>", $html);
    
    // Description
    $html = preg_replace('/<meta name="description" content=".*?">/i', '<meta name="description" content="' . $description . '">', $html);
    
    // OG Title
    $html = preg_replace('/<meta property="og:title" content=".*?">/i', '<meta property="og:title" content="' . $title . '">', $html);
    
    // OG Description
    $html = preg_replace('/<meta property="og:description" content=".*?">/i', '<meta property="og:description" content="' . $description . '">', $html);
    
    // OG Image
    $html = preg_replace('/<meta property="og:image" content=".*?">/i', '<meta property="og:image" content="' . $imagePath . '">', $html);

    // Add og:url and canonical if slug is present
    $currentUrl = $siteUrl . '/post?slug=' . urlencode($slug);
    
    if (!str_contains($html, 'og:url')) {
        $html = str_replace('<meta property="og:type" content="article">', '<meta property="og:type" content="article">' . "\n  " . '<meta property="og:url" content="' . $currentUrl . '">', $html);
    }
    
    if (!str_contains($html, 'link rel="canonical"')) {
        $html = str_replace('</head>', '  <link rel="canonical" href="' . $currentUrl . '">' . "\n" . '</head>', $html);
    }
}

// 4. Output the final HTML
header('Content-Type: text/html; charset=utf-8');
echo $html;
