<?php
// modules/pelanggan.php
require_once 'config/koneksi.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start(); 
}

$dusun_login = $_SESSION['dusun_pengelola'] ?? '';
$role_user = strtoupper($_SESSION['role'] ?? '');

// --- AMBIL DATA ODP UNTUK DROPDOWN FORM ---
$odp_list = [];
$query_odp_opt = mysqli_query($koneksi, "SELECT * FROM odp ORDER BY nama_odp ASC");
if ($query_odp_opt && mysqli_num_rows($query_odp_opt) > 0) {
    while ($row_odp = mysqli_fetch_assoc($query_odp_opt)) {
        $odp_list[] = $row_odp;
    }
}

// --- AMBIL DATA PELANGGAN SEKALIGUS UNTUK OPTIMASI PERFORMA ID GENERATOR ---
$all_pelanggan_data = [];
$query_all_id = "SELECT id_pelanggan, dusun FROM pelanggan";
$res_all_id = mysqli_query($koneksi, $query_all_id);
if ($res_all_id && mysqli_num_rows($res_all_id) > 0) {
    while ($row_id = mysqli_fetch_assoc($res_all_id)) {
        $all_pelanggan_data[] = $row_id;
    }
}

// --- FUNGSI HYBRID PHP-SIDE: MENENTUKAN ID BERIKUTNYA SECARA AKURAT & ANTI-DUPLIKAT ---
function getNextIdByDusun($allData, $dusunName, $minNum, $maxNum, $defaultId) {
    $max_found = 0;
    $dusunNameLower = strtolower(trim($dusunName));
    
    if (!empty($allData)) {
        foreach ($allData as $row) {
            $currentDusun = isset($row['dusun']) ? strtolower(trim($row['dusun'])) : '';
            $id_str = $row['id_pelanggan'] ?? '';
            
            $num = 0;
            if (preg_match('/-(\d+)/', $id_str, $matches)) {
                $num = intval($matches[1]);
            } elseif (preg_match('/\d+/', $id_str, $matches)) {
                $num = intval($matches[0]);
            }
            
            if ($num === 0) {
                continue; 
            }
            
            $isMatch = false;
            
            if ($currentDusun === $dusunNameLower) {
                $isMatch = true;
            } elseif ($currentDusun === '') {
                if ($maxNum === null) {
                    if ($num >= $minNum) {
                        $isMatch = true;
                    }
                } else {
                    if ($num >= $minNum && $num <= $maxNum) {
                        $isMatch = true;
                    }
                }
            }
            
            if ($isMatch && $num > $max_found) {
                $max_found = $num;
            }
        }
    }
    
    if ($max_found > 0) {
        return "YO-" . sprintf("%03d", $max_found + 1);
    }
    
    return $defaultId;
}

$next_id_kemitir = getNextIdByDusun($all_pelanggan_data, 'Kemitir', 1, 300, 'YO-001');
$next_id_mbalong = getNextIdByDusun($all_pelanggan_data, 'Mbalong', 301, 500, 'YO-301');
$next_id_ngoho   = getNextIdByDusun($all_pelanggan_data, 'Ngoho', 501, null, 'YO-501');

if ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR') {
    $initial_id = $next_id_kemitir; 
} else {
    $petugas_dusun = $_SESSION['dusun_pengelola'] ?? 'Kemitir';
    if (strcasecmp($petugas_dusun, 'Mbalong') === 0) {
        $initial_id = $next_id_mbalong;
    } elseif (strcasecmp($petugas_dusun, 'Ngoho') === 0) {
        $initial_id = $next_id_ngoho;
    } else {
        $initial_id = $next_id_kemitir;
    }
}

// --- PROSES 1: TAMBAH PELANGGAN BARU ---
if (isset($_POST['tambah_pelanggan'])) {
    $id_pelan   = mysqli_real_escape_string($koneksi, $_POST['id_pelanggan']);
    $nama       = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $no_wa      = mysqli_real_escape_string($koneksi, $_POST['no_wa']);
    $alamat     = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $id_paket   = intval($_POST['id_paket']);
    $tgl_join   = mysqli_real_escape_string($koneksi, $_POST['tanggal_join']);
    $dusun      = ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR') ? mysqli_real_escape_string($koneksi, $_POST['dusun'] ?? '') : mysqli_real_escape_string($koneksi, $_SESSION['dusun_pengelola'] ?? '');
    $id_odp    = !empty($_POST['id_odp']) ? "'".$_POST['id_odp']."'" : "NULL";
    $latitude  = !empty($_POST['latitude']) ? "'".$_POST['latitude']."'" : "NULL";
    $longitude = !empty($_POST['longitude']) ? "'".$_POST['longitude']."'" : "NULL";
    $redaman   = !empty($_POST['redaman_dbm']) ? "'".$_POST['redaman_dbm']."'" : "NULL";
    
    $query_add = "INSERT INTO pelanggan (
    id_pelanggan, nama, no_wa, alamat, id_paket, status, tanggal_join, dusun, id_odp, latitude, longitude, redaman_dbm
) VALUES (
    '$id_pelan', '$nama', '$no_wa', '$alamat', '$id_paket', 'Aktif', '$tgl_join', '$dusun', $id_odp, $latitude, $longitude, $redaman
)";
    
    if (mysqli_query($koneksi, $query_add)) {
        if (function_exists('update_google_sheet')) {
            $q_pkt = mysqli_query($koneksi, "SELECT nama_paket, tarif_bulanan FROM paket_internet WHERE id = '$id_paket'");
            $d_pkt = mysqli_fetch_assoc($q_pkt);
            $nama_paket = $d_pkt['nama_paket'] ?? '';
            $tarif_paket = $d_pkt['tarif_bulanan'] ?? 0;

            $data_ke_sheet = [
                'no_wa'   => $no_wa,
                'nama'       => $nama,
                'tagihan'    => $tarif_paket,
                'tunggakan'  => 0,
                'paket'      => $nama_paket,
                'tempo'      => date('d/m/Y', strtotime($tgl_join)),
                'status'     => 'Lunas / Aman'
            ];
            @update_google_sheet($dusun, $data_ke_sheet, 'tambah'); 
        }

        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => 'Pelanggan baru berhasil ditambahkan ke sistem.'];
        echo "<script>window.location='index.php?page=pelanggan';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal Tambah!', 'message' => mysqli_error($koneksi)];
    }
}

// --- PROSES 2: EDIT / UPDATE DATA PELANGGAN ---
if (isset($_POST['edit_pelanggan'])) {
    $id_pelan   = mysqli_real_escape_string($koneksi, $_POST['id_pelanggan']);
    $nama       = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $no_wa      = mysqli_real_escape_string($koneksi, $_POST['no_wa']);
    $alamat     = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $id_paket   = intval($_POST['id_paket']);
    $status     = mysqli_real_escape_string($koneksi, $_POST['status']);
    $tgl_join   = mysqli_real_escape_string($koneksi, $_POST['tanggal_join']);
    $id_odp    = !empty($_POST['id_odp']) ? "'".$_POST['id_odp']."'" : "NULL";
    $latitude  = !empty($_POST['latitude']) ? "'".$_POST['latitude']."'" : "NULL";
    $longitude = !empty($_POST['longitude']) ? "'".$_POST['longitude']."'" : "NULL";
    $redaman   = !empty($_POST['redaman_dbm']) ? "'".$_POST['redaman_dbm']."'" : "NULL";

    if ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR') {
        $query_edit = "UPDATE pelanggan SET nama = '$nama', no_wa = '$no_wa', alamat = '$alamat', id_paket = '$id_paket', status = '$status', tanggal_join = '$tgl_join', id_odp = $id_odp, latitude = $latitude, longitude = $longitude, redaman_dbm = $redaman WHERE id_pelanggan = '$id_pelan'";
    } else {
        $dusun_login_safe = mysqli_real_escape_string($koneksi, $_SESSION['dusun_pengelola'] ?? '');
        $query_edit = "UPDATE pelanggan SET nama = '$nama', no_wa = '$no_wa', alamat = '$alamat', id_paket = '$id_paket', status = '$status', tanggal_join = '$tgl_join', id_odp = $id_odp, latitude = $latitude, longitude = $longitude, redaman_dbm = $redaman WHERE id_pelanggan = '$id_pelan' AND dusun = '$dusun_login_safe'";
    }
    
    if (mysqli_query($koneksi, $query_edit)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Diperbarui!', 'message' => 'Data pelanggan berhasil diperbarui!'];
        echo "<script>window.location='index.php?page=pelanggan';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal Edit!', 'message' => mysqli_error($koneksi)];
    }
}

// --- PROSES FITUR BARU: IMPORT TIKOR PELANGGAN DARI CSV ---
if (isset($_POST['import_tikor'])) {
    if (isset($_FILES['file_csv']['tmp_name']) && !empty($_FILES['file_csv']['tmp_name'])) {
        $file = $_FILES['file_csv']['tmp_name'];
        $handle = fopen($file, "r");

        // Abaikan baris pertama (header CSV)
        $header = fgetcsv($handle, 1000, ",");

        $updated_count = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Kolom 0: Nama, Kolom 1: Latitude, Kolom 2: Longitude
            if (count($data) >= 3) {
                $nama      = trim($data[0]);
                // Otomatis ganti koma (,) menjadi titik (.) pada nilai koordinat
                $latitude  = str_replace(',', '.', trim($data[1]));
                $longitude = str_replace(',', '.', trim($data[2]));

                if (!empty($nama) && !empty($latitude) && !empty($longitude)) {
                    $nama_clean = mysqli_real_escape_string($koneksi, $nama);
                    $lat_clean  = mysqli_real_escape_string($koneksi, $latitude);
                    $lng_clean  = mysqli_real_escape_string($koneksi, $longitude);

                    if ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR') {
                        $query_update = "UPDATE pelanggan SET latitude = '$lat_clean', longitude = '$lng_clean' WHERE LOWER(TRIM(nama)) = LOWER('$nama_clean')";
                    } else {
                        $dusun_login_safe = mysqli_real_escape_string($koneksi, $_SESSION['dusun_pengelola'] ?? '');
                        $query_update = "UPDATE pelanggan SET latitude = '$lat_clean', longitude = '$lng_clean' WHERE LOWER(TRIM(nama)) = LOWER('$nama_clean') AND dusun = '$dusun_login_safe'";
                    }

                    if (mysqli_query($koneksi, $query_update)) {
                        if (mysqli_affected_rows($koneksi) > 0) {
                            $updated_count++;
                        }
                    }
                }
            }
        }
        fclose($handle);

        $_SESSION['toast'] = [
            'type' => 'success',
            'title' => 'Import Tikor Berhasil!',
            'message' => "Berhasil memperbarui titik koordinat untuk $updated_count pelanggan."
        ];
        echo "<script>window.location='index.php?page=pelanggan';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal Upload!', 'message' => 'File CSV tidak ditemukan atau kosong.'];
    }
}

// --- PROSES 3: HAPUS PELANGGAN ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_hapus = mysqli_real_escape_string($koneksi, $_GET['id']);

    if ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR') {
        $query_del = "DELETE FROM pelanggan WHERE id_pelanggan = '$id_hapus'";
    } else {
        $dusun_login_safe = mysqli_real_escape_string($koneksi, $_SESSION['dusun_pengelola'] ?? '');
        $query_del = "DELETE FROM pelanggan WHERE id_pelanggan = '$id_hapus' AND dusun = '$dusun_login_safe'";
    }
    
    if (mysqli_query($koneksi, $query_del)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Dihapus!', 'message' => 'Pelanggan berhasil dihapus dari sistem.'];
        echo "<script>window.location='index.php?page=pelanggan';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Error!', 'message' => mysqli_error($koneksi)];
    }
}

// --- PROSES 4: AMBIL DATA PELANGGAN ---
$query_view = "SELECT pelanggan.*, paket_internet.nama_paket, paket_internet.tarif_bulanan, odp.nama_odp 
               FROM pelanggan 
               LEFT JOIN paket_internet ON pelanggan.id_paket = paket_internet.id
               LEFT JOIN odp ON pelanggan.id_odp = odp.id";

if ($role_user !== 'ADMIN' && $role_user !== 'ADMINISTRATOR') {
    $dusun_user = mysqli_real_escape_string($koneksi, $_SESSION['dusun_pengelola'] ?? '');
    $query_view .= " WHERE pelanggan.dusun = '$dusun_user'";
}

$query_view .= " ORDER BY pelanggan.dusun ASC, pelanggan.nama ASC";
$result_view = mysqli_query($koneksi, $query_view);

// --- PROSES 5: AMBIL DAFTAR PAKET ---
$list_paket = mysqli_query($koneksi, "SELECT id, nama_paket FROM paket_internet ORDER BY nama_paket ASC");
$pakets = [];
if ($list_paket) {
    while($p = mysqli_fetch_assoc($list_paket)) {
        $pakets[] = $p;
    }
}
?>

<!-- HEADER HALAMAN & OPERASIONAL CONTROLS -->
<div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-extrabold text-slate-100 tracking-wide flex items-center gap-2.5">
            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 text-lg">
                <i class="fa-solid fa-users"></i>
            </span>
            Data Pelanggan
        </h3>
        <p class="text-xs text-slate-400 mt-1">
            Kelola data pelanggan internet aktif, isolir, maupun nonaktif secara real-time di <span class="text-amber-400 font-semibold">YOU-ONE.net</span>
        </p>
    </div>
    
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <!-- Filter Dusun -->
        <div class="relative w-full sm:w-44">
            <select id="filter-dusun-select" onchange="applyFilters()" 
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer font-medium">
                <option value="">Semua Dusun</option>
                <option value="kemitir">Dusun Kemitir</option>
                <option value="mbalong">Dusun Mbalong</option>
                <option value="ngoho">Dusun Ngoho</option>
            </select>
            <i class="fa-solid fa-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
        </div>

        <!-- Filter Status -->
        <div class="relative w-full sm:w-36">
            <select id="filter-status-select" onchange="applyFilters()" 
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer font-medium">
                <option value="">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="isolir">Isolir</option>
                <option value="nonaktif">Nonaktif</option>
            </select>
            <i class="fa-solid fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
        </div>

        <!-- Live Search Input -->
        <div class="relative w-full sm:w-64">
            <input type="text" id="live-search-input" oninput="applyFilters()" placeholder="Cari ID, nama, alamat..." 
                class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-9 py-2.5 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-medium">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
            <button type="button" id="clear-search-btn" onclick="resetLiveSearch()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-rose-400 hidden transition focus:outline-none">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Button Import Tikor CSV -->
        <button onclick="toggleModal('modal-import-tikor')" class="py-2.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-lg shadow-emerald-950/40 flex items-center justify-center gap-2 whitespace-nowrap active:scale-95 cursor-pointer">
            <i class="fa-solid fa-file-csv text-sm"></i> Import Tikor CSV
        </button>

        <!-- Button Tambah Pelanggan -->
        <button onclick="toggleModal('modal-pelanggan')" class="py-2.5 px-4 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-lg shadow-orange-950/40 flex items-center justify-center gap-2 whitespace-nowrap active:scale-95 cursor-pointer">
            <i class="fa-solid fa-user-plus text-sm"></i> Tambah Pelanggan
        </button>
    </div>
</div>

<!-- TABEL DATA PELANGGAN -->
<div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs text-slate-300">
            <thead class="bg-slate-950/80 text-slate-400 uppercase font-semibold tracking-wider border-b border-slate-800">
                <tr>
                    <th class="p-4">ID / Nama Pelanggan</th>
                    <th class="p-4">No. WhatsApp</th>
                    <th class="p-4">Paket Internet</th>
                    <th class="p-4">ODP & Redaman</th>
                    <th class="p-4">Koordinat GPS</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Tgl Join</th>
                    <th class="p-4 text-center w-28">Aksi</th>
                </tr>
            </thead>
            <tbody id="pelanggan-table-body" class="divide-y divide-slate-800/50">
                <?php if ($result_view && mysqli_num_rows($result_view) > 0) : ?>
                    <?php while ($row = mysqli_fetch_assoc($result_view)) : ?>
                    <tr class="pelanggan-data-row hover:bg-slate-800/40 transition-colors group" 
                        data-dusun="<?= htmlspecialchars(strtolower(trim($row['dusun'] ?? ''))); ?>"
                        data-status="<?= htmlspecialchars(strtolower(trim($row['status'] ?? ''))); ?>">
                        <td class="p-4">
                            <div class="text-[11px] font-mono font-bold tracking-wider text-amber-400 uppercase flex items-center gap-1.5">
                                <i class="fa-solid fa-hashtag text-[9px] text-amber-500/70"></i>
                                <?= htmlspecialchars($row['id_pelanggan']); ?>
                            </div>
                            <div class="font-bold text-slate-100 mt-0.5 group-hover:text-amber-300 transition text-sm">
                                <?= htmlspecialchars($row['nama']); ?>
                            </div>
                            <div class="text-[11px] text-slate-400 truncate max-w-xs mt-0.5 flex items-center gap-1">
                                <i class="fa-solid fa-location-dot text-[10px] text-slate-500"></i>
                                <?= htmlspecialchars($row['alamat']); ?> 
                                <span class="text-amber-400/80 font-semibold">(Dusun: <?= htmlspecialchars($row['dusun'] ?: '-'); ?>)</span>
                            </div>
                        </td>
                        <td class="p-4 font-mono text-slate-300">
                            <a href="https://wa.me/<?= htmlspecialchars($row['no_wa']); ?>" target="_blank" class="inline-flex items-center gap-1.5 hover:text-emerald-400 transition">
                                <i class="fa-brands fa-whatsapp text-emerald-400 text-sm"></i>
                                <span><?= htmlspecialchars($row['no_wa']); ?></span>
                            </a>
                        </td>
                        <td class="p-4">
                            <div class="text-slate-100 font-bold"><?= htmlspecialchars($row['nama_paket'] ?? 'Tanpa Paket'); ?></div>
                            <div class="text-[11px] text-amber-400/90 font-mono font-semibold mt-0.5">
                                Rp <?= number_format($row['tarif_bulanan'] ?? 0, 0, ',', '.'); ?>
                            </div>
                        </td>
                        <td class="p-4 text-xs">
                            <div class="font-bold text-amber-300 flex items-center gap-1">
                                <i class="fa-solid fa-box text-[10px] text-orange-400"></i>
                                <?= htmlspecialchars($row['nama_odp'] ?? '-'); ?>
                            </div>
                            <div class="text-slate-400 font-mono text-[11px] mt-0.5">
                                <?= !empty($row['redaman_dbm']) ? htmlspecialchars($row['redaman_dbm']) . ' dBm' : '<span class="text-slate-600">-</span>'; ?>
                            </div>
                        </td>

                        <!-- KOORDINAT GPS -->
                        <td class="p-4">
                            <?php if (!empty($row['latitude']) && !empty($row['longitude'])) : ?>
                                <button type="button" 
                                    onclick="bukaGoogleMaps('<?= htmlspecialchars($row['latitude']); ?>', '<?= htmlspecialchars($row['longitude']); ?>', '<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>')"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 text-[11px] font-mono font-medium transition shadow-sm cursor-pointer" title="Buka di Google Maps">
                                    <i class="fa-solid fa-map-pin text-orange-400"></i>
                                    <span><?= htmlspecialchars($row['latitude']); ?>, <?= htmlspecialchars($row['longitude']); ?></span>
                                </button>
                            <?php else : ?>
                                <span class="text-slate-600 font-mono text-[11px]">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- STATUS BADGES -->
                        <td class="p-4">
                            <?php if ($row['status'] == 'Aktif') : ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wide bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    Aktif
                                </span>
                            <?php elseif ($row['status'] == 'Isolir') : ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wide bg-amber-500/10 border border-amber-500/20 text-amber-400 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                    Isolir
                                </span>
                            <?php else : ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wide bg-rose-500/10 border border-rose-500/20 text-rose-400 shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                    Nonaktif
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="p-4 text-slate-400 font-medium"><?= date('d M Y', strtotime($row['tanggal_join'])); ?></td>
                        <td class="p-4 text-center">
                            <div class="flex justify-center items-center gap-1.5">
                                <button type="button"
                                    data-id="<?= htmlspecialchars($row['id_pelanggan'], ENT_QUOTES); ?>"
                                    data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                    data-no_wa="<?= htmlspecialchars($row['no_wa'], ENT_QUOTES); ?>"
                                    data-alamat="<?= htmlspecialchars($row['alamat'], ENT_QUOTES); ?>"
                                    data-id_paket="<?= htmlspecialchars($row['id_paket'], ENT_QUOTES); ?>"
                                    data-id_odp="<?= htmlspecialchars($row['id_odp'] ?? '', ENT_QUOTES); ?>"
                                    data-redaman_dbm="<?= htmlspecialchars($row['redaman_dbm'] ?? '', ENT_QUOTES); ?>"
                                    data-latitude="<?= htmlspecialchars($row['latitude'] ?? '', ENT_QUOTES); ?>"
                                    data-longitude="<?= htmlspecialchars($row['longitude'] ?? '', ENT_QUOTES); ?>"
                                    data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES); ?>"
                                    data-tanggal_join="<?= htmlspecialchars($row['tanggal_join'], ENT_QUOTES); ?>"
                                    onclick="openEditPelanggan(this)" 
                                    class="p-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 rounded-xl transition border border-amber-500/30 active:scale-95 shadow-sm" title="Ubah Data">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <button type="button"
                                    data-id="<?= htmlspecialchars($row['id_pelanggan'], ENT_QUOTES); ?>"
                                    data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                    onclick="pemicuHapusPelanggan(this)" 
                                    class="p-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-xl transition border border-rose-500/30 active:scale-95 shadow-sm" title="Hapus Pelanggan">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr class="fallback-empty-row">
                        <td colspan="8" class="p-8 text-center text-slate-500">
                            <i class="fa-solid fa-users-slash text-3xl mb-2 block text-slate-700"></i> Belum ada data pelanggan yang terdaftar.
                        </td>
                    </tr>
                <?php endif; ?>

                <tr id="js-search-empty-row" class="hidden">
                    <td colspan="8" class="p-8 text-center text-slate-500">
                        <i class="fa-solid fa-magnifying-glass-blur text-3xl mb-2 block text-slate-700"></i>
                        Data pelanggan yang Anda cari tidak ditemukan.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL IMPORT TIKOR CSV -->
<div id="modal-import-tikor" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800/90 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden my-8">
        <div class="p-5 border-b border-slate-800/80 flex justify-between items-center bg-slate-950/60">
            <h4 class="font-extrabold text-emerald-400 text-sm flex items-center gap-2">
                <i class="fa-solid fa-file-csv text-base"></i> Import Tikor Pelanggan (CSV)
            </h4>
            <button type="button" onclick="toggleModal('modal-import-tikor')" class="text-slate-500 hover:text-emerald-400 transition focus:outline-none">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        
        <form action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Pilih File CSV Tikor</label>
                <div class="relative">
                    <input type="file" name="file_csv" accept=".csv" required
                        class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-emerald-500/60 transition shadow-inner font-mono file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-500/20 file:text-emerald-400 hover:file:bg-emerald-500/30">
                </div>
                <div class="mt-3 p-3 bg-slate-950/60 rounded-xl border border-slate-800/80 text-[11px] text-slate-400 space-y-1">
                    <p class="font-bold text-slate-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info text-emerald-400"></i> Ketentuan Format CSV:
                    </p>
                    <p>1. Header baris pertama: <code class="text-amber-400 font-mono">nama, latitude, longitude</code></p>
                    <p>2. Nama di CSV harus sama persis dengan nama pelanggan di database.</p>
                    <p>3. Tanda koma (,) pada angka koordinat otomatis dikonversi ke titik (.).</p>
                </div>
            </div>

            <div class="pt-3 flex justify-end gap-3 border-t border-slate-800/60">
                <button type="button" onclick="toggleModal('modal-import-tikor')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition active:scale-95 cursor-pointer">Batal</button>
                <button type="submit" name="import_tikor" class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-lg shadow-emerald-950/40 active:scale-95 cursor-pointer">Upload & Update</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL REGISTER PELANGGAN -->
<div id="modal-pelanggan" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800/90 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden my-8">
        <div class="p-5 border-b border-slate-800/80 flex justify-between items-center bg-slate-950/60">
            <h4 class="font-extrabold text-amber-400 text-sm flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-base"></i> Registrasi Pelanggan Baru
            </h4>
            <button type="button" onclick="toggleModal('modal-pelanggan')" class="text-slate-500 hover:text-amber-400 transition focus:outline-none">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        
        <form action="" method="POST" class="p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">ID Pelanggan</label>
                    <div class="relative">
                        <input type="text" name="id_pelanggan" id="reg-id-pelanggan" value="<?= htmlspecialchars($initial_id); ?>" readonly required
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-amber-400 font-bold font-mono focus:outline-none cursor-not-allowed">
                        <i class="fa-solid fa-hashtag absolute left-3 top-1/2 -translate-y-1/2 text-xs text-amber-500/70"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Tanggal Join</label>
                    <div class="relative">
                        <input type="date" name="tanggal_join" value="<?= date('Y-m-d'); ?>" required
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                        <i class="fa-solid fa-calendar-days absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    </div>
                </div>
            </div>
            
            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Nama Lengkap</label>
                <div class="relative">
                    <input type="text" name="nama" required placeholder="Contoh: Budi Santoso"
                        class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-semibold">
                    <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">No. WhatsApp (Awali 62)</label>
                <div class="relative">
                    <input type="text" name="no_wa" required placeholder="Contoh: 628123456789"
                        class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                    <i class="fa-brands fa-whatsapp absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Pilih Paket Internet</label>
                <div class="relative">
                    <select name="id_paket" required class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer">
                        <option value="">-- Pilih Layanan --</option>
                        <?php foreach($pakets as $p) : ?>
                            <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['nama_paket']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fa-solid fa-wifi absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Pilih ODP</label>
                    <div class="relative">
                        <select name="id_odp" class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer">
                            <option value="">-- Tanpa ODP --</option>
                            <?php foreach($odp_list as $odp) : ?>
                                <option value="<?= $odp['id']; ?>"><?= htmlspecialchars($odp['nama_odp']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-box absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Redaman (dBm)</label>
                    <div class="relative">
                        <input type="number" step="0.01" name="redaman_dbm" placeholder="-19.50"
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                        <i class="fa-solid fa-gauge-high absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Latitude</label>
                    <div class="relative">
                        <input type="text" name="latitude" placeholder="-7.550000"
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                        <i class="fa-solid fa-map-pin absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Longitude</label>
                    <div class="relative">
                        <input type="text" name="longitude" placeholder="110.820000"
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                        <i class="fa-solid fa-map-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    </div>
                </div>
            </div>
           
            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Alamat Pemasangan</label>
                <div class="relative">
                    <textarea name="alamat" rows="2" placeholder="Alamat lengkap lokasi rumah..."
                        class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner"></textarea>
                    <i class="fa-solid fa-house-chimney absolute left-3 top-3 text-xs text-slate-500"></i>
                </div>
            </div>
            
            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Dusun Kelola</label>
                <div class="relative">
                    <?php if ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR') : ?>
                        <select name="dusun" required onchange="updateIdPelanggan(this.value)"
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer">
                            <option value="Kemitir">Kemitir</option>
                            <option value="Mbalong">Mbalong</option>
                            <option value="Ngoho">Ngoho</option>
                        </select>
                        <i class="fa-solid fa-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
                    <?php else : ?>
                        <input type="text" name="dusun" value="<?= htmlspecialchars($_SESSION['dusun_pengelola'] ?? ''); ?>" readonly required
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-400 font-semibold cursor-not-allowed">
                        <i class="fa-solid fa-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="pt-3 flex justify-end gap-3 border-t border-slate-800/60">
                <button type="button" onclick="toggleModal('modal-pelanggan')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition active:scale-95 cursor-pointer">Batal</button>
                <button type="submit" name="tambah_pelanggan" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-lg shadow-orange-950/40 active:scale-95 cursor-pointer">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT PELANGGAN -->
<div id="modal-edit-pelanggan" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800/90 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden my-8">
        <div class="p-5 border-b border-slate-800/80 flex justify-between items-center bg-slate-950/60">
            <h4 class="font-extrabold text-amber-400 text-sm flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-base"></i> Ubah Data Pelanggan
            </h4>
            <button type="button" onclick="toggleModal('modal-edit-pelanggan')" class="text-slate-500 hover:text-amber-400 transition focus:outline-none">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">ID Pelanggan</label>
                    <div class="relative">
                        <input type="text" name="id_pelanggan" id="edit-id-pelan" readonly required
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-amber-400 font-bold font-mono focus:outline-none cursor-not-allowed">
                        <i class="fa-solid fa-hashtag absolute left-3 top-1/2 -translate-y-1/2 text-xs text-amber-500/70"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Tanggal Join</label>
                    <div class="relative">
                        <input type="date" name="tanggal_join" id="edit-tgl-join" required
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                        <i class="fa-solid fa-calendar-days absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Nama Lengkap</label>
                <div class="relative">
                    <input type="text" name="nama" id="edit-nama-pelan" required
                        class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-semibold">
                    <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">No. WhatsApp</label>
                <div class="relative">
                    <input type="text" name="no_wa" id="edit-wa-pelan" required
                        class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                    <i class="fa-brands fa-whatsapp absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Paket Internet</label>
                    <div class="relative">
                        <select name="id_paket" id="edit-paket-pelan" required class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer">
                            <?php foreach($pakets as $p) : ?>
                                <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['nama_paket']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-wifi absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Status Koneksi</label>
                    <div class="relative">
                        <select name="status" id="edit-status-pelan" required class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer">
                            <option value="Aktif">Aktif</option>
                            <option value="Isolir">Isolir</option>
                            <option value="Nonaktif">Nonaktif</option>
                        </select>
                        <i class="fa-solid fa-signal absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Pilih ODP</label>
                    <div class="relative">
                        <select name="id_odp" id="edit-odp-pelan" class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer">
                            <option value="">-- Tanpa ODP --</option>
                            <?php foreach($odp_list as $odp) : ?>
                                <option value="<?= $odp['id']; ?>"><?= htmlspecialchars($odp['nama_odp']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-box absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Redaman (dBm)</label>
                    <div class="relative">
                        <input type="number" step="0.01" name="redaman_dbm" id="edit-redaman-pelan" placeholder="-19.50"
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                        <i class="fa-solid fa-gauge-high absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Latitude</label>
                    <div class="relative">
                        <input type="text" name="latitude" id="edit-lat-pelan" placeholder="-7.550000"
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                        <i class="fa-solid fa-map-pin absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Longitude</label>
                    <div class="relative">
                        <input type="text" name="longitude" id="edit-lng-pelan" placeholder="110.820000"
                            class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                        <i class="fa-solid fa-map-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Alamat Pemasangan</label>
                <div class="relative">
                    <textarea name="alamat" id="edit-alamat-pelan" rows="2"
                        class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner"></textarea>
                    <i class="fa-solid fa-house-chimney absolute left-3 top-3 text-xs text-slate-500"></i>
                </div>
            </div>

            <div class="pt-3 flex justify-end gap-3 border-t border-slate-800/60">
                <button type="button" onclick="toggleModal('modal-edit-pelanggan')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition active:scale-95 cursor-pointer">Batal</button>
                <button type="submit" name="edit_pelanggan" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-lg shadow-orange-950/40 active:scale-95 cursor-pointer">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Alert Session Handler -->
<?php if (isset($_SESSION['toast'])): 
    $toast_type = $_SESSION['toast']['type'];
    $toast_title = $_SESSION['toast']['title'];
    $toast_msg = $_SESSION['toast']['message'];
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $toast_type; ?>',
        title: '<?= htmlspecialchars($toast_title, ENT_QUOTES); ?>',
        text: '<?= htmlspecialchars($toast_msg, ENT_QUOTES); ?>',
        background: '#0f172a',
        color: '#f8fafc',
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'OK',
        customClass: {
            popup: 'border border-slate-800 rounded-2xl shadow-2xl backdrop-blur-md',
            title: 'font-extrabold tracking-wide text-xl text-slate-100',
            htmlContainer: 'text-xs text-slate-400 mt-2'
        }
    });
});
</script>
<?php 
    unset($_SESSION['toast']); 
endif; 
?>

<script>
function applyFilters() {
    const searchInput = document.getElementById('live-search-input');
    const dusunSelect = document.getElementById('filter-dusun-select');
    const statusSelect = document.getElementById('filter-status-select');
    const clearBtn = document.getElementById('clear-search-btn');
    const emptyRow = document.getElementById('js-search-empty-row');
    const dataRows = document.querySelectorAll('.pelanggan-data-row');

    const searchQuery = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const selectedDusun = dusunSelect ? dusunSelect.value.toLowerCase().trim() : '';
    const selectedStatus = statusSelect ? statusSelect.value.toLowerCase().trim() : '';
    let matchCount = 0;

    if (searchQuery.length > 0) {
        if(clearBtn) clearBtn.classList.remove('hidden');
    } else {
        if(clearBtn) clearBtn.classList.add('hidden');
    }

    dataRows.forEach(row => {
        const rowText = row.textContent.toLowerCase();
        const rowDusun = row.dataset.dusun || '';
        const rowStatus = row.dataset.status || '';

        const matchesSearch = rowText.includes(searchQuery);
        const matchesDusun = (selectedDusun === '' || rowDusun === selectedDusun);
        const matchesStatus = (selectedStatus === '' || rowStatus === selectedStatus);

        if (matchesSearch && matchesDusun && matchesStatus) {
            row.style.display = ''; 
            matchCount++;
        } else {
            row.style.display = 'none'; 
        }
    });

    if (emptyRow) {
        if (matchCount === 0 && dataRows.length > 0) {
            emptyRow.classList.remove('hidden');
        } else {
            emptyRow.classList.add('hidden');
        }
    }
}

function resetLiveSearch() {
    const searchInput = document.getElementById('live-search-input');
    const dusunSelect = document.getElementById('filter-dusun-select');
    const statusSelect = document.getElementById('filter-status-select');
    
    if (searchInput) searchInput.value = '';
    if (dusunSelect) dusunSelect.value = ''; 
    if (statusSelect) statusSelect.value = ''; 
    
    applyFilters(); 
    if (searchInput) searchInput.focus();
}

const rangeDusunIds = {
    'Kemitir': '<?= $next_id_kemitir; ?>',
    'Mbalong': '<?= $next_id_mbalong; ?>',
    'Ngoho': '<?= $next_id_ngoho; ?>'
};

function updateIdPelanggan(dusunVal) {
    const targetInput = document.getElementById('reg-id-pelanggan');
    if (targetInput && rangeDusunIds[dusunVal]) {
        targetInput.value = rangeDusunIds[dusunVal];
    }
}

function toggleModal(id) {
    const modal = document.getElementById(id);
    if (modal.classList.contains('hidden')) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}

function openEditPelanggan(button) {
    document.getElementById('edit-id-pelan').value = button.dataset.id || '';
    document.getElementById('edit-nama-pelan').value = button.dataset.nama || '';
    document.getElementById('edit-wa-pelan').value = button.dataset.no_wa || '';
    document.getElementById('edit-alamat-pelan').value = button.dataset.alamat || '';
    document.getElementById('edit-paket-pelan').value = button.dataset.id_paket || '';
    document.getElementById('edit-status-pelan').value = button.dataset.status || '';
    document.getElementById('edit-tgl-join').value = button.dataset.tanggal_join || '';
    document.getElementById('edit-odp-pelan').value = button.dataset.id_odp || '';
    document.getElementById('edit-redaman-pelan').value = button.dataset.redaman_dbm || '';
    document.getElementById('edit-lat-pelan').value = button.dataset.latitude || '';
    document.getElementById('edit-lng-pelan').value = button.dataset.longitude || '';
    
    toggleModal('modal-edit-pelanggan');
}

function pemicuHapusPelanggan(button) {
    const id = button.dataset.id;
    const nama = button.dataset.nama;

    Swal.fire({
        title: 'Hapus Pelanggan?',
        html: `Apakah Anda yakin ingin menghapus <b class="text-amber-400">${nama}</b>?<br><span class="text-rose-400/90 text-xs block mt-2">Seluruh riwayat tagihan pelanggan ini juga akan terhapus otomatis secara permanen.</span>`,
        icon: 'warning',
        showCancelButton: true,
        background: '#0f172a',
        color: '#f8fafc',
        confirmButtonColor: '#f43f5e',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        customClass: {
            popup: 'border border-slate-800 rounded-2xl shadow-2xl backdrop-blur-md',
            title: 'font-extrabold tracking-wide text-lg text-slate-100',
            htmlContainer: 'text-xs text-slate-400 leading-relaxed'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "index.php?page=pelanggan&action=delete&id=" + encodeURIComponent(id);
        }
    });
}

function bukaGoogleMaps(lat, lng, nama) {
    Swal.fire({
        title: 'Buka Google Maps?',
        html: `Buka lokasi <b class="text-amber-400">${nama}</b> (${lat}, ${lng}) di Google Maps?`,
        icon: 'question',
        showCancelButton: true,
        background: '#0f172a',
        color: '#f8fafc',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Ya, Buka Maps',
        cancelButtonText: 'Batal',
        customClass: {
            popup: 'border border-slate-800 rounded-2xl shadow-2xl backdrop-blur-md',
            title: 'font-extrabold tracking-wide text-lg text-slate-100',
            htmlContainer: 'text-xs text-slate-400 leading-relaxed'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Menggunakan URL API web resmi agar aman di mobile browser & WebView
            const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
            window.open(mapsUrl, '_blank');
        }
    });
}
</script>