<?php

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\Logger;
use App\Helpers\ResponseHelper;
use App\Helpers\UtilsHelper;
use App\Middleware\AuthMiddleware;
use PDO;

class BlogController
{
    public function createPost()
    {
        // Ensure user is authenticated
        $decoded = AuthMiddleware::handle();
        if ($decoded->role !== "admin" && $decoded->role !== "editor") {
            ResponseHelper::badRequest("You don't have enough privilege to perform this action", []);
        }

        // Validate POST and file input
        $data = $_POST;

        $title = trim($data['title'] ?? '');
        $slug = trim($data['slug'] ?? '');

        // Failsafe: if slug is still empty, generate it from the title
        if (empty($slug) && !empty($title)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
        }

        $excerpt = trim($data['excerpt'] ?? '');
        $content = trim($data['content'] ?? '');
        $meta_title = trim($data['meta_title'] ?? '');
        $canonical_url = trim($data['canonical_url'] ?? '');
        $meta_description = trim($data['meta_description'] ?? '');
        $og_title = trim($data['og_title'] ?? '');
        $og_description = trim($data['og_description'] ?? '');
        $reading_time = (int)($data['reading_time'] ?? 0);
        $status = trim($data['status'] ?? 'Draft');
        $author = trim($data['author'] ?? '');
        $category = trim($data['category'] ?? '');

        $tagsRaw = trim($data['tags'] ?? '');
        $tags = '';
        if ($tagsRaw) {
            // Tagify sends data as: [{"value":"tag1"},{"value":"tag2"}]
            $tagsArray = json_decode($tagsRaw, true);
            if (is_array($tagsArray)) {
                $tagsList = array_map(function ($tag) {
                    return $tag['value'] ?? '';
                }, $tagsArray);
                $tags = implode(', ', array_filter($tagsList));
            } else {
                $tags = $tagsRaw; // Fallback if plain string
            }
        }

        $visibility = trim($data['visibility'] ?? 'public');
        $allow_comments = isset($data['allow_comments']) && $data['allow_comments'] == '1' ? 1 : 0;
        $is_featured = isset($data['is_featured']) && $data['is_featured'] == '1' ? 1 : 0;
        $is_editors_choice = isset($data['is_editors_choice']) && $data['is_editors_choice'] == '1' ? 1 : 0;
        $is_popular = isset($data['is_popular']) && $data['is_popular'] == '1' ? 1 : 0;
        $content_type = trim($data['content_type'] ?? 'text');

        $publish_date = trim($data['publish_date'] ?? '');
        if ($publish_date) {
            // Convert '2026-03-12 14:30' to '2026-03-12 14:30:00' for MySQL DATETIME
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i', $publish_date);
            if (!$dateTime) {
                // Fallback in case the old format accidentally sneaks through during transition
                $dateTime = \DateTime::createFromFormat('m/d/Y', $publish_date);
            }
            if ($dateTime) {
                $publish_date = $dateTime->format('Y-m-d H:i:s');
            }
        }

        if (!$title || !$content || !$status || !$author || !$category) {
            ResponseHelper::badRequest('Required fields (title, content, status, author, category) must be provided', []);
        }

        $featuredImageFile = $_FILES['featured_image'] ?? null;
        $ogImageFile = $_FILES['og_image'] ?? null;

        $featuredImagePath = null;
        if ($featuredImageFile && $featuredImageFile['error'] === UPLOAD_ERR_OK) {
            $featuredImagePath = $this->uploadFile($featuredImageFile, 'blog_images');
        }

        $ogImagePath = null;
        if ($ogImageFile && $ogImageFile['error'] === UPLOAD_ERR_OK) {
            $ogImagePath = $this->uploadFile($ogImageFile, 'blog_images');
        }

        $db = Database::connect();
        $table = 'blog_posts';

        // This relies on UtilsHelper having a generateUniqueRef method
        $postRef = UtilsHelper::generateUniqueRef($table, 'postRef');
        $createdAt = date('Y-m-d H:i:s');

        try {
            $stmt = $db->prepare("
                INSERT INTO $table (
                    postRef, title, slug, excerpt, content, meta_title, canonical_url, 
                    meta_description, og_title, og_description, reading_time, status, 
                    author, category, tags, visibility, allow_comments, is_featured, 
                    is_editors_choice, is_popular, content_type,
                    publish_date, featured_image, og_image, created_at
                ) VALUES (
                    :postRef, :title, :slug, :excerpt, :content, :meta_title, :canonical_url,
                    :meta_description, :og_title, :og_description, :reading_time, :status,
                    :author, :category, :tags, :visibility, :allow_comments, :is_featured,
                    :is_editors_choice, :is_popular, :content_type,
                    :publish_date, :featured_image, :og_image, :created_at
                )
            ");

            $stmt->execute([
                'postRef' => $postRef,
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'content' => $content,
                'meta_title' => $meta_title,
                'canonical_url' => $canonical_url,
                'meta_description' => $meta_description,
                'og_title' => $og_title,
                'og_description' => $og_description,
                'reading_time' => $reading_time,
                'status' => $status,
                'author' => $author,
                'category' => $category,
                'tags' => $tags,
                'visibility' => $visibility,
                'allow_comments' => $allow_comments,
                'is_featured' => $is_featured,
                'is_editors_choice' => $is_editors_choice,
                'is_popular' => $is_popular,
                'content_type' => $content_type,
                'publish_date' => $publish_date ?: null,
                'featured_image' => $featuredImagePath,
                'og_image' => $ogImagePath,
                'created_at' => $createdAt,
            ]);

            $id = $db->lastInsertId();

            ResponseHelper::created([
                'id' => $id,
                'postRef' => $postRef
            ], 'Blog post created successfully');
        } catch (\Exception $e) {
            error_log("Create Blog Post Error: " . $e->getMessage());
            ResponseHelper::internalError('Failed to create blog post. Please try again later.');
        }
    }

    public function getPopularTags()
    {
        try {
            $db = Database::connect();

            // Fetch tags from Published or officially Scheduled posts
            $sql = "
                SELECT tags 
                FROM blog_posts 
                WHERE (status = 'Published' OR (status = 'Scheduled' AND publish_date <= NOW()))
                AND visibility = 'public'
                AND tags IS NOT NULL AND tags != ''
            ";

            $stmt = $db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $tagCounts = [];

            foreach ($results as $tagLine) {
                // Tags are stored as "tag1, tag2, tag3"
                $tags = explode(',', $tagLine);
                foreach ($tags as $tag) {
                    $trimmedTag = trim($tag);
                    if ($trimmedTag) {
                        // Normalize to Title Case for consistent grouping and display
                        $normalizedTag = ucwords(strtolower($trimmedTag));
                        if (!isset($tagCounts[$normalizedTag])) {
                            $tagCounts[$normalizedTag] = 0;
                        }
                        $tagCounts[$normalizedTag]++;
                    }
                }
            }

            // Sort by count descending
            arsort($tagCounts);

            // Limit to top 15
            $popularTags = array_slice($tagCounts, 0, 15, true);

            // Format for response: [{name: "tag", count: 5}, ...]
            $formattedTags = [];
            foreach ($popularTags as $name => $count) {
                $formattedTags[] = [
                    'name' => $name,
                    'count' => $count
                ];
            }

            ResponseHelper::ok([
                'tags' => $formattedTags
            ], 'Popular tags fetched successfully');
        } catch (\Exception $e) {
            error_log("Fetch Popular Tags Error: " . $e->getMessage());
            ResponseHelper::internalError('Failed to fetch popular tags.');
        }
    }

    public function getPosts()
    {
        try {
            $db = Database::connect();

            // Get pagination params
            $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 0;
            $size = isset($_GET['size']) && is_numeric($_GET['size']) ? (int)$_GET['size'] : 10;
            $offset = $page * $size;

            // Optional categorization filtering
            $category = isset($_GET['category']) ? trim($_GET['category']) : null;
            $tag = isset($_GET['tag']) ? trim($_GET['tag']) : null;
            $search = isset($_GET['search']) ? trim($_GET['search']) : null;
            $is_featured = isset($_GET['is_featured']) ? (int)$_GET['is_featured'] : null;
            $is_editors_choice = isset($_GET['is_editors_choice']) ? (int)$_GET['is_editors_choice'] : null;
            $is_popular = isset($_GET['is_popular']) ? (int)$_GET['is_popular'] : null;
            $content_type = isset($_GET['content_type']) ? trim($_GET['content_type']) : null;

            // Admin view vs Public view
            $isAdminView = isset($_GET['admin']) && $_GET['admin'] == '1';

            $baseQuery = " FROM blog_posts ";
            $whereClauses = [];
            $params = [];

            if (!$isAdminView) {
                $whereClauses[] = "(status = 'Published' OR (status = 'Scheduled' AND publish_date <= NOW()))";
                $whereClauses[] = "visibility = 'public'";
            } else {
                // Ensure only admin/editor can use admin view
                $decoded = AuthMiddleware::handle();
                if ($decoded->role !== "admin" && $decoded->role !== "editor") {
                    ResponseHelper::badRequest("You don't have enough privilege to perform this action", []);
                }
            }

            if ($category) {
                $whereClauses[] = "category = :category";
                $params[':category'] = $category;
            }


            if ($tag) {
                $lowerTag = strtolower($tag);
                $whereClauses[] = "(
                    LOWER(REPLACE(tags, ', ', ',')) = :tag 
                    OR LOWER(REPLACE(tags, ', ', ',')) LIKE :tag_start 
                    OR LOWER(REPLACE(tags, ', ', ',')) LIKE :tag_end 
                    OR LOWER(REPLACE(tags, ', ', ',')) LIKE :tag_mid
                )";
                $params[':tag'] = $lowerTag;
                $params[':tag_start'] = $lowerTag . ',%';
                $params[':tag_end'] = '%,' . $lowerTag;
                $params[':tag_mid'] = '%,' . $lowerTag . ',%';
            }

            if ($search) {
                $whereClauses[] = "(title LIKE :search OR excerpt LIKE :search OR tags LIKE :search OR category LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }

            if ($is_featured !== null) {
                $whereClauses[] = "is_featured = :is_featured";
                $params[':is_featured'] = $is_featured;
            }

            if ($is_editors_choice !== null) {
                $whereClauses[] = "is_editors_choice = :is_editors_choice";
                $params[':is_editors_choice'] = $is_editors_choice;
            }

            if ($is_popular !== null) {
                $whereClauses[] = "is_popular = :is_popular";
                $params[':is_popular'] = $is_popular;
            }

            if ($content_type) {
                $whereClauses[] = "content_type = :content_type";
                $params[':content_type'] = $content_type;
            }

            if (!empty($whereClauses)) {
                $baseQuery .= " WHERE " . implode(" AND ", $whereClauses);
            }


            // Get total count
            $countSql = "SELECT COUNT(*) " . $baseQuery;
            $countStmt = $db->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $countStmt->execute();
            $totalElements = (int)$countStmt->fetchColumn();
            $totalPages = (int)ceil($totalElements / $size);

            // Fetch actual paginated, sorted records
            $sql = "
                SELECT 
                    postRef, title, slug, excerpt, reading_time, author, category, tags,
                    publish_date, featured_image, created_at, allow_comments, is_featured,
                    is_editors_choice, is_popular, content_type, status, visibility
                " . $baseQuery . " 
                ORDER BY is_featured DESC, is_editors_choice DESC, COALESCE(publish_date, created_at) DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $size, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode the comma-separated tags string back into a simple array for the frontend UI
            foreach ($posts as &$post) {
                $post['tags'] = $post['tags'] ? array_map('trim', explode(',', $post['tags'])) : [];
            }

            ResponseHelper::ok([
                'result' => $posts,
                'currentPage' => $page,
                'size' => $size,
                'totalElements' => $totalElements,
                'totalPages' => $totalPages,
                'first' => $page === 0,
                'last' => $page >= ($totalPages - 1)
            ], 'Blog posts fetched successfully');
        } catch (\Exception $e) {
            error_log("Fetch Blog Posts Error: " . $e->getMessage());
            ResponseHelper::internalError('Failed to fetch blog posts.');
        }
    }

    public function getPostBySlug($slug)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT 
                    postRef, title, slug, excerpt, content, meta_title, canonical_url, 
                    meta_description, og_title, og_description, reading_time, author, 
                    category, tags, publish_date, featured_image, og_image, created_at, allow_comments,
                    is_featured, is_editors_choice, is_popular, content_type
                FROM blog_posts 
                WHERE slug = :slug 
                AND (status = 'Published' OR (status = 'Scheduled' AND publish_date <= NOW()))
                AND visibility = 'public'
                LIMIT 1
            ");
            $stmt->execute(['slug' => $slug]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                ResponseHelper::notFound('Blog post not found');
            }

            // Decode tags
            $post['tags'] = $post['tags'] ? array_map('trim', explode(',', $post['tags'])) : [];

            ResponseHelper::ok($post, 'Blog post fetched successfully');
        } catch (\Exception $e) {
            error_log("Fetch Blog Post Error (Slug: $slug): " . $e->getMessage());
            ResponseHelper::internalError('Failed to fetch blog post.');
        }
    }

    public function getComments($postRef)
    {
        try {
            $db = Database::connect();

            // 1. Get post ID from postRef
            $stmt = $db->prepare("SELECT id FROM blog_posts WHERE postRef = :postRef LIMIT 1");
            $stmt->execute(['postRef' => $postRef]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                ResponseHelper::notFound('Blog post not found');
            }

            $postId = $post['id'];

            // 2. Fetch ALL approved comments for this post
            $stmt = $db->prepare("
                SELECT id, parent_id, author_name, content, created_at 
                FROM blog_comments 
                WHERE post_id = :post_id AND status = 'Approved' 
                ORDER BY created_at ASC
            ");
            $stmt->execute(['post_id' => $postId]);
            $allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Build recursive tree
            $commentTree = $this->buildCommentTree($allComments);

            ResponseHelper::ok([
                'comments' => $commentTree,
                'total' => count($allComments)
            ], 'Comments fetched successfully');
        } catch (\Exception $e) {
            error_log("Fetch Comments Error (PostRef: $postRef): " . $e->getMessage());
            ResponseHelper::internalError('Failed to fetch comments.');
        }
    }

    private function buildCommentTree(array $comments, $parentId = null)
    {
        $branch = [];
        foreach ($comments as $comment) {
            if ($comment['parent_id'] == $parentId) {
                $children = $this->buildCommentTree($comments, $comment['id']);
                $comment['replies'] = $children;
                $branch[] = $comment;
            }
        }

        // Root level: Newer comments first
        if ($parentId === null) {
            usort($branch, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
        }

        return $branch;
    }

    public function uploadImage()
    {
        AuthMiddleware::handle(); // Requires authentication

        $file = $_FILES['upload'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => ['message' => 'Upload failed']]);
            exit;
        }

        try {
            $path = $this->uploadFile($file, 'blog_content');

            // Output exactly what CKEditor expects:
            echo json_encode(['url' => '/' . $path]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => ['message' => $e->getMessage()]]);
            exit;
        }
    }

    public function addComment($postRef)
    {
        try {
            $db = Database::connect();

            // 1. Verify post exists and allows comments
            $stmt = $db->prepare("SELECT id, allow_comments FROM blog_posts WHERE postRef = :postRef LIMIT 1");
            $stmt->execute(['postRef' => $postRef]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                ResponseHelper::notFound('Blog post not found');
            }

            if ((int)$post['allow_comments'] !== 1) {
                ResponseHelper::badRequest('Comments are disabled for this post');
            }

            // 2. Validate Input
            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $authorName = trim($data['author_name'] ?? '');
            $authorEmail = trim($data['author_email'] ?? '');
            $content = trim($data['content'] ?? '');
            $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

            if (!$authorName || !$authorEmail || !$content) {
                ResponseHelper::badRequest('Name, Email and Content are required');
            }

            if (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                ResponseHelper::badRequest('Invalid email address');
            }

            // 3. Verify parent if provided
            if ($parentId) {
                $parentStmt = $db->prepare("SELECT id FROM blog_comments WHERE id = :id AND post_id = :post_id LIMIT 1");
                $parentStmt->execute(['id' => $parentId, 'post_id' => $post['id']]);
                if (!$parentStmt->fetch()) {
                    ResponseHelper::notFound('Parent comment not found');
                }
            }

            // 4. Insert Comment/Reply
            $stmt = $db->prepare("
                INSERT INTO blog_comments (post_id, parent_id, author_name, author_email, content, status)
                VALUES (:post_id, :parent_id, :author_name, :author_email, :content, 'Pending')
            ");

            $stmt->execute([
                'post_id' => $post['id'],
                'parent_id' => $parentId,
                'author_name' => $authorName,
                'author_email' => $authorEmail,
                'content' => $content
            ]);

            ResponseHelper::created(['id' => $db->lastInsertId()], 'Submission received and awaiting moderation');
        } catch (\Exception $e) {
            error_log("Add Comment Error (PostRef: $postRef): " . $e->getMessage());
            ResponseHelper::internalError('Failed to submit comment.');
        }
    }
    public function deletePost($postRef)
    {
        try {
            // Ensure user is authenticated and is admin/editor
            $decoded = AuthMiddleware::handle();
            if ($decoded->role !== "admin" && $decoded->role !== "editor") {
                ResponseHelper::badRequest("You don't have enough privilege to perform this action", []);
            }

            $db = Database::connect();

            // Fetch image paths before deletion
            $stmt = $db->prepare("SELECT featured_image, og_image FROM blog_posts WHERE postRef = :postRef LIMIT 1");
            $stmt->execute(['postRef' => $postRef]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                ResponseHelper::notFound('Blog post not found');
            }

            // Delete files from filesystem
            $publicDir = __DIR__ . '/../../public/';
            if (!empty($post['featured_image'])) {
                $featuredPath = $publicDir . $post['featured_image'];
                if (file_exists($featuredPath)) {
                    unlink($featuredPath);
                }
            }
            if (!empty($post['og_image'])) {
                $ogPath = $publicDir . $post['og_image'];
                if (file_exists($ogPath)) {
                    unlink($ogPath);
                }
            }

            // Delete database record
            $stmt = $db->prepare("DELETE FROM blog_posts WHERE postRef = :postRef LIMIT 1");
            $stmt->execute(['postRef' => $postRef]);

            ResponseHelper::ok([], 'Blog post and associated assets deleted successfully');
        } catch (\Exception $e) {
            error_log("Delete Blog Post Error (PostRef: $postRef): " . $e->getMessage());
            ResponseHelper::internalError('Failed to delete blog post.');
        }
    }

    public function getPostByRef($postRef)
    {
        try {
            // Ensure user is authenticated
            $decoded = AuthMiddleware::handle();
            if ($decoded->role !== "admin" && $decoded->role !== "editor") {
                ResponseHelper::badRequest("You don't have enough privilege to perform this action", []);
            }

            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT 
                    postRef, title, slug, excerpt, content, meta_title, canonical_url, 
                    meta_description, og_title, og_description, reading_time, author, 
                    category, tags, publish_date, featured_image, og_image, created_at, allow_comments,
                    is_featured, is_editors_choice, is_popular, content_type, status, visibility
                FROM blog_posts 
                WHERE postRef = :postRef 
                LIMIT 1
            ");
            $stmt->execute(['postRef' => $postRef]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$post) {
                ResponseHelper::notFound('Blog post not found');
            }

            // Decode tags
            if ($post['tags']) {
                $tagsArr = array_map('trim', explode(',', $post['tags']));
                $post['tags'] = array_map(function ($t) {
                    return ['value' => $t];
                }, $tagsArr);
            } else {
                $post['tags'] = [];
            }

            ResponseHelper::ok(['post' => $post], 'Blog post fetched successfully');
        } catch (\Exception $e) {
            error_log("Fetch Blog Post Error (Ref: $postRef): " . $e->getMessage());
            ResponseHelper::internalError('Failed to fetch blog post.');
        }
    }

    public function updatePost($postRef)
    {
        try {
            // Ensure user is authenticated
            $decoded = AuthMiddleware::handle();
            if ($decoded->role !== "admin" && $decoded->role !== "editor") {
                ResponseHelper::badRequest("You don't have enough privilege to perform this action", []);
            }

            // Parse PUT multipart/form-data
            $parsed = UtilsHelper::parseMultipartPut();
            $data = $parsed['fields'];
            $files = $parsed['files'];

            $db = Database::connect();

            // 1. Verify post exists
            $stmt = $db->prepare("SELECT * FROM blog_posts WHERE postRef = :postRef LIMIT 1");
            $stmt->execute(['postRef' => $postRef]);
            $existingPost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingPost) {
                ResponseHelper::notFound('Blog post not found');
            }

            // 2. Validate Input
            $title = trim($data['title'] ?? '');
            $slug = trim($data['slug'] ?? '');
            if (empty($slug) && !empty($title)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
                $slug = preg_replace('/-+/', '-', $slug);
                $slug = trim($slug, '-');
            }

            $excerpt = trim($data['excerpt'] ?? '');
            $content = trim($data['content'] ?? '');
            $meta_title = trim($data['meta_title'] ?? '');
            $canonical_url = trim($data['canonical_url'] ?? '');
            $meta_description = trim($data['meta_description'] ?? '');
            $og_title = trim($data['og_title'] ?? '');
            $og_description = trim($data['og_description'] ?? '');
            $reading_time = (int)($data['reading_time'] ?? 0);
            $status = trim($data['status'] ?? 'Draft');
            $author = trim($data['author'] ?? '');
            $category = trim($data['category'] ?? '');

            $tagsRaw = trim($data['tags'] ?? '');
            $tags = '';
            if ($tagsRaw) {
                $tagsArray = json_decode($tagsRaw, true);
                if (is_array($tagsArray)) {
                    $tagsList = array_map(function ($tag) {
                        return $tag['value'] ?? '';
                    }, $tagsArray);
                    $tags = implode(', ', array_filter($tagsList));
                } else {
                    $tags = $tagsRaw;
                }
            }

            $visibility = trim($data['visibility'] ?? 'public');
            $allow_comments = isset($data['allow_comments']) && $data['allow_comments'] == '1' ? 1 : 0;
            $is_featured = isset($data['is_featured']) && $data['is_featured'] == '1' ? 1 : 0;
            $is_editors_choice = isset($data['is_editors_choice']) && $data['is_editors_choice'] == '1' ? 1 : 0;
            $is_popular = isset($data['is_popular']) && $data['is_popular'] == '1' ? 1 : 0;
            $content_type = trim($data['content_type'] ?? 'text');

            $publish_date = trim($data['publish_date'] ?? '');
            if ($publish_date) {
                $dateTime = \DateTime::createFromFormat('Y-m-d H:i', $publish_date);
                if ($dateTime) {
                    $publish_date = $dateTime->format('Y-m-d H:i:s');
                }
            }

            if (!$title || !$content || !$status || !$author || !$category) {
                ResponseHelper::badRequest('Required fields (title, content, status, author, category) must be provided');
            }

            // 3. Handle Images
            $featuredImagePath = $existingPost['featured_image'];
            $ogImagePath = $existingPost['og_image'];

            $publicDir = __DIR__ . '/../../public/';

            // Featured Image: Upload first, then unlink
            if (isset($files['featured_image']) && $files['featured_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['featured_image']['name'], PATHINFO_EXTENSION));
                $filename = uniqid('img_', true) . '.' . $ext;
                $newFeaturedPath = 'assets/media/blog_images/' . $filename;

                if (rename($files['featured_image']['tmp_name'], $publicDir . $newFeaturedPath)) {
                    // Delete old image if it exists
                    if ($existingPost['featured_image'] && file_exists($publicDir . $existingPost['featured_image'])) {
                        unlink($publicDir . $existingPost['featured_image']);
                    }
                    $featuredImagePath = $newFeaturedPath;
                }
            }

            // OG Image: Upload first, then unlink
            if (isset($files['og_image']) && $files['og_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['og_image']['name'], PATHINFO_EXTENSION));
                $filename = uniqid('img_', true) . '.' . $ext;
                $newOgPath = 'assets/media/blog_images/' . $filename;

                if (rename($files['og_image']['tmp_name'], $publicDir . $newOgPath)) {
                    // Delete old image if it exists
                    if ($existingPost['og_image'] && file_exists($publicDir . $existingPost['og_image'])) {
                        unlink($publicDir . $existingPost['og_image']);
                    }
                    $ogImagePath = $newOgPath;
                }
            }

            // 4. Update Database
            $stmt = $db->prepare("
                UPDATE blog_posts SET
                    title = :title, slug = :slug, excerpt = :excerpt, content = :content, 
                    meta_title = :meta_title, canonical_url = :canonical_url, 
                    meta_description = :meta_description, og_title = :og_title, 
                    og_description = :og_description, reading_time = :reading_time, 
                    status = :status, author = :author, category = :category, tags = :tags, 
                    visibility = :visibility, allow_comments = :allow_comments, 
                    is_featured = :is_featured, is_editors_choice = :is_editors_choice, 
                    is_popular = :is_popular, content_type = :content_type,
                    publish_date = :publish_date, featured_image = :featured_image, og_image = :og_image
                WHERE postRef = :postRef
            ");

            $stmt->execute([
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'content' => $content,
                'meta_title' => $meta_title,
                'canonical_url' => $canonical_url,
                'meta_description' => $meta_description,
                'og_title' => $og_title,
                'og_description' => $og_description,
                'reading_time' => $reading_time,
                'status' => $status,
                'author' => $author,
                'category' => $category,
                'tags' => $tags,
                'visibility' => $visibility,
                'allow_comments' => $allow_comments,
                'is_featured' => $is_featured,
                'is_editors_choice' => $is_editors_choice,
                'is_popular' => $is_popular,
                'content_type' => $content_type,
                'publish_date' => $publish_date ?: null,
                'featured_image' => $featuredImagePath,
                'og_image' => $ogImagePath,
                'postRef' => $postRef
            ]);

            ResponseHelper::ok([], 'Blog post updated successfully');
        } catch (\Exception $e) {
            error_log("Update Blog Post Error (Ref: $postRef): " . $e->getMessage());
            ResponseHelper::internalError('Failed to update blog post. ' . $e->getMessage());
        }
    }



    private function uploadFile($file, $subfolder = 'images')
    {
        $uploadDir = __DIR__ . '/../../public/assets/media/' . $subfolder . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            throw new \Exception('Invalid image format.');
        }

        // Validate file size (Max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new \Exception('File size exceeds the maximum limit of 5MB.');
        }

        $filename = uniqid('img_', true) . '.' . $ext;
        $path = 'assets/media/' . $subfolder . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            throw new \Exception('Failed to save image.');
        }

        return $path;
    }
}
