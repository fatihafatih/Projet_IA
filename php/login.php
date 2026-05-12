<?php
session_start();
require_once '../includes/connexionbd.php';

header('Content-Type: application/json');

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

if (!$email || !$password) {
    echo json_encode(["status" => "error", "message" => "Email et mot de passe requis."]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(["status" => "error", "message" => "Email ou mot de passe incorrect."]);
    exit;
}

// Vérifie hash bcrypt OU mot de passe en clair (pour tes données de test)
$valid = password_verify($password, $user['password']) 
      || $password === $user['password'];

if ($valid) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nom']     = $user['nom'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];

    echo json_encode([
        "status" => "success",
        "user"   => [
            "id"    => $user['id'],
            "nom"   => $user['nom'],
            "email" => $user['email'],
            "role"  => $user['role']
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Email ou mot de passe incorrect."]);
}