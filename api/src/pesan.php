<?php
// Mengatur header respons API
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Menangani permintaan OPTIONS dari browser
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// API hanya menerima POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "pesan" => "Gunakan metode POST"
    ]);
    exit;
}

// Menghubungkan database
require_once "koneksi.php";

try {
    // Membaca data JSON
    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        throw new Exception("Data JSON tidak valid");
    }

    // Mengambil data pelanggan
    $nama = trim($data["nama_pelanggan"] ?? "");
    $email = trim($data["email"] ?? "");
    $telepon = trim($data["no_telepon"] ?? "");

    $idProduk = filter_var(
        $data["id_produk"] ?? null,
        FILTER_VALIDATE_INT
    );

    $jumlah = filter_var(
        $data["jumlah"] ?? null,
        FILTER_VALIDATE_INT
    );

    // Validasi data
    if ($nama === "" || !$idProduk || !$jumlah ||
        $idProduk < 1 || $jumlah < 1) {
        throw new Exception("Data pesanan tidak lengkap");
    }

    if (strlen($nama) > 150 ||
        strlen($email) > 150 ||
        strlen($telepon) > 20) {
        throw new Exception("Data pelanggan terlalu panjang");
    }

    if ($email !== "" &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Format email tidak valid");
    }

    // Memulai transaksi database
    $pdo->beginTransaction();

    // Memeriksa produk dan mengunci stok
    $stmt = $pdo->prepare(
        "SELECT id_produk, nama_produk, harga_rm, stok
         FROM produk
         WHERE id_produk = :id
         FOR UPDATE"
    );

    $stmt->execute(["id" => $idProduk]);
    $produk = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produk) {
        throw new Exception("Produk tidak ditemukan");
    }

    if ($produk["stok"] < $jumlah) {
        throw new Exception("Stok produk tidak mencukupi");
    }

    // Menghitung harga dari database
    $harga = (float) $produk["harga_rm"];
    $subtotal = $harga * $jumlah;

    // Mencari pelanggan berdasarkan email
    $idPelanggan = null;

    if ($email !== "") {
        $stmt = $pdo->prepare(
            "SELECT id_pelanggan
             FROM pelanggan
             WHERE email = :email
             LIMIT 1"
        );

        $stmt->execute(["email" => $email]);
        $pelanggan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pelanggan) {
            $idPelanggan = $pelanggan["id_pelanggan"];
        }
    }

    // Menyimpan pelanggan baru
    if (!$idPelanggan) {
        $stmt = $pdo->prepare(
            "INSERT INTO pelanggan
             (nama_pelanggan, email, no_telepon)
             VALUES (:nama, :email, :telepon)"
        );

        $stmt->execute([
            "nama" => $nama,
            "email" => $email !== "" ? $email : null,
            "telepon" => $telepon !== "" ? $telepon : null
        ]);

        $idPelanggan = $pdo->lastInsertId();
    }

    // Menyimpan pesanan
    $stmt = $pdo->prepare(
        "INSERT INTO pesanan (id_pelanggan, total_rm)
         VALUES (:pelanggan, :total)"
    );

    $stmt->execute([
        "pelanggan" => $idPelanggan,
        "total" => $subtotal
    ]);

    $idPesanan = $pdo->lastInsertId();

    // Menyimpan detail pesanan
    $stmt = $pdo->prepare(
        "INSERT INTO detail_pesanan
         (id_pesanan, id_produk, jumlah,
          harga_satuan_rm, subtotal_rm)
         VALUES (:pesanan, :produk, :jumlah, :harga, :subtotal)"
    );

    $stmt->execute([
        "pesanan" => $idPesanan,
        "produk" => $idProduk,
        "jumlah" => $jumlah,
        "harga" => $harga,
        "subtotal" => $subtotal
    ]);

    // Mengurangi stok produk
    $stmt = $pdo->prepare(
        "UPDATE produk
         SET stok = stok - :jumlah
         WHERE id_produk = :id"
    );

    $stmt->execute([
        "jumlah" => $jumlah,
        "id" => $idProduk
    ]);

    // Menyelesaikan transaksi
    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "pesan" => "Pesanan berhasil disimpan",
        "data" => [
            "id_pesanan" => $idPesanan,
            "nama_produk" => $produk["nama_produk"],
            "jumlah" => $jumlah,
            "harga_satuan_rm" => $harga,
            "total_rm" => $subtotal
        ]
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {

    // Membatalkan transaksi jika gagal
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "pesan" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
