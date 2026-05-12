<?php

require '../includes/bd.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/Exception.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Email invalide."]);
    exit;
}

$stmt = $conn->prepare("SELECT nom FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(["status" => "error", "message" => "Aucun compte avec cet email."]);
    exit;
}

$otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', time() + 600);

$conn->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
$conn->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)")
     ->execute([$email, $otp, $expiresAt]);

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'elmoudensiham17@gmail.com';
    $mail->Password   = 'iefxwpapkeuivyyx';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

    $mail->setFrom('elmoudensiham17@gmail.com', 'PRJ_AI');
    $mail->addAddress($email, $user['nom']);
    $mail->isHTML(true);
    $mail->Subject = 'Votre code de vérification';
    $mail->Body    = "<div style='text-align:center;font-family:Arial'>
        <h2>Code de vérification</h2>
        <p>Bonjour <b>{$user['nom']}</b>, votre code est :</p>
        <div style='font-size:36px;font-weight:700;letter-spacing:10px;color:#3b82f6;padding:16px;background:#eff6ff;border-radius:10px;'>$otp</div>
        <p style='color:#9ca3af;font-size:12px;'>Expire dans <b>10 minutes</b>.</p>
    </div>";

    $mail->send();
    echo json_encode(["status" => "success", "message" => "Code envoyé."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Erreur email : " . $e->getMessage()]);
}