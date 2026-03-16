<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Auth;
use App\Middleware\AuthMiddleware;
use App\Helpers\ResponseHelper;
use App\Helpers\UtilsHelper;
use App\Constants\TableNames;
use App\Helpers\MailHelper;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication Endpoints"
 * )
 */
class AuthController
{
    public function login()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            ResponseHelper::unauthorized('Invalid credentials', []);
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $remaining = strtotime($user['locked_until']) - time();
            ResponseHelper::locked('Account is temporarily locked. Try again in ' . ceil($remaining / 60) . ' minute(s).', []);
        }

        if (!password_verify($password, $user['password'])) {
            $attempts = $user['login_attempts'] + 1;

            if ($attempts >= 5) {
                $lockedUntil = date('Y-m-d H:i:s', time() + 15 * 60); // 15 minutes lock
                $updateStmt = $db->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
                $updateStmt->execute([$attempts, $lockedUntil, $user['id']]);
                ResponseHelper::locked('Account locked after 5 failed attempts. Try again in 15 minutes.', []);
            } else {
                $updateStmt = $db->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                $updateStmt->execute([$attempts, $user['id']]);
                ResponseHelper::unauthorized("Invalid credentials. Attempt $attempts of 5.", []);
            }
        }

        $resetStmt = $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
        $resetStmt->execute([$user['id']]);

        $stmtProfile = $db->prepare("SELECT * FROM profiles WHERE user_ref = ?");
        $stmtProfile->execute([$user['user_ref']]);
        $profile = $stmtProfile->fetch();

        $payload = [
            'userId'    => $user['id'],
            'userRef'   => $user['user_ref'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'firstName' => $profile['first_name'] ?? '',
            'lastName'  => $profile['last_name'] ?? '',
        ];

        $accessToken = Auth::generateToken($payload);
        $refreshToken = Auth::generateToken([...$payload, 'refresh' => true], 7 * 24 * 60 * 60); // 7 days

        ResponseHelper::ok([
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ], 'Login successful');
    }

    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="user@example.com"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 example="StrongPass123!"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP has been sent to the user's email",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP has been sent to user@example.com"),
     *             @OA\Property(property="data", type="object", example={})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Email and password are required"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Email already in use",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Email already in use"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to register user"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function register()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');
        $firstname = trim($data['firstName'] ?? '');
        $lastname = trim($data['lastName'] ?? '');

        if (!$email || !$password || !$firstname || !$lastname) {
            ResponseHelper::badRequest('All fields are required', []);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::badRequest('Invalid email format', []);
        }

        $passwordErrors = UtilsHelper::validatePassword($password);
        if (!empty($passwordErrors)) {
            ResponseHelper::badRequest('Invalid password', $passwordErrors);
        }

        $db = Database::connect();
        $table = TableNames::USERS;
        $userRef = UtilsHelper::generateUniqueRef($table, 'user_ref');
        $verificationCode = UtilsHelper::generateNumericOTP(6);

        // Check if user already exists
        $stmt = $db->prepare("SELECT * FROM $table WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            ResponseHelper::conflict('Email already in use', []);
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Begin transaction
            $db->beginTransaction();

            // Insert into users table
            $stmtUser = $db->prepare("INSERT INTO users (`user_ref`, `email`, `password`, `verification_code`) VALUES (?, ?, ?, ?)");
            $stmtUser->execute([$userRef, $email, $hashedPassword, $verificationCode]);

            // Insert into profiles table
            $stmtProfile = $db->prepare("INSERT INTO profiles (`user_ref`, `first_name`, `last_name`) VALUES (?, ?, ?)");
            $stmtProfile->execute([$userRef, $firstname, $lastname]);

            // Commit transaction
            $db->commit();

            MailHelper::sendActivationEmail($email, $verificationCode);

            ResponseHelper::created(['email' => $email], 'OTP has been sent to ' . $email);
        } catch (\Exception $e) {
            // Rollback transaction if any failure occurs
            $db->rollBack();
            error_log("Register User Error: " . $e->getMessage());
            ResponseHelper::internalError('Failed to register user. Please try again later.');
        }
    }


    public function verifyOtp()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? '');
        $otp = trim($data['otp'] ?? '');

        if (!$email || !$otp) {
            ResponseHelper::badRequest('Email and OTP are required', []);
        }

        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            ResponseHelper::notFound('User not found', []);
        }

        if ($user['verified']) {
            ResponseHelper::conflict('Email already verified', []);
        }

        if ($user['verification_code'] !== $otp) {
            ResponseHelper::unauthorized('Invalid OTP', []);
        }

        $table = TableNames::USERS;

        $stmt = $db->prepare("UPDATE $table SET verified = 1, verification_code = NULL, verified_at = NOW() WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);

        ResponseHelper::ok([], 'Email verified successfully');
    }

    public function resendOtp()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? '');

        if (!$email) {
            ResponseHelper::badRequest('Email is required', []);
        }

        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            ResponseHelper::notFound('User not found', []);
        }

        if ($user['verified']) {
            ResponseHelper::conflict('Email already verified', []);
        }

        $newOtp = UtilsHelper::generateNumericOTP(6);

        $stmt = $db->prepare("UPDATE users SET verification_code = ? WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$newOtp, $email]);

        MailHelper::sendActivationEmail($email, $newOtp);

        ResponseHelper::ok([], 'A new OTP has been sent to your email');
    }

    public function refreshToken()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['refreshToken'] ?? '';

        $decoded = Auth::validateToken($token);
        if (!isset($decoded->refresh)) {
            ResponseHelper::badRequest('Invalid refresh token', []);
        }

        $newToken = Auth::generateToken(['userId' => $decoded->id, 'userRef' => $decoded->user_ref, 'email' => $decoded->email]);

        ResponseHelper::ok([
            'accessToken' => $newToken
        ], 'Valid refresh token');
    }
}
