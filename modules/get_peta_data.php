<?php
session_start();
header('Content-Type: application/json');

// Sesuaikan path file koneksi database Anda
require_once '../config/database.php';

if (!isset($_SESSION['login'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_role  = $_SESSION['role'] ?? 'petugas';
$user_dusun = $_SESSION['dusun'] ?? '';

// Lokasi Central Server ODC Utama
$server_location = [
    'nama' => 'ODC Central Server YOU-ONE',
    'latitude' => -7.47000000,
    'longitude' => 110.21500000
];

$where_odp = "";
$where_pelanggan = "";

if ($user_role !== 'admin' && !empty($user_dusun)) {
    $dusun_clean = mysqli_real_escape_string($conn, $user_dusun);
    $where_odp = " WHERE dusun = '$dusun_clean' ";
    $where_pelanggan = " WHERE dusun = '$dusun_clean' ";
}

// 1. Ambil Data ODP & Hitung Port Terpakai
$query_odp = "SELECT * FROM odp $where_odp";
$res_odp = mysqli_query($conn, $query_odp);
$odp_list = [];

while ($row = mysqli_fetch_assoc($res_odp)) {
    $odp_id = $row['id'];
    
    // Ambil daftar pelanggan terhubung ke ODP ini
    $q_pel = mysqli_query($conn, "SELECT id_pelanggan, nama, status FROM pelanggan WHERE odp_id = '$odp_id' OR id_odp = '$odp_id'");
    $pelanggan_terhubung = [];
    if ($q_pel) {
        while ($p = mysqli_fetch_assoc($q_pel)) {
            $pelanggan_terhubung[] = $p;
        }
    }

    $row['port_terpakai'] = count($pelanggan_terhubung);
    $row['port_tersedia'] = (int)$row['kapasitas_port'] - $row['port_terpakai'];
    $row['pelanggan_list'] = $pelanggan_terhubung;
    $odp_list[] = $row;
}

// 2. Ambil Data Pelanggan yang Memiliki Koordinat (Latitude & Longitude)
$query_pelanggan = "SELECT p.*, o.kode_odp 
                    FROM pelanggan p 
                    LEFT JOIN odp o ON (p.odp_id = o.id OR p.id_odp = o.id) 
                    " . ($where_pelanggan ? "$where_pelanggan AND" : "WHERE") . " p.latitude IS NOT NULL AND p.longitude IS NOT NULL";

$res_pelanggan = mysqli_query($conn, $query_pelanggan);
$pelanggan_list = [];

if ($res_pelanggan) {
    while ($row = mysqli_fetch_assoc($res_pelanggan)) {
        $pelanggan_list[] = $row;
    }
}

echo json_encode([
    'status' => 'success',
    'server' => $server_location,
    'odp' => $odp_list,
    'pelanggan' => $pelanggan_list
]);