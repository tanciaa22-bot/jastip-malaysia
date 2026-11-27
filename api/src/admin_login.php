<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "pesan" => "Metode tidak diizinkan"
    ]);
    exit;
}

require_once __DIR__ . '/private_admin/database.php';

try {
    $input = json_decode(file_get_contents("php://input"), true);

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "pesan" => "Username dan password wajib diisi"
        ]);
        exit;
    }

    $pdo = adminDb();

    $stmt = $pdo->prepare("
        SELECT id, username, password_hash
        FROM admin_users
        WHERE username = :username
        AND active = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $username
    ]);

    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "pesan" => "Username atau password salah"
        ]);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $sekarang = time();
    $kedaluwarsa = $sekarang + (8 * 60 * 60);

    $stmt = $pdo->prepare("
        INSERT INTO admin_sessions
        (user_id, token_hash, expires_at, created_at)
        VALUES (:user_id, :token_hash, :expires_at, :created_at)
    ");

    $stmt->execute([
        ':user_id' => $admin['id'],
        ':token_hash' => $tokenHash,
        ':expires_at' => $kedaluwarsa,
        ':created_at' => $sekarang
    ]);

    echo json_encode([
        "status" => "success",
        "pesan" => "Login Admin berhasil",
        "token" => $token,
        "username" => $admin['username'],
        "expires_in" => 28800
    ]);

} catch (Throwable $e) {
    error_log("Admin login error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "pesan" => "Terjadi kesalahan pada server"
    ]);
}
