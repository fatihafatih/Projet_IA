<?php
session_start();

define('GOOGLE_CLIENT_ID',    '1006787855-ndoa4l80mkmssdb2ngu6uv9f5jfc55cd.apps.googleusercontent.com');
define('GOOGLE_REDIRECT_URI', 'http://localhost/PROJET_IA/php/google_callback.php');

$state = bin2hex(random_bytes(16)); // génère un code aléatoire de sécurité (anti-piratage)
$_SESSION['oauth_state'] = $state;

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
]));
exit;