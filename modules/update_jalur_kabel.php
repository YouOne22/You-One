<?php
// modules/update_jalur_kabel.php
require_once '../config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

// Tangkap input POST standar
$type       = trim($_POST['type'] ?? '');
$id         = trim($_POST['id'] ?? '');
$path_kabel = $_POST['path_kabel'] ?? '';

// Jika kosong, cek payload dari raw JSON body (fetch API body)
if (empty($type) || empty($id)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $type       = trim($json['type'] ?? '');
            $id         = trim($json['id'] ?? '');
            $path_kabel = $json['path_kabel'] ?? '';
        }
    }
}

// Validasi parameter utama
if (empty($id) || !in_array($type, ['pelanggan', 'odp'], true)) {
    echo json_encode(['status' => 'error', 'message' => "Parameter ID ($id) atau Tipe ($type) tidak valid"]);
    exit();
}

$table       = ($type === 'pelanggan') ? 'pelanggan' : 'odp';
$primary_key = ($type === 'pelanggan') ? 'id_pelanggan' : 'id';

// Formatting & Validasi JSON path_kabel
if (!empty($path_kabel)) {
    if (is_array($path_kabel)) {
        $path_kabel = json_encode($path_kabel);
    } else {
        $path_kabel = trim($path_kabel);
    }
    
    json_decode($path_kabel);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Format JSON path_kabel tidak valid']);
        exit();
    }
} else {
    $path_kabel = null;
}

// Eksekusi query dengan Prepared Statement
$query = "UPDATE {$table} SET path_kabel = ? WHERE {$primary_key} = ?";
$stmt  = mysqli_prepare($koneksi, $query);

if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan query: ' . mysqli_error($koneksi)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "ss", $path_kabel, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success', 'message' => 'Jalur kabel berhasil diperbarui']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal eksekusi: ' . mysqli_stmt_error($stmt)]);
}

mysqli_stmt_close($stmt);