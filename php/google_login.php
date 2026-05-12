<?php
session_start();

$client_id = "1006787855-ndoa4l80mkmssdb2ngu6uv9f5jfc55cd.apps.googleusercontent.com";
$redirect_uri = "http://localhost/PROJET_IA/php/google_callback.php";

$auth_url = "https://accounts.google.com/o/oauth2/v2/auth?"
  . "client_id=$client_id"
  . "&redirect_uri=$redirect_uri"
  . "&response_type=code"
  . "&scope=email profile";

header("Location: $auth_url");
exit;
?>