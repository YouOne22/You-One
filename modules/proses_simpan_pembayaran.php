<?php
session_start();
require_once 'config/koneksi.php';

// 1. Cek apakah user sudah login
if (!isset($_SESSION['id'])) { // Sesuaikan dengan key session login Anda
    die("Anda harus login terlebih dahulu.");
}

// 2. Ambil data dari formulir
$no_invoice    = mysqli_real_escape_string($koneksi, $_POST['no_invoice']);
$jumlah_bayar  = mysqli_real_escape_string($koneksi, $_POST['jumlah_bayar']);
$metode        = mysqli_real_escape_string($koneksi, $_POST['metode_pembayaran']);
$tanggal_bayar = date('Y-m-d H:i:s'); 

// 3. PENTING: Ambil ID User yang sedang login
// Pastikan key ['id'] ini SAMA dengan yang Anda buat di login.php
$id_user = $_SESSION['id']; 

// 4. Masukkan ke database
$query = "INSERT INTO pembayaran (no_invoice, jumlah_bayar, tanggal_bayar, metode_pembayaran, id_user) 
          VALUES ('$no_invoice', '$jumlah_bayar', '$tanggal_bayar', '$metode', '$id_user')";

if (mysqli_query($koneksi, $query)) {
    echo "<script>alert('Data berhasil disimpan!'); window.location='index.php?page=pembayaran';</script>";
} else {
    // Menampilkan error jika gagal (untuk debug)
    echo "Error: " . mysqli_error($koneksi);
}
?>