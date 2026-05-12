<?php
session_start();
session_destroy();
header('Location: ../php/dashboard.php');
exit;