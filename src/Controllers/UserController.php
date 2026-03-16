<?php

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\Logger;
use App\Helpers\ResponseHelper;
use App\Helpers\UtilsHelper;
use App\Middleware\AuthMiddleware;
use PDO;

class UserController
{
    public function user()
    {
        try {
            $decoded = (array) AuthMiddleware::handle();
            $subset = array_intersect_key($decoded, array_flip(['userId', 'userRef', 'email', 'role', 'firstName', 'lastName']));
            ResponseHelper::ok([
                'user' => $subset
            ], 'You are authenticated');
        } catch (\Exception $e) {
            ResponseHelper::unauthorized('Unauthorized', []);
        }
    }

    public function addFeaturedMember()
    {
        // Ensure user is authenticated
        $decoded = AuthMiddleware::handle();
        if ($decoded->role !== "admin") {
            ResponseHelper::badRequest('You don\'t have enough privilege to perform this action', []);
        }

        // Validate POST and file input
        $data = $_POST;
        $file = $_FILES['avatar'] ?? null;

        $title = trim($data['title'] ?? '');
        $firstName = trim($data['first_name'] ?? '');
        $middleName = trim($data['middle_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $position = trim($data['position'] ?? '');
        $speciality = trim($data['speciality'] ?? '');
        $jobDescription = trim($data['job_description'] ?? '');

        if (!$title || !$firstName || !$lastName || !$position || !$jobDescription || !$file) {
            ResponseHelper::badRequest('All required fields must be provided', []);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Logger::getLogger()->error('Upload failed', ['error_code' => $file['error']]);

            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $message = 'File exceeds the maximum size allowed by server settings.';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $message = 'File exceeds the maximum size allowed by the form.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message = 'File was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message = 'No file was uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = 'Missing a temporary folder.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = 'Failed to write file to disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $message = 'A PHP extension stopped the file upload.';
                    break;
                default:
                    $message = 'Unknown upload error.';
            }

            ResponseHelper::badRequest($message, ['code' => $file['error']]);
        }


        // Upload avatar
        $uploadDir = __DIR__ . '/../../public/assets/media/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            ResponseHelper::badRequest('Invalid image format. Only JPG, JPEG, PNG are allowed.', []);
        }

        $avatarFilename = uniqid('avatar_', true) . '.' . $ext;
        $avatarPath = 'assets/media/avatars/' . $avatarFilename;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $avatarFilename)) {
            ResponseHelper::internalError('Failed to save avatar image', []);
        }

        // Prepare DB insertion
        $db = Database::connect();
        $table = 'featured_members';
        $memberRef = UtilsHelper::generateUniqueRef($table, 'memberRef');
        $createdAt = date('Y-m-d H:i:s');

        try {
            $stmt = $db->prepare("
                INSERT INTO $table (memberRef, avatar, title, first_name, middle_name, last_name, position, speciality, job_description, created_at)
                VALUES (:memberRef, :avatar, :title, :first_name, :middle_name, :last_name, :position, :speciality, :job_description, :created_at)
            ");

            $stmt->execute([
                'memberRef' => $memberRef,
                'avatar' => $avatarPath,
                'title' => $title,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'position' => $position,
                'speciality' => $speciality,
                'job_description' => $jobDescription,
                'created_at' => $createdAt,
            ]);

            $id = $db->lastInsertId();

            ResponseHelper::created([
                'id' => $id,
                'memberRef' => $memberRef,
                'avatar' => $avatarPath,
                'title' => $title,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'position' => $position,
                'speciality' => $speciality,
                'job_description' => $jobDescription,
                'created_at' => $createdAt,
            ], 'Featured member added successfully');
        } catch (\Exception $e) {
            error_log("Add Featured Member Error: " . $e->getMessage());
            ResponseHelper::internalError('Failed to add featured member. Please try again later.');
        }
    }

    public function updateFeaturedMember($memberRef)
    {
        // Parse PUT multipart/form-data
        $parsed = UtilsHelper::parseMultipartPut();
        $data = $parsed['fields'];
        $file = $parsed['files']['avatar'] ?? null;

        // Ensure user is authenticated and admin
        $decoded = AuthMiddleware::handle();
        if ($decoded->role !== "admin") {
            ResponseHelper::badRequest("You don't have enough privilege to perform this action", []);
        }

        // Fetch existing member
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM featured_members WHERE memberRef = :memberRef");
        $stmt->bindParam(':memberRef', $memberRef, PDO::PARAM_STR);
        $stmt->execute();
        $existingMember = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingMember) {
            ResponseHelper::notFound("Featured member not found", []);
        }

        // Extract fields
        $title = trim($data['title'] ?? '');
        $firstName = trim($data['first_name'] ?? '');
        $middleName = trim($data['middle_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $position = trim($data['position'] ?? '');
        $speciality = trim($data['speciality'] ?? '');
        $jobDescription = trim($data['job_description'] ?? '');

        if (!$title || !$firstName || !$lastName || !$position || !$jobDescription) {
            ResponseHelper::badRequest("All required fields must be provided", []);
        }

        // Default avatar (keep existing if no new upload)
        $avatarPath = $existingMember['avatar'];

        // Process new avatar IF provided
        if ($file) {
            if ($file['error'] !== 0) {
                Logger::getLogger()->error('Upload failed', ['error_code' => $file['error']]);
                ResponseHelper::badRequest("Image upload failed", ['code' => $file['error']]);
            }

            $uploadDir = __DIR__ . '/../../public/assets/media/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                ResponseHelper::badRequest("Invalid image format. Only JPG, JPEG, PNG allowed", []);
            }

            $newFilename = uniqid('avatar_', true) . '.' . $ext;
            $newPath = $uploadDir . $newFilename;

            // Move uploaded file manually (PUT uploads are stored at temp path)
            if (!rename($file['tmp_name'], $newPath)) {
                ResponseHelper::internalError("Failed to save uploaded avatar", []);
            }

            // Delete old avatar
            $oldFile = __DIR__ . '/../../public/' . $avatarPath;
            if (file_exists($oldFile)) unlink($oldFile);

            // Save new path
            $avatarPath = 'assets/media/avatars/' . $newFilename;
        }

        // Update DB
        try {
            $stmt = $db->prepare("
            UPDATE featured_members
            SET 
                avatar = :avatar,
                title = :title,
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                position = :position,
                speciality = :speciality,
                job_description = :job_description
            WHERE memberRef = :memberRef
        ");

            $stmt->execute([
                'avatar' => $avatarPath,
                'title' => $title,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'position' => $position,
                'speciality' => $speciality,
                'job_description' => $jobDescription,
                'memberRef' => $memberRef
            ]);

            ResponseHelper::ok([
                'memberRef' => $memberRef,
                'avatar' => $avatarPath,
                'title' => $title,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'position' => $position,
                'speciality' => $speciality,
                'job_description' => $jobDescription,
                'created_at' => $existingMember['created_at']
            ], 'Featured member updated successfully');
        } catch (\Exception $e) {
            error_log("Update Featured Member Error (MemberRef: $memberRef): " . $e->getMessage());
            ResponseHelper::internalError("Failed to update featured member.");
        }
    }



    public function getFeaturedMembers()
    {
        // AuthMiddleware::handle();
        try {
            $db = Database::connect();

            // Get pagination params
            $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 0;
            $size = isset($_GET['size']) && is_numeric($_GET['size']) ? (int)$_GET['size'] : 10;
            $offset = $page * $size;

            // Search param
            $search = isset($_GET['search']) ? trim($_GET['search']) : null;
            $searchQuery = '';
            $params = [];

            if (!empty($search)) {
                $searchQuery = "WHERE 
                first_name LIKE :search OR 
                last_name LIKE :search OR 
                middle_name LIKE :search OR 
                title LIKE :search OR 
                speciality LIKE :search OR 
                position LIKE :search OR 
                job_description LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }

            // Total count with search
            $countSql = "SELECT COUNT(*) FROM featured_members $searchQuery";
            $countStmt = $db->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $countStmt->execute();
            $totalElements = (int)$countStmt->fetchColumn();
            $totalPages = (int)ceil($totalElements / $size);

            // Fetch paginated results with search
            $sql = "SELECT 
                    id, 
                    memberRef, 
                    avatar, 
                    title, 
                    first_name, 
                    middle_name, 
                    last_name, 
                    position, 
                    speciality, 
                    job_description,
                    created_at 
                FROM featured_members
                $searchQuery
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

            $stmt = $db->prepare($sql);

            // Bind search if applicable
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            $stmt->bindValue(':limit', $size, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Response
            ResponseHelper::ok([
                'result' => $members,
                'currentPage' => $page,
                'size' => $size,
                'totalElements' => $totalElements,
                'totalPages' => $totalPages,
                'first' => $page === 0,
                'last' => $page >= ($totalPages - 1)
            ], 'Featured members fetched successfully');
        } catch (\Exception $e) {
            error_log("Fetch Featured Members Error: " . $e->getMessage());
            ResponseHelper::internalError('Failed to fetch featured members.');
        }
    }

    public function getFeaturedMember($memberRef)
    {
        // Ensure user is authenticated
        AuthMiddleware::handle();

        try {
            $db = Database::connect();

            $stmt = $db->prepare("
            SELECT 
                id,
                memberRef,
                avatar,
                title,
                first_name,
                middle_name,
                last_name,
                position,
                speciality,
                job_description,
                created_at
            FROM featured_members
            WHERE memberRef = :memberRef
            LIMIT 1
        ");

            $stmt->bindParam(':memberRef', $memberRef, PDO::PARAM_STR);
            $stmt->execute();

            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$member) {
                ResponseHelper::notFound("Featured member not found", []);
            }

            ResponseHelper::ok([
                'member' => $member
            ], 'Featured member fetched successfully');
        } catch (\Exception $e) {
            error_log("Fetch Featured Member Error (MemberRef: $memberRef): " . $e->getMessage());
            ResponseHelper::internalError('Failed to fetch featured member.');
        }
    }


    public function deleteFeaturedMember($memberRef)
    {
        // Ensure user is authenticated
        AuthMiddleware::handle();

        try {
            $db = Database::connect();

            // First, fetch the member to ensure they exist and to get the avatar path
            $stmt = $db->prepare("SELECT avatar FROM featured_members WHERE memberRef = :memberRef");
            $stmt->bindParam(':memberRef', $memberRef, PDO::PARAM_STR);
            $stmt->execute();

            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$member) {
                ResponseHelper::notFound("Featured member not found", []);
            }

            // Begin transaction
            $db->beginTransaction();

            // Delete the member record
            $deleteStmt = $db->prepare("DELETE FROM featured_members WHERE memberRef = :memberRef");
            $deleteStmt->bindParam(':memberRef', $memberRef, PDO::PARAM_STR);
            $deleteStmt->execute();

            // Commit before deleting the file, in case the file doesn't exist
            $db->commit();

            // Delete the avatar file if it exists
            $avatarPath = __DIR__ . '/../../public/' . $member['avatar'];
            if (file_exists($avatarPath)) {
                unlink($avatarPath);
            }

            ResponseHelper::ok([], "Featured member deleted successfully");
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Delete Featured Member Error (MemberRef: $memberRef): " . $e->getMessage());
            ResponseHelper::internalError("Failed to delete featured member.");
        }
    }
}
