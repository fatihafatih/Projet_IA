<?php
session_start();
require '../includes/connexionbd.php'; // ← même fichier que login.php

header('Content-Type: application/json');

$nom      = trim($_POST['nom']      ?? '');
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

// Validation
if (!$nom || !$email || !$password) {
    echo json_encode(["status" => "error", "message" => "Tous les champs sont requis."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Format email invalide."]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(["status" => "error", "message" => "Mot de passe trop court (min 6)."]);
    exit;
}

// Email déjà utilisé ?
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?"); // ← $pdo
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(["status" => "error", "message" => "Cet email est déjà utilisé."]);
    exit;
}

// Insertion — hash du mot de passe
$hash = password_hash($password, PASSWORD_BCRYPT); // ← NE PAS stocker en clair
$stmt = $pdo->prepare("INSERT INTO users (nom, email, password, role, status) VALUES (?, ?, ?, 'adherent', 'active')");
$stmt->execute([$nom, $email, $hash]);

echo json_encode(["status" => "success", "message" => "Compte créé avec succès !"]);