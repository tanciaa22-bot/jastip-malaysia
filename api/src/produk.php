<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once "koneksi.php";

try {
    $query = $pdo->query(
        "SELECT * FROM produk ORDER BY id_produk ASC"
    );

    $produk = $query->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "jumlah" => count($produk),
        "data" => $produk
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "pesan" => "Gagal mengambil data produk"
    ]);
}
?>
