<?php
// modules/peta.php - Mode Debugging & Proteksi
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$role_user  = strtoupper($_SESSION['role'] ?? '');
$dusun_user = $_SESSION['dusun_pengelola'] ?? '';
$is_admin   = in_array($role_user, ['ADMIN', 'ADMINISTRATOR', 'SUPERADMIN'], true);

// --- PROTEKSI BACKEND FORMS ---
if (isset($_POST['tambah_odp_peta']) || isset($_POST['edit_odp_peta']) || isset($_POST['tambah_pelanggan_peta']) || isset($_POST['edit_pelanggan_peta'])) {
    if (!$is_admin) {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Akses Ditolak!', 'message' => 'Petugas hanya memiliki akses lihat (Read-Only).'];
        header("Location: index.php?page=peta");
        exit();
    }
}

// --- PROSES 1: TAMBAH ODP DARI PETA ---
if (isset($_POST['tambah_odp_peta'])) {
    $kode_odp   = trim($_POST['kode_odp'] ?? '');
    $nama_odp   = trim($_POST['nama_odp'] ?? '');
    if ($nama_odp === '') { $nama_odp = $kode_odp; }
    $jenis      = trim($_POST['jenis'] ?? 'ODP');
    $dusun      = trim($_POST['dusun'] ?? '');
    $kapasitas  = intval($_POST['kapasitas_port'] ?? 8);
    $latitude   = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? trim($_POST['latitude']) : null;
    $longitude  = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? trim($_POST['longitude']) : null;
    $parent_id  = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $keterangan = trim($_POST['keterangan'] ?? '');

    $stmt = mysqli_prepare($koneksi, "INSERT INTO odp (kode_odp, nama_odp, jenis, dusun, kapasitas_port, latitude, longitude, parent_id, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssissis", $kode_odp, $nama_odp, $jenis, $dusun, $kapasitas, $latitude, $longitude, $parent_id, $keterangan);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => "ODP ($kode_odp) berhasil ditambahkan."];
    } else {
        error_log("Error Query Tambah ODP: " . mysqli_stmt_error($stmt));
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => 'Gagal menambahkan ODP. Silakan coba lagi.'];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php?page=peta");
    exit();
}

// --- PROSES 2: EDIT ODP DARI PETA ---
if (isset($_POST['edit_odp_peta'])) {
    $id         = intval($_POST['id_odp'] ?? 0);
    $kode_odp   = trim($_POST['kode_odp'] ?? '');
    $nama_odp   = trim($_POST['nama_odp'] ?? '');
    if ($nama_odp === '') { $nama_odp = $kode_odp; }
    $jenis      = trim($_POST['jenis'] ?? 'ODP');
    $dusun      = trim($_POST['dusun'] ?? '');
    $kapasitas  = intval($_POST['kapasitas_port'] ?? 8);
    $latitude   = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? trim($_POST['latitude']) : null;
    $longitude  = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? trim($_POST['longitude']) : null;
    $parent_id  = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $keterangan = trim($_POST['keterangan'] ?? '');

    $stmt = mysqli_prepare($koneksi, "UPDATE odp SET kode_odp = ?, nama_odp = ?, jenis = ?, dusun = ?, kapasitas_port = ?, latitude = ?, longitude = ?, parent_id = ?, keterangan = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssssissisi", $kode_odp, $nama_odp, $jenis, $dusun, $kapasitas, $latitude, $longitude, $parent_id, $keterangan, $id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => "Data ODP ($kode_odp) berhasil diperbarui."];
    } else {
        error_log("Error Query Edit ODP: " . mysqli_stmt_error($stmt));
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => 'Gagal memperbarui ODP. Silakan coba lagi.'];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php?page=peta");
    exit();
}

// --- PROSES 3: TAMBAH PELANGGAN DARI PETA ---
if (isset($_POST['tambah_pelanggan_peta'])) {
    $nama      = trim($_POST['nama'] ?? '');
    $no_wa     = trim($_POST['no_wa'] ?? '');
    $id_odp    = !empty($_POST['id_odp']) ? intval($_POST['id_odp']) : null;
    $port_odp  = !empty($_POST['port_odp']) ? intval($_POST['port_odp']) : null;
    $status    = trim($_POST['status'] ?? 'Aktif');
    $dusun     = trim($_POST['dusun'] ?? '');
    $latitude  = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? trim($_POST['latitude']) : null;
    $longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? trim($_POST['longitude']) : null;

    $stmt = mysqli_prepare($koneksi, "INSERT INTO pelanggan (nama, no_wa, id_odp, port_odp, status, dusun, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssiissss", $nama, $no_wa, $id_odp, $port_odp, $status, $dusun, $latitude, $longitude);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => "Pelanggan ($nama) berhasil ditambahkan."];
    } else {
        error_log("Error Query Tambah Pelanggan: " . mysqli_stmt_error($stmt));
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => 'Gagal menambahkan pelanggan. Silakan coba lagi.'];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php?page=peta");
    exit();
}

// --- PROSES 4: EDIT PELANGGAN DARI PETA ---
if (isset($_POST['edit_pelanggan_peta'])) {
    $id        = intval($_POST['id_pelanggan'] ?? 0);
    $nama      = trim($_POST['nama'] ?? '');
    $no_wa     = trim($_POST['no_wa'] ?? '');
    $id_odp    = !empty($_POST['id_odp']) ? intval($_POST['id_odp']) : null;
    $port_odp  = !empty($_POST['port_odp']) ? intval($_POST['port_odp']) : null;
    $status    = trim($_POST['status'] ?? 'Aktif');
    $dusun     = trim($_POST['dusun'] ?? '');
    $latitude  = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? trim($_POST['latitude']) : null;
    $longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? trim($_POST['longitude']) : null;

    $stmt = mysqli_prepare($koneksi, "UPDATE pelanggan SET nama = ?, no_wa = ?, id_odp = ?, port_odp = ?, status = ?, dusun = ?, latitude = ?, longitude = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssiissssi", $nama, $no_wa, $id_odp, $port_odp, $status, $dusun, $latitude, $longitude, $id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => "Data Pelanggan ($nama) berhasil diperbarui."];
    } else {
        error_log("Error Query Edit Pelanggan: " . mysqli_stmt_error($stmt));
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => 'Gagal memperbarui data pelanggan. Silakan coba lagi.'];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php?page=peta");
    exit();
}

// 1. Query Data ODP
if ($is_admin) {
    $q_odp = "SELECT odp.*, odp.path_kabel, COALESCE(p.total, 0) AS terpakai, parent.kode_odp AS kode_parent
              FROM odp
              LEFT JOIN (
                  SELECT id_odp, COUNT(*) AS total 
                  FROM pelanggan 
                  WHERE id_odp IS NOT NULL 
                  GROUP BY id_odp
              ) p ON odp.id = p.id_odp
              LEFT JOIN odp parent ON odp.parent_id = parent.id";
    $res_odp = mysqli_query($koneksi, $q_odp);
} else {
    $stmt_odp = mysqli_prepare($koneksi, "SELECT odp.*, odp.path_kabel, COALESCE(p.total, 0) AS terpakai, parent.kode_odp AS kode_parent
              FROM odp
              LEFT JOIN (
                  SELECT id_odp, COUNT(*) AS total 
                  FROM pelanggan 
                  WHERE id_odp IS NOT NULL 
                  GROUP BY id_odp
              ) p ON odp.id = p.id_odp
              LEFT JOIN odp parent ON odp.parent_id = parent.id
              WHERE odp.dusun = ?");
    mysqli_stmt_bind_param($stmt_odp, "s", $dusun_user);
    mysqli_stmt_execute($stmt_odp);
    $res_odp = mysqli_stmt_get_result($stmt_odp);
}

$odp_list = [];
if ($res_odp) {
    while ($r = mysqli_fetch_assoc($res_odp)) {
        $odp_list[] = $r;
    }
}

// 2. Query Data Pelanggan
if ($is_admin) {
    $q_pel = "SELECT pelanggan.*, pelanggan.path_kabel, odp.kode_odp 
              FROM pelanggan 
              LEFT JOIN odp ON pelanggan.id_odp = odp.id 
              WHERE pelanggan.latitude IS NOT NULL 
                AND pelanggan.longitude IS NOT NULL
                AND pelanggan.latitude != '' 
                AND pelanggan.longitude != ''";
    $res_pel = mysqli_query($koneksi, $q_pel);
} else {
    $stmt_pel = mysqli_prepare($koneksi, "SELECT pelanggan.*, pelanggan.path_kabel, odp.kode_odp 
              FROM pelanggan 
              LEFT JOIN odp ON pelanggan.id_odp = odp.id 
              WHERE pelanggan.latitude IS NOT NULL 
                AND pelanggan.longitude IS NOT NULL
                AND pelanggan.latitude != '' 
                AND pelanggan.longitude != ''
                AND pelanggan.dusun = ?");
    mysqli_stmt_bind_param($stmt_pel, "s", $dusun_user);
    mysqli_stmt_execute($stmt_pel);
    $res_pel = mysqli_stmt_get_result($stmt_pel);
}

$pelanggan_list = [];
if ($res_pel) {
    while ($r = mysqli_fetch_assoc($res_pel)) {
        $pelanggan_list[] = $r;
    }
}
?>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Leaflet Geoman -->
<link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css" />
<script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.min.js"></script>

<style>
.custom-leaflet-tooltip {
    background: rgba(15, 23, 42, 0.95) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #f8fafc !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    padding: 3px 8px !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
    white-space: nowrap !important;
    backdrop-filter: blur(8px);
    transition: opacity 0.2s ease-in-out;
}
.leaflet-tooltip-top:before {
    border-top-color: rgba(15, 23, 42, 0.95) !important;
}

/* CSS untuk menyembunyikan seluruh label nama di peta */
.hide-map-labels .custom-leaflet-tooltip {
    display: none !important;
}

/* Style Garis Solid dengan Efek Shadow / Glow */
.fiber-backbone, .fiber-active, .fiber-isolir, .fiber-nonaktif {
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 0 5px rgba(0, 0, 0, 0.8));
    cursor: <?= $is_admin ? 'pointer' : 'default'; ?>;
}

.fiber-base-glow {
    opacity: 0.3;
    filter: drop-shadow(0 0 8px currentColor);
}

/* Style Titik Neon Hijau Bergerak (Moving Particle) */
.neon-particle {
    background: #2EFFFC;
    border-radius: 50%;
    box-shadow: 0 0 6px #FFFF00, 0 0 10px #FFFF00, 0 0 18px #FFFF00;
    width: 5px;
    height: 5px;
    transform: translate(-50%, -50%);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.6);
    border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(51, 65, 85, 0.8);
    border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(71, 85, 105, 1);
}
</style>

<!-- HEADER UTAMA HALAMAN -->
<div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 shadow-lg shadow-amber-500/10">
            <i class="fa-solid fa-map-location-dot text-xl"></i>
        </div>
        <div>
            <h3 class="text-xl font-bold text-slate-100 tracking-wide flex items-center gap-2">
                Peta Network & Sebaran ODP
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
                Pemetaan visual real-time infrastruktur jaringan fiber optic. 
                <?php if($is_admin): ?>
                    <span class="text-amber-400 font-semibold">(Klik garis untuk mengaktifkan belokan)</span>
                <?php else: ?>
                    <span class="text-amber-400 font-semibold">(Mode Lihat / Read-Only)</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2.5 flex-wrap">
        <!-- TOMBOL TOGGLE SEMBUNYIKAN / TAMPILKAN LABEL NAMA -->
        <button id="btn-toggle-labels" onclick="toggleLabels()" class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-700/80 text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition-all flex items-center gap-2 shadow-md hover:border-slate-600">
            <i id="btn-label-icon" class="fa-solid fa-eye text-cyan-400"></i>
            <span id="btn-label-text">Label Nama: Tampil</span>
        </button>

        <!-- TOMBOL TOGGLE SEMBUNYIKAN / TAMPILKAN TITIK PELANGGAN -->
        <button id="btn-toggle-pelanggan" onclick="togglePelangganMarkers()" class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-700/80 text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition-all flex items-center gap-2 shadow-md hover:border-slate-600">
            <i id="btn-pelanggan-icon" class="fa-solid fa-users text-emerald-400"></i>
            <span id="btn-pelanggan-text">Titik Pelanggan: Tampil</span>
        </button>

        <?php if ($is_admin): ?>
        <button id="btn-toggle-drag" onclick="toggleMarkerDrag()" class="px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-700/80 text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition-all flex items-center gap-2 shadow-md hover:border-slate-600">
            <i class="fa-solid fa-lock text-rose-400"></i>
            <span id="btn-drag-label">Posisi Marker: Terkunci</span>
        </button>
        <?php endif; ?>

        <div class="flex items-center gap-2 bg-slate-900/90 px-3.5 py-2 rounded-xl border border-slate-800 shadow-md">
            <div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></div>
            <span class="text-xs text-slate-300 font-medium">
                Total ODP: <b class="ml-1 text-amber-400 font-mono font-bold"><?= count($odp_list); ?></b>
            </span>
        </div>

        <div class="flex items-center gap-2 bg-slate-900/90 px-3.5 py-2 rounded-xl border border-slate-800 shadow-md">
            <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
            <span class="text-xs text-slate-300 font-medium">
                Mapped User: <b class="ml-1 text-emerald-400 font-mono font-bold"><?= count($pelanggan_list); ?></b>
            </span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- WIDGET MAP UTAMA -->
    <div class="lg:col-span-3 bg-slate-900/60 backdrop-blur-xl rounded-2xl p-2.5 border border-slate-800 shadow-2xl overflow-hidden relative">
        <div class="relative w-full h-[620px] rounded-xl overflow-hidden border border-slate-800/80 shadow-inner">
            <div id="map" class="w-full h-full z-10"></div>

            <?php if ($is_admin): ?>
            <!-- CONTEXT MENU KLIK KANAN -->
            <div id="map-context-menu" class="absolute hidden z-[9999] bg-slate-900/95 border border-slate-800 backdrop-blur-md rounded-2xl shadow-2xl py-2 w-60 text-xs text-slate-200 transition-all">
                <div class="px-4 py-2 border-b border-slate-800/80 flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Aksi Lokasi Peta</span>
                    <span id="ctx-coords" class="text-[10px] font-mono text-amber-400 font-bold bg-amber-500/10 px-2 py-0.5 rounded-md border border-amber-500/20"></span>
                </div>
                <div class="p-1 space-y-1">
                    <button type="button" onclick="openModalOdpFromMap()" class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-amber-500/10 hover:text-amber-300 text-slate-300 flex items-center gap-3 transition group border border-transparent hover:border-amber-500/20">
                        <div class="w-7 h-7 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-box text-xs"></i>
                        </div>
                        <div>
                            <div class="font-bold text-slate-200 group-hover:text-amber-300">Tambah ODP / ODC</div>
                            <div class="text-[10px] text-slate-500">Pasang tiang / box baru</div>
                        </div>
                    </button>
                    <button type="button" onclick="openModalPelangganFromMap()" class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-emerald-500/10 hover:text-emerald-300 text-slate-300 flex items-center gap-3 transition group border border-transparent hover:border-emerald-500/20">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-user-plus text-xs"></i>
                        </div>
                        <div>
                            <div class="font-bold text-slate-200 group-hover:text-emerald-300">Tambah Pelanggan</div>
                            <div class="text-[10px] text-slate-500">Pasang user di titik ini</div>
                        </div>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PANEL LEGENDA & KAPASITAS PORT -->
    <div class="space-y-4">
        <!-- LEGENDA NETWORK -->
        <div class="bg-slate-900/60 backdrop-blur-xl rounded-2xl p-5 border border-slate-800 shadow-xl">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-amber-400"></i> Legenda Network
            </h4>
            <div class="space-y-3 text-xs">
                <div class="flex items-center gap-3 p-1.5 rounded-lg hover:bg-slate-800/40 transition">
                    <span class="w-3.5 h-3.5 rounded-lg bg-amber-500 border border-amber-300 shadow-sm shadow-amber-500/50 flex-shrink-0"></span>
                    <span class="text-slate-300 font-medium">Titik ODP Utama / ODC</span>
                </div>
                <div class="flex items-center gap-3 p-1.5 rounded-lg hover:bg-slate-800/40 transition">
                    <span class="w-8 h-1.5 rounded bg-orange-500 shadow-sm shadow-orange-500 flex-shrink-0"></span>
                    <span class="text-slate-300 font-medium">Kabel Feeder (Solid + Shadow)</span>
                </div>
                <div class="flex items-center gap-3 p-1.5 rounded-lg hover:bg-slate-800/40 transition">
                    <span class="w-3.5 h-3.5 rounded-full bg-emerald-500 border border-emerald-300 shadow-sm shadow-emerald-500/50 flex-shrink-0"></span>
                    <span class="text-slate-300 font-medium">Pelanggan Aktif</span>
                </div>
                <div class="flex items-center gap-3 p-1.5 rounded-lg hover:bg-slate-800/40 transition">
                    <span class="w-3.5 h-3.5 rounded-full bg-amber-500 border border-amber-300 shadow-sm flex-shrink-0"></span>
                    <span class="text-slate-300 font-medium">Pelanggan Isolir</span>
                </div>
                <div class="flex items-center gap-3 p-1.5 rounded-lg hover:bg-slate-800/40 transition">
                    <span class="w-3.5 h-3.5 rounded-full bg-rose-500 border border-rose-300 shadow-sm flex-shrink-0"></span>
                    <span class="text-slate-300 font-medium">Pelanggan Nonaktif</span>
                </div>
            </div>
        </div>

        <!-- KAPASITAS PORT ODP -->
        <div class="bg-slate-900/60 backdrop-blur-xl rounded-2xl p-5 border border-slate-800 shadow-xl max-h-[380px] overflow-y-auto custom-scrollbar flex flex-col">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 sticky top-0 bg-slate-900/90 py-1 backdrop-blur-md z-10 flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-orange-400"></i> Kapasitas Port ODP
                </span>
                <span class="text-[10px] font-mono text-slate-500 uppercase">Status Usage</span>
            </h4>
            <div class="space-y-2.5">
                <?php foreach($odp_list as $odp): 
                    $kapasitas = (int)($odp['kapasitas_port'] ?? 0);
                    $terpakai  = (int)($odp['terpakai'] ?? 0);
                    $persen    = ($kapasitas > 0) ? round(($terpakai / $kapasitas) * 100) : 0;
                    $color     = ($persen >= 100) ? 'bg-rose-500' : (($persen >= 75) ? 'bg-amber-500' : 'bg-orange-500');
                    $badgeBg   = ($persen >= 100) ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : (($persen >= 75) ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20');
                ?>
                <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 hover:border-slate-700/80 transition">
                    <div class="flex justify-between items-center text-xs mb-1.5">
                        <span class="font-bold text-slate-200"><?= htmlspecialchars($odp['kode_odp'] ?? ''); ?></span>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded-md border <?= $badgeBg; ?>">
                            <?= $terpakai; ?> / <?= $kapasitas; ?> Port (<?= $persen; ?>%)
                        </span>
                    </div>
                    <div class="w-full bg-slate-800/80 h-2 rounded-full overflow-hidden p-0.5 border border-slate-700/30">
                        <div class="<?= $color; ?> h-full rounded-full transition-all duration-500 shadow-sm" style="width: <?= min($persen, 100); ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($is_admin): ?>
<!-- ==================== MODAL TAMBAH ODP ==================== -->
<div id="modal-tambah-odp" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden my-8 transform transition-all">
        <div class="p-5 border-b border-slate-800/80 flex justify-between items-center bg-slate-950/40">
            <h4 class="font-bold text-slate-200 text-base flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i class="fa-solid fa-box text-sm"></i>
                </div>
                Tambah ODP / ODC
            </h4>
            <button type="button" onclick="toggleModal('modal-tambah-odp')" class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kode ODP / ODC</label>
                    <input type="text" name="kode_odp" required placeholder="ODP-KMT-01"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-amber-400 font-bold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/30 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Nama ODP</label>
                    <input type="text" name="nama_odp" placeholder="ODP Pertigaan"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/30 transition">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Jenis</label>
                    <select name="jenis" required class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-300">
                        <option value="ODP">ODP (Distribution)</option>
                        <option value="ODC">ODC (Cabinet / Utama)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kapasitas Port</label>
                    <input type="number" name="kapasitas_port" value="8" required min="1"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 font-mono transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-amber-400 mb-2">Uplink / Induk (ODC / ODP Utama)</label>
                <select name="parent_id" class="w-full bg-slate-950/70 border border-amber-500/40 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-amber-300 font-semibold">
                    <option value="">-- Tidak Ada (Pusat / Standalone) --</option>
                    <?php foreach ($odp_list as $o): ?>
                        <option value="<?= $o['id']; ?>"> Hubungkan ke: <?= htmlspecialchars($o['kode_odp']); ?> (<?= $o['jenis']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Dusun</label>
                <select name="dusun" required
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-300">
                    <option value="Kemitir" <?= ($dusun_user == 'Kemitir') ? 'selected' : ''; ?>>Kemitir</option>
                    <option value="Ngoho" <?= ($dusun_user == 'Ngoho') ? 'selected' : ''; ?>>Ngoho</option>
                    <option value="Mbalong" <?= ($dusun_user == 'Mbalong') ? 'selected' : ''; ?>>Mbalong</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Latitude</label>
                    <input type="text" id="odp-lat" name="latitude" readonly required
                        class="w-full bg-slate-950 border border-amber-500/40 rounded-xl px-4 py-2.5 text-sm text-amber-300 font-mono font-bold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Longitude</label>
                    <input type="text" id="odp-lng" name="longitude" readonly required
                        class="w-full bg-slate-950 border border-amber-500/40 rounded-xl px-4 py-2.5 text-sm text-amber-300 font-mono font-bold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Keterangan / Patokan</label>
                <textarea name="keterangan" rows="2" placeholder="Samping tiang listrik..."
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition"></textarea>
            </div>

            <div class="pt-3 flex justify-end gap-3 border-t border-slate-800/80">
                <button type="button" onclick="toggleModal('modal-tambah-odp')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">Batal</button>
                <button type="submit" name="tambah_odp_peta" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-amber-500/20">Simpan ODP</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL EDIT ODP ==================== -->
<div id="modal-edit-odp" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden my-8 transform transition-all">
        <div class="p-5 border-b border-slate-800/80 flex justify-between items-center bg-slate-950/40">
            <h4 class="font-bold text-slate-200 text-base flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i class="fa-solid fa-pen-to-square text-sm"></i>
                </div>
                Edit Data ODP / ODC
            </h4>
            <button type="button" onclick="toggleModal('modal-edit-odp')" class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id_odp" id="edit-odp-id">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kode ODP / ODC</label>
                    <input type="text" id="edit-odp-kode" name="kode_odp" required
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-amber-400 font-bold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/30 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Nama ODP</label>
                    <input type="text" id="edit-odp-nama" name="nama_odp"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500/30 transition">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Jenis</label>
                    <select id="edit-odp-jenis" name="jenis" required class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-300">
                        <option value="ODP">ODP (Distribution)</option>
                        <option value="ODC">ODC (Cabinet / Utama)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kapasitas Port</label>
                    <input type="number" id="edit-odp-kapasitas" name="kapasitas_port" required min="1"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 font-mono transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-amber-400 mb-2">Uplink / Induk (ODC / ODP Utama)</label>
                <select id="edit-odp-parent" name="parent_id" class="w-full bg-slate-950/70 border border-amber-500/40 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-amber-300 font-semibold">
                    <option value="">-- Tidak Ada (Pusat / Standalone) --</option>
                    <?php foreach ($odp_list as $o): ?>
                        <option value="<?= $o['id']; ?>"> Hubungkan ke: <?= htmlspecialchars($o['kode_odp']); ?> (<?= $o['jenis']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Dusun</label>
                <select id="edit-odp-dusun" name="dusun" required
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-300">
                    <option value="Kemitir">Kemitir</option>
                    <option value="Ngoho">Ngoho</option>
                    <option value="Mbalong">Mbalong</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Latitude</label>
                    <input type="text" id="edit-odp-lat" name="latitude" required
                        class="w-full bg-slate-950 border border-amber-500/40 rounded-xl px-4 py-2.5 text-sm text-amber-300 font-mono font-bold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Longitude</label>
                    <input type="text" id="edit-odp-lng" name="longitude" required
                        class="w-full bg-slate-950 border border-amber-500/40 rounded-xl px-4 py-2.5 text-sm text-amber-300 font-mono font-bold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Keterangan / Patokan</label>
                <textarea id="edit-odp-keterangan" name="keterangan" rows="2"
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition"></textarea>
            </div>

            <div class="pt-3 flex justify-end gap-3 border-t border-slate-800/80">
                <button type="button" onclick="toggleModal('modal-edit-odp')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">Batal</button>
                <button type="submit" name="edit_odp_peta" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-amber-500/20">Update ODP</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL TAMBAH PELANGGAN ==================== -->
<div id="modal-tambah-pelanggan" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden my-8 transform transition-all">
        <div class="p-5 border-b border-slate-800/80 flex justify-between items-center bg-slate-950/40">
            <h4 class="font-bold text-slate-200 text-base flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-user-plus text-sm"></i>
                </div>
                Tambah Pelanggan Baru
            </h4>
            <button type="button" onclick="toggleModal('modal-tambah-pelanggan')" class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Nama Pelanggan</label>
                <input type="text" name="nama" required placeholder="Bpk. Ahmad"
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">No. WhatsApp</label>
                    <input type="text" name="no_wa" placeholder="08123456789"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Dusun</label>
                    <select name="dusun" required
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 text-slate-300">
                        <option value="Kemitir" <?= ($dusun_user == 'Kemitir') ? 'selected' : ''; ?>>Kemitir</option>
                        <option value="Ngoho" <?= ($dusun_user == 'Ngoho') ? 'selected' : ''; ?>>Ngoho</option>
                        <option value="Mbalong" <?= ($dusun_user == 'Mbalong') ? 'selected' : ''; ?>>Mbalong</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Hubungkan ODP</label>
                    <select name="id_odp" class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 text-slate-300">
                        <option value="">-- Pilih ODP --</option>
                        <?php foreach ($odp_list as $o): ?>
                            <option value="<?= $o['id']; ?>"><?= htmlspecialchars($o['kode_odp']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Port ODP</label>
                    <input type="number" name="port_odp" min="1" max="64" placeholder="1"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 text-slate-200 font-mono transition">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Latitude</label>
                    <input type="text" id="pelanggan-lat" name="latitude" readonly required
                        class="w-full bg-slate-950 border border-emerald-500/40 rounded-xl px-4 py-2.5 text-sm text-emerald-300 font-mono font-bold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Longitude</label>
                    <input type="text" id="pelanggan-lng" name="longitude" readonly required
                        class="w-full bg-slate-950 border border-emerald-500/40 rounded-xl px-4 py-2.5 text-sm text-emerald-300 font-mono font-bold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Status Subskripsi</label>
                <select name="status" class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 text-slate-300">
                    <option value="Aktif">Aktif</option>
                    <option value="Isolir">Isolir</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>

            <div class="pt-3 flex justify-end gap-3 border-t border-slate-800/80">
                <button type="button" onclick="toggleModal('modal-tambah-pelanggan')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">Batal</button>
                <button type="submit" name="tambah_pelanggan_peta" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-600/20">Simpan Pelanggan</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL EDIT PELANGGAN ==================== -->
<div id="modal-edit-pelanggan" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden my-8 transform transition-all">
        <div class="p-5 border-b border-slate-800/80 flex justify-between items-center bg-slate-950/40">
            <h4 class="font-bold text-slate-200 text-base flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-user-pen text-sm"></i>
                </div>
                Edit Data Pelanggan
            </h4>
            <button type="button" onclick="toggleModal('modal-edit-pelanggan')" class="w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id_pelanggan" id="edit-pelanggan-id">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Nama Pelanggan</label>
                <input type="text" id="edit-pelanggan-nama" name="nama" required
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">No. WhatsApp</label>
                    <input type="text" id="edit-pelanggan-wa" name="no_wa"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/30 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Dusun</label>
                    <select id="edit-pelanggan-dusun" name="dusun" required
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-300 focus:outline-none focus:border-emerald-500">
                        <option value="Kemitir">Kemitir</option>
                        <option value="Ngoho">Ngoho</option>
                        <option value="Mbalong">Mbalong</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Hubungkan ODP</label>
                    <select id="edit-pelanggan-odp" name="id_odp" class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 text-slate-300">
                        <option value="">-- Pilih ODP --</option>
                        <?php foreach ($odp_list as $o): ?>
                            <option value="<?= $o['id']; ?>"><?= htmlspecialchars($o['kode_odp']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Port ODP</label>
                    <input type="number" id="edit-pelanggan-port" name="port_odp" min="1" max="64"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 text-slate-200 font-mono transition">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Latitude</label>
                    <input type="text" id="edit-pelanggan-lat" name="latitude" required
                        class="w-full bg-slate-950 border border-emerald-500/40 rounded-xl px-4 py-2.5 text-sm text-emerald-300 font-mono font-bold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Longitude</label>
                    <input type="text" id="edit-pelanggan-lng" name="longitude" required
                        class="w-full bg-slate-950 border border-emerald-500/40 rounded-xl px-4 py-2.5 text-sm text-emerald-300 font-mono font-bold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Status Subskripsi</label>
                <select id="edit-pelanggan-status" name="status" class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-emerald-500 text-slate-300">
                    <option value="Aktif">Aktif</option>
                    <option value="Isolir">Isolir</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>

            <div class="pt-3 flex justify-end gap-3 border-t border-slate-800/80">
                <button type="button" onclick="toggleModal('modal-edit-pelanggan')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">Batal</button>
                <button type="submit" name="edit_pelanggan_peta" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-600/20">Update Pelanggan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['toast'])): 
    $toast_type  = $_SESSION['toast']['type'] ?? 'info';
    $toast_title = $_SESSION['toast']['title'] ?? '';
    $toast_msg   = $_SESSION['toast']['message'] ?? '';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: '<?= $toast_type; ?>',
            title: '<?= htmlspecialchars($toast_title, ENT_QUOTES); ?>',
            text: '<?= htmlspecialchars($toast_msg, ENT_QUOTES); ?>',
            background: '#0c101a',
            color: '#f1f5f9',
            confirmButtonColor: '#f97316',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'border border-slate-800 rounded-2xl shadow-2xl backdrop-blur-md',
                title: 'font-bold tracking-wide text-xl text-slate-100',
                htmlContainer: 'text-sm text-slate-300 mt-2'
            }
        });
    }
});
</script>
<?php 
    unset($_SESSION['toast']); 
endif; 
?>

<script>
// FLAG HAK AKSES PETA DARI SERVER
const isAdmin = <?= json_encode($is_admin); ?>;

let clickedLat = 0;
let clickedLng = 0;
let globalOdpData = [];
let globalPelangganData = [];

// STATUS KUNCI MARKER (DEFAULT: TERKUNCI SUPAYA TIDAK KETARIK TANPA SENGAJA)
let isDragEnabled = false;

// STATUS LABEL VISIBILITAS
let isLabelVisible = true;

// STATUS VISIBILITAS TITIK PELANGGAN
let isPelangganVisible = true;

const odpMarkers = {};
const pelMarkers = {};
const cablePolylines = {};
const backbonePolylines = {};

// Deklarasikan variabel map secara global agar dapat diakses fungsi luar DOMContentLoaded
let map;

/**
 * FUNGSI TOGGLE SEMBUNYIKAN / TAMPILKAN TITIK PELANGGAN & KABEL DROP
 */
function togglePelangganMarkers() {
    isPelangganVisible = !isPelangganVisible;
    const btnIcon = document.getElementById('btn-pelanggan-icon');
    const btnText = document.getElementById('btn-pelanggan-text');
    const btn     = document.getElementById('btn-toggle-pelanggan');

    // Sembunyikan/Tampilkan marker pelanggan
    Object.values(pelMarkers).forEach(m => {
        if (isPelangganVisible) {
            if (map && !map.hasLayer(m)) m.addTo(map);
        } else {
            if (map && map.hasLayer(m)) map.removeLayer(m);
        }
    });

    // Sembunyikan/Tampilkan kabel drop pelanggan
    Object.values(cablePolylines).forEach(fiber => {
        if (isPelangganVisible) {
            if (fiber && fiber.group && map && !map.hasLayer(fiber.group)) fiber.group.addTo(map);
        } else {
            if (fiber && fiber.group && map && map.hasLayer(fiber.group)) map.removeLayer(fiber.group);
        }
    });

    if (btnIcon && btnText && btn) {
        if (isPelangganVisible) {
            btnIcon.className = 'fa-solid fa-users text-emerald-400';
            btnText.innerText = 'Titik Pelanggan: Tampil';
            btn.classList.remove('border-emerald-500/50', 'bg-slate-800');
        } else {
            btnIcon.className = 'fa-solid fa-users-slash text-slate-500';
            btnText.innerText = 'Titik Pelanggan: Sembunyi';
            btn.classList.add('border-emerald-500/50', 'bg-slate-800');
        }
    }

    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500,
            background: '#0c101a',
            color: '#f1f5f9'
        });
        Toast.fire({
            icon: 'info',
            title: isPelangganVisible ? 'Titik Pelanggan ditampilkan' : 'Titik Pelanggan disembunyikan'
        });
    }
}

/**
 * FUNGSI TOGGLE SEMBUNYIKAN / TAMPILKAN LABEL NAMA
 */
function toggleLabels() {
    isLabelVisible = !isLabelVisible;
    const mapWidget = document.getElementById('map');
    const btnIcon   = document.getElementById('btn-label-icon');
    const btnText   = document.getElementById('btn-label-text');
    const btn       = document.getElementById('btn-toggle-labels');

    if (isLabelVisible) {
        if (mapWidget) mapWidget.classList.remove('hide-map-labels');
        if (btnIcon)   btnIcon.className = 'fa-solid fa-eye text-cyan-400';
        if (btnText)   btnText.innerText = 'Label Nama: Tampil';
        if (btn)       btn.classList.remove('border-amber-500/50', 'bg-slate-800');
    } else {
        if (mapWidget) mapWidget.classList.add('hide-map-labels');
        if (btnIcon)   btnIcon.className = 'fa-solid fa-eye-slash text-slate-500';
        if (btnText)   btnText.innerText = 'Label Nama: Sembunyi';
        if (btn)       btn.classList.add('border-amber-500/50', 'bg-slate-800');
    }

    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500,
            background: '#0c101a',
            color: '#f1f5f9'
        });
        Toast.fire({
            icon: 'info',
            title: isLabelVisible ? 'Label nama ditampilkan' : 'Label nama disembunyikan'
        });
    }
}

/**
 * FUNGSI TOGGLE LOCK / UNLOCK DRAGGING MARKER
 */
function toggleMarkerDrag() {
    if (!isAdmin) return;

    isDragEnabled = !isDragEnabled;
    const btn = document.getElementById('btn-toggle-drag');
    const label = document.getElementById('btn-drag-label');

    Object.values(odpMarkers).forEach(m => {
        if (isDragEnabled) m.dragging.enable();
        else m.dragging.disable();
    });

    Object.values(pelMarkers).forEach(m => {
        if (isDragEnabled) m.dragging.enable();
        else m.dragging.disable();
    });

    if (btn && label) {
        if (isDragEnabled) {
            btn.innerHTML = `<i class="fa-solid fa-lock-open text-emerald-400"></i> <span id="btn-drag-label" class="text-emerald-400 font-bold">Mode Geser Marker (Aktif)</span>`;
            btn.classList.add('border-emerald-500/50', 'bg-emerald-950/40');
        } else {
            btn.innerHTML = `<i class="fa-solid fa-lock text-rose-400"></i> <span id="btn-drag-label" class="text-slate-300">Posisi Marker: Terkunci</span>`;
            btn.classList.remove('border-emerald-500/50', 'bg-emerald-950/40');
        }
    }

    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            background: '#0c101a',
            color: '#f1f5f9'
        });
        Toast.fire({
            icon: isDragEnabled ? 'info' : 'success',
            title: isDragEnabled ? 'Mode geser posisi diaktifkan' : 'Posisi marker dikunci kembali'
        });
    }
}

/**
 * FUNGSI ANIMASI TITIK NEON BERGERAK SEPANJANG JALUR KABEL
 */
function startMovingParticle(mapInstance, latlngs) {
    if (!latlngs || latlngs.length < 2) return null;

    const neonIcon = L.divIcon({
        className: 'neon-particle',
        iconSize: [9, 9],
        iconAnchor: [4.5, 4.5]
    });

    let particleMarker = L.marker(latlngs[0], { icon: neonIcon, interactive: false }).addTo(mapInstance);
    let duration = 4000; // 4 detik per siklus
    let startTime = null;

    function animateStep(timestamp) {
        if (!startTime) startTime = timestamp;
        let elapsed = timestamp - startTime;
        let progress = (elapsed % duration) / duration;

        let totalSegments = latlngs.length - 1;
        let exactSeg = progress * totalSegments;
        let segIdx = Math.floor(exactSeg);
        if (segIdx >= totalSegments) segIdx = totalSegments - 1;
        let segProgress = exactSeg - segIdx;

        let p1 = latlngs[segIdx];
        let p2 = latlngs[segIdx + 1];

        let lat1 = typeof p1.lat === 'number' ? p1.lat : p1[0];
        let lng1 = typeof p1.lng === 'number' ? p1.lng : p1[1];
        let lat2 = typeof p2.lat === 'number' ? p2.lat : p2[0];
        let lng2 = typeof p2.lng === 'number' ? p2.lng : p2[1];

        let currLat = lat1 + (lat2 - lat1) * segProgress;
        let currLng = lng1 + (lng2 - lng1) * segProgress;

        particleMarker.setLatLng([currLat, currLng]);
        requestAnimationFrame(animateStep);
    }
    requestAnimationFrame(animateStep);
    return particleMarker;
}

/**
 * FUNGSI GARIS FIBER SOLID DENGAN SHADOW & PARTIKEL NEON
 */
function drawFiberLine(mapInstance, latlngs, status = 'aktif', itemType = null, itemId = null) {
    let colorHex = '#10b981';
    let lineClass = 'fiber-active';

    const statusClean = String(status).toLowerCase();
    if (statusClean === 'isolir') {
        colorHex = '#f59e0b';
        lineClass = 'fiber-isolir';
    } else if (statusClean === 'nonaktif') {
        colorHex = '#ef4444';
        lineClass = 'fiber-nonaktif';
    } else if (statusClean === 'backbone') {
        colorHex = '#f97316';
        lineClass = 'fiber-backbone';
    }

    const baseLine = L.polyline(latlngs, {
        color: colorHex,
        weight: (statusClean === 'backbone' ? 5 : 4),
        opacity: 0.3,
        className: 'fiber-base-glow',
        smoothFactor: 1
    });

    const mainLine = L.polyline(latlngs, {
        color: colorHex,
        weight: (statusClean === 'backbone' ? 3.5 : 2.5),
        opacity: 0.95,
        className: lineClass,
        smoothFactor: 1
    });

    // Jalankan partikel titik neon bergerak di sepanjang garis
    startMovingParticle(mapInstance, latlngs);

    if (isAdmin) {
        mainLine.pm.enable({
            snappingOption: true,
            allowSelfIntersection: false
        });
        mainLine.pm.disable();

        mainLine.on('click', function(e) {
            L.DomEvent.stopPropagation(e);
            if (mainLine.pm.enabled()) {
                mainLine.pm.disable();
            } else {
                mainLine.pm.enable();
            }
        });

        mainLine.on('pm:edit pm:vertexadded pm:vertexremoved pm:centerplaced pm:markerdragend pm:disable', function() {
            saveFiberPath();
        });
    }

    function saveFiberPath() {
        if (!isAdmin) return;

        baseLine.setLatLngs(mainLine.getLatLngs());

        if (itemType && itemId) {
            const rawCoords = mainLine.getLatLngs();
            const formattedPath = rawCoords.map(pt => [
                parseFloat(pt.lat.toFixed(6)), 
                parseFloat(pt.lng.toFixed(6))
            ]);

            const formData = new FormData();
            formData.append('type', itemType);
            formData.append('id', itemId);
            formData.append('path_kabel', JSON.stringify(formattedPath));

            fetch('modules/update_jalur_kabel.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: data.status === 'success' ? 1500 : 4000,
                        background: '#0c101a',
                        color: '#f1f5f9'
                    });
                    if (data.status === 'success') {
                        Toast.fire({
                            icon: 'success',
                            title: 'Belokan kabel disimpan!'
                        });
                    } else {
                        console.error('Gagal simpan jalur kabel:', data.message);
                        Toast.fire({
                            icon: 'error',
                            title: 'Gagal simpan: ' + (data.message || 'unknown error')
                        });
                    }
                } else if (data.status !== 'success') {
                    console.error('Gagal simpan jalur kabel:', data.message);
                }
            })
            .catch(err => console.error('Gagal simpan jalur (fetch error):', err));
        }
    }

    const fiberGroup = L.featureGroup([baseLine, mainLine]).addTo(mapInstance);

    return {
        group: fiberGroup,
        baseLine: baseLine,
        mainLine: mainLine,
        setLatLngs: function(coords) {
            baseLine.setLatLngs(coords);
            mainLine.setLatLngs(coords);
        },
        getLatLngs: function() {
            return mainLine.getLatLngs();
        }
    };
}

document.addEventListener('DOMContentLoaded', function() {
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function saveNewCoordinates(type, id, lat, lng) {
        if (!isAdmin) return;

        const formData = new FormData();
        formData.append('type', type);
        formData.append('id', id);
        formData.append('latitude', lat);
        formData.append('longitude', lng);

        fetch('modules/update_koordinat.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true,
                        background: '#0c101a',
                        color: '#f1f5f9'
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Posisi ' + type.toUpperCase() + ' berhasil di-update!'
                    });
                }
            } else {
                alert('Gagal update posisi: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
        });
    }

    // 1. Layer Peta
    const darkLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CARTO',
        maxZoom: 19
    });

    const satLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri',
        maxZoom: 22,
        maxNativeZoom: 18
    });

    const googleLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
        maxZoom: 22,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
        attribution: '&copy; Google Maps'
    });

    // 2. Inisialisasi Peta (Menggunakan variabel map global)
    map = L.map('map', {
        center: [-7.5500, 110.8200],
        zoom: 14,
        layers: [darkLayer]
    });

    if (isAdmin) {
        map.pm.addControls({
            position: 'topleft',
            drawMarker: false,
            drawCircleMarker: false,
            drawPolyline: false,
            drawRectangle: false,
            drawPolygon: false,
            drawCircle: false,
            editMode: true,
            dragMode: false,
            cutPolygon: false,
            removalMode: true
        });
    }

    const baseMaps = {
        "Peta Gelap": darkLayer,
        "Peta Satelit": satLayer,
        "Peta Google Hybrid": googleLayer
    };

    L.control.layers(baseMaps).addTo(map);

    const bounds = [];

    // Render Data ODP
    globalOdpData = <?= json_encode($odp_list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];

    globalOdpData.forEach(odp => {
        const lat = parseFloat(odp.latitude);
        const lng = parseFloat(odp.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
            bounds.push([lat, lng]);

            const odpIcon = L.divIcon({
                className: 'custom-odp-marker',
                html: `<div style="background-color: #f59e0b; width:22px; height:22px; border-radius:6px; border:2px solid #ffffff; display:flex; align-items:center; justify-content:center; color:white; font-size:10px; cursor:${isAdmin ? 'pointer' : 'pointer'}; box-shadow:0 0 10px #f59e0b;"><i class="fa-solid fa-box"></i></div>`,
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            });

            const parentInfo = odp.kode_parent ? `<small>Uplink: <b style="color:#f59e0b;">${escapeHtml(odp.kode_parent)}</b></small><br>` : '';

            const editBtnHtml = isAdmin ? `
                <div style="margin-top:8px; text-align:right;">
                    <button onclick="openModalEditOdp('${odp.id}')" style="background:#d97706; color:#fff; border:none; padding:4px 8px; border-radius:6px; font-size:10px; cursor:pointer; font-weight:bold;">
                        <i class="fa-solid fa-pen-to-square"></i> Edit ODP
                    </button>
                </div>
            ` : '';

            const popupText = `
                <div style="color: #0f172a; font-family: sans-serif; min-width:160px;">
                    <b style="font-size:13px; color:#d97706;">ODP: ${escapeHtml(odp.kode_odp)}</b><br>
                    <small>${escapeHtml(odp.nama_odp || odp.kode_odp)}</small><hr style="margin:5px 0;">
                    ${parentInfo}
                    <small>Dusun: <b>${escapeHtml(odp.dusun)}</b></small><br>
                    <small>Kapasitas: <b>${escapeHtml(odp.terpakai)} / ${escapeHtml(odp.kapasitas_port)} Port</b></small>
                    ${editBtnHtml}
                </div>
            `;

            const m = L.marker([lat, lng], {
                icon: odpIcon,
                draggable: false
            }).bindPopup(popupText).addTo(map);

            m.bindTooltip(escapeHtml(odp.kode_odp), {
                permanent: true,
                direction: 'top',
                className: 'custom-leaflet-tooltip',
                offset: [0, -10]
            });

            odpMarkers[odp.id] = m;

            if (isAdmin) {
                m.on('dragend', function (e) {
                    const newPos = e.target.getLatLng();
                    const newLat = newPos.lat.toFixed(6);
                    const newLng = newPos.lng.toFixed(6);

                    odp.latitude = newLat;
                    odp.longitude = newLng;

                    globalPelangganData.forEach(pel => {
                        const pelId = pel.id || pel.id_pelanggan;
                        if (pel.id_odp == odp.id && cablePolylines[pelId] && pelMarkers[pelId]) {
                            let curCoords = cablePolylines[pelId].getLatLngs();
                            if (Array.isArray(curCoords) && curCoords.length >= 2) {
                                curCoords[0] = [newLat, newLng];
                                cablePolylines[pelId].setLatLngs(curCoords);
                            }
                        }
                    });

                    globalOdpData.forEach(childOdp => {
                        if (childOdp.parent_id == odp.id && backbonePolylines[childOdp.id] && odpMarkers[childOdp.id]) {
                            let curCoords = backbonePolylines[childOdp.id].getLatLngs();
                            if (Array.isArray(curCoords) && curCoords.length >= 2) {
                                curCoords[0] = [newLat, newLng];
                                backbonePolylines[childOdp.id].setLatLngs(curCoords);
                            }
                        }
                    });

                    if (odp.parent_id && odpMarkers[odp.parent_id] && backbonePolylines[odp.id]) {
                        let curCoords = backbonePolylines[odp.id].getLatLngs();
                        if (Array.isArray(curCoords) && curCoords.length >= 2) {
                            curCoords[curCoords.length - 1] = [newLat, newLng];
                            backbonePolylines[odp.id].setLatLngs(curCoords);
                        }
                    }

                    saveNewCoordinates('odp', odp.id, newLat, newLng);
                });
            }
        }
    });

    // Render Backbone Kabel ODP - Parent ODP
    globalOdpData.forEach(odp => {
        if (odp.parent_id && odpMarkers[odp.parent_id] && odpMarkers[odp.id]) {
            const parentLat = odpMarkers[odp.parent_id].getLatLng().lat;
            const parentLng = odpMarkers[odp.parent_id].getLatLng().lng;
            const childLat  = odpMarkers[odp.id].getLatLng().lat;
            const childLng  = odpMarkers[odp.id].getLatLng().lng;

            let pathCoords = [[parentLat, parentLng], [childLat, childLng]];
            if (odp.path_kabel) {
                try {
                    const parsed = JSON.parse(odp.path_kabel);
                    if (Array.isArray(parsed) && parsed.length >= 2) {
                        pathCoords = parsed;
                    }
                } catch (err) {}
            }

            const bbFiber = drawFiberLine(map, pathCoords, 'backbone', 'odp', odp.id);
            backbonePolylines[odp.id] = bbFiber;
        }
    });

    // Render Data Pelanggan
    globalPelangganData = <?= json_encode($pelanggan_list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];

    globalPelangganData.forEach(pel => {
        const pelId = pel.id || pel.id_pelanggan;
        pel.id = pelId;

        const lat = parseFloat(pel.latitude);
        const lng = parseFloat(pel.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
            bounds.push([lat, lng]);

            let color = '#10b981';
            if (pel.status === 'Isolir') color = '#f59e0b';
            if (pel.status === 'Nonaktif') color = '#f43f5e';

            const userIcon = L.divIcon({
                className: 'custom-user-marker',
                html: `<div style="background-color: ${color}; width:14px; height:14px; border-radius:50%; border:2px solid #ffffff; cursor:${isAdmin ? 'pointer' : 'pointer'}; box-shadow:0 0 8px ${color};"></div>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7]
            });

            const namaUser = pel.nama || pel.nama_pelanggan || 'Pelanggan';

            const editBtnPelHtml = isAdmin ? `
                <div style="margin-top:8px; text-align:right;">
                    <button onclick="openModalEditPelanggan('${pelId}')" style="background:#059669; color:#fff; border:none; padding:4px 8px; border-radius:6px; font-size:10px; cursor:pointer; font-weight:bold;">
                        <i class="fa-solid fa-user-pen"></i> Edit Pelanggan
                    </button>
                </div>
            ` : '';

            const popupUser = `
                <div style="color: #0f172a; font-family: sans-serif; min-width:150px;">
                    <b style="font-size:12px;">${escapeHtml(namaUser)}</b><br>
                    <small>Status: <b>${escapeHtml(pel.status)}</b></small><br>
                    <small>ODP: <b>${escapeHtml(pel.kode_odp || '-')}</b></small>
                    ${editBtnPelHtml}
                </div>
            `;

            const m = L.marker([lat, lng], {
                icon: userIcon,
                draggable: false
            }).bindPopup(popupUser).addTo(map);

            m.bindTooltip(escapeHtml(namaUser), {
                permanent: true,
                direction: 'top',
                className: 'custom-leaflet-tooltip',
                offset: [0, -6]
            });

            pelMarkers[pelId] = m;

            if (pel.id_odp && odpMarkers[pel.id_odp]) {
                const odpLat = odpMarkers[pel.id_odp].getLatLng().lat;
                const odpLng = odpMarkers[pel.id_odp].getLatLng().lng;

                let pathCoords = [[odpLat, odpLng], [lat, lng]];
                if (pel.path_kabel) {
                    try {
                        const parsed = JSON.parse(pel.path_kabel);
                        if (Array.isArray(parsed) && parsed.length >= 2) {
                            pathCoords = parsed;
                        }
                    } catch (err) {}
                }

                const fiberObject = drawFiberLine(map, pathCoords, pel.status, 'pelanggan', pelId);
                cablePolylines[pelId] = fiberObject;
            }

            if (isAdmin) {
                m.on('dragend', function (e) {
                    const newPos = e.target.getLatLng();
                    const newLat = newPos.lat.toFixed(6);
                    const newLng = newPos.lng.toFixed(6);

                    pel.latitude = newLat;
                    pel.longitude = newLng;

                    if (pel.id_odp && odpMarkers[pel.id_odp] && cablePolylines[pelId]) {
                        let curCoords = cablePolylines[pelId].getLatLngs();
                        if (Array.isArray(curCoords) && curCoords.length >= 2) {
                            curCoords[curCoords.length - 1] = [newLat, newLng];
                            cablePolylines[pelId].setLatLngs(curCoords);
                        }
                    }

                    saveNewCoordinates('pelanggan', pelId, newLat, newLng);
                });
            }
        }
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [30, 30] });
    }

    const ctxMenu = document.getElementById('map-context-menu');
    const ctxCoords = document.getElementById('ctx-coords');

    map.on('contextmenu', function (e) {
        if (!isAdmin) return;

        clickedLat = e.latlng.lat.toFixed(6);
        clickedLng = e.latlng.lng.toFixed(6);

        if (ctxCoords) ctxCoords.innerText = `${clickedLat}, ${clickedLng}`;

        if (ctxMenu) {
            ctxMenu.style.left = e.containerPoint.x + 'px';
            ctxMenu.style.top  = e.containerPoint.y + 'px';
            ctxMenu.classList.remove('hidden');
        }
    });

    map.on('click dragstart zoomstart', function () {
        if (ctxMenu) ctxMenu.classList.add('hidden');
    });

    document.addEventListener('click', function (e) {
        if (ctxMenu && !ctxMenu.contains(e.target)) {
            ctxMenu.classList.add('hidden');
        }
    });
});

function toggleModal(id) {
    if (!isAdmin) return;
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.toggle('hidden');
    }
}

function openModalOdpFromMap() {
    if (!isAdmin) return;
    const ctxMenu = document.getElementById('map-context-menu');
    if (ctxMenu) ctxMenu.classList.add('hidden');
    document.getElementById('odp-lat').value = clickedLat;
    document.getElementById('odp-lng').value = clickedLng;
    toggleModal('modal-tambah-odp');
}

function openModalPelangganFromMap() {
    if (!isAdmin) return;
    const ctxMenu = document.getElementById('map-context-menu');
    if (ctxMenu) ctxMenu.classList.add('hidden');
    document.getElementById('pelanggan-lat').value = clickedLat;
    document.getElementById('pelanggan-lng').value = clickedLng;
    toggleModal('modal-tambah-pelanggan');
}

function openModalEditOdp(id) {
    if (!isAdmin) return;
    const odp = globalOdpData.find(item => String(item.id) === String(id));
    if (!odp) return;

    document.getElementById('edit-odp-id').value = odp.id;
    document.getElementById('edit-odp-kode').value = odp.kode_odp || '';
    document.getElementById('edit-odp-nama').value = odp.nama_odp || '';
    document.getElementById('edit-odp-jenis').value = odp.jenis || 'ODP';
    document.getElementById('edit-odp-kapasitas').value = odp.kapasitas_port || 8;
    document.getElementById('edit-odp-parent').value = odp.parent_id || '';
    
    const selectDusun = document.getElementById('edit-odp-dusun');
    if (selectDusun) selectDusun.value = odp.dusun || 'Kemitir';

    document.getElementById('edit-odp-lat').value = odp.latitude || '';
    document.getElementById('edit-odp-lng').value = odp.longitude || '';
    document.getElementById('edit-odp-keterangan').value = odp.keterangan || '';

    toggleModal('modal-edit-odp');
}

function openModalEditPelanggan(id) {
    if (!isAdmin) return;
    const pel = globalPelangganData.find(item => String(item.id) === String(id) || String(item.id_pelanggan) === String(id));
    if (!pel) return;

    const realId = pel.id || pel.id_pelanggan;

    document.getElementById('edit-pelanggan-id').value = realId;
    document.getElementById('edit-pelanggan-nama').value = pel.nama || pel.nama_pelanggan || '';
    document.getElementById('edit-pelanggan-wa').value = pel.no_wa || '';
    
    const selectDusun = document.getElementById('edit-pelanggan-dusun');
    if (selectDusun) selectDusun.value = pel.dusun || 'Kemitir';

    document.getElementById('edit-pelanggan-odp').value = pel.id_odp || '';
    document.getElementById('edit-pelanggan-port').value = pel.port_odp || '';
    document.getElementById('edit-pelanggan-lat').value = pel.latitude || '';
    document.getElementById('edit-pelanggan-lng').value = pel.longitude || '';
    document.getElementById('edit-pelanggan-status').value = pel.status || 'Aktif';

    toggleModal('modal-edit-pelanggan');
}
</script>