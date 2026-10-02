<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';

logoutUser();

echo json_encode(['success' => true]);