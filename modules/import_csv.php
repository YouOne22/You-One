<?php
// Sambungkan ke database Anda (sesuaikan dengan file koneksi yang ada)
require_once 'koneksi.php'; 

if (isset($_POST['upload_csv'])) {
    $file_tmp = $_FILES['file_csv']['tmp_name'];

    if (!empty($file_tmp)) {
        $handle = fopen($file_tmp, "r");

        // Lewati baris pertama (header CSV)
        fgetcsv($handle, 1000, ",");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Ambil kolom dari CSV Map Marker:
            // $data[2] = Latitude, $data[3] = Longitude, $data[4] = Title, $data[5] = Description
            $lat   = mysqli_real_escape_string($conn, $data[2]);
            $lng   = mysqli_real_escape_string($conn, $data[3]);
            $title = mysqli_real_escape_string($conn, $data[4]);
            $desc  = mysqli_real_escape_string($conn, $data[5]);

            // Pastikan koordinat tidak kosong
            if (!empty($lat) && !empty($lng) && !empty($title)) {
                $sql = "INSERT INTO odp (kode_odp, nama_odp, latitude, longitude, keterangan, jenis, kapasitas_port, redaman_dbm, created_at) 
                        VALUES ('$title', '$title', '$lat', '$lng', '$desc', 'ODP', 8, -18.50, NOW())";
                mysqli_query($conn, $sql);
            }
        }

        fclose($handle);
        header("Location: odp.php?msg=success");
        exit();
    }
}
?>