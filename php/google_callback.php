<?php
session_start();
 require '../includes/connexionbd.php';


// Vérification sécurité
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('Location:/PROJET_IA/php/dashboard.php?error=state');
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: /PROJET_IA/php/dashboard.php?error=no_code');
    exit;
}

// Échanger le code contre un token
$tokenRes = file_get_contents('https://oauth2.googleapis.com/token', false,
    stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ])
    ]])
);



$token = json_decode($tokenRes, true);

if (!isset($token['access_token'])) {
    header('Location: /PROJET_IA/php/dashboard.php?error=token');
    exit;
}

// Récupérer les infos Google
$userInfo = json_decode(file_get_contents(
    'https://www.googleapis.com/oauth2/v3/userinfo',
    false,
    stream_context_create(['http' => [
        'header' => 'Authorization: Bearer ' . $token['access_token']
    ]])
), true);

$nom   = $userInfo['name'];
$email = $userInfo['email'];

// Créer le compte si n'existe pas
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $pdo->prepare("INSERT INTO users (nom, email, password, role) VALUES (?, ?, '', 'adherent')")
         ->execute([$nom, $email]);
    $stmt->execute([$email]);
    $user = $stmt->fetch();
}

// Session
$_SESSION['user_id'] = $user['id'];
$_SESSION['nom']     = $user['nom'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];

header('Location: /PROJET_IA/php/dashboard.php');
exit;