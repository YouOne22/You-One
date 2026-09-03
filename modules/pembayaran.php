<?php
// modules/pembayaran.php

// PENGAMAN ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Pastikan session aktif untuk menampung data toast
}

// --- PROSES HAPUS PEMBAYARAN ---
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_bayar = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    // 1. Ambil data informasi terkait sebelum dihapus
    $q_cek_info = "SELECT pembayaran.no_invoice, tagihan.id_pelanggan, pelanggan.dusun, pelanggan.nama 
                   FROM pembayaran 
                   INNER JOIN tagihan ON pembayaran.no_invoice = tagihan.no_invoice 
                   INNER JOIN pelanggan ON tagihan.id_pelanggan = pelanggan.id_pelanggan 
                   WHERE pembayaran.id_pembayaran = '$id_bayar'";
    $res_info = mysqli_query($koneksi, $q_cek_info);
    $info_data = mysqli_fetch_assoc($res_info);
    
    if ($info_data) {
        $id_pelanggan_cek = $info_data['id_pelanggan'];
        $dusun_pelanggan  = $info_data['dusun'];
        $nama_pelanggan   = $info_data['nama'];
        
        // Hitung ulang total tagihan dan total pembayaran riil setelah pembayaran ini dihapus
        $q_tot_tagihan = mysqli_query($koneksi, "SELECT SUM(total_tagihan) as tot FROM tagihan WHERE id_pelanggan = '$id_pelanggan_cek'");
        $r_tot_tagihan = mysqli_fetch_assoc($q_tot_tagihan);
        $total_semua_tagihan = floatval($r_tot_tagihan['tot'] ?? 0);
        
        $q_tot_bayar = mysqli_query($koneksi, "SELECT SUM(pb.jumlah_bayar) as tot FROM pembayaran pb JOIN tagihan tg ON pb.no_invoice = tg.no_invoice WHERE tg.id_pelanggan = '$id_pelanggan_cek' AND pb.id_pembayaran != '$id_bayar'");
        $r_tot_bayar = mysqli_fetch_assoc($q_tot_bayar);
        $total_semua_bayar_baru = floatval($r_tot_bayar['tot'] ?? 0);
        
        $sisa_tunggakan_baru = max(0, $total_semua_tagihan - $total_semua_bayar_baru);
        $status_sheet = ($sisa_tunggakan_baru <= 0) ? "Lunas / Aman" : ($total_semua_bayar_baru > 0 ? "Dicicil" : "Belum Lunas");
    }
    
    $query_hapus = "DELETE FROM pembayaran WHERE id_pembayaran = '$id_bayar'";
    
    if (mysqli_query($koneksi, $query_hapus)) {
        
        // 2. Sinkronisasi otomatis ke Google Sheet setelah pembayaran dibatalkan
        if ($info_data && function_exists('update_google_sheet')) {
            $data_ke_sheet = [
                'nama'        => $nama_pelanggan,
                'tunggakan'   => $sisa_tunggakan_baru, 
                'total_bayar' => $total_semua_bayar_baru,
                'status'      => $status_sheet      
            ];
            update_google_sheet($dusun_pelanggan, $data_ke_sheet);
        }

        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Dibatalkan!', 'message' => 'Pembayaran berhasil dibatalkan.'];
        echo "<script>window.location='index.php?page=pembayaran';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => 'Gagal membatalkan pembayaran: ' . mysqli_error($koneksi)];
        echo "<script>window.location='index.php?page=pembayaran';</script>";
        exit();
    }
}

// --- AMBIL DAFTAR DUSUN UNTUK DROPDOWN FILTER ---
$q_dusun = mysqli_query($koneksi, "SELECT DISTINCT dusun FROM pelanggan WHERE dusun IS NOT NULL AND dusun != '' ORDER BY dusun ASC");

// --- LOGIKA FILTER PENCARIAN, DUSUN, BULAN & PETUGAS ---
$keyword      = isset($_GET['keyword']) ? mysqli_real_escape_string($koneksi, $_GET['keyword']) : '';
$bulan_filter = isset($_GET['bulan']) && !empty($_GET['bulan']) ? mysqli_real_escape_string($koneksi, $_GET['bulan']) : date('Y-m');
$dusun_filter = isset($_GET['dusun']) ? mysqli_real_escape_string($koneksi, $_GET['dusun']) : '';

$role_user  = strtoupper($_SESSION['role'] ?? '');
$dusun_user = mysqli_real_escape_string($koneksi, $_SESSION['dusun'] ?? '');

// Ambil ID User dari session
$id_user_session = mysqli_real_escape_string($koneksi, $_SESSION['id_user'] ?? $_SESSION['id'] ?? 0);

// Gunakan array untuk menampung kondisi WHERE
$conditions = [];

// 1. Filter Pencarian Keyword
if (!empty($keyword)) {
    $conditions[] = "(pembayaran.no_invoice LIKE '%$keyword%' OR pelanggan.nama LIKE '%$keyword%')";
}

// 2. Filter Berdasarkan Bulan Transaksi
if (!empty($bulan_filter)) {
    $conditions[] = "pembayaran.tanggal_bayar LIKE '$bulan_filter%'";
}

// 3. Filter Berdasarkan Dusun Pelanggan
if (!empty($dusun_filter)) {
    $conditions[] = "pelanggan.dusun = '$dusun_filter'";
}

// 4. Filter berdasarkan id_user petugas jika bukan admin
if ($role_user !== 'ADMIN' && $role_user !== 'ADMINISTRATOR') {
    $conditions[] = "pembayaran.id_user = '$id_user_session'";
}

// Gabungkan semua kondisi menjadi string WHERE
$where_sql = "";
if (count($conditions) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $conditions);
}

// --- AMBIL TOTAL KAS MASUK SESUAI FILTER ---
$q_total_kas = "SELECT SUM(pembayaran.jumlah_bayar) AS total_masuk 
                FROM pembayaran 
                INNER JOIN tagihan ON pembayaran.no_invoice = tagihan.no_invoice
                INNER JOIN pelanggan ON tagihan.id_pelanggan = pelanggan.id_pelanggan
                $where_sql";

$res_kas = mysqli_query($koneksi, $q_total_kas);
$data_kas = mysqli_fetch_assoc($res_kas);
$total_kas_periode = isset($data_kas['total_masuk']) ? floatval($data_kas['total_masuk']) : 0;

// --- QUERY UTAMA ---
$query_history = "SELECT pembayaran.*, 
                         pelanggan.nama AS nama_pelanggan, 
                         pelanggan.dusun,
                         paket_internet.nama_paket,
                         users.nama AS nama_petugas
                  FROM pembayaran 
                  INNER JOIN tagihan ON pembayaran.no_invoice = tagihan.no_invoice
                  INNER JOIN pelanggan ON tagihan.id_pelanggan = pelanggan.id_pelanggan
                  LEFT JOIN paket_internet ON pelanggan.id_paket = paket_internet.id
                  LEFT JOIN users ON pembayaran.id_user = users.id 
                  $where_sql 
                  ORDER BY pembayaran.id_pembayaran DESC";

$result_history = mysqli_query($koneksi, $query_history);
?>

<!-- Header Halaman -->
<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <div>
        <h3 class="text-2xl font-bold text-slate-100 tracking-wide flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-amber-500"></i> Riwayat Pembayaran
        </h3>
        <p class="text-xs text-slate-400 mt-1">
            Log transaksi masuk kas <span class="text-amber-400 font-semibold">YOU-ONE.net</span>
        </p>
    </div>
    
    <!-- Kartu Ringkasan (Stat Card) -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 flex items-center justify-between shadow-xl backdrop-blur-md min-w-[280px]">
        <div>
            <p class="text-xs font-medium text-slate-400">
                Total Kas (<?= date('M Y', strtotime($bulan_filter)); ?><?= !empty($dusun_filter) ? ' - ' . htmlspecialchars($dusun_filter) : ''; ?>)
            </p>
            <h4 class="text-xl font-bold text-emerald-400 mt-1 font-mono">
                Rp <?= number_format($total_kas_periode, 0, ',', '.'); ?>
            </h4>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg ml-4">
            <i class="fa-solid fa-sack-dollar"></i>
        </div>
    </div>
</div>

<!-- Toolbar Pencarian & Filter -->
<div class="mb-6 bg-slate-900/60 border border-slate-800/80 p-4 rounded-2xl shadow-xl backdrop-blur-md">
    <form action="index.php" method="GET" class="flex flex-col md:flex-row gap-3">
        <input type="hidden" name="page" value="pembayaran">
        
        <!-- Input Kata Kunci -->
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="text" name="keyword" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari invoice atau nama pelanggan..." 
                   class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/60 transition">
        </div>

        <!-- Dropdown Filter Dusun -->
        <div class="relative min-w-[160px]">
            <select name="dusun" class="w-full bg-slate-950/60 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-amber-500/60 transition cursor-pointer">
                <option value="">Semua Dusun</option>
                <?php 
                if ($q_dusun && mysqli_num_rows($q_dusun) > 0) {
                    while ($row_dusun = mysqli_fetch_assoc($q_dusun)) {
                        $d_name = $row_dusun['dusun'];
                        $selected = ($dusun_filter === $d_name) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($d_name) . "\" $selected>" . htmlspecialchars($d_name) . "</option>";
                    }
                }
                ?>
            </select>
        </div>

        <!-- Input Filter Bulan -->
        <div class="relative min-w-[170px]">
            <input type="month" name="bulan" value="<?= htmlspecialchars($bulan_filter); ?>" 
                   class="w-full bg-slate-950/60 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-amber-500/60 transition [color-scheme:dark]">
        </div>
        
        <div class="flex gap-2">
            <button type="submit" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 px-6 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 shadow-lg shadow-orange-950/40 flex items-center justify-center gap-2 active:scale-95 w-full md:w-auto">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            
            <?php if (!empty($keyword) || $bulan_filter !== date('Y-m') || !empty($dusun_filter)) : ?>
                <a href="index.php?page=pembayaran" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2.5 rounded-xl text-sm font-medium transition flex items-center justify-center gap-2 border border-slate-700" title="Reset Filter">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabel Riwayat Pembayaran -->
<div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm text-slate-300">
            <thead class="bg-slate-950/90 text-slate-400 text-xs uppercase font-semibold tracking-wider border-b border-slate-800">
                <tr>
                    <th class="p-4 w-12 text-center">No</th>
                    <th class="p-4">No. Invoice / Pelanggan</th>
                    <th class="p-4">Paket</th>
                    <th class="p-4">Waktu Transaksi</th>
                    <th class="p-4">Petugas</th>
                    <th class="p-4">Metode</th>
                    <th class="p-4 text-right">Jumlah Setor</th>
                    <th class="p-4 text-center w-24">Aksi</th> 
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php 
                if ($result_history && mysqli_num_rows($result_history) > 0) : 
                    $no = 1;
                    while ($transaksi = mysqli_fetch_assoc($result_history)) : 
                ?>
                <tr class="hover:bg-slate-800/40 transition-colors">
                    <td class="p-4 text-center text-slate-500 font-mono text-xs"><?= $no++; ?></td>
                    <td class="p-4">
                        <div class="text-[11px] font-mono font-bold tracking-wider text-amber-400 uppercase"><?= htmlspecialchars($transaksi['no_invoice']); ?></div>
                        <div class="font-bold text-slate-100 mt-0.5"><?= htmlspecialchars($transaksi['nama_pelanggan']); ?></div>
                        <?php if (!empty($transaksi['dusun'])): ?>
                            <div class="text-[10px] text-slate-500 font-medium flex items-center gap-1 mt-0.5">
                                <i class="fa-solid fa-location-dot text-slate-600"></i> <?= htmlspecialchars($transaksi['dusun']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                            <i class="fa-solid fa-wifi text-[10px] text-amber-400"></i>
                            <?= htmlspecialchars($transaksi['nama_paket'] ?? '-'); ?>
                        </span>
                    </td>
                    <td class="p-4">
                        <div class="text-xs font-medium text-slate-300">
                            <i class="fa-regular fa-calendar-check mr-1 text-slate-500"></i>
                            <?= date('d M Y', strtotime($transaksi['tanggal_bayar'])); ?>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5 font-mono">
                            <i class="fa-regular fa-clock mr-1 text-slate-500/70"></i>
                            <?= date('H:i', strtotime($transaksi['tanggal_bayar'])); ?> WIB
                        </div>
                    </td>
                    <td class="p-4">
                        <span class="text-xs font-medium text-slate-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-user-tie text-slate-500"></i>
                            <?= htmlspecialchars($transaksi['nama_petugas'] ?? 'Sistem'); ?>
                        </span>
                    </td>
                    <td class="p-4">
                        <span class="text-[11px] font-semibold text-slate-300 bg-slate-950/60 px-2.5 py-1 rounded-md border border-slate-700/50 uppercase tracking-wider">
                            <?= htmlspecialchars($transaksi['metode_pembayaran']); ?>
                        </span>
                    </td>
                    <td class="p-4 text-right font-mono font-bold text-emerald-400 bg-emerald-500/5">
                        + Rp <?= number_format($transaksi['jumlah_bayar'], 0, ',', '.'); ?>
                    </td>
                    <td class="p-4 text-center">
                        <button onclick="pemicuHapusPembayaran('<?= $transaksi['id_pembayaran']; ?>', '<?= htmlspecialchars($transaksi['no_invoice'], ENT_QUOTES); ?>', '<?= htmlspecialchars($transaksi['nama_pelanggan'], ENT_QUOTES); ?>')" 
                                class="w-8 h-8 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 inline-flex items-center justify-center transition border border-rose-500/20 active:scale-90" title="Batalkan Pembayaran">
                            <i class="fa-solid fa-trash-can text-sm"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; 
                else : ?>
                <tr>
                    <td colspan="8" class="p-8 text-center text-slate-500">
                        <i class="fa-solid fa-clock-rotate-left text-2xl mb-2 block text-slate-600"></i>
                        Tidak ada riwayat transaksi yang ditemukan untuk filter ini.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SweetAlert Handler -->
<?php if (isset($_SESSION['toast'])): 
    $toast_type  = $_SESSION['toast']['type'];
    $toast_title = $_SESSION['toast']['title'];
    $toast_msg   = $_SESSION['toast']['message'];
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $toast_type; ?>',
        title: '<span class="<?= $toast_type == 'success' ? 'text-emerald-400' : 'text-rose-400' ?> font-bold tracking-wide"><?= htmlspecialchars($toast_title, ENT_QUOTES); ?></span>',
        text: '<?= htmlspecialchars($toast_msg, ENT_QUOTES); ?>',
        background: '#0c101a',
        color: '#f1f5f9',
        confirmButtonColor: '<?= $toast_type == 'success' ? '#10b981' : '#ef4444' ?>',
        iconColor: '<?= $toast_type == 'success' ? '#10b981' : '#ef4444' ?>',
        confirmButtonText: 'OK',
        customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
    });
});
</script>
<?php 
    unset($_SESSION['toast']); 
endif; 
?>

<script>
function pemicuHapusPembayaran(id, invoice, nama) {
    Swal.fire({
        title: '<span class="text-slate-100 font-bold tracking-wide">Batalkan Pembayaran?</span>',
        html: `<div class="text-sm text-slate-300 text-left">
                  Apakah Anda yakin ingin membatalkan transaksi untuk invoice <br>
                  <span class="text-amber-400 font-mono font-bold tracking-wider">${invoice}</span> an. <span class="text-slate-100 font-bold">${nama}</span>?<br><br>
                  <div class="bg-rose-500/10 border border-rose-500/20 p-3 rounded-xl mt-2">
                      <span class="text-rose-400 text-xs font-semibold uppercase tracking-wider block mb-1">Peringatan Sistem:</span>
                      <span class="text-slate-300 text-[11px] leading-relaxed">Status tagihan pelanggan akan di-reset kembali dan nominal kas sistem/sheets akan otomatis dikurangi.</span>
                  </div>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        background: '#0c101a',
        color: '#f1f5f9',
        iconColor: '#f59e0b',
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Kembali',
        customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "index.php?page=pembayaran&action=hapus&id=" + id;
        }
    });
}
</script>