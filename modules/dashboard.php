<?php
// modules/dashboard.php
require_once 'config/koneksi.php';

// PENGAMAN ERROR
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$bulan_ini_format = date('Y-m');
$hari_ini_format  = date('Y-m-d');
$role_user = strtoupper($_SESSION['role'] ?? '');
$id_user_login = $_SESSION['id'] ?? $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0;
$nama_user_login = $_SESSION['nama'] ?? 'Petugas';

$is_admin = ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR');

// --- AUTOMATIC FALLBACK DETECTOR UNTUK DUSUN PETUGAS ---
$dusun_user = $_SESSION['dusun'] ?? '';
if (!$is_admin && empty($dusun_user)) {
    // Langkah 1: Coba ambil langsung dari kolom dusun di tabel users
    $check_user = mysqli_query($koneksi, "SELECT dusun FROM users WHERE id = '$id_user_login'");
    if ($check_user && mysqli_num_rows($check_user) > 0) {
        $row_user = mysqli_fetch_assoc($check_user);
        if (!empty($row_user['dusun'])) {
            $dusun_user = $row_user['dusun'];
        }
    }
    
    // Langkah 2: Jika di tabel users kosong, deteksi otomatis dari dusun pelanggan yang pernah dibayar melalui petugas ini
    if (empty($dusun_user)) {
        $check_pay = mysqli_query($koneksi, "SELECT pl.dusun FROM pembayaran p 
            INNER JOIN tagihan t ON p.no_invoice = t.no_invoice 
            INNER JOIN pelanggan pl ON t.id_pelanggan = pl.id_pelanggan 
            WHERE p.id_user = '$id_user_login' LIMIT 1");
        if ($check_pay && mysqli_num_rows($check_pay) > 0) {
            $row_pay = mysqli_fetch_assoc($check_pay);
            $dusun_user = $row_pay['dusun'];
        }
    }
}

// --- 1. FILTER QUERY ---
$filter_dusun = (!$is_admin) ? " WHERE dusun = '$dusun_user'" : "";
$filter_dusun_join = (!$is_admin) ? " WHERE pl.dusun = '$dusun_user' AND " : " WHERE ";

// --- 2. QUERY DATA UTAMA ---
$total_p = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pelanggan $filter_dusun"))['total'] ?? 0;
$aktif_p = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pelanggan " . ($filter_dusun ? $filter_dusun . " AND status = 'Aktif'" : " WHERE status = 'Aktif'")))['total'] ?? 0;

// LOGIKA SINKRON ISOLIR: Menghitung total pelanggan yang memiliki tunggakan kumulatif
$q_unpaid = "SELECT COUNT(DISTINCT pl.id_pelanggan) AS jml 
             FROM pelanggan pl
             INNER JOIN tagihan t ON pl.id_pelanggan = t.id_pelanggan
             $filter_dusun
             " . ($filter_dusun ? " AND " : " WHERE ") . "
             (SELECT IFNULL(SUM(t2.total_tagihan), 0) FROM tagihan t2 WHERE t2.id_pelanggan = pl.id_pelanggan) - 
             (SELECT IFNULL(SUM(p.jumlah_bayar), 0) FROM pembayaran p INNER JOIN tagihan t3 ON p.no_invoice = t3.no_invoice WHERE t3.id_pelanggan = pl.id_pelanggan) > 0";

$count_belum_bayar = mysqli_fetch_assoc(mysqli_query($koneksi, $q_unpaid))['jml'] ?? 0;

// QUERY PEMASUKAN BULAN INI
if ($is_admin) {
    $q_income = "SELECT SUM(jumlah_bayar) AS total FROM pembayaran WHERE tanggal_bayar LIKE '$bulan_ini_format%'";
} else {
    $q_income = "SELECT SUM(jumlah_bayar) AS total FROM pembayaran WHERE tanggal_bayar LIKE '$bulan_ini_format%' AND id_user = '$id_user_login'";
}
$pemasukan_bulan_ini = mysqli_fetch_assoc(mysqli_query($koneksi, $q_income))['total'] ?? 0;

// --- 3. QUERY PEMASUKAN DUSUN & PETUGAS ---
$query_inc_dusun = "SELECT pl.dusun, SUM(p.jumlah_bayar) as total FROM pembayaran p JOIN tagihan t ON p.no_invoice = t.no_invoice JOIN pelanggan pl ON t.id_pelanggan = pl.id_pelanggan WHERE p.tanggal_bayar LIKE '$bulan_ini_format%'" . (!$is_admin ? " AND pl.dusun = '$dusun_user'" : "") . " GROUP BY pl.dusun";
$res_inc_dusun = mysqli_query($koneksi, $query_inc_dusun);

$query_inc_petugas = "SELECT u.nama as nama_petugas, SUM(p.jumlah_bayar) as total FROM pembayaran p JOIN users u ON p.id_user = u.id WHERE p.tanggal_bayar LIKE '$bulan_ini_format%'" . (!$is_admin ? " AND u.id = '$id_user_login'" : "") . " GROUP BY u.id";
$res_inc_petugas = mysqli_query($koneksi, $query_inc_petugas);

// --- 4. DATA LIST ---
$res_bayar_hari_ini = mysqli_query($koneksi, "SELECT p.*, pl.nama FROM pembayaran p INNER JOIN tagihan t ON p.no_invoice = t.no_invoice INNER JOIN pelanggan pl ON t.id_pelanggan = pl.id_pelanggan WHERE DATE(p.tanggal_bayar) = '$hari_ini_format'" . (!$is_admin ? " AND pl.dusun = '$dusun_user'" : "") . " ORDER BY p.id_pembayaran DESC LIMIT 5");
$res_pelanggan_baru = mysqli_query($koneksi, "SELECT p.* FROM pelanggan p $filter_dusun ORDER BY p.id_pelanggan DESC LIMIT 5");

// --- 5. LOGIKA GRAFIK ---
$res_dates = mysqli_query($koneksi, "SELECT DISTINCT DAY(tanggal_bayar) as hari FROM pembayaran WHERE MONTH(tanggal_bayar) = '" . date('m') . "' AND YEAR(tanggal_bayar) = '" . date('Y') . "' ORDER BY hari ASC");
$labels_grafik = [];
while($d = mysqli_fetch_assoc($res_dates)) { $labels_grafik[] = (int)$d['hari']; }

$data_final_dusun = [];
$data_final_petugas_chart = [];

if ($is_admin) {
    // Data Grafik untuk Admin (Per Dusun)
    $q_dusun = mysqli_query($koneksi, "SELECT DISTINCT dusun FROM pelanggan");
    while($d = mysqli_fetch_assoc($q_dusun)){
        $dusun_name = $d['dusun'];
        $running_total = 0;
        foreach($labels_grafik as $h) {
            $res_val = mysqli_query($koneksi, "SELECT SUM(p.jumlah_bayar) as total FROM pembayaran p JOIN tagihan t ON p.no_invoice = t.no_invoice JOIN pelanggan pl ON t.id_pelanggan = pl.id_pelanggan WHERE DAY(p.tanggal_bayar) = '$h' AND pl.dusun = '$dusun_name' AND MONTH(p.tanggal_bayar) = '" . date('m') . "' AND YEAR(p.tanggal_bayar) = '" . date('Y') . "'");
            $val = mysqli_fetch_assoc($res_val)['total'] ?? 0;
            $running_total += $val;
            $data_final_dusun[$dusun_name][] = $running_total;
        }
    }
} else {
    // Data Grafik Khusus untuk Petugas Login (Pemasukan Pribadi Petugas)
    $running_total_petugas = 0;
    foreach($labels_grafik as $h) {
        $res_val = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total FROM pembayaran WHERE DAY(tanggal_bayar) = '$h' AND id_user = '$id_user_login' AND MONTH(tanggal_bayar) = '" . date('m') . "' AND YEAR(tanggal_bayar) = '" . date('Y') . "'");
        $val = mysqli_fetch_assoc($res_val)['total'] ?? 0;
        $running_total_petugas += $val;
        $data_final_petugas_chart[] = $running_total_petugas;
    }
}

$data_final_global = [];
if ($is_admin) {
    $run_global = 0;
    foreach($labels_grafik as $h) {
        $res_val = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total FROM pembayaran WHERE DAY(tanggal_bayar) = '$h' AND MONTH(tanggal_bayar) = '" . date('m') . "' AND YEAR(tanggal_bayar) = '" . date('Y') . "'");
        $val = mysqli_fetch_assoc($res_val)['total'] ?? 0;
        $run_global += $val;
        $data_final_global[] = $run_global;
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Kartu Standar Dashboard Glassmorphism */
    .dashboard-glass-card {
        background: rgba(11, 15, 23, 0.7) !important; 
        backdrop-filter: blur(20px) !important;       
        -webkit-backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255, 255, 255, 0.07) !important;                                
        border-radius: 20px; 
        box-shadow: 0px 10px 30px 0px rgba(0, 0, 0, 0.4) !important; 
        transition: all 0.25s ease;
    }
    
    .dashboard-glass-card:hover {
        border-color: rgba(249, 115, 22, 0.3) !important;
        box-shadow: 0px 12px 35px 0px rgba(249, 115, 22, 0.08) !important;
    }

    /* KARTU GREETING HERO BANNER - ULTRA PREMIUM MOBILE & DESKTOP */
    .greeting-card-hero {
        background: linear-gradient(135deg, rgba(249, 115, 22, 0.16) 0%, rgba(245, 158, 11, 0.08) 40%, rgba(11, 15, 23, 0.95) 100%) !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(249, 115, 22, 0.35) !important;
        border-top: 1px solid rgba(251, 191, 36, 0.5) !important;
        border-radius: 22px;
        box-shadow: 0 15px 10px -5px rgba(249, 115, 22, 0.2), inset 0 1px 1px rgba(255, 255, 255, 0.12) !important;
        position: relative;
        overflow: hidden;
    }
</style>

<!-- ROW 1: HEADER BANNER & STATS ATAS -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <!-- KARTU GREETING (HERO BANNER MODE) -->
    <div class="<?= $is_admin ? 'lg:col-span-4' : 'lg:col-span-6'; ?> greeting-card-hero p-6 flex flex-col justify-between">
        <!-- Ambient Glow & Floating Watermark Icon -->
        <div class="absolute -right-6 -bottom-6 w-36 h-36 bg-gradient-to-br from-orange-500/25 to-amber-500/15 blur-2xl rounded-full pointer-events-none"></div>
        <div class="absolute right-4 top-4 text-orange-500/10 text-6xl font-black pointer-events-none select-none">
            <i class="fa-solid fa-bolt"></i>
        </div>
        
        <div class="relative z-10">
            <!-- Badge Capsule Modern -->
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-orange-500/20 to-amber-500/20 border border-orange-500/35 text-orange-300 text-[11px] font-extrabold mb-3 shadow-md shadow-orange-500/10">
                <span class="w-2 h-2 rounded-full bg-orange-400 animate-ping"></span>
                <span>Ringkasan Sistem</span>
            </div>
            
            <!-- Title dengan Gradient Text Emas / Orange -->
            <h3 class="text-2xl font-black tracking-wide">
                <span class="bg-gradient-to-r from-orange-200 via-amber-300 to-orange-400 bg-clip-text text-transparent">
                    <?= $is_admin ? 'Halo Admin' : 'Halo ' . htmlspecialchars($nama_user_login); ?>
                </span> 👋
            </h3>
            <p class="text-xs text-slate-400 mt-1 font-medium">Semoga harimu menyenangkan & aktivitas lancar.</p>
        </div>

        <div class="mt-5 relative z-10">
            <!-- Notification Pill Glowing -->
            <div class="px-3.5 py-2.5 bg-slate-950/80 border border-orange-500/30 rounded-xl w-fit text-xs text-slate-200 shadow-lg shadow-orange-500/5 flex items-center gap-2.5 backdrop-blur-md">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                </span>
                <span>Ada <strong class="text-orange-400 font-extrabold underline decoration-orange-500/50 underline-offset-2"><?= $count_belum_bayar; ?> pelanggan</strong> belum lunas.</span>
            </div>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <!-- KARTU PENDAPATAN TOTAL -->
    <div class="lg:col-span-4 dashboard-glass-card p-6 flex flex-col justify-between relative overflow-hidden group">
        <div class="absolute right-4 top-4 w-12 h-12 bg-orange-500/10 border border-orange-500/20 rounded-2xl flex items-center justify-center text-orange-400 text-xl group-hover:scale-110 transition-transform shadow-inner">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <div>
            <span class="text-[11px] text-slate-400 font-extrabold uppercase tracking-wider">Pendapatan Total Bulan <?= date('M'); ?></span>
            <div class="text-3xl font-extrabold text-orange-400 mt-2 tracking-tight font-mono">
                Rp <?= number_format($pemasukan_bulan_ini, 0, ',', '.'); ?>
            </div>
        </div>
        <p class="text-[11px] text-slate-500 mt-3 flex items-center gap-1.5">
            <i class="fa-solid fa-circle-check text-emerald-400 text-[10px]"></i> Sesuai dengan data kas pembayaran
        </p>
    </div>
    <?php endif; ?>

    <!-- KARTU JAM REALTIME -->
    <div class="<?= $is_admin ? 'lg:col-span-4' : 'lg:col-span-6'; ?> dashboard-glass-card p-6 flex items-center justify-between">
        <div>
            <span class="text-[11px] text-slate-400 font-extrabold uppercase tracking-wider">Waktu Sistem</span>
            <div class="text-3xl font-mono font-bold text-slate-100 mt-1" id="live-clock">--:--:--</div>
            <div class="text-xs text-orange-400/80 font-semibold mt-1 flex items-center gap-1.5">
                <i class="fa-regular fa-calendar-check text-[11px]"></i> <?= date('l, d F Y'); ?>
            </div>
        </div>
        <div id="icon-time" class="w-14 h-14 rounded-2xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center text-3xl text-orange-400 shadow-lg">
            <i class="fa-solid fa-sun"></i>
        </div>
    </div>
</div>

<!-- ROW 2: STATISTIK RINGKAS -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <?php if ($is_admin): ?>
    <div class="dashboard-glass-card p-5 flex items-center justify-between">
        <div>
            <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Total Pelanggan</span>
            <span class="text-3xl font-extrabold text-slate-100 block mt-1 font-mono"><?= $total_p; ?></span>
        </div>
        <div class="w-11 h-11 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center text-orange-400 text-lg shadow-sm">
            <i class="fa-solid fa-users"></i>
        </div>
    </div>
    
    <div class="dashboard-glass-card p-5 flex items-center justify-between">
        <div>
            <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Pelanggan Aktif</span>
            <span class="text-3xl font-extrabold text-emerald-400 block mt-1 font-mono"><?= $aktif_p; ?></span>
        </div>
        <div class="w-11 h-11 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg shadow-sm">
            <i class="fa-solid fa-user-check"></i>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="dashboard-glass-card p-5 flex items-center justify-between <?= !$is_admin ? 'md:col-span-3' : ''; ?>">
        <div>
            <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Belum Lunas</span>
            <span class="text-3xl font-extrabold text-rose-400 block mt-1 font-mono"><?= $count_belum_bayar; ?></span>
        </div>
        <div class="w-11 h-11 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-lg shadow-sm">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
    </div>
</div>

<!-- ROW 3: TABEL PEMASUKAN DUSUN & PETUGAS -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <?php if ($is_admin): ?>
    <div class="lg:col-span-6 dashboard-glass-card p-6">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800/80">
            <h4 class="text-xs font-extrabold uppercase tracking-wider text-orange-400 flex items-center gap-2">
                <i class="fa-solid fa-map-pin"></i> Pemasukan Dusun
            </h4>
            <span class="text-[10px] bg-slate-900/80 px-2.5 py-1 rounded-full text-slate-400 border border-slate-800/80 font-mono">Bulan Ini</span>
        </div>
        <div class="space-y-2.5">
            <?php if (mysqli_num_rows($res_inc_dusun) > 0): while($d = mysqli_fetch_assoc($res_inc_dusun)): ?>
            <div class="flex justify-between items-center p-3 rounded-xl bg-slate-950/50 border border-slate-800/60 hover:border-orange-500/30 transition">
                <span class="text-xs font-semibold text-slate-300 flex items-center gap-2">
                    <i class="fa-solid fa-location-dot text-orange-400 text-xs"></i> Dusun <?= htmlspecialchars($d['dusun']); ?>
                </span>
                <span class="text-xs font-bold text-orange-400 font-mono">Rp <?= number_format($d['total'], 0, ',', '.'); ?></span>
            </div>
            <?php endwhile; else: ?>
            <div class="text-xs text-slate-500 py-3 text-center border border-dashed border-slate-800 rounded-xl">Belum ada pemasukan dusun bulan ini.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="<?= $is_admin ? 'lg:col-span-6' : 'lg:col-span-12'; ?> dashboard-glass-card p-6">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800/80">
            <h4 class="text-xs font-extrabold uppercase tracking-wider text-orange-400 flex items-center gap-2">
                <i class="fa-solid fa-user-tie"></i> Pemasukan Petugas
            </h4>
            <span class="text-[10px] bg-slate-900/80 px-2.5 py-1 rounded-full text-slate-400 border border-slate-800/80 font-mono">Bulan Ini</span>
        </div>
        <div class="space-y-2.5">
            <?php if (mysqli_num_rows($res_inc_petugas) > 0): while($u = mysqli_fetch_assoc($res_inc_petugas)): ?>
            <div class="flex justify-between items-center p-3 rounded-xl bg-slate-950/50 border border-slate-800/60 hover:border-orange-500/30 transition">
                <span class="text-xs font-semibold text-slate-300 flex items-center gap-2">
                    <i class="fa-solid fa-circle-user text-amber-400 text-xs"></i> <?= htmlspecialchars($u['nama_petugas']); ?>
                </span>
                <span class="text-xs font-bold text-amber-400 font-mono">Rp <?= number_format($u['total'], 0, ',', '.'); ?></span>
            </div>
            <?php endwhile; else: ?>
            <div class="text-xs text-slate-500 py-3 text-center border border-dashed border-slate-800 rounded-xl">Belum ada pemasukan petugas bulan ini.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ROW 4: GRAFIK PENDAPATAN -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
    <div class="<?= $is_admin ? 'lg:col-span-6' : 'lg:col-span-12'; ?> dashboard-glass-card p-6">
        <h4 class="text-xs font-extrabold uppercase tracking-wider text-orange-400 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-line"></i> <?= $is_admin ? 'Grafik Pendapatan (Per Dusun)' : 'Grafik Pendapatan Saya'; ?>
        </h4>
        <div class="w-full h-64"><canvas id="incomeChart"></canvas></div>
    </div>
    
    <?php if ($is_admin): ?>
    <div class="lg:col-span-6 dashboard-glass-card p-6">
        <h4 class="text-xs font-extrabold uppercase tracking-wider text-orange-400 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-area"></i> Pertumbuhan Pendapatan Global
        </h4>
        <div class="w-full h-64"><canvas id="globalIncomeChart"></canvas></div>
    </div>
    <?php endif; ?>
</div>

<!-- ROW 5: SETORAN HARI INI & PELANGGAN BARU -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="dashboard-glass-card p-6">
        <h4 class="text-xs font-extrabold uppercase tracking-wider text-orange-400 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-receipt"></i> Setoran Hari Ini
        </h4>
        <div class="space-y-2">
            <?php if(mysqli_num_rows($res_bayar_hari_ini) > 0): while($b = mysqli_fetch_assoc($res_bayar_hari_ini)): ?>
                <div class="flex justify-between items-center bg-slate-950/50 p-3 rounded-xl border border-slate-800/60 hover:border-orange-500/30 transition text-xs">
                    <span class="text-slate-200 font-semibold truncate w-36 flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-400 text-[10px]"></i> <?= htmlspecialchars($b['nama']); ?>
                    </span>
                    <span class="text-orange-400 font-bold font-mono">Rp <?= number_format($b['jumlah_bayar'], 0, ',', '.'); ?></span>
                </div>
            <?php endwhile; else: ?>
                <div class="text-xs text-slate-500 py-4 text-center border border-dashed border-slate-800/80 rounded-xl">Belum ada setoran hari ini.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-glass-card p-6">
        <h4 class="text-xs font-extrabold uppercase tracking-wider text-orange-400 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-user-plus"></i> Pelanggan Baru
        </h4>
        <div class="space-y-2">
            <?php if(mysqli_num_rows($res_pelanggan_baru) > 0): while($p = mysqli_fetch_assoc($res_pelanggan_baru)): ?>
                <div class="flex justify-between items-center bg-slate-950/50 p-3 rounded-xl border border-slate-800/60 hover:border-orange-500/30 transition text-xs">
                    <span class="text-slate-200 font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-user-tag text-amber-400 text-[10px]"></i> <?= htmlspecialchars($p['nama']); ?>
                    </span>
                    <span class="text-slate-400 bg-slate-900 px-2.5 py-0.5 rounded-md border border-slate-800 text-[10px] font-medium"><?= htmlspecialchars($p['dusun']); ?></span>
                </div>
            <?php endwhile; else: ?>
                <div class="text-xs text-slate-500 py-4 text-center border border-dashed border-slate-800/80 rounded-xl">Belum ada pelanggan baru.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SCRIPT UPDATE JAM & GRAFIK -->
<script>
function updateClock() {
    const now = new Date();
    const hours = now.getHours();
    const clockElem = document.getElementById('live-clock');
    if (clockElem) {
        clockElem.textContent = hours.toString().padStart(2, '0') + ":" + 
                                now.getMinutes().toString().padStart(2, '0') + ":" + 
                                now.getSeconds().toString().padStart(2, '0');
    }
    const iconContainer = document.getElementById('icon-time');
    if (iconContainer) {
        if (hours >= 6 && hours < 18) { 
            iconContainer.innerHTML = '<i class="fa-solid fa-sun"></i>'; 
            iconContainer.className = 'w-14 h-14 rounded-2xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center text-3xl text-orange-400 shadow-lg'; 
        } else { 
            iconContainer.innerHTML = '<i class="fa-solid fa-moon"></i>'; 
            iconContainer.className = 'w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-3xl text-amber-400 shadow-lg'; 
        }
    }
}
setInterval(updateClock, 1000); 
updateClock();

const labels = <?= json_encode($labels_grafik); ?>;
const isAdminUser = <?= $is_admin ? 'true' : 'false'; ?>;

const ctx = document.getElementById('incomeChart').getContext('2d');
const datasets = [];

if (isAdminUser) {
    const dataDusun = <?= json_encode($data_final_dusun); ?>;
    const colors = ['#f97316', '#f59e0b', '#10b981', '#3b82f6', '#ec4899']; 
    let i = 0;
    for (const [dusun, data] of Object.entries(dataDusun)) {
        datasets.push({ 
            label: 'Dusun ' + dusun, 
            data: data, 
            borderColor: colors[i % colors.length], 
            backgroundColor: colors[i % colors.length] + '1A', 
            borderWidth: 2.5, 
            tension: 0.35, 
            pointRadius: 3,
            fill: true 
        });
        i++;
    }
} else {
    const dataPetugasChart = <?= json_encode($data_final_petugas_chart); ?>;
    datasets.push({ 
        label: '<?= htmlspecialchars($nama_user_login); ?>', 
        data: dataPetugasChart, 
        borderColor: '#f97316', 
        backgroundColor: 'rgba(249, 115, 22, 0.15)', 
        borderWidth: 2.5, 
        tension: 0.35, 
        pointRadius: 3,
        fill: true 
    });
}

new Chart(ctx, {
    type: 'line',
    data: { labels: labels, datasets: datasets },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: {
            legend: {
                labels: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans', size: 11 } }
            }
        },
        scales: { 
            y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans' } } }, 
            x: { grid: { display: false }, ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans' } } } 
        } 
    }
});

<?php if ($is_admin): ?>
const dataGlobal = <?= json_encode($data_final_global); ?>;
const ctxGlobal = document.getElementById('globalIncomeChart').getContext('2d');
new Chart(ctxGlobal, {
    type: 'line',
    data: { 
        labels: labels, 
        datasets: [{ 
            label: 'Total Global', 
            data: dataGlobal, 
            borderColor: '#f59e0b', 
            backgroundColor: 'rgba(245, 158, 11, 0.15)', 
            borderWidth: 2.5,
            pointRadius: 3,
            fill: true, 
            tension: 0.4 
        }] 
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { display: false } }, 
        scales: { 
            y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans' } } }, 
            x: { grid: { display: false }, ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans' } } } 
        } 
    }
});
<?php endif; ?>
</script>