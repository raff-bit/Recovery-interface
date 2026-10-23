<?php
require_once __DIR__ . '/../config/auth.php';

// POST only: as a GET, any page could log you out with <img src=".../api_logout.php">
requireMethod('POST');

logoutUser();

jsonResponse(['success' => true]);