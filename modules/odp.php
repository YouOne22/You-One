<?php
// modules/odp.php - Manajemen Data ODP/ODC (Theme Matched: Dark & Orange Accent)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$role_user  = strtoupper($_SESSION['role'] ?? '');
$dusun_user = $_SESSION['dusun_pengelola'] ?? '';
$is_admin   = in_array($role_user, ['ADMIN', 'ADMINISTRATOR', 'SUPERADMIN'], true);

// --- BATASI HANYA UNTUK ADMIN ---
if (!$is_admin) {
    echo '
    <div class="glass-card p-8 rounded-2xl border border-slate-800/80 bg-slate-900/60 shadow-xl text-center max-w-lg mx-auto mt-10 backdrop-blur-md">
        <div class="w-16 h-16 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl shadow-inner">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-100 mb-2">Akses Ditolak</h3>
        <p class="text-xs text-slate-400 mb-6 leading-relaxed">Halaman Manajemen Data ODP / ODC hanya dapat diakses oleh Administrator.</p>
        <a href="index.php?page=dashboard" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold transition border border-slate-700/50 shadow-md">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>';
    return;
}

// Daftar Opsi Dusun
$daftar_dusun = ['Kemitir', 'Ngoho', 'Mbalong'];

// --- PROSES TAMBAH ODP ---
if (isset($_POST['tambah_odp'])) {
    $kode_odp   = trim($_POST['kode_odp'] ?? '');
    $nama_odp   = trim($_POST['nama_odp'] ?? '') ?: $kode_odp;
    $jenis      = trim($_POST['jenis'] ?? 'ODP');
    $parent_id  = !empty($_POST['id_parent']) ? intval($_POST['id_parent']) : 0;
    $dusun      = trim($_POST['dusun'] ?? '');
    $kapasitas  = intval($_POST['kapasitas_port'] ?? 8);
    $latitude   = trim($_POST['latitude'] ?? '');
    $longitude  = trim($_POST['longitude'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Disimpan ke kolom parent_id dan id_parent sekaligus agar sinkron
    $stmt = mysqli_prepare($koneksi, "INSERT INTO odp (kode_odp, nama_odp, jenis, parent_id, id_parent, dusun, kapasitas_port, latitude, longitude, keterangan) VALUES (?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssississs", $kode_odp, $nama_odp, $jenis, $parent_id, $parent_id, $dusun, $kapasitas, $latitude, $longitude, $keterangan);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => "Data ODP berhasil ditambahkan."];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => "Gagal menambah ODP: " . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php?page=odp");
    exit();
}

// --- PROSES EDIT ODP ---
if (isset($_POST['edit_odp'])) {
    $id         = intval($_POST['id_odp'] ?? 0);
    $kode_odp   = trim($_POST['kode_odp'] ?? '');
    $nama_odp   = trim($_POST['nama_odp'] ?? '') ?: $kode_odp;
    $jenis      = trim($_POST['jenis'] ?? 'ODP');
    $parent_id  = !empty($_POST['id_parent']) ? intval($_POST['id_parent']) : 0;
    $dusun      = trim($_POST['dusun'] ?? '');
    $kapasitas  = intval($_POST['kapasitas_port'] ?? 8);
    $latitude   = trim($_POST['latitude'] ?? '');
    $longitude  = trim($_POST['longitude'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($parent_id === $id) { $parent_id = 0; }

    // Dibarui pada kolom parent_id dan id_parent sekaligus
    $stmt = mysqli_prepare($koneksi, "UPDATE odp SET kode_odp = ?, nama_odp = ?, jenis = ?, parent_id = NULLIF(?, 0), id_parent = NULLIF(?, 0), dusun = ?, kapasitas_port = ?, latitude = ?, longitude = ?, keterangan = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "sssississsi", $kode_odp, $nama_odp, $jenis, $parent_id, $parent_id, $dusun, $kapasitas, $latitude, $longitude, $keterangan, $id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => "Data ODP berhasil diperbarui."];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => "Gagal memperbarui ODP: " . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php?page=odp");
    exit();
}

// --- PROSES HAPUS ODP ---
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);

    $stmt = mysqli_prepare($koneksi, "DELETE FROM odp WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Dihapus!', 'message' => "Data ODP berhasil dihapus."];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => "Gagal menghapus ODP."];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php?page=odp");
    exit();
}

// --- PROSES IMPORT CSV MAP MARKER ---
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        $handle = fopen($file_tmp, "r");

        if ($handle !== FALSE) {
            $header = fgetcsv($handle, 2000, ",");

            $idx_lat   = 2;
            $idx_lng   = 3;
            $idx_title = 4;
            $idx_desc  = 5;

            if ($header !== FALSE) {
                $header_lower = array_map(function($h) {
                    return strtolower(trim($h));
                }, $header);

                $f_lat   = array_search('latitude', $header_lower);
                $f_lng   = array_search('longitude', $header_lower);
                $f_title = array_search('title', $header_lower);
                $f_desc  = array_search('description', $header_lower);

                if ($f_lat !== false)   $idx_lat = $f_lat;
                if ($f_lng !== false)   $idx_lng = $f_lng;
                if ($f_title !== false) $idx_title = $f_title;
                if ($f_desc !== false)  $idx_desc = $f_desc;
            }

            $dusun_default = $daftar_dusun[0] ?? 'Kemitir';
            $inserted_count = 0;
            $updated_count = 0;

            $stmt_check  = mysqli_prepare($koneksi, "SELECT id FROM odp WHERE kode_odp = ? LIMIT 1");
            $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO odp (kode_odp, nama_odp, jenis, parent_id, id_parent, dusun, kapasitas_port, latitude, longitude, keterangan) VALUES (?, ?, 'ODP', NULL, NULL, ?, 8, ?, ?, ?)");
            $stmt_update = mysqli_prepare($koneksi, "UPDATE odp SET latitude = ?, longitude = ?, keterangan = ? WHERE id = ?");

            while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                $lat   = trim($data[$idx_lat] ?? '');
                $lng   = trim($data[$idx_lng] ?? '');
                $title = trim($data[$idx_title] ?? '');
                $desc  = trim($data[$idx_desc] ?? '');

                if (!empty($lat) && !empty($lng) && !empty($title)) {
                    mysqli_stmt_bind_param($stmt_check, "s", $title);
                    mysqli_stmt_execute($stmt_check);
                    $res_check = mysqli_stmt_get_result($stmt_check);

                    if ($row_exist = mysqli_fetch_assoc($res_check)) {
                        $existing_id = $row_exist['id'];
                        mysqli_stmt_bind_param($stmt_update, "sssi", $lat, $lng, $desc, $existing_id);
                        mysqli_stmt_execute($stmt_update);
                        $updated_count++;
                    } else {
                        $nama = $title;
                        mysqli_stmt_bind_param($stmt_insert, "ssssss", $title, $nama, $dusun_default, $lat, $lng, $desc);
                        mysqli_stmt_execute($stmt_insert);
                        $inserted_count++;
                    }
                    if ($res_check) { mysqli_free_result($res_check); }
                }
            }

            fclose($handle);
            mysqli_stmt_close($stmt_check);
            mysqli_stmt_close($stmt_insert);
            mysqli_stmt_close($stmt_update);

            $_SESSION['toast'] = [
                'type' => 'success',
                'title' => 'Import Berhasil!',
                'message' => "Proses CSV selesai. Baru: {$inserted_count} titik, Diperbarui: {$updated_count} titik."
            ];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => "Tidak dapat membaca file CSV."];
        }
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal!', 'message' => "File CSV tidak valid atau gagal diunggah."];
    }
    header("Location: index.php?page=odp");
    exit();
}

// Filter dan Search
$search_keyword = trim($_GET['search'] ?? '');
$filter_dusun   = trim($_GET['dusun'] ?? '');

// Fetch ODP / ODC untuk opsi dropdown Induk
$parent_list = [];
$res_parent = mysqli_query($koneksi, "SELECT id, kode_odp, nama_odp, jenis FROM odp ORDER BY kode_odp ASC");
if ($res_parent) {
    while ($p_row = mysqli_fetch_assoc($res_parent)) {
        $parent_list[] = $p_row;
    }
}

// Query Pencarian Dinamis
$where_clauses = [];
$params        = [];
$types         = "";

if (!empty($search_keyword)) {
    $where_clauses[] = "(odp.kode_odp LIKE ? OR odp.nama_odp LIKE ? OR odp.keterangan LIKE ?)";
    $param_s = "%{$search_keyword}%";
    $params[] = $param_s; $params[] = $param_s; $params[] = $param_s;
    $types .= "sss";
}

if (!empty($filter_dusun)) {
    $where_clauses[] = "odp.dusun = ?";
    $params[] = $filter_dusun;
    $types .= "s";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// JOIN menggunakan COALESCE(odp.parent_id, odp.id_parent) agar membaca dari kedua kolom ganda tersebut
$query_list = "SELECT odp.*, COALESCE(p.total, 0) AS terpakai, parent.kode_odp AS parent_kode
               FROM odp
               LEFT JOIN odp parent ON COALESCE(odp.parent_id, odp.id_parent) = parent.id
               LEFT JOIN (
                   SELECT id_odp, COUNT(*) AS total 
                   FROM pelanggan 
                   WHERE id_odp IS NOT NULL 
                   GROUP BY id_odp
               ) p ON odp.id = p.id_odp
               {$where_sql}
               ORDER BY odp.id DESC";

if (!empty($params)) {
    $stmt_list = mysqli_prepare($koneksi, $query_list);
    mysqli_stmt_bind_param($stmt_list, $types, ...$params);
    mysqli_stmt_execute($stmt_list);
    $result = mysqli_stmt_get_result($stmt_list);
} else {
    $result = mysqli_query($koneksi, $query_list);
}

$odps = [];
$total_kapasitas_all = 0;
$total_terpakai_all  = 0;
$count_odc = 0;
$count_odp = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $odps[] = $row;
        $total_kapasitas_all += (int)($row['kapasitas_port'] ?? 0);
        $total_terpakai_all  += (int)($row['terpakai'] ?? 0);
        if (($row['jenis'] ?? 'ODP') === 'ODC') {
            $count_odc++;
        } else {
            $count_odp++;
        }
    }
}
if (isset($stmt_list) && $stmt_list) { mysqli_stmt_close($stmt_list); }

$total_unit = count($odps);
$persen_okupansi = ($total_kapasitas_all > 0) ? round(($total_terpakai_all / $total_kapasitas_all) * 100) : 0;
?>

<!-- HEADER HALAMAN -->
<div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2">
            <div class="w-2.5 h-7 bg-gradient-to-b from-orange-500 to-amber-500 rounded-full"></div>
            <h3 class="text-2xl font-bold text-slate-100 tracking-wide">Manajemen ODP & ODC</h3>
        </div>
        <p class="text-xs text-slate-400 mt-1 pl-4">Pemetaan, kapasitas port, dan hirarki distribusi jaringan fiber optik.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2.5">
        <button type="button" onclick="toggleModal('modal-import-csv')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700/60 text-slate-200 rounded-xl text-xs font-semibold transition flex items-center gap-2 shadow-md transform hover:-translate-y-0.5 active:translate-y-0">
            <i class="fa-solid fa-file-csv text-emerald-400 text-sm"></i> Import CSV
        </button>
        <button type="button" onclick="toggleModal('modal-tambah-odp')" class="px-4 py-2.5 bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white rounded-xl text-xs font-semibold transition shadow-lg shadow-orange-500/20 flex items-center gap-2 transform hover:-translate-y-0.5 active:translate-y-0">
            <i class="fa-solid fa-plus text-sm"></i> Tambah ODP Baru
        </button>
    </div>
</div>

<!-- CARDS STATISTIK KILAT -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="glass-card p-4 rounded-2xl border border-slate-800/80 bg-slate-900/60 shadow-lg relative overflow-hidden group">
        <div class="absolute -right-3 -bottom-3 text-slate-800/40 text-6xl group-hover:text-orange-500/10 transition-colors duration-300">
            <i class="fa-solid fa-box font-black"></i>
        </div>
        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Total Unit Perangkat</div>
        <div class="text-2xl font-black text-slate-100 font-mono"><?= number_format($total_unit); ?> <span class="text-xs font-normal text-slate-400">Unit</span></div>
        <div class="mt-2 text-[10px] text-slate-400 flex items-center gap-2">
            <span class="text-amber-400 font-semibold"><?= $count_odc; ?> ODC</span> • 
            <span class="text-indigo-400 font-semibold"><?= $count_odp; ?> ODP</span>
        </div>
    </div>

    <div class="glass-card p-4 rounded-2xl border border-slate-800/80 bg-slate-900/60 shadow-lg relative overflow-hidden group">
        <div class="absolute -right-3 -bottom-3 text-slate-800/40 text-6xl group-hover:text-orange-500/10 transition-colors duration-300">
            <i class="fa-solid fa-network-wired"></i>
        </div>
        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Total Kapasitas</div>
        <div class="text-2xl font-black text-slate-100 font-mono"><?= number_format($total_kapasitas_all); ?> <span class="text-xs font-normal text-slate-400">Port</span></div>
        <div class="mt-2 text-[10px] text-slate-400">Total ketersediaan splitter</div>
    </div>

    <div class="glass-card p-4 rounded-2xl border border-slate-800/80 bg-slate-900/60 shadow-lg relative overflow-hidden group">
        <div class="absolute -right-3 -bottom-3 text-slate-800/40 text-6xl group-hover:text-emerald-500/10 transition-colors duration-300">
            <i class="fa-solid fa-plug"></i>
        </div>
        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Port Terpakai</div>
        <div class="text-2xl font-black text-emerald-400 font-mono"><?= number_format($total_terpakai_all); ?> <span class="text-xs font-normal text-slate-400">Port</span></div>
        <div class="mt-2 text-[10px] text-slate-400">Pelanggan aktif terhubung</div>
    </div>

    <div class="glass-card p-4 rounded-2xl border border-slate-800/80 bg-slate-900/60 shadow-lg relative overflow-hidden group">
        <div class="absolute -right-3 -bottom-3 text-slate-800/40 text-6xl group-hover:text-orange-500/10 transition-colors duration-300">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Rasio Okupansi</div>
        <div class="text-2xl font-black text-orange-400 font-mono"><?= $persen_okupansi; ?>%</div>
        <div class="w-full bg-slate-950 h-1.5 rounded-full overflow-hidden border border-slate-800 mt-2">
            <div class="bg-gradient-to-r from-orange-500 to-amber-400 h-full transition-all duration-500" style="width: <?= min($persen_okupansi, 100); ?>%"></div>
        </div>
    </div>
</div>

<!-- BARIS PENCARIAN & FILTER -->
<div class="glass-card p-4 rounded-2xl border border-slate-800/80 bg-slate-900/60 shadow-lg mb-6 backdrop-blur-md">
    <form method="GET" action="index.php" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="page" value="odp">
        
        <div class="flex-1 min-w-[240px]">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Cari Kode, Nama ODP, atau Keterangan..."
                    class="w-full bg-slate-950/70 border border-slate-800/80 rounded-xl pl-9 pr-4 py-2.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
            </div>
        </div>

        <div class="w-full sm:w-52">
            <select name="dusun" onchange="this.form.submit()" class="w-full bg-slate-950/70 border border-slate-800/80 rounded-xl px-3 py-2.5 text-xs text-slate-300 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                <option value="">-- Semua Dusun --</option>
                <?php foreach ($daftar_dusun as $d): ?>
                    <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?>" <?= $filter_dusun === $d ? 'selected' : ''; ?>><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700/60 text-slate-200 rounded-xl text-xs font-medium transition flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-filter text-orange-400"></i> Filter
        </button>

        <?php if (!empty($search_keyword) || !empty($filter_dusun)): ?>
            <a href="index.php?page=odp" class="px-3.5 py-2.5 bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500/20 rounded-xl text-xs font-medium transition flex items-center gap-1.5" title="Reset Filter">
                <i class="fa-solid fa-xmark"></i> Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- TABEL DATA ODP -->
<div class="glass-card rounded-2xl border border-slate-800/80 bg-slate-900/60 shadow-xl overflow-hidden backdrop-blur-md">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950/80 uppercase tracking-wider text-slate-400 border-b border-slate-800/80 text-[11px]">
                <tr>
                    <th class="px-5 py-4 font-semibold">Kode / Nama Perangkat</th>
                    <th class="px-5 py-4 font-semibold">Jenis</th>
                    <th class="px-5 py-4 font-semibold">Uplink / Induk</th>
                    <th class="px-5 py-4 font-semibold">Dusun</th>
                    <th class="px-5 py-4 font-semibold">Port Terpakai / Kapasitas</th>
                    <th class="px-5 py-4 font-semibold">Koordinat GPS</th>
                    <th class="px-5 py-4 font-semibold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if (empty($odps)): ?>
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                        <div class="w-12 h-12 rounded-full bg-slate-800/50 flex items-center justify-center mx-auto mb-3 text-slate-600 text-lg">
                            <i class="fa-solid fa-inbox"></i>
                        </div>
                        Belum ada data ODP / ODC yang ditemukan.
                    </td>
                </tr>
                <?php else: foreach ($odps as $o): 
                    $terpakai  = (int)($o['terpakai'] ?? 0);
                    $kapasitas = (int)($o['kapasitas_port'] ?? 8);
                    $persen    = ($kapasitas > 0) ? round(($terpakai / $kapasitas) * 100) : 0;
                    
                    if ($persen >= 100) {
                        $color = 'bg-rose-500';
                        $badge_status = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                    } elseif ($persen >= 75) {
                        $color = 'bg-amber-500';
                        $badge_status = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                    } else {
                        $color = 'bg-gradient-to-r from-orange-500 to-emerald-400';
                        $badge_status = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                    }
                    
                    $has_coords = !empty($o['latitude']) && !empty($o['longitude']);
                    $map_link   = $has_coords ? "https://www.google.com/maps?q=" . urlencode($o['latitude'] . ',' . $o['longitude']) : '#';
                    $parent_val = !empty($o['parent_id']) ? $o['parent_id'] : ($o['id_parent'] ?? '');
                ?>
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-4">
                        <div class="font-bold text-slate-100 text-sm tracking-wide font-mono flex items-center gap-2">
                            <span><?= htmlspecialchars($o['kode_odp'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5"><?= htmlspecialchars($o['nama_odp'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($o['keterangan'])): ?>
                            <div class="text-[10px] text-slate-500 mt-1 truncate max-w-[200px]" title="<?= htmlspecialchars($o['keterangan'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa-regular fa-comment-dots mr-1 text-slate-600"></i><?= htmlspecialchars($o['keterangan'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 whitespace-nowrap">
                        <?php if (($o['jenis'] ?? 'ODP') === 'ODC'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span> ODC
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span> ODP
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 font-mono text-slate-300">
                        <?php if (!empty($o['parent_kode'])): ?>
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-orange-300 bg-orange-500/10 px-2.5 py-1 rounded-lg border border-orange-500/20 shadow-sm">
                                <i class="fa-solid fa-sitemap text-[10px] text-orange-400"></i> <?= htmlspecialchars($o['parent_kode'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-500 text-[11px] italic">Pusat / Standalone</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 font-medium text-slate-300">
                        <span class="inline-flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-[10px] text-slate-500"></i>
                            <?= htmlspecialchars($o['dusun'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex justify-between items-center text-[11px] mb-1.5 font-mono">
                            <span class="text-slate-200 font-semibold"><?= $terpakai; ?> / <?= $kapasitas; ?> <span class="text-[10px] font-normal text-slate-400">Port</span></span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] border <?= $badge_status; ?> font-bold"><?= $persen; ?>%</span>
                        </div>
                        <div class="w-36 bg-slate-950 h-2 rounded-full overflow-hidden border border-slate-800/80 p-0.5">
                            <div class="<?= $color; ?> h-full rounded-full transition-all duration-500" style="width: <?= min($persen, 100); ?>%"></div>
                        </div>
                    </td>
                    <td class="px-5 py-4 font-mono text-[11px] whitespace-nowrap">
                        <?php if ($has_coords): ?>
                            <a href="<?= $map_link; ?>" target="_blank" class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-slate-950/60 border border-slate-800 text-amber-400 hover:text-orange-300 hover:border-orange-500/40 transition group" title="Buka di Google Maps">
                                <i class="fa-solid fa-map-location-dot text-orange-400 group-hover:scale-110 transition-transform"></i>
                                <span><?= htmlspecialchars($o['latitude'], ENT_QUOTES, 'UTF-8'); ?>, <?= htmlspecialchars($o['longitude'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        <?php else: ?>
                            <span class="text-slate-500 italic text-[11px]">Belum diset</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-center whitespace-nowrap">
                        <div class="flex items-center justify-center gap-2">
                            <button type="button" 
                                class="btn-edit-odp w-8 h-8 rounded-xl bg-orange-500/10 border border-orange-500/20 hover:bg-orange-500/20 text-orange-400 flex items-center justify-center transition shadow-sm" 
                                title="Edit"
                                data-id="<?= $o['id']; ?>"
                                data-kode="<?= htmlspecialchars($o['kode_odp'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-nama="<?= htmlspecialchars($o['nama_odp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-jenis="<?= htmlspecialchars($o['jenis'] ?? 'ODP', ENT_QUOTES, 'UTF-8'); ?>"
                                data-kapasitas="<?= $o['kapasitas_port'] ?? 8; ?>"
                                data-parent="<?= $parent_val; ?>"
                                data-dusun="<?= htmlspecialchars($o['dusun'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-lat="<?= htmlspecialchars($o['latitude'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-lng="<?= htmlspecialchars($o['longitude'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-ket="<?= htmlspecialchars($o['keterangan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>
                            <button type="button" 
                                onclick="konfirmasiHapus(<?= $o['id']; ?>, '<?= htmlspecialchars($o['kode_odp'], ENT_QUOTES, 'UTF-8'); ?>')" 
                                class="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 text-rose-400 flex items-center justify-center transition shadow-sm" 
                                title="Hapus">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH ODP -->
<div id="modal-tambah-odp" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800/80 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden my-8 transform transition-all">
        <div class="p-5 border-b border-slate-800 bg-slate-950/50 flex justify-between items-center">
            <h4 class="font-bold text-slate-100 text-base flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center text-orange-400">
                    <i class="fa-solid fa-box text-sm"></i>
                </div>
                <span>Tambah ODP / ODC Baru</span>
            </h4>
            <button type="button" onclick="toggleModal('modal-tambah-odp')" class="w-8 h-8 rounded-xl bg-slate-800/60 hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Kode ODP / ODC <span class="text-rose-400">*</span></label>
                    <input type="text" name="kode_odp" required placeholder="ODP-KMT-01"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-orange-400 font-bold font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nama Perangkat</label>
                    <input type="text" name="nama_odp" placeholder="ODC Pertigaan Utm"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Jenis Perangkat <span class="text-rose-400">*</span></label>
                    <select name="jenis" required class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-300 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                        <option value="ODP">ODP (Splitter / Pole)</option>
                        <option value="ODC">ODC (Cabinet / Utama)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Kapasitas Port <span class="text-rose-400">*</span></label>
                    <input type="number" name="kapasitas_port" value="8" required min="1"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-200 font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Uplink / Induk (ODC / ODP Utama)</label>
                <select name="id_parent" class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-300 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                    <option value="">-- Tidak Ada (Pusat / Standalone) --</option>
                    <?php foreach ($parent_list as $p): ?>
                        <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['kode_odp'], ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars($p['nama_odp'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Dusun <span class="text-rose-400">*</span></label>
                <select name="dusun" required class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-300 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                    <?php foreach ($daftar_dusun as $d): ?>
                        <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Latitude</label>
                    <input type="text" name="latitude" placeholder="-7.550000"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Longitude</label>
                    <input type="text" name="longitude" placeholder="110.820000"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Keterangan / Patokan</label>
                <textarea name="keterangan" rows="2" placeholder="Catatan posisi atau tiang tumpuan..."
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-800/80 flex justify-end gap-3">
                <button type="button" onclick="toggleModal('modal-tambah-odp')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">Batal</button>
                <button type="submit" name="tambah_odp" class="px-4 py-2.5 bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white rounded-xl text-xs font-semibold transition shadow-lg shadow-orange-500/20">Simpan ODP</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT ODP -->
<div id="modal-edit-odp" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800/80 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden my-8 transform transition-all">
        <div class="p-5 border-b border-slate-800 bg-slate-950/50 flex justify-between items-center">
            <h4 class="font-bold text-slate-100 text-base flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center text-orange-400">
                    <i class="fa-solid fa-pen-to-square text-sm"></i>
                </div>
                <span>Edit Data ODP / ODC</span>
            </h4>
            <button type="button" onclick="toggleModal('modal-edit-odp')" class="w-8 h-8 rounded-xl bg-slate-800/60 hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id_odp" id="edit-id">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Kode ODP / ODC <span class="text-rose-400">*</span></label>
                    <input type="text" name="kode_odp" id="edit-kode" required
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-orange-400 font-bold font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nama Perangkat</label>
                    <input type="text" name="nama_odp" id="edit-nama"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Jenis Perangkat <span class="text-rose-400">*</span></label>
                    <select name="jenis" id="edit-jenis" required class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-300 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                        <option value="ODP">ODP (Splitter / Pole)</option>
                        <option value="ODC">ODC (Cabinet / Utama)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Kapasitas Port <span class="text-rose-400">*</span></label>
                    <input type="number" name="kapasitas_port" id="edit-kapasitas" required min="1"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-200 font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Uplink / Induk (ODC / ODP Utama)</label>
                <select name="id_parent" id="edit-parent" class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-300 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                    <option value="">-- Tidak Ada (Pusat / Standalone) --</option>
                    <?php foreach ($parent_list as $p): ?>
                        <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['kode_odp'], ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars($p['nama_odp'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Dusun <span class="text-rose-400">*</span></label>
                <select name="dusun" id="edit-dusun" required class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-300 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                    <?php foreach ($daftar_dusun as $d): ?>
                        <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Latitude</label>
                    <input type="text" name="latitude" id="edit-lat"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Longitude</label>
                    <input type="text" name="longitude" id="edit-lng"
                        class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Keterangan / Patokan</label>
                <textarea name="keterangan" id="edit-keterangan" rows="2"
                    class="w-full bg-slate-950/70 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500/30 transition"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-800/80 flex justify-end gap-3">
                <button type="button" onclick="toggleModal('modal-edit-odp')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">Batal</button>
                <button type="submit" name="edit_odp" class="px-4 py-2.5 bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white rounded-xl text-xs font-semibold transition shadow-lg shadow-orange-500/20">Update ODP</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT CSV MAP MARKER -->
<div id="modal-import-csv" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800/80 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden my-8 transform transition-all">
        <div class="p-5 border-b border-slate-800 bg-slate-950/50 flex justify-between items-center">
            <h4 class="font-bold text-slate-100 text-base flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-file-csv text-sm"></i>
                </div>
                <span>Import Data Tikor dari CSV</span>
            </h4>
            <button type="button" onclick="toggleModal('modal-import-csv')" class="w-8 h-8 rounded-xl bg-slate-800/60 hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div class="p-3 bg-slate-950/50 border border-slate-800 rounded-xl text-xs text-slate-400 leading-relaxed">
                <p class="font-semibold text-slate-300 mb-1 flex items-center gap-1.5"><i class="fa-solid fa-circle-info text-amber-400"></i> Informasi Format File:</p>
                Unggah file <code class="text-orange-400 font-mono">.csv</code> ekspor dari aplikasi Map Marker Anda. Semua titik koordinat akan otomatis diimpor ke tabel <b>odp</b> dan dipetakan di layar peta.
            </div>

            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Pilih File CSV <span class="text-rose-400">*</span></label>
                <input type="file" name="file_csv" accept=".csv" required
                    class="w-full text-xs text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-orange-500/10 file:text-orange-400 file:border file:border-orange-500/20 hover:file:bg-orange-500/20 file:transition cursor-pointer bg-slate-950/70 border border-slate-800 rounded-xl p-1.5 focus:outline-none">
            </div>

            <div class="pt-3 border-t border-slate-800/80 flex justify-end gap-3">
                <button type="button" onclick="toggleModal('modal-import-csv')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">Batal</button>
                <button type="submit" name="import_csv" class="px-4 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white rounded-xl text-xs font-semibold transition shadow-lg shadow-emerald-500/20 flex items-center gap-1.5">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload & Import
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_SESSION['toast'])): 
    $t = $_SESSION['toast'];
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: '<?= $t['type']; ?>',
            title: '<?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'); ?>',
            text: '<?= htmlspecialchars($t['message'], ENT_QUOTES, 'UTF-8'); ?>',
            background: '#0b0f17',
            color: '#f1f5f9',
            confirmButtonColor: '#f97316'
        });
    }
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<script>
function toggleModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.toggle('hidden');
}

function konfirmasiHapus(id, kode) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus ODP / ODC?',
            html: `Apakah Anda yakin ingin menghapus perangkat <b class="text-orange-400 font-mono">${kode}</b>?<br><span class="text-xs text-slate-400">Tindakan ini tidak dapat dibatalkan.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            background: '#0b0f17',
            color: '#f1f5f9',
            customClass: {
                popup: 'border border-slate-800 rounded-2xl shadow-2xl backdrop-blur-md',
                title: 'text-slate-100 font-bold',
                htmlContainer: 'text-slate-300 text-sm'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `index.php?page=odp&hapus=${id}`;
            }
        });
    } else {
        if (confirm(`Yakin ingin menghapus ODP ${kode}?`)) {
            window.location.href = `index.php?page=odp&hapus=${id}`;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-odp').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit-id').value         = this.dataset.id || '';
            document.getElementById('edit-kode').value       = this.dataset.kode || '';
            document.getElementById('edit-nama').value       = this.dataset.nama || '';
            document.getElementById('edit-jenis').value      = this.dataset.jenis || 'ODP';
            document.getElementById('edit-kapasitas').value  = this.dataset.kapasitas || 8;
            document.getElementById('edit-parent').value     = this.dataset.parent || '';
            document.getElementById('edit-dusun').value      = this.dataset.dusun || 'Kemitir';
            document.getElementById('edit-lat').value        = this.dataset.lat || '';
            document.getElementById('edit-lng').value        = this.dataset.lng || '';
            document.getElementById('edit-keterangan').value = this.dataset.ket || '';
            
            toggleModal('modal-edit-odp');
        });
    });
});
</script>