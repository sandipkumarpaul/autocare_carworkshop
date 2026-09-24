<?php
// POST { action: signup | login | logout | check, ... }
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

$input  = readJsonInput();
$action = inputString($input, 'action');

switch ($action) {
    case 'signup':
        $name            = inputString($input, 'name');
        $email           = strtolower(inputString($input, 'email'));
        $password        = isset($input['password']) ? (string)$input['password'] : '';
        $confirmPassword = isset($input['confirm_password']) ? (string)$input['confirm_password'] : '';

        $errors = [];
        if ($name === '') {
            $errors[] = 'Full name is required.';
        } elseif (!preg_match("/^[a-zA-Z\s.'\-]+$/", $name)) {
            $errors[] = 'Name should contain only letters, spaces, dots, and hyphens.';
        }
        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
        if ($errors) {
            jsonError(implode(' ', $errors), 400, $errors);
        }

        $emailTaken = 'An account with this email already exists. Please login instead.';
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            jsonError($emailTaken, 409);
        }

        try {
            $stmt = db()->prepare("
                INSERT INTO users (name, email, password, role)
                VALUES (:name, :email, :password, 'client')
            ");
            $stmt->execute([
                ':name'     => $name,
                ':email'    => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $e) {
            if (isDuplicateKeyError($e)) {
                jsonError($emailTaken, 409);
            }
            throw $e;
        }

        $user = ['id' => (int)db()->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'client'];
        startUserSession($user);
        jsonResponse([
            'success' => true,
            'message' => 'Account created successfully! You are now logged in.',
            'data'    => $user,
        ], 201);

    case 'login':
        $email    = strtolower(inputString($input, 'email'));
        $password = isset($input['password']) ? (string)$input['password'] : '';
        if ($email === '' || $password === '') {
            jsonError('Email and password are required.');
        }

        $stmt = db()->prepare('SELECT id, name, email, password, role FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            jsonError('Invalid email or password.', 401);
        }

        startUserSession($user);
        jsonResponse([
            'success' => true,
            'message' => 'Login successful!',
            'data'    => sessionUser(),
        ]);

    case 'logout':
        $_SESSION = [];
        session_destroy();
        jsonResponse(['success' => true, 'message' => 'Logged out successfully.']);

    case 'check':
        if (currentUserId() > 0) {
            jsonResponse(['success' => true, 'authenticated' => true, 'data' => sessionUser()]);
        }
        jsonResponse(['success' => true, 'authenticated' => false]);

    default:
        jsonError('Unknown action. Use signup, login, logout, or check.');
}
