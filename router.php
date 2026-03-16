<?php

// sudo php -S localhost:80 -t public router.php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicPath = __DIR__ . '/public';


// 1. Let PHP built-in server serve static files
$staticFile = realpath($publicPath . $uri);

if ($uri !== '/' && $staticFile && str_starts_with($staticFile, $publicPath) && is_file($staticFile)) {
    return false;
}

// 2. Custom routes (like .htaccess rewrites)
$routes = [
    ''                            => 'orc/index.html',
    '/'                           => 'orc/index.html',
    '/welcome'                    => 'orc/index.html',
    '/signup'                     => 'orc/signup.html',
    '/signin'                     => 'orc/signin.html',
    '/login'                      => 'orc/signin.html',
    '/verify'                     => 'orc/verify.html',
    '/admin'                      => 'orc/admin/index.html',
    '/admin/featured-members'     => 'orc/admin/landing-page/featured-members/index.html',
    '/admin/create-blog-post'     => 'orc/admin/blog/create-blog-post/index.html',
    '/blog'                       => 'orc/blog/index.html',
    '/post'                       => 'post.php',
    '/posts'                      => 'orc/blog/posts.html',
    '/favicon.ico'                => 'orc/assets/media/logos/favicon.ico',
];

// Normalize URI
$cleanUri = rtrim($uri, '/');

// Basic check for set routes
if (isset($routes[$cleanUri])) {
    $target = $publicPath . '/' . $routes[$cleanUri];
    if (file_exists($target)) {
        // Set content type for HTML files
        if (str_ends_with($target, '.html')) {
            header('Content-Type: text/html; charset=utf-8');
        } elseif (str_ends_with($target, '.ico')) {
            header('Content-Type: image/x-icon');
        } elseif (str_ends_with($target, '.png')) {
            header('Content-Type: image/png');
        }
        readfile($target);
        return true;
    } else {
        http_response_code(404);
        $notFoundPage = $publicPath . '/error-404.html';
        if (file_exists($notFoundPage)) {
            readfile($notFoundPage);
        } else {
            echo "Not found: " . htmlspecialchars($routes[$cleanUri]);
        }
        return true;
    }
}

// 3. Fallback to index.php or show 404 if not found
$fallbackIndex = $publicPath . '/index.php';
if (file_exists($fallbackIndex)) {
    require_once $fallbackIndex;
} else {
    http_response_code(404);
    $notFoundPage = $publicPath . '/error-404.html';
    if (file_exists($notFoundPage)) {
        readfile($notFoundPage);
    } else {
        echo "404 Not Found";
    }
}
