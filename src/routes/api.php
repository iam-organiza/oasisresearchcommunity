<?php

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\UtilsController;
use App\Controllers\BlogController;
use App\Controllers\CategoryController;

$r->addGroup('/api', function ($r) {
    $r->addRoute('GET', '/health', [UtilsController::class, 'health']);

    $r->addRoute('POST', '/register', [AuthController::class, 'register']);
    $r->addRoute('POST', '/verify-otp', [AuthController::class, 'verifyOtp']);
    $r->addRoute('POST', '/resend-otp', [AuthController::class, 'resendOtp']);
    $r->addRoute('POST', '/refresh-token', [AuthController::class, 'refreshToken']);
    $r->addRoute('POST', '/login', [AuthController::class, 'login']);

    $r->addRoute('GET', '/user', [UserController::class, 'user']);

    $r->post('/featured-members', [UserController::class, 'addFeaturedMember']);
    $r->get('/featured-members', [UserController::class, 'getFeaturedMembers']);
    $r->get('/featured-members/{memberRef}', [UserController::class, 'getFeaturedMember']);
    $r->addRoute('DELETE', '/featured-members/{memberRef}', [UserController::class, 'deleteFeaturedMember']);
    $r->put('/featured-members/{memberRef}', [UserController::class, 'updateFeaturedMember']);

    $r->get('/blog-posts', [BlogController::class, 'getPosts']);
    $r->get('/blog-posts/{slug}', [BlogController::class, 'getPostBySlug']);
    $r->get('/blog-posts/ref/{postRef}', [BlogController::class, 'getPostByRef']);
    $r->get('/blog-posts/{postRef}/comments', [BlogController::class, 'getComments']);
    $r->post('/blog-posts', [BlogController::class, 'createPost']);
    $r->put('/blog-posts/{postRef}', [BlogController::class, 'updatePost']);
    $r->addRoute('DELETE', '/blog-posts/{postRef}', [BlogController::class, 'deletePost']);

    $r->post('/blog-posts/{postRef}/comments', [BlogController::class, 'addComment']);
    $r->post('/upload-image', [BlogController::class, 'uploadImage']);

    $r->get('/blog-categories', [CategoryController::class, 'getCategories']);
    $r->post('/blog-categories', [CategoryController::class, 'createCategory']);

    $r->get('/blog-tags', [BlogController::class, 'getPopularTags']);
});
