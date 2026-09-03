<?php
// Pastikan path ke koneksi.php benar
include 'koneksi.php'; 

// Menggunakan $_REQUEST agar bisa menerima data baik dari URL (GET) maupun dari Aplikasi (POST)
$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : '';
$onesignal_id = isset($_REQUEST['onesignal_id']) ? $_REQUEST['onesignal_id'] : '';

if (!empty($username) && !empty($onesignal_id)) {
    // Update ke database
    $sql = "UPDATE users SET onesignal_id = '$onesignal_id' WHERE username = '$username'";
    
    if (mysqli_query($koneksi, $sql)) {
        echo "Berhasil update ID untuk user: $username";
    } else {
        echo "Gagal database: " . mysqli_error($koneksi);
    }
} else {
    echo "Data tidak lengkap. Username: '$username', ID: '$onesignal_id'";
}
?>