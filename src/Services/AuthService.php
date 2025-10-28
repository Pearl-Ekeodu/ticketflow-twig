<?php

namespace App\Services;

use App\Models\User;

class AuthService
{
    private User $userModel;
    private array $config;

    public function __construct()
    {
        $this->userModel = new User();
        $this->config = require __DIR__ . '/../../config/app.php';
    }

    public function login(string $email, string $password): array
    {
        $user = $this->userModel->findByEmail($email);
        
        if (!$user || !$this->userModel->verifyPassword($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid email or password'
            ];
        }
        
        $this->startSession($user);
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ]
        ];
    }

    public function register(string $name, string $email, string $password): array
    {
        // Validate input
        $validation = $this->validateRegistration($name, $email, $password);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }
        
        // Check if email already exists
        if ($this->userModel->emailExists($email)) {
            return [
                'success' => false,
                'message' => 'User with this email already exists'
            ];
        }
        
        // Create user
        try {
            $userId = $this->userModel->create($name, $email, $password);
            $user = $this->userModel->findById($userId);
            
            $this->startSession($user);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email']
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_destroy();
    }

    public function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION['user_id'];
        return $this->userModel->findById($userId);
    }

    public function getCurrentUserId(): ?int
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['user_id'] ?? null;
    }

    private function startSession(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionConfig = $this->config['session'];
            session_name($sessionConfig['name']);
            session_set_cookie_params([
                'lifetime' => $sessionConfig['lifetime'],
                'secure' => $sessionConfig['secure'],
                'httponly' => $sessionConfig['httponly'],
                'samesite' => $sessionConfig['samesite']
            ]);
            session_start();
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
    }

    private function validateRegistration(string $name, string $email, string $password): array
    {
        if (empty($name) || strlen(trim($name)) < 2) {
            return [
                'valid' => false,
                'message' => 'Name must be at least 2 characters long'
            ];
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Please enter a valid email address'
            ];
        }
        
        $minLength = $this->config['security']['password_min_length'];
        if (empty($password) || strlen($password) < $minLength) {
            return [
                'valid' => false,
                'message' => "Password must be at least {$minLength} characters long"
            ];
        }
        
        return ['valid' => true];
    }

    public function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }

    public function validateCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
