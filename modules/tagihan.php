<?php
// modules/tagihan.php

// PENGAMAN ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Jakarta');

// PERBAIKAN: Pastikan session dimulai jika belum aktif untuk mencegah E_NOTICE saat dipanggil via AJAX
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/koneksi.php';

// ------------------------------------------------------------------------------
// HELPER FUNCTIONS (Anti-Spam Spintax & Unique Footprint)
// ------------------------------------------------------------------------------
if (!function_exists('parse_spintax')) {
    function parse_spintax($text) {
        return preg_replace_callback('/\{(((?>[^\{\}]+)|(?R))*)\}/x', function($matches) {
            $choices = explode('|', $matches[1]);
            return $choices[array_rand($choices)];
        }, $text);
    }
}

if (!function_exists('add_anti_spam_footer')) {
    function add_anti_spam_footer($pesan) {
        $ref_id = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
        return $pesan . "\n\n_Ref: #" . $ref_id . "_";
    }
}

$nama_bulan_ini = date('F Y');
$format_bulan_ini = date('Ym');

$dusun_pengelola = mysqli_real_escape_string($koneksi, $_SESSION['dusun_pengelola'] ?? '');
$filter_dusun = mysqli_real_escape_string($koneksi, $_GET['dusun'] ?? '');

// --- PROSES 1: GENERATE TAGIHAN BULAN INI (SISTEM BATCH) ---
if (isset($_POST['generate_tagihan'])) {
    @set_time_limit(300);

    $query_pelan = "SELECT pelanggan.*, paket_internet.tarif_bulanan 
                    FROM pelanggan 
                    LEFT JOIN paket_internet ON pelanggan.id_paket = paket_internet.id 
                    WHERE pelanggan.status = 'Aktif'";
    $result_pelan = mysqli_query($koneksi, $query_pelan);
    
    if (!$result_pelan) {
        die("<div class='bg-red-950 border border-red-800 text-red-400 p-4 rounded-xl text-sm font-mono'>[Gagal Query Pelanggan]: " . mysqli_error($koneksi) . "</div>");
    }
    
    $generated = 0;
    $skipped   = 0;
    $list_batch_sheet = []; // Array penampung seluruh data pelanggan

    while ($pelan = mysqli_fetch_assoc($result_pelan)) {
        $id_p  = mysqli_real_escape_string($koneksi, $pelan['id_pelanggan']);
        $tarif = floatval($pelan['tarif_bulanan'] ?? 0);
        
        $clean_id   = str_replace('-', '', $id_p);
        $no_invoice = "INV-" . date('Ym') . "-" . $clean_id;
        
        $cek_tagihan = mysqli_query($koneksi, "SELECT no_invoice FROM tagihan WHERE no_invoice = '$no_invoice'");
        
        if ($cek_tagihan && mysqli_num_rows($cek_tagihan) == 0) {
            $b_tagihan = date('Y-m-d'); 
            $query_ins = "INSERT INTO tagihan (no_invoice, id_pelanggan, total_tagihan, bulan_tagihan) 
                          VALUES ('$no_invoice', '$id_p', '$tarif', '$b_tagihan')";
            if (mysqli_query($koneksi, $query_ins)) {
                $generated++;
            }
        } else {
            $skipped++;
        }

        // --- KUMPULKAN DATA TUNGGAKAN & TEMPO ---
        $q_old = mysqli_query($koneksi, "
            SELECT 
                (IFNULL(SUM(total_tagihan), 0) - 
                 IFNULL((SELECT SUM(p.jumlah_bayar) 
                         FROM pembayaran p 
                         JOIN tagihan t2 ON p.no_invoice = t2.no_invoice 
                         WHERE t2.id_pelanggan = '$id_p' AND t2.no_invoice < '$no_invoice'), 0)
                ) as sisa 
            FROM tagihan 
            WHERE id_pelanggan = '$id_p' AND no_invoice < '$no_invoice'
        ");
        
        $tunggakan_lalu = 0;
        if ($q_old && $r_old = mysqli_fetch_assoc($q_old)) {
            $tunggakan_lalu = max(0, floatval($r_old['sisa'] ?? 0));
        }

        $teks_alamat = strtolower(($pelan['alamat'] ?? '') . ' ' . ($pelan['dusun'] ?? ''));
        $hari_tempo  = (strpos($teks_alamat, 'mbalong') !== false) ? '25' : '10';
        $tempo_sheet = $hari_tempo . '/' . date('m/Y');

        // Tambahkan ke penampung batch
        $list_batch_sheet[] = [
            'dusun'     => $pelan['dusun'] ?? '',
            'nomor_wa'  => $pelan['no_wa'] ?? '',
            'nama'      => $pelan['nama'] ?? '',
            'tagihan'   => $tarif,
            'tunggakan' => $tunggakan_lalu,
            'tempo'     => $tempo_sheet,
            'status'    => "Belum Lunas"
        ];
    }

    // --- DIKIRIM HANYA 1 KALI REQUEST KE GOOGLE SHEETS ---
    if (!empty($list_batch_sheet) && function_exists('update_google_sheet_batch')) {
        update_google_sheet_batch($list_batch_sheet);
    }

    $pesan = "$generated Invoice baru dibuat. Sinkronisasi Google Sheets selesai.";
    echo "<script>
    Swal.fire({
        title: '<span class=\"text-amber-400 font-bold tracking-wide\">Selesai!</span>',
        text: '$pesan',
        icon: 'success',
        background: '#0c101a',
        color: '#f1f5f9',
        confirmButtonColor: '#f59e0b',
        iconColor: '#f59e0b',
        customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
    }).then(() => { window.location='index.php?page=tagihan'; });
    </script>";
    exit;
}

// --- PROSES 2: CATAT PEMBAYARAN ---
if (isset($_GET['action']) && $_GET['action'] == 'set_lunas' && isset($_GET['invoice']) && isset($_GET['jumlah'])) {
    $no_invoice = mysqli_real_escape_string($koneksi, $_GET['invoice']);
    $jumlah_bayar = intval($_GET['jumlah']);
    $metode = isset($_GET['metode']) ? mysqli_real_escape_string($koneksi, $_GET['metode']) : 'Cash / Manual';
    
    // Mengambil ID User dari session login
    $id_user_login = isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 0;
    
    if ($jumlah_bayar > 0) {
        
        // =========================================================================
        // PENGAMAN 1: CEK ANTISIPASI DOUBLE CLICK (KLIK GANDA) DI BACKEND
        // =========================================================================
        $cek_dobel = mysqli_query($koneksi, "
            SELECT no_invoice FROM pembayaran 
            WHERE no_invoice = '$no_invoice' 
              AND jumlah_bayar = '$jumlah_bayar' 
              AND tanggal_bayar >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
        ");
        
        if ($cek_dobel && mysqli_num_rows($cek_dobel) > 0) {
            echo "<script>window.location='index.php?page=tagihan';</script>";
            exit;
        }
        // =========================================================================

        $tgl_bayar = date('Y-m-d H:i:s');
        
        $query_ins_bayar = "INSERT INTO pembayaran (no_invoice, tanggal_bayar, jumlah_bayar, metode_pembayaran, id_user) 
                            VALUES ('$no_invoice', '$tgl_bayar', '$jumlah_bayar', '$metode', '$id_user_login')";
        
        if (mysqli_query($koneksi, $query_ins_bayar)) {
            
            // --- START INTEGRASI WHATSAPP API FONNTE & GOOGLE SHEETS ---
            $q_detail = mysqli_query($koneksi, "
                SELECT p.id_pelanggan, p.nama, p.no_wa, p.dusun, pkt.nama_paket, t.total_tagihan 
                FROM tagihan t 
                JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan 
                LEFT JOIN paket_internet pkt ON p.id_paket = pkt.id 
                WHERE t.no_invoice = '$no_invoice'
            ");
            
            if ($q_detail && mysqli_num_rows($q_detail) > 0) {
                $detail = mysqli_fetch_assoc($q_detail);
                $id_pelanggan = $detail['id_pelanggan'];
                $nama_pelanggan = $detail['nama'];
                $wa_pelanggan = preg_replace('/[^0-9]/', '', $detail['no_wa'] ?? '');
                $dusun_pelanggan = $detail['dusun'];
                $nama_paket = $detail['nama_paket'];
                $harga_paket = $detail['total_tagihan'];
                
                $q_tot_tagihan = mysqli_query($koneksi, "SELECT SUM(total_tagihan) as tot FROM tagihan WHERE id_pelanggan = '$id_pelanggan'");
                $r_tot_tagihan = mysqli_fetch_assoc($q_tot_tagihan);
                $total_semua_tagihan = $r_tot_tagihan['tot'] ?? 0;
                
                $q_tot_bayar = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as tot FROM pembayaran pb JOIN tagihan tg ON pb.no_invoice = tg.no_invoice WHERE tg.id_pelanggan = '$id_pelanggan'");
                $r_tot_bayar = mysqli_fetch_assoc($q_tot_bayar);
                $total_semua_bayar = $r_tot_bayar['tot'] ?? 0;
                
                $sisa_tunggakan = max(0, $total_semua_tagihan - $total_semua_bayar);
                $sisa_sblm_bayar = $sisa_tunggakan + $jumlah_bayar;
                $tunggakan_lalu = max(0, $sisa_sblm_bayar - $harga_paket);
                
                $tgl_format = date('d F Y H:i:s', strtotime($tgl_bayar));
                $metode_upper = strtoupper($metode);

                if ($sisa_tunggakan <= 0) {
                    $status_wa = "*LUNAS* ✅";
                    $status_sheet = "Lunas / Aman";
                } elseif ($total_semua_bayar > 0) {
                    $status_wa = "*DICICIL* ⚠️";
                    $status_sheet = "Dicicil";
                } else {
                    $status_wa = "*BELUM LUNAS* ❌";
                    $status_sheet = "Belum Lunas";
                }

                // =========================================================================
                // INTEGRASI GOOGLE SHEETS OTOMATIS PER DUSUN VIA KONEKSI.PHP
                // =========================================================================
                if (function_exists('update_google_sheet')) {
                    $data_ke_sheet = [
                        'nomor_wa'   => $wa_pelanggan,
                        'nama'       => $nama_pelanggan,
                        'tunggakan'  => $sisa_tunggakan,
                        'total_bayar'=> $total_semua_bayar,
                        'status'     => $status_sheet
                    ];
                    update_google_sheet($dusun_pelanggan, $data_ke_sheet);
                }
                // =========================================================================

                $nama_petugas = $_SESSION['nama'] ?? $_SESSION['username'] ?? $_SESSION['nama_user'] ?? 'Petugas / Admin';

                // TEMPLATE SPINTAX UNTUK PELANGGAN
                $template_pelanggan = "{📡 You-One.net|🌐 YOU-ONE.NET}\n\n"
                    . "{Halo|Yth.|Selamat Pagi/Siang} Bapak/Ibu *$nama_pelanggan* 😊\n\n"
                    . "{Perpanjangan langganan sudah kami terima ya|Pembayaran iuran internet Anda telah berhasil kami terima|Terima kasih, pembayaran tagihan Anda telah terverifikasi} 🙏\n\n"
                    . "📅 Waktu: $tgl_format\n"
                    . "📊 Status: $status_wa\n\n"
                    . "📦 Detail Tagihan:\n"
                    . "• Paket: $nama_paket\n"
                    . "• Harga: Rp. " . number_format($harga_paket, 0, ',', '.') . "\n"
                    . "• Tunggakan: Rp. " . number_format($tunggakan_lalu, 0, ',', '.') . "\n"
                    . "• Total Bayar: *Rp. " . number_format($jumlah_bayar, 0, ',', '.') . "*\n"
                    . "• Sisa Tunggakan: Rp. " . number_format($sisa_tunggakan, 0, ',', '.') . "\n"
                    . "• Bayar Via: $metode_upper\n\n"
                    . "👨‍💻 Admin: $nama_petugas\n\n"
                    . "{Terima kasih sudah menggunakan layanan You-One.net|Semoga koneksi selalu lancar dan nyaman|Terima kasih atas kepercayaannya} 🙏\n"
                    . "{Semoga koneksi selalu lancar 🚀|Salam hangat dari kami 🚀|Selamat menikmati layanan kami 🚀}";

                $pesan_pelanggan = add_anti_spam_footer(parse_spintax($template_pelanggan));

                // TEMPLATE SPINTAX UNTUK OWNER
                $template_owner = "🔔 *NOTIFIKASI PEMBAYARAN*\n\n"
                    . "Masuk dari *$nama_pelanggan* sebesar *Rp. " . number_format($jumlah_bayar, 0, ',', '.') . "* ✅\n\n"
                    . "• Status: $status_wa\n"
                    . "• Paket: $nama_paket\n"
                    . "• Tagihan: Rp. " . number_format($harga_paket, 0, ',', '.') . "\n"
                    . "• Tunggakan: Rp. " . number_format($tunggakan_lalu, 0, ',', '.') . "\n"
                    . "• Sisa Tunggakan: Rp. " . number_format($sisa_tunggakan, 0, ',', '.') . "\n"
                    . "• Bayar Via: $metode_upper\n\n"
                    . "Petugas: $nama_petugas\n"
                    . "Waktu: $tgl_format";

                $pesan_owner = add_anti_spam_footer(parse_spintax($template_owner));

                // 1. KIRIM KE PELANGGAN
                if (!empty($wa_pelanggan)) {
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://api.fonnte.com/send',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => array(
                            'target' => $wa_pelanggan,
                            'message' => $pesan_pelanggan,
                            'countryCode' => '62',
                            'delay' => (string)rand(7, 15)
                        ),
                        CURLOPT_HTTPHEADER => array("Authorization: " . TOKEN_FONNTE),
                    ));
                    curl_exec($curl);
                    curl_close($curl);

                    sleep(rand(3, 5));
                }

                // 2. KIRIM KE OWNER (Menggunakan konstanta NO_OWNER dan TOKEN_FONNTE)
                $curl2 = curl_init();
                curl_setopt_array($curl2, array(
                    CURLOPT_URL => 'https://api.fonnte.com/send',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array(
                        'target' => NO_OWNER,
                        'message' => $pesan_owner,
                        'countryCode' => '62',
                        'delay' => (string)rand(3, 5)
                    ),
                    CURLOPT_HTTPHEADER => array("Authorization: " . TOKEN_FONNTE),
                ));
                curl_exec($curl2);
                curl_close($curl2);
                
                // =========================================================================
                // PUSH NOTIFIKASI ONESIGNAL KE PETUGAS DUSUN
                // =========================================================================
                if (!empty($dusun_pelanggan)) {
                    echo "<script>console.log('LOG DEBUG 1: Dusun Pelanggan adalah -> $dusun_pelanggan');</script>";

                    $sql_petugas = "SELECT onesignal_id FROM users WHERE role = 'Petugas' AND LOWER(TRIM(dusun)) = LOWER(TRIM('$dusun_pelanggan')) LIMIT 1";
                    $query_petugas = mysqli_query($koneksi, $sql_petugas);
                    
                    if ($query_petugas && mysqli_num_rows($query_petugas) > 0) {
                        $data_petugas = mysqli_fetch_assoc($query_petugas);
                        $id_hp_petugas = $data_petugas['onesignal_id'];
                        
                        echo "<script>console.log('LOG DEBUG 2: OneSignal ID Petugas ditemukan -> $id_hp_petugas');</script>";
                        
                        if (!empty($id_hp_petugas)) {
                            if (file_exists('config/fungsi_notifikasi.php')) {
                                include_once 'config/fungsi_notifikasi.php';
                                $judul_notif = "Sudah Masuk! Dusun $dusun_pelanggan";
                                $pesan_notif = "Pelanggan bernama $nama_pelanggan baru saja membayar Rp " . number_format($jumlah_bayar, 0, ',', '.');
                                
                                $respon_onesignal = kirimNotifikasiBaruPetugas($id_hp_petugas, $judul_notif, $pesan_notif);
                                echo "<script>console.log('LOG DEBUG 4: Respon Mentah OneSignal -> " . addslashes($respon_onesignal) . "');</script>";
                            } else {
                                echo "<script>alert('LOG ERR 3: File config/fungsi_notifikasi.php TIDAK DITEMUKAN! Periksa letak foldernya.');</script>";
                            }
                        }
                    } else {
                        echo "<script>console.log('LOG ERR 2: Tidak ditemukan petugas yang cocok dengan dusun $dusun_pelanggan');</script>";
                    }
                }
            }

            echo "<script>
            Swal.fire({
                title: '<span class=\"text-emerald-400 font-bold tracking-wide\">Berhasil!</span>',
                text: 'Mencatat pembayaran via $metode sebesar Rp " . number_format($jumlah_bayar, 0, ',', '.') . "',
                icon: 'success',
                background: '#0c101a',
                color: '#f1f5f9',
                confirmButtonColor: '#10b981',
                iconColor: '#10b981',
                customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
            }).then(() => { window.location='index.php?page=tagihan'; });
            </script>";
        }
        exit;
    }
}

// --- PROSES 2B: PENGIRIMAN NOTIFIKASI TAGIHAN BULANAN (AJAX HANDLER) ---
if (isset($_GET['action']) && $_GET['action'] == 'kirim_tagihan_wa' && isset($_GET['invoice'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    ob_start();
    
    $no_invoice = mysqli_real_escape_string($koneksi, $_GET['invoice']);
    
    $q_notif = mysqli_query($koneksi, "
        SELECT p.nama, p.no_wa, t.id_pelanggan, t.tgl_terakhir_ditagih, pkt.nama_paket 
        FROM tagihan t 
        JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan 
        LEFT JOIN paket_internet pkt ON p.id_paket = pkt.id
        WHERE t.no_invoice = '$no_invoice'
    ");
    
    $response_data = ['status' => 'error', 'message' => 'Data tidak ditemukan.'];
    
    if ($q_notif && mysqli_num_rows($q_notif) > 0) {
        $d_notif = mysqli_fetch_assoc($q_notif);

        // 🔴 PENGAMAN ANTI-SPAM: Cek Cooldown Pengiriman 24 Jam
        if (!empty($d_notif['tgl_terakhir_ditagih'])) {
            $selisih_jam = (time() - strtotime($d_notif['tgl_terakhir_ditagih'])) / 3600;
            if ($selisih_jam < 24) {
                $sisa_jam = ceil(24 - $selisih_jam);
                $response_data = [
                    'status' => 'error', 
                    'message' => "Tagihan sudah dikirim sebelumnya. Harap tunggu $sisa_jam jam lagi untuk menagih ulang."
                ];
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode($response_data);
                exit;
            }
        }

        $nama_p_notif = $d_notif['nama'];
        $wa_p_notif = preg_replace('/[^0-9]/', '', $d_notif['no_wa'] ?? '');
        $id_p_notif = $d_notif['id_pelanggan'];
        $nama_paket_notif = $d_notif['nama_paket'] ?? 'Paket Internet';
        
        $q_tot_tagihan = mysqli_query($koneksi, "SELECT SUM(total_tagihan) as tot FROM tagihan WHERE id_pelanggan = '$id_p_notif'");
        $r_tot_tagihan = mysqli_fetch_assoc($q_tot_tagihan);
        $total_semua_tagihan = $r_tot_tagihan['tot'] ?? 0;
        
        $q_tot_bayar = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as tot FROM pembayaran pb JOIN tagihan tg ON pb.no_invoice = tg.no_invoice WHERE tg.id_pelanggan = '$id_p_notif'");
        $r_tot_bayar = mysqli_fetch_assoc($q_tot_bayar);
        $total_semua_bayar = $r_tot_bayar['tot'] ?? 0;
        
        $sisa_tagihan_real = max(0, $total_semua_tagihan - $total_semua_bayar);
        $nama_admin = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin You-One';
        
        $tahun_bulan = substr($no_invoice, 4, 6); 
        $tahun = substr($tahun_bulan, 0, 4);
        $bulan = substr($tahun_bulan, 4, 2);
        $periode_tampil_notif = (is_numeric($bulan) && is_numeric($tahun)) ? date('F Y', mktime(0, 0, 0, (int)$bulan, 1, (int)$tahun)) : date('F Y');
        
        // TEMPLATE SPINTAX UNTUK TAGIHAN BULANAN
        $template_tagihan = "{📡 You-One.net|🌐 YOU-ONE.NET}\n\n"
            . "{Halo|Yth.|Selamat Pagi/Siang} Bapak/Ibu *$nama_p_notif* 🙏\n"
            . "{Mengingatkan kembali mengenai perpanjangan bulanan.|Berikut informasi tagihan internet bulanan Anda.|Informasi tagihan langganan internet periode berjalan.}\n"
            . "Saat ini, tagihan Anda belum tercatat disistem kami dengan rincian berikut:\n\n"
            . "📦 Paket Langganan *$nama_paket_notif* \n"
            . "⏳ Jatuh Tempo Pembayaran *$periode_tampil_notif*.\n"
            . "💰 Sisa Tagihan: *Rp " . number_format($sisa_tagihan_real, 0, ',', '.') . "*\n\n"
            . "{Mohon untuk segera melakukan perpanjangan agar tetap menikmati layanan internet dengan lancar tanpa isolir.|Silakan lakukan pembayaran tepat waktu agar koneksi tetap stabil dan tidak terisolir.|Mohon dapat melakukan perpanjangan tepat waktu demi kenyamanan bersama.}\n"
            . "{Jika anda sudah melakukan pembayaran sebelum pesan ini masuk via transfer, silakan abaikan pesan ini atau kirimkan foto bukti bayarnya ya.|Abaikan pesan ini jika Anda sudah melakukan pembayaran sebelumnya.|Bila sudah bayar, mohon konfirmasi bukti transfernya ya.}\n\n"
            . "{Terima kasih banyak atas perhatian dan kerjasamanya. 😊|Terima kasih dan salam hangat. 😊|Hormat kami, You-One.net 😊}";

        $pesan_tagihan = add_anti_spam_footer(parse_spintax($template_tagihan));
            
        if (!empty($wa_p_notif)) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'target' => $wa_p_notif,
                    'message' => $pesan_tagihan,
                    'countryCode' => '62',
                    'delay' => (string)rand(2, 5)
                ),
                CURLOPT_HTTPHEADER => array("Authorization: " . TOKEN_FONNTE),
            ));
            $res_curl = curl_exec($curl);
            curl_close($curl);
            
            // CATAT WAKTU TERAKHIR DITAGIH
            $now = date('Y-m-d H:i:s');
            mysqli_query($koneksi, "UPDATE tagihan SET tgl_terakhir_ditagih = '$now' WHERE no_invoice = '$no_invoice'");

            $response_data = ['status' => 'success', 'message' => 'Notifikasi pengingat iuran berhasil terkirim ke WhatsApp pelanggan.'];
        } else {
            $response_data = ['status' => 'error', 'message' => 'Nomor WhatsApp tidak valid atau kosong.'];
        }
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response_data);
    exit;
}

// --- PROSES 3: AMBIL DATA DENGAN INNER JOIN ---
$query_view = "SELECT pelanggan.*, tagihan.no_invoice, tagihan.total_tagihan, paket_internet.nama_paket 
               FROM pelanggan 
               LEFT JOIN tagihan ON pelanggan.id_pelanggan = tagihan.id_pelanggan 
                    AND tagihan.no_invoice = (
                        SELECT MAX(t2.no_invoice) 
                        FROM tagihan t2 
                        WHERE t2.id_pelanggan = pelanggan.id_pelanggan
                    )
               LEFT JOIN paket_internet ON pelanggan.id_paket = paket_internet.id";

$role_user = strtoupper($_SESSION['role'] ?? '');

if (!empty($filter_dusun)) {
    $query_view .= " WHERE pelanggan.dusun = '$filter_dusun'";
} elseif ($role_user !== 'ADMIN' && $role_user !== 'ADMINISTRATOR') {
    $query_view .= " WHERE pelanggan.dusun = '$dusun_pengelola'";
}

$query_view .= " ORDER BY pelanggan.dusun ASC, pelanggan.nama ASC";
$result = mysqli_query($koneksi, $query_view);

$data_grouped = [];
$stat_total_warga = 0;
$stat_lunas_count = 0;
$stat_belum_lunas_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $stat_total_warga++;
    
    if (empty($row['no_invoice'])) {
        $row['tunggakan_awal'] = 0;
        $row['tunggakan_tampil'] = 0;
        $row['grand_total_awal'] = 0;
        $row['sisa_tagihan'] = 0;
        $row['is_lunas'] = true;
        $row['terbayar_bulan_ini'] = 0;
        $row['periode_tampil'] = '-';
        $row['jatuh_tempo_tampil'] = '-';
        $row['link_wa'] = '#';
        $stat_lunas_count++;
    } else {
        $inv_aktif = mysqli_real_escape_string($koneksi, $row['no_invoice']);
        $id_p = mysqli_real_escape_string($koneksi, $row['id_pelanggan']);
        
        $q_paid = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total FROM pembayaran WHERE no_invoice = '$inv_aktif'");
        $p = mysqli_fetch_assoc($q_paid);
        $terbayar_bulan_ini = floatval($p['total'] ?? 0);
        
        $q_old = mysqli_query($koneksi, "SELECT (SUM(total_tagihan) - (SELECT IFNULL(SUM(jumlah_bayar),0) FROM pembayaran p JOIN tagihan t ON p.no_invoice = t.no_invoice WHERE t.id_pelanggan = '$id_p' AND t.no_invoice < '$inv_aktif')) as sisa FROM tagihan WHERE id_pelanggan = '$id_p' AND no_invoice < '$inv_aktif'");
        $old = mysqli_fetch_assoc($q_old);
        $tunggakan_awal = max(0, floatval($old['sisa'] ?? 0));
        
        $tunggakan_tampil = max(0, $tunggakan_awal - $terbayar_bulan_ini);
        $total_t = isset($row['total_tagihan']) ? floatval($row['total_tagihan']) : 0;
        $grand_total_awal = $total_t + $tunggakan_awal;
        $sisa_tagihan = max(0, $grand_total_awal - $terbayar_bulan_ini);
        $is_lunas = ($sisa_tagihan <= 0);
        
        if ($is_lunas) {
            $stat_lunas_count++;
        } else {
            $stat_belum_lunas_count++;
        }
        
        $tahun_bulan = substr($inv_aktif, 4, 6); 
        $tahun = substr($tahun_bulan, 0, 4);
        $bulan = substr($tahun_bulan, 4, 2);
        $periode_tampil = date('F Y', mktime(0, 0, 0, (int)$bulan, 1, (int)$tahun));

        $teks_alamat = isset($row['alamat']) ? strtolower($row['alamat']) : '';
        $teks_wilayah = isset($row['wilayah']) ? strtolower($row['wilayah']) : '';
        $hari_jatuh_tempo = '10'; 
        if (strpos($teks_alamat, 'mbalong') !== false || strpos($teks_wilayah, 'mbalong') !== false) {
            $hari_jatuh_tempo = '25';
        }
        $jatuh_tempo_tampil = $hari_jatuh_tempo . ' ' . date('M Y', mktime(0, 0, 0, (int)$bulan, 1, (int)$tahun));
        
        $nama_p = isset($row['nama']) ? htmlspecialchars($row['nama']) : 'Pelanggan';
        $no_wa_p = isset($row['no_wa']) ? htmlspecialchars($row['no_wa']) : '';
        
        $pesan_wa = "Halo Kak *" . $nama_p . "*, \n\nBerikut rincian tagihan internet You-One.net periode *" . $periode_tampil . "*:\n";
        $pesan_wa .= "• Tagihan Paket: *Rp " . number_format($total_t, 0, ',', '.') . "*\n";
        if ($tunggakan_awal > 0) {
            $pesan_wa .= "• Tunggakan Lalu: *Rp " . number_format($tunggakan_awal, 0, ',', '.') . "*\n";
        }
        $pesan_wa .= "• Total Keseluruhan: *Rp " . number_format($grand_total_awal, 0, ',', '.') . "*\n";
        
        if ($terbayar_bulan_ini > 0) {
            $pesan_wa .= "• Sudah Dibayar/Dicicil: *Rp " . number_format($terbayar_bulan_ini, 0, ',', '.') . "*\n";
            $pesan_wa .= "• *Sisa Harus Dibayar: Rp " . number_format($sisa_tagihan, 0, ',', '.') . "*\n";
        } else {
            $pesan_wa .= "• *Total Harus Dibayar: Rp " . number_format($sisa_tagihan, 0, ',', '.') . "*\n";
        }
        
        $pesan_wa .= "\nPembayaran dicicil sebagian tetap diterima ya kak. Mohon disetor sebelum jatuh tempo tanggal *" . $jatuh_tempo_tampil . "*. Terima kasih! 🙏";
        $link_wa = "https://api.whatsapp.com/send?phone=" . $no_wa_p . "&text=" . urlencode($pesan_wa);
        
        $row['tunggakan_awal'] = $tunggakan_awal;
        $row['tunggakan_tampil'] = $tunggakan_tampil;
        $row['grand_total_awal'] = $grand_total_awal;
        $row['sisa_tagihan'] = $sisa_tagihan;
        $row['is_lunas'] = $is_lunas;
        $row['terbayar_bulan_ini'] = $terbayar_bulan_ini;
        $row['periode_tampil'] = $periode_tampil;
        $row['jatuh_tempo_tampil'] = $jatuh_tempo_tampil;
        $row['link_wa'] = $link_wa;
    }
    $data_grouped[$row['dusun'] ?? 'Tanpa Dusun'][] = $row;
}

foreach ($data_grouped as $dusun => &$items) {
    usort($items, function($a, $b) {
        if ($a['is_lunas'] !== $b['is_lunas']) {
            return $a['is_lunas'] ? 1 : -1;
        }
        return strcmp($a['nama'], $b['nama']);
    });
}
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-slate-100 tracking-wide flex items-center gap-2">
            <i class="fa-solid fa-file-invoice-dollar text-amber-500"></i> Tagihan & Invoice
        </h3>
        <p class="text-xs text-slate-400 mt-1">
            Periode Berjalan: <span class="text-amber-400 font-semibold"><?= $nama_bulan_ini; ?></span>
        </p>
    </div>
    <form id="formGenerateTagihan" action="" method="POST" class="self-start sm:self-auto">
        <button type="button" onclick="konfirmasiGenerateTagihan()" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-bold text-xs px-4 py-2.5 rounded-xl transition-all duration-200 shadow-lg shadow-orange-950/40 flex items-center gap-2 active:scale-95">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Generate / Update Invoice
        </button>
        <input type="hidden" name="generate_tagihan" value="1">
    </form>
</div>

<!-- Kartu Ringkasan (Stat Cards) -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 flex items-center justify-between shadow-xl backdrop-blur-md">
        <div>
            <p class="text-xs font-medium text-slate-400">Total Terdata</p>
            <h4 class="text-2xl font-bold text-slate-100 mt-1"><?= $stat_total_warga; ?> <span class="text-xs font-normal text-slate-400">Pelanggan</span></h4>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-lg">
            <i class="fa-solid fa-users"></i>
        </div>
    </div>
    
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 flex items-center justify-between shadow-xl backdrop-blur-md">
        <div>
            <p class="text-xs font-medium text-slate-400">Status Lunas / Aman</p>
            <h4 class="text-2xl font-bold text-emerald-400 mt-1"><?= $stat_lunas_count; ?> <span class="text-xs font-normal text-slate-400">Invoice</span></h4>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>

    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 flex items-center justify-between shadow-xl backdrop-blur-md">
        <div>
            <p class="text-xs font-medium text-slate-400">Belum Lunas / Cicil</p>
            <h4 class="text-2xl font-bold text-rose-400 mt-1"><?= $stat_belum_lunas_count; ?> <span class="text-xs font-normal text-slate-400">Invoice</span></h4>
        </div>
        <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-lg">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
    </div>
</div>

<!-- Toolbar Pencarian -->
<div class="flex flex-col sm:flex-row justify-between items-center gap-3 mb-4">
    <div class="relative w-full sm:w-80">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
        <input type="text" id="searchTagihan" placeholder="Cari nama pelanggan atau invoice..." 
            class="w-full bg-slate-900/90 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/60 transition">
    </div>
    <div class="text-xs text-slate-400 self-end sm:self-auto">
        Wilayah Dikelola: <span class="text-amber-400 font-semibold"><?= !empty($filter_dusun) ? htmlspecialchars($filter_dusun) : (!empty($dusun_pengelola) ? htmlspecialchars($dusun_pengelola) : 'Semua Dusun'); ?></span>
    </div>
</div>

<!-- Tabel Data Tagihan Modern -->
<div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm text-slate-300">
            <thead class="bg-slate-950/90 text-slate-400 text-xs uppercase font-semibold tracking-wider border-b border-slate-800">
                <tr>
                    <th class="p-4">No. Invoice / Pelanggan</th>
                    <th class="p-4">Layanan</th>
                    <th class="p-4">Periode & Jatuh Tempo</th>
                    <th class="p-4">Total Tagihan</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-center w-40">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php 
                $ada_data = false;
                if (!empty($data_grouped)) {
                    foreach ($data_grouped as $dusun => $items) {
                        if (!empty($items)) $ada_data = true;
                    }
                }

                if ($ada_data) : 
                    foreach ($data_grouped as $dusun => $items) :
                        if (empty($items)) continue;
                        ?>
                        <!-- Header Dusun -->
                        <tr class="bg-slate-950/80 border-y border-slate-800">
                            <td colspan="6" class="px-4 py-2.5 font-bold text-xs text-amber-400 uppercase tracking-wider bg-amber-500/5">
                                <i class="fa-solid fa-map-location-dot mr-2 text-amber-500/80"></i>Dusun: <?= htmlspecialchars($dusun); ?> 
                                <span class="text-[10px] text-slate-500 font-normal lowercase ml-1">(<?= count($items); ?> pelanggan)</span>
                            </td>
                        </tr>
                        <?php
                        foreach ($items as $row) :
                            $id_p_aktif = $row['id_pelanggan'];
                            $inv_aktif  = !empty($row['no_invoice']) ? $row['no_invoice'] : '';
                            
                            $nama_p  = isset($row['nama']) ? htmlspecialchars($row['nama']) : 'Pelanggan';
                            $alamat_p = isset($row['alamat']) ? htmlspecialchars($row['alamat']) : '-';
                            $no_wa_p = isset($row['no_wa']) ? htmlspecialchars($row['no_wa']) : '';
                            $paket_p = isset($row['nama_paket']) ? htmlspecialchars($row['nama_paket']) : 'Tanpa Paket';
                            $total_t = isset($row['total_tagihan']) ? floatval($row['total_tagihan']) : 0;
                            
                            $periode_tampil = $row['periode_tampil'];
                            $jatuh_tempo_tampil = $row['jatuh_tempo_tampil'];
                            $terbayar_bulan_ini = $row['terbayar_bulan_ini'];
                            $tunggakan_awal = $row['tunggakan_awal'];
                            $tunggakan_tampil = $row['tunggakan_tampil'];
                            $grand_total_awal = $row['grand_total_awal'];
                            $sisa_tagihan = $row['sisa_tagihan'];
                            $is_lunas = $row['is_lunas'];
                            $link_wa = $row['link_wa'];
                ?>
                <tr class="hover:bg-slate-800/40 transition-colors row-tagihan">
                    <td class="p-4">
                        <div class="text-[11px] font-mono font-bold tracking-wider text-amber-400 uppercase"><?= htmlspecialchars($inv_aktif !== '' ? $inv_aktif : 'TIDAK ADA TAGIHAN'); ?></div>
                        <div class="font-bold text-slate-100 mt-0.5"><?= $nama_p; ?></div>
                        <div class="text-[11px] text-slate-400 mt-0.5 font-medium flex items-center">
                            <i class="fa-solid fa-location-dot mr-1.5 text-slate-500"></i><?= $alamat_p; ?>
                        </div>
                    </td>
                    
                    <td class="p-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                            <i class="fa-solid fa-wifi text-[10px] text-amber-400"></i>
                            <?= $paket_p; ?>
                        </span>
                    </td>
                    
                    <td class="p-4">
                        <div class="text-xs font-medium text-slate-300">
                            <i class="fa-regular fa-calendar-check mr-1 text-slate-500"></i><?= $periode_tampil; ?>
                        </div>
                        <div class="text-[11px] text-rose-400 mt-0.5 font-medium">
                            <i class="fa-solid fa-clock mr-1 text-rose-500/70"></i>Jatuh Tempo: <?= $jatuh_tempo_tampil; ?>
                        </div>
                    </td>
                    
                    <td class="p-4">
                        <div class="text-xs text-slate-400">Bulan Ini: Rp <?= number_format($total_t, 0, ',', '.'); ?></div>
                        
                        <?php if ($tunggakan_awal > 0) : ?>
                            <div class="text-xs <?= $tunggakan_tampil > 0 ? 'text-amber-400' : 'text-emerald-400' ?> mt-0.5 font-medium">
                                Tunggakan: Rp <?= number_format($tunggakan_tampil, 0, ',', '.'); ?>
                                <?php if ($tunggakan_tampil == 0) echo '<span class="text-[10px] text-emerald-500/80 ml-1 italic">(Tertutup)</span>'; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-sm text-slate-100 font-bold border-t border-slate-800/80 mt-1 pt-1">Sisa Total: Rp <?= number_format($sisa_tagihan, 0, ',', '.'); ?></div>
                    </td>
                    
                    <td class="p-4">
                        <?php if ($is_lunas) : ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                                <i class="fa-solid fa-circle-check text-[10px]"></i> Lunas / Aman
                            </span>
                        <?php elseif ($terbayar_bulan_ini > 0) : ?>
                            <div class="mb-1">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400">
                                    <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> Dicicil
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-400">Masuk: Rp <?= number_format($terbayar_bulan_ini, 0, ',', '.'); ?></div>
                            <div class="text-[10px] text-rose-400 font-semibold">Sisa: Rp <?= number_format($sisa_tagihan, 0, ',', '.'); ?></div>
                        <?php else : ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400">
                                <i class="fa-solid fa-circle-xmark text-[10px]"></i> Belum Lunas
                            </span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="p-4 text-center">
                        <div class="flex justify-center gap-2">
                            <?php if (!$is_lunas && !empty($inv_aktif)) : ?>
                                <button onclick="jalankanBayarCicil('<?= $inv_aktif; ?>', '<?= addslashes($nama_p); ?>', <?= $sisa_tagihan; ?>)"
                                    class="px-2.5 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-xl text-xs font-semibold transition border border-emerald-500/20 active:scale-90 flex items-center gap-1" title="Input Pembayaran">
                                    <i class="fa-solid fa-money-bill-wave"></i> Bayar
                                </button>
                                
                                <?php if (!empty($no_wa_p) && ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR' || $dusun == $dusun_pengelola)) : ?>
                                    <button onclick="jalankanKirimTagihan('<?= $inv_aktif; ?>')" 
                                        class="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 rounded-xl text-xs font-semibold transition border border-amber-500/20 active:scale-90 flex items-center gap-1" title="Kirim Tagihan WhatsApp">
                                        <i class="fa-brands fa-whatsapp text-sm"></i> Tagih
                                    </button>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="text-xs text-emerald-500/80 font-medium italic"><i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> Selesai</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php 
                        endforeach;
                    endforeach; 
                else : 
                ?>
                <tr>
                    <td colspan="6" class="p-8 text-center text-slate-500">
                        <i class="fa-solid fa-file-invoice-dollar text-2xl mb-2 block text-slate-600"></i>
                        Belum ada data pelanggan atau invoice yang tercatat.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL PEMBAYARAN -->
<div id="modalBayar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="flex justify-between items-center pb-4 border-b border-slate-800 mb-4">
            <h3 class="text-base font-bold text-slate-100 flex items-center gap-2">
                <i class="fa-solid fa-receipt text-amber-500"></i> Konfirmasi Pembayaran
            </h3>
            <button type="button" onclick="tutupModal()" class="text-slate-500 hover:text-slate-300 transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <p class="text-xs text-amber-400 mb-4 font-mono font-bold tracking-wider" id="textInvoice"></p>
        
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Pelanggan</label>
                <div id="textNamaPelanggan" class="text-slate-100 text-sm font-semibold p-3 bg-slate-950/60 rounded-xl border border-slate-800"></div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Nominal Bayar (Rp)</label>
                <div class="relative">
                    <i class="fa-solid fa-money-bill-wave absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="number" id="inputJumlah" class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Metode Pembayaran</label>
                <div class="relative">
                    <i class="fa-solid fa-wallet absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <select id="inputMetode" class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition appearance-none">
                        <option value="Cash">Cash / Tunai</option>
                        <option value="Transfer">Transfer Bank</option>
                        <option value="Dana/QRIS">Dana / QRIS</option>
                    </select>
                </div>
            </div>
            <div class="pt-2 flex justify-end gap-3">
                <button onclick="tutupModal()" class="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">Batal</button>
                <button id="btnSimpanBayar" onclick="prosesBayar()" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-bold rounded-xl text-xs transition shadow-lg shadow-emerald-950/30">Simpan Bayar</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT LOGIC INTERACTION -->
<script>
let invoiceAktif = "";

function jalankanBayarCicil(invoice, nama, sisaTagihan) {
    document.getElementById('textInvoice').innerText = "Invoice: " + invoice;
    document.getElementById('textNamaPelanggan').innerText = nama;
    document.getElementById('inputJumlah').value = sisaTagihan;
    invoiceAktif = invoice;
    document.getElementById('modalBayar').classList.remove('hidden');
}

function tutupModal() {
    document.getElementById('modalBayar').classList.add('hidden');
}

function prosesBayar() {
    let nominalInput = document.getElementById('inputJumlah').value;
    let nominal = parseFloat(nominalInput) || 0;
    let metode = document.getElementById('inputMetode').value;
    
    if (nominal > 0) {
        let btn = document.getElementById('btnSimpanBayar');
        if (btn) {
            btn.style.pointerEvents = 'none';
            btn.disabled = true;             
            btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin mr-1"></i> Memproses...'; 
        }

        window.location.href = `index.php?page=tagihan&action=set_lunas&invoice=${encodeURIComponent(invoiceAktif)}&jumlah=${nominal}&metode=${encodeURIComponent(metode)}`;
    } else {
        Swal.fire({
            title: '<span class="text-amber-400 font-bold tracking-wide">Peringatan</span>',
            text: 'Masukkan nominal yang valid!',
            icon: 'warning',
            background: '#0c101a',
            color: '#f1f5f9',
            confirmButtonColor: '#f59e0b',
            iconColor: '#f59e0b',
            customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
        });
    }
}

function jalankanKirimTagihan(invoice) {
    if (!invoice) {
        Swal.fire({
            title: '<span class="text-amber-400 font-bold tracking-wide">Peringatan</span>',
            text: 'Nomor invoice tidak ditemukan atau tidak valid!',
            icon: 'warning',
            background: '#0c101a',
            color: '#f1f5f9',
            confirmButtonColor: '#f59e0b',
            iconColor: '#f59e0b',
            customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
        });
        return;
    }

    Swal.fire({
        title: '<span class="text-amber-400 font-bold tracking-wide">Mengirim Tagihan...</span>',
        text: 'Sedang memproses pengiriman notifikasi WhatsApp.',
        background: '#0c101a',
        color: '#f1f5f9',
        iconColor: '#f59e0b',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(`index.php?page=tagihan&action=kirim_tagihan_wa&invoice=${encodeURIComponent(invoice)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    title: '<span class="text-emerald-400 font-bold tracking-wide">Berhasil Terkirim!</span>',
                    text: data.message || 'Notifikasi WhatsApp berhasil dikirim.',
                    icon: 'success',
                    background: '#0c101a',
                    color: '#f1f5f9',
                    confirmButtonColor: '#10b981',
                    iconColor: '#10b981',
                    customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
                });
            } else {
                Swal.fire({
                    title: '<span class="text-rose-400 font-bold tracking-wide">Gagal!</span>',
                    text: data.message || 'Gagal mengirim notifikasi tagihan.',
                    icon: 'error',
                    background: '#0c101a',
                    color: '#f1f5f9',
                    confirmButtonColor: '#ef4444',
                    iconColor: '#ef4444',
                    customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
                });
            }
        })
        .catch(error => {
            console.error('Error pengiriman tagihan:', error);
            Swal.fire({
                title: '<span class="text-rose-400 font-bold tracking-wide">Sistem Error!</span>',
                text: 'Terjadi kegagalan koneksi atau respons server tidak valid.',
                icon: 'error',
                background: '#0c101a',
                color: '#f1f5f9',
                confirmButtonColor: '#ef4444',
                iconColor: '#ef4444',
                customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
            });
        });
}

function konfirmasiGenerateTagihan() {
    Swal.fire({
        title: '<span class="text-amber-400 font-bold tracking-wide">Konfirmasi Generate</span>',
        text: 'Sistem akan mengecek dan membuat invoice bulanan yang belum ada untuk seluruh pelanggan Aktif. Lanjutkan?',
        icon: 'question',
        showCancelButton: true,
        background: '#0c101a',
        color: '#f1f5f9',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal',
        iconColor: '#f59e0b',
        customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formGenerateTagihan').submit();
        }
    });
}

document.getElementById('searchTagihan').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('.row-tagihan');

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        if (text.includes(filter)) {
            row.style.display = ""; 
        } else {
            row.style.display = "none"; 
        }
    });
});
</script>