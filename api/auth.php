<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request. Action is required.'
        ]);
        exit();
    }
    $action = trim($input['action']);
    if ($action === 'signup') {
        $name     = isset($input['name']) ? trim($input['name']) : '';
        $email    = isset($input['email']) ? trim(strtolower($input['email'])) : '';
        $password = isset($input['password']) ? $input['password'] : '';
        $confirmPassword = isset($input['confirm_password']) ? $input['confirm_password'] : '';
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Full name is required.';
        } elseif (!preg_match('/^[a-zA-Z\s.\'\-]+$/', $name)) {
            $errors[] = 'Name should contain only letters, spaces, dots, and hyphens.';
        }
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => implode(' ', $errors),
                'errors'  => $errors
            ]);
            exit();
        }
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'An account with this email already exists. Please login instead.'
                ]);
                exit();
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
            exit();
        }
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role)
                VALUES (:name, :email, :password, 'client')
            ");
            $stmt->execute([
                ':name'     => $name,
                ':email'    => $email,
                ':password' => $hashedPassword
            ]);
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id']   = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'client';
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Account created successfully! You are now logged in.',
                'data' => [
                    'id'    => $userId,
                    'name'  => $name,
                    'email' => $email,
                    'role'  => 'client'
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage()
            ]);
        }
    } elseif ($action === 'login') {
        $email    = isset($input['email']) ? trim(strtolower($input['email'])) : '';
        $password = isset($input['password']) ? $input['password'] : '';
        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Email and password are required.'
            ]);
            exit();
        }
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email or password.'
                ]);
                exit();
            }
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            echo json_encode([
                'success' => true,
                'message' => 'Login successful!',
                'data' => [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role']
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    } elseif ($action === 'logout') {
        session_unset();
        session_destroy();
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    } elseif ($action === 'check') {
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'success'       => true,
                'authenticated' => true,
                'data' => [
                    'id'    => $_SESSION['user_id'],
                    'name'  => $_SESSION['user_name'],
                    'email' => $_SESSION['user_email'],
                    'role'  => $_SESSION['user_role']
                ]
            ]);
        } else {
            echo json_encode([
                'success'       => true,
                'authenticated' => false
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action. Use signup, login, logout, or check.'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
}
