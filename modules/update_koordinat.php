<?php
// modules/update_koordinat.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Diubah ke 0 agar output JSON bersih tanpa warning PHP

require_once '../config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

// Menggabungkan data dari $_POST dan JSON body (php://input)
$data = $_POST;
$inputJSON = file_get_contents('php://input');
if (!empty($inputJSON)) {
    $inputData = json_decode($inputJSON, true);
    if (is_array($inputData)) {
        $data = array_merge($data, $inputData);
    }
}

// Menangkap parameter dengan fleksibel
$type      = trim($data['type'] ?? $data['jenis'] ?? 'pelanggan');
$id        = trim($data['id'] ?? '');
$latitude  = trim($data['latitude'] ?? $data['lat'] ?? '');
$longitude = trim($data['longitude'] ?? $data['lng'] ?? $data['lon'] ?? '');

if ($id !== '' && $latitude !== '' && $longitude !== '') {
    
    if ($type === 'odp') {
        $query = "UPDATE odp SET latitude = ?, longitude = ? WHERE id = ?";
    } elseif ($type === 'pelanggan') {
        $query = "UPDATE pelanggan SET latitude = ?, longitude = ? WHERE id_pelanggan = ?";
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Tipe data tidak valid']);
        exit();
    }

    $stmt = mysqli_prepare($koneksi, $query);
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan query: ' . mysqli_error($koneksi)]);
        exit();
    }

    // Bind parameter: latitude (s), longitude (s), id (s)
    mysqli_stmt_bind_param($stmt, "sss", $latitude, $longitude, $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_stmt_error($stmt)]);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Data koordinat atau ID tidak lengkap',
        'received_data' => $data
    ]);
}