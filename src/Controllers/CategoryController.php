<?php

namespace App\Controllers;

use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Helpers\ResponseHelper;
use PDO;

class CategoryController
{
    /**
     * Parse the request body (JSON or form data).
     *
     * @return array
     */
    private function getRequestData(): array
    {
        $contentType = $_SERVER["CONTENT_TYPE"] ?? '';

        if (strpos($contentType, "application/json") !== false) {
            $json = file_get_contents('php://input');
            return json_decode($json, true) ?? [];
        }

        return $_POST;
    }

    /**
     * Auto-generate a unique slug.
     */
    private function generateSlug(PDO $db, string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

        // Ensure unique slug
        $originalSlug = $slug;
        $count = 1;

        $stmt = $db->prepare("SELECT COUNT(*) FROM blog_categories WHERE slug = ?");
        
        while (true) {
            $stmt->execute([$slug]);
            $exists = $stmt->fetchColumn();
            if (!$exists) {
                break;
            }
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Provide a standard JSON response.
     */
    private function jsonResponse(bool $success, string $message, array $data = [], int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ]);
        exit;
    }

    /**
     * Create a new category.
     * POST /api/blog-categories
     */
    public function createCategory()
    {
        // Ensure user is authenticated
        $decoded = AuthMiddleware::handle();
        if ($decoded->role !== "admin" && $decoded->role !== "editor") {
            return $this->jsonResponse(false, "You don't have enough privilege to perform this action", [], 400);
        }

        $data = $this->getRequestData();
        $name = strtolower(trim($data['name'] ?? ''));
        $description = trim($data['description'] ?? '');

        if (empty($name)) {
             return $this->jsonResponse(false, "Category name is required.", [], 400);
        }
        
        try {
            $db = Database::connect();

            // Check for duplicate category name (case-insensitive)
            $stmt = $db->prepare("SELECT COUNT(*) FROM blog_categories WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                return $this->jsonResponse(false, "Category name already exists.", [], 409);
            }

            $slug = $this->generateSlug($db, $name);
            $categoryRef = 'CAT-' . strtoupper(substr(uniqid(), -6));
            
            $stmt = $db->prepare("
                INSERT INTO blog_categories (categoryRef, name, slug, description, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([$categoryRef, $name, $slug, $description]);

            // Fetch the inserted record
            $fetchStmt = $db->prepare("SELECT * FROM blog_categories WHERE categoryRef = ?");
            $fetchStmt->execute([$categoryRef]);
            $category = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            return $this->jsonResponse(true, "Category created successfully", ['category' => $category], 201);
            
        } catch (\Exception $e) {
            return $this->jsonResponse(false, "Failed to create category: " . $e->getMessage(), [], 500);
        }
    }

    /**
     * Fetch all available categories.
     * GET /api/blog-categories
     */
    public function getCategories()
    {
        try {
            $db = Database::connect();
            $stmt = $db->query("
                SELECT c.*, COUNT(b.id) as post_count 
                FROM blog_categories c 
                LEFT JOIN blog_posts b ON c.name = b.category 
                AND (b.status = 'Published' OR (b.status = 'Scheduled' AND b.publish_date <= NOW()))
                AND b.visibility = 'public'
                GROUP BY c.id 
                ORDER BY c.name ASC
            ");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->jsonResponse(true, "Categories retrieved successfully", ['categories' => $categories]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, "Failed to retrieve categories: " . $e->getMessage(), [], 500);
        }
    }
}
