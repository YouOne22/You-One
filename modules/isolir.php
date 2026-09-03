<?php
// modules/isolir.php
    
// PENGAMAN ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// SET TIMEZONE
date_default_timezone_set('Asia/Jakarta');

// 1. HANDLER AJAX: Memproses kirim pesan per baris
if (isset($_GET['action']) && $_GET['action'] === 'send_broadcast') {
    if (file_exists(__DIR__ . '/../config/koneksi.php')) {
        require_once __DIR__ . '/../config/koneksi.php';
    } else {
        require_once 'config/koneksi.php';
    }
    
    header('Content-Type: application/json');
    
    $target = mysqli_real_escape_string($koneksi, $_POST['target'] ?? '');
    $message = $_POST['message'] ?? ''; 

    if (empty($target) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Nomor WA atau isi pesan tidak boleh kosong.']);
        exit;
    }

    $token_fonnte = "eoTkcnhieYpXdHCo7M5H";

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'target' => $target,
            'message' => $message,
            'countryCode' => '62',
        ),
        CURLOPT_HTTPHEADER => array(
            "Authorization: $token_fonnte"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    
    $result_api = json_decode($response, true);

    if (isset($result_api['status']) && $result_api['status'] == true) {
        echo json_encode(['success' => true]);
    } else {
        $error_msg = $result_api['reason'] ?? 'Gagal terhubung ke server Fonnte.';
        echo json_encode(['success' => false, 'message' => $error_msg]);
    }
    exit;
}

// 2. LOAD DATA NORMAL
require_once 'config/koneksi.php';

$role_user = strtoupper($_SESSION['role'] ?? '');
$dusun_pengelola = mysqli_real_escape_string($koneksi, $_SESSION['dusun_pengelola'] ?? '');
$filter_dusun = mysqli_real_escape_string($koneksi, $_GET['dusun'] ?? '');

// Proteksi Wilayah Dropdown Berdasarkan Role
if ($role_user !== 'ADMIN' && $role_user !== 'ADMINISTRATOR') {
    $q_list_dusun = "SELECT DISTINCT dusun FROM pelanggan WHERE status IN ('AKTIF', 'ISOLIR') AND dusun = '$dusun_pengelola' AND dusun != '' ORDER BY dusun ASC";
} else {
    $q_list_dusun = "SELECT DISTINCT dusun FROM pelanggan WHERE status IN ('AKTIF', 'ISOLIR') AND dusun != '' ORDER BY dusun ASC";
}
$res_list_dusun = mysqli_query($koneksi, $q_list_dusun);

// Ambil seluruh data pelanggan aktif/isolir terlebih dahulu
$q_pelanggan = "SELECT p.*, pi.nama_paket, t.no_invoice, t.total_tagihan,
                (SELECT IFNULL(SUM(jumlah_bayar), 0) FROM pembayaran pem WHERE pem.no_invoice = t.no_invoice) as terbayar_bulan_ini,
                (SELECT IFNULL(SUM(t2.total_tagihan), 0) - (SELECT IFNULL(SUM(p2.jumlah_bayar), 0) FROM pembayaran p2 JOIN tagihan t3 ON p2.no_invoice = t3.no_invoice WHERE t3.id_pelanggan = p.id_pelanggan AND t3.no_invoice < t.no_invoice) FROM tagihan t2 WHERE t2.id_pelanggan = p.id_pelanggan AND t2.no_invoice < t.no_invoice) as tunggakan_awal
                FROM pelanggan p
                LEFT JOIN tagihan t ON p.id_pelanggan = t.id_pelanggan 
                    AND t.no_invoice = (
                        SELECT MAX(t2.no_invoice) 
                        FROM tagihan t2 
                        WHERE t2.id_pelanggan = p.id_pelanggan
                    )
                LEFT JOIN paket_internet pi ON p.id_paket = pi.id
                WHERE p.status IN ('AKTIF', 'ISOLIR')";

if ($role_user !== 'ADMIN' && $role_user !== 'ADMINISTRATOR') {
    $q_pelanggan .= " AND p.dusun = '$dusun_pengelola'";
} else {
    if (!empty($filter_dusun)) {
        $q_pelanggan .= " AND p.dusun = '$filter_dusun'";
    }
}
$q_pelanggan .= " ORDER BY p.nama ASC";
$result_pelanggan = mysqli_query($koneksi, $q_pelanggan);

// Proses Filter Saringan Array: Hanya mengambil yang memiliki sisa tagihan > 0 (Belum Lunas)
$data_isolir = [];
if ($result_pelanggan) {
    while ($row = mysqli_fetch_assoc($result_pelanggan)) {
        if (empty($row['no_invoice'])) {
            continue; 
        }
        
        $terbayar_bulan_ini = floatval($row['terbayar_bulan_ini']);
        $tunggakan_awal = max(0, floatval($row['tunggakan_awal']));
        $total_t = isset($row['total_tagihan']) ? floatval($row['total_tagihan']) : 0;
        $sisa_tagihan = max(0, ($total_t + $tunggakan_awal) - $terbayar_bulan_ini);

        if ($sisa_tagihan <= 0) {
            continue;
        }

        $tahun_bulan = substr($row['no_invoice'], 4, 6); 
        $tahun = substr($tahun_bulan, 0, 4); 
        $bulan = substr($tahun_bulan, 4, 2);
        $hari_jatuh_tempo = (strpos(strtolower($row['alamat']??''), 'mbalong') !== false || strpos(strtolower($row['wilayah']??''), 'mbalong') !== false) ? '25' : '10';
        
        $row['computed_total_t'] = $total_t;
        $row['computed_tunggakan'] = max(0, $tunggakan_awal - $terbayar_bulan_ini);
        $row['computed_sisa'] = $sisa_tagihan;
        $row['computed_jatuh_tempo'] = $hari_jatuh_tempo . '/' . $bulan . '/' . $tahun;
        $row['computed_status'] = $terbayar_bulan_ini > 0 ? 'Dicicil (Belum Lunas)' : 'Belum Terbayar';

        $data_isolir[] = $row;
    }
}
$total_pelanggan = count($data_isolir);
?>

<!-- Header Halaman & Statistik Ringkas -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <h3 class="text-2xl font-extrabold text-slate-100 tracking-wide flex items-center gap-2.5">
            <span class="p-2 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 text-lg">
                <i class="fa-solid fa-plug-circle-xmark"></i>
            </span>
            Daftar Tunggakan & Isolir
        </h3>
        <p class="text-xs text-slate-400 mt-1">
            Mengelola pemutusan atau pengingat darurat untuk pelanggan yang memiliki tunggakan berjalan di <span class="text-amber-400 font-semibold">YOU-ONE.net</span>
        </p>
    </div>
    
    <div class="bg-slate-900/80 border border-rose-500/30 px-5 py-2.5 rounded-2xl shadow-xl backdrop-blur-md flex items-center gap-3">
        <div class="p-2 rounded-xl bg-rose-500/10 text-rose-400 text-sm">
            <i class="fa-solid fa-user-slash text-rose-400"></i>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Belum Lunas</span>
            <span class="text-base font-bold font-mono text-rose-400" id="textTotalPelanggan"><?= $total_pelanggan; ?> Orang</span>
        </div>
    </div>
</div>

<!-- Control Bar: Filter & Search -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 items-end">
    <!-- Filter Wilayah -->
    <div class="flex flex-col gap-1.5">
        <label class="text-xs text-slate-400 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-location-dot text-rose-500"></i> Filter Wilayah Tunggakan:
        </label>
        <div class="relative">
            <select onchange="filterByDusun(this.value)" class="w-full bg-slate-900/80 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-rose-500/60 transition cursor-pointer shadow-lg backdrop-blur-md appearance-none pr-8">
                <?php if ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR') : ?>
                    <option value="">🌍 Semua Wilayah</option>
                <?php endif; ?>
                <?php mysqli_data_seek($res_list_dusun, 0); while($d = mysqli_fetch_assoc($res_list_dusun)): ?>
                    <option value="<?= htmlspecialchars($d['dusun']); ?>" <?= $filter_dusun === $d['dusun'] ? 'selected' : ''; ?>>📍 Dusun <?= htmlspecialchars($d['dusun']); ?></option>
                <?php endwhile; ?>
            </select>
            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
        </div>
    </div>

    <!-- Search Box -->
    <div class="flex flex-col gap-1.5">
        <label class="text-xs text-slate-400 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-magnifying-glass text-rose-500"></i> Pencarian Cepat:
        </label>
        <div class="relative">
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari nama pelanggan..." class="w-full bg-slate-900/80 border border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-200 focus:outline-none focus:border-rose-500/60 transition shadow-lg backdrop-blur-md">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
        </div>
    </div>
</div>

<!-- Tabel Data Tunggakan Pelanggan -->
<div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs text-slate-300" id="tabelPengingat">
            <thead class="bg-slate-950/80 text-slate-400 uppercase font-semibold tracking-wider border-b border-slate-800">
                <tr>
                    <th class="p-4 text-center w-14">No</th>
                    <th class="p-4">Nama Pelanggan</th>
                    <th class="p-4">Nomor WA</th>
                    <th class="p-4">Wilayah / Alamat</th>
                    <th class="p-4 text-right">Sisa Tunggakan</th>
                    <th class="p-4 text-center w-40">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php if ($total_pelanggan > 0) : 
                    $no = 1;
                    foreach ($data_isolir as $row) : 
                ?>
                <tr class="hover:bg-slate-800/40 transition-colors baris-pelanggan" id="row-<?= $row['id_pelanggan']; ?>">
                    <td class="p-4 text-center text-slate-500 font-mono"><?= $no++; ?></td>
                    <td class="p-4 font-bold text-slate-100">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-user-circle text-slate-500 text-sm"></i>
                            <?= htmlspecialchars($row['nama']); ?>
                        </div>
                    </td>
                    <td class="p-4 text-amber-400 font-mono font-semibold tracking-wider">
                        <div class="flex items-center gap-1.5">
                            <i class="fa-brands fa-whatsapp text-emerald-400 text-sm"></i>
                            <span><?= htmlspecialchars($row['no_wa']); ?></span>
                        </div>
                    </td>
                    <td class="p-4">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700/60 mb-1">
                            <i class="fa-solid fa-map-pin text-[9px] text-amber-400"></i>
                            Dusun <?= htmlspecialchars($row['dusun']); ?>
                        </span>
                        <span class="text-[11px] text-slate-400 block truncate max-w-[200px] font-normal"><?= htmlspecialchars($row['alamat']); ?></span>
                    </td>
                    <td class="p-4 text-right font-bold text-rose-400 font-mono text-sm">
                        Rp <?= number_format($row['computed_sisa'], 0, ',', '.'); ?>
                    </td>
                    <td class="p-4 text-center">
                        <button onclick="kirimPesanSingle('<?= $row['id_pelanggan']; ?>')" id="btn-<?= $row['id_pelanggan']; ?>"
                                data-id="<?= $row['id_pelanggan']; ?>" 
                                data-wa="<?= $row['no_wa']; ?>" 
                                data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                data-paket="<?= htmlspecialchars($row['nama_paket'] ?? 'Reguler', ENT_QUOTES); ?>" 
                                data-jatuhtempo="<?= $row['computed_jatuh_tempo']; ?>"
                                data-tagihan="Rp <?= number_format($row['computed_total_t'], 0, ',', '.'); ?>,00" 
                                data-tunggakan="Rp <?= number_format($row['computed_tunggakan'], 0, ',', '.'); ?>"
                                data-total="Rp. <?= number_format($row['computed_sisa'], 0, ',', '.'); ?>,00" 
                                data-status="<?= $row['computed_status']; ?>" 
                                data-status-kirim="ready"
                                class="btn-kirim-single w-full py-1.5 px-3 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 text-xs font-bold rounded-xl transition border border-rose-500/30 flex items-center justify-center gap-1.5 shadow-sm active:scale-95">
                            <i class="fa-solid fa-bell text-[11px]"></i> Peringatkan
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else : ?>
                <tr>
                    <td colspan="6" class="p-8 text-center text-slate-500">
                        <i class="fa-solid fa-circle-check text-3xl mb-2 block text-emerald-500/60"></i>
                        🎉 Bersih! Tidak ada data tunggakan pelanggan di wilayah ini.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Dialog Alert Custom -->
<div id="customAlertModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden transition-all duration-300">
    <div class="bg-slate-900 border border-slate-800/80 p-6 rounded-2xl w-full max-w-sm mx-4 text-center shadow-2xl flex flex-col items-center">
        <div id="alertIconWrapper" class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-3 shadow-inner"><i id="alertIcon" class="fa-solid"></i></div>
        <h4 id="alertTitle" class="text-lg font-bold text-slate-100 mb-1"></h4>
        <p id="customAlertMessage" class="text-xs text-slate-400 mb-5 leading-relaxed"></p>
        <button onclick="closeCustomAlert()" class="w-full py-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-bold text-xs rounded-xl shadow-lg shadow-orange-950/40 transition active:scale-95">OK</button>
    </div>
</div>

<script>
// TEMPLATE PESAN WA ISOLIR
const broadcastMessageTemplate = `Yth. Bapak/Ibu *{nama}*
Kami informasikan bahwa layanan internet Anda saat ini *⚠️ SEMENTARA TERISOLIR ⚠️* karena tagihan belum dibayarkan hingga melewati batas jatuh tempo.

Rincian Tunggakan:
📦 Paket Layanan : {paket}
💸 Tagihan Sebesar : *{total_bayar}*

Silakan segera melakukan perpanjangan agar layanan dapat kami aktifkan kembali.

🏛️ Pembayaran via Transfer / E-Wallet:
• BRI - 6100 01 026499 53 4
• BPD (Bank Jateng) - 2 159 08207 8
• Dana - 0822 4316 7575
(Semua Atas Nama: Nur Rochim)

Jika sudah melakukan pembayaran via transfer, mohon kirimkan bukti pembayaran ke admin untuk proses re-aktivasi.

Terima kasih.`;

function filterByDusun(val) { window.location.href = 'index.php?page=isolir&dusun=' + encodeURIComponent(val); }

function filterTable() {
    let filter = document.getElementById("searchInput").value.toLowerCase();
    let tr = document.getElementById("tabelPengingat").getElementsByClassName("baris-pelanggan");
    let countActive = 0;
    for (let i = 0; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName("td")[1]; // Indeks 1 mengarah ke Nama Pelanggan
        if (td) {
            let matches = (td.textContent || td.innerText).toLowerCase().indexOf(filter) > -1;
            tr[i].style.display = matches ? "" : "none";
            if(matches) countActive++;
        }
    }
    document.getElementById("textTotalPelanggan").innerText = countActive + " Orang";
}

function bukaCustomAlert(type, judul, pesan) {
    const modal = document.getElementById("customAlertModal"), iw = document.getElementById("alertIconWrapper"), icon = document.getElementById("alertIcon");
    iw.className = "w-14 h-14 rounded-2xl flex items-center justify-center text-2xl mb-3 shadow-inner"; icon.className = "fa-solid";
    if(type === 'success') { iw.classList.add("bg-emerald-500/10", "border", "border-emerald-500/20", "text-emerald-400"); icon.classList.add("fa-check"); }
    else if(type === 'warning') { iw.classList.add("bg-amber-500/10", "border", "border-amber-500/20", "text-amber-400"); icon.classList.add("fa-triangle-exclamation"); }
    else { iw.classList.add("bg-rose-500/10", "border", "border-rose-500/20", "text-rose-400"); icon.classList.add("fa-xmark"); }
    document.getElementById("alertTitle").innerText = judul; document.getElementById("customAlertMessage").innerHTML = pesan; modal.classList.remove("hidden");
}

function closeCustomAlert() { document.getElementById("customAlertModal").classList.add("hidden"); }

async function kirimPesanSingle(id) {
    const nama = document.getElementById("btn-" + id).getAttribute("data-nama");
    
    if(await eksekusiKirimAjax(id)) {
        bukaCustomAlert('success', 'Berhasil!', `Notifikasi isolir ke <b>${nama}</b> terkirim.`);
    } else {
        bukaCustomAlert('danger', 'Gagal!', `Pesan tagihan ke <b>${nama}</b> gagal dikirim.`);
    }
}

function eksekusiKirimAjax(id) {
    return new Promise((resolve) => {
        const btn = document.getElementById("btn-" + id);
        let pesanPersonal = broadcastMessageTemplate
            .replace(/{nama}/g, btn.getAttribute("data-nama"))
            .replace(/{paket}/g, btn.getAttribute("data-paket"))
            .replace(/{jatuh_tempo}/g, btn.getAttribute("data-jatuhtempo"))
            .replace(/{tagihan}/g, btn.getAttribute("data-tagihan"))
            .replace(/{tunggakan}/g, btn.getAttribute("data-tunggakan"))
            .replace(/{total_bayar}/g, btn.getAttribute("data-total"))
            .replace(/{status_bayar}/g, btn.getAttribute("data-status"));
            
        btn.disabled = true; 
        btn.className = "w-full py-1.5 px-3 bg-slate-800 text-slate-500 text-xs font-semibold rounded-xl flex items-center justify-center gap-1.5 cursor-not-allowed"; 
        btn.innerHTML = `<i class="fa-solid fa-spinner animate-spin"></i> Proses`;
        
        const fd = new FormData(); 
        fd.append('target', btn.getAttribute("data-wa")); 
        fd.append('message', pesanPersonal);
        
        fetch('modules/isolir.php?action=send_broadcast', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) { 
                btn.className = "w-full py-1.5 px-3 bg-emerald-500/10 text-emerald-400 text-xs font-semibold rounded-xl border border-emerald-500/30 flex items-center justify-center gap-1.5"; 
                btn.innerHTML = `<i class="fa-solid fa-circle-check"></i> Terkirim`; 
                btn.setAttribute("data-status-kirim", "success"); 
                resolve(true); 
            } else { 
                btn.className = "w-full py-1.5 px-3 bg-rose-500/10 text-rose-400 text-xs font-semibold rounded-xl border border-rose-500/30 flex items-center justify-center gap-1.5"; 
                btn.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Gagal`; 
                btn.disabled = false;
                resolve(false); 
            }
        }).catch(() => { 
            btn.className = "w-full py-1.5 px-3 bg-rose-500/10 text-rose-400 text-xs font-semibold rounded-xl border border-rose-500/30 flex items-center justify-center gap-1.5"; 
            btn.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Error`; 
            btn.disabled = false;
            resolve(false); 
        });
    });
}
</script>