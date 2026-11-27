<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "pesan" => "Metode tidak diizinkan"
    ]);
    exit;
}

require_once "koneksi.php";

try {
    $sql = "
        SELECT
            p.id_pesanan,
            p.tanggal_pesanan,
            p.total_rm,
            p.status,
            pl.nama_pelanggan,
            dp.id_detail,
            dp.jumlah,
            dp.harga_satuan_rm,
            dp.subtotal_rm,
            pr.nama_produk
        FROM pesanan p
        JOIN pelanggan pl
            ON p.id_pelanggan = pl.id_pelanggan
        LEFT JOIN detail_pesanan dp
            ON p.id_pesanan = dp.id_pesanan
        LEFT JOIN produk pr
            ON dp.id_produk = pr.id_produk
        ORDER BY p.id_pesanan DESC
    ";

    $stmt = $pdo->query($sql);
    $hasil = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $daftarPesanan = [];

    foreach ($hasil as $baris) {
        $id = $baris["id_pesanan"];

        if (!isset($daftarPesanan[$id])) {
            $daftarPesanan[$id] = [
                "id_pesanan" => $id,
                "nama_pelanggan" => $baris["nama_pelanggan"],
                "tanggal_pesanan" => $baris["tanggal_pesanan"],
                "total_rm" => $baris["total_rm"],
                "status" => $baris["status"],
                "detail" => []
            ];
        }

        if ($baris["id_detail"] !== null) {
            $daftarPesanan[$id]["detail"][] = [
                "nama_produk" => $baris["nama_produk"],
                "jumlah" => $baris["jumlah"],
                "harga_satuan_rm" => $baris["harga_satuan_rm"],
                "subtotal_rm" => $baris["subtotal_rm"]
            ];
        }
    }

    echo json_encode([
        "status" => "success",
        "jumlah" => count($daftarPesanan),
        "data" => array_values($daftarPesanan)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "pesan" => "Gagal mengambil riwayat pesanan"
    ]);
}
