<?php
require '../includes/bd.php';

header('Content-Type: application/json');

$email    = trim($_POST['email']    ?? '');
$otp      = trim($_POST['otp']      ?? '');
$password = $_POST['password']      ?? '';

if (!$email || !$otp || !$password) {
    echo json_encode(["status" => "error", "message" => "Données manquantes."]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(["status" => "error", "message" => "Minimum 6 caractères."]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM password_resets WHERE email = ? AND otp = ? AND used = 0 AND expires_at > NOW()");
$stmt->execute([$email, $otp]);

if (!$stmt->fetch()) {
    echo json_encode(["status" => "error", "message" => "Code invalide ou expiré."]);
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$conn->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $email]);
$conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND otp = ?")->execute([$email, $otp]);

echo json_encode(["status" => "success", "message" => "Mot de passe mis à jour !"]);