<?php
require_once '../../includes/auth_admin.php'; // Sécurité : admin uniquement

header('Content-Type: application/json');
// Lire le body JSON envoyé par le JS
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Corps de requête invalide']);
    exit;
}

// $apiKey = 'sk-ant-VOTRE_CLE_API_ICI'; // ← Mettez votre clé ici (ou via env var)

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS     => json_encode($input),
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'Erreur cURL : ' . $error]]);
    exit;
}

http_response_code($httpCode);
echo $response;