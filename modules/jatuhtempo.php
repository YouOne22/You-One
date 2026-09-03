<?php
// modules/jatuhtempo.php
    
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

// QUERY UTAMA PELANGGAN
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

// Proteksi Filter Query Berdasarkan Role
if ($role_user !== 'ADMIN' && $role_user !== 'ADMINISTRATOR') {
    $q_pelanggan .= " AND p.dusun = '$dusun_pengelola'";
} else {
    if (!empty($filter_dusun)) {
        $q_pelanggan .= " AND p.dusun = '$filter_dusun'";
    }
}

$q_pelanggan .= " ORDER BY p.nama ASC";
$result_pelanggan = mysqli_query($koneksi, $q_pelanggan);
$total_pelanggan = mysqli_num_rows($result_pelanggan);
?>

<!-- Header Halaman & Statistik Ringkas -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <h3 class="text-2xl font-extrabold text-slate-100 tracking-wide flex items-center gap-2.5">
            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 text-lg">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </span>
            Pemberitahuan Jatuh Tempo
        </h3>
        <p class="text-xs text-slate-400 mt-1">
            Kirim rincian tagihan bulanan pelanggan via WhatsApp secara terukur & otomatis untuk <span class="text-amber-400 font-semibold">YOU-ONE.net</span>
        </p>
    </div>
    
    <div class="bg-slate-900/80 border border-amber-500/30 px-5 py-2.5 rounded-2xl shadow-xl backdrop-blur-md flex items-center gap-3">
        <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400 text-sm">
            <i class="fa-solid fa-users text-amber-400"></i>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Total Target</span>
            <span class="text-base font-bold font-mono text-amber-400" id="textTotalPelanggan"><?= $total_pelanggan; ?> Orang</span>
        </div>
    </div>
</div>

<!-- Control Bar: Filter, Tombol Massal, & Cari -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 items-end">
    <!-- Filter Wilayah -->
    <div class="flex flex-col gap-1.5">
        <label class="text-xs text-slate-400 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-location-dot text-amber-500"></i> Filter Wilayah:
        </label>
        <div class="relative">
            <select onchange="filterByDusun(this.value)" class="w-full bg-slate-900/80 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition cursor-pointer shadow-lg backdrop-blur-md appearance-none pr-8">
                <?php if ($role_user === 'ADMIN' || $role_user === 'ADMINISTRATOR') : ?>
                    <option value="">🌍 Semua Wilayah</option>
                <?php endif; ?>
                <?php while($d = mysqli_fetch_assoc($res_list_dusun)): ?>
                    <option value="<?= htmlspecialchars($d['dusun']); ?>" <?= $filter_dusun === $d['dusun'] ? 'selected' : ''; ?>>📍 Dusun <?= htmlspecialchars($d['dusun']); ?></option>
                <?php endwhile; ?>
            </select>
            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
        </div>
    </div>

    <!-- Tombol Kirim Massal -->
    <div>
        <button onclick="jalankanKirimMassal()" id="btnMassal" class="w-full py-2.5 px-4 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-lg shadow-orange-950/40 flex items-center justify-center gap-2 active:scale-95">
            <i class="fa-solid fa-paper-plane"></i> Kirim Massal Area Ini
        </button>
    </div>

    <!-- Search Box -->
    <div class="flex flex-col gap-1.5">
        <label class="text-xs text-slate-400 font-semibold flex items-center gap-1.5">
            <i class="fa-solid fa-magnifying-glass text-amber-500"></i> Pencarian Cepat:
        </label>
        <div class="relative">
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari nama pelanggan..." class="w-full bg-slate-900/80 border border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-lg backdrop-blur-md">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
        </div>
    </div>
</div>

<!-- Tabel Data Pelanggan -->
<div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs text-slate-300" id="tabelPengingat">
            <thead class="bg-slate-950/80 text-slate-400 uppercase font-semibold tracking-wider border-b border-slate-800">
                <tr>
                    <th class="p-4 text-center w-14">No</th>
                    <th class="p-4">Nama Pelanggan</th>
                    <th class="p-4">Nomor WhatsApp</th>
                    <th class="p-4 text-center">Wilayah</th>
                    <th class="p-4 text-center w-40">Aksi Broadcast</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php if ($total_pelanggan > 0) : 
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result_pelanggan)) : 
                        if (empty($row['no_invoice'])) {
                            $total_t = 0; $tunggakan_awal = 0; $sisa_tagihan = 0; $is_lunas = true; $terbayar_bulan_ini = 0; $jatuh_tempo_tampil = '-';
                        } else {
                            $terbayar_bulan_ini = floatval($row['terbayar_bulan_ini']);
                            $tunggakan_awal = max(0, floatval($row['tunggakan_awal']));
                            $total_t = isset($row['total_tagihan']) ? floatval($row['total_tagihan']) : 0;

                            $sisa_tagihan = max(0, ($total_t + $tunggakan_awal) - $terbayar_bulan_ini);
                            $is_lunas = ($sisa_tagihan <= 0);

                            $tahun_bulan = substr($row['no_invoice'], 4, 6); 
                            $tahun = substr($tahun_bulan, 0, 4); $bulan = substr($tahun_bulan, 4, 2);

                            $hari_jatuh_tempo = (strpos(strtolower($row['alamat']??''), 'mbalong') !== false || strpos(strtolower($row['wilayah']??''), 'mbalong') !== false) ? '25' : '10';
                            $jatuh_tempo_tampil = $hari_jatuh_tempo . '/' . $bulan . '/' . $tahun;
                        }
                        $status_bayar = $is_lunas ? 'Lunas / Aman' : ($terbayar_bulan_ini > 0 ? 'Dicicil (Belum Lunas)' : 'Belum Terbayar');
                ?>
                <tr class="hover:bg-slate-800/40 transition-colors baris-pelanggan" id="row-<?= $row['id_pelanggan']; ?>">
                    <td class="p-4 text-center text-slate-500 font-mono"><?= $no++; ?></td>
                    <td class="p-4 font-bold text-slate-100 flex items-center gap-2">
                        <i class="fa-solid fa-user-circle text-slate-500 text-sm"></i>
                        <?= htmlspecialchars($row['nama']); ?>
                    </td>
                    <td class="p-4 text-amber-400 font-mono font-semibold tracking-wider">
                        <i class="fa-brands fa-whatsapp mr-1 text-emerald-400"></i>
                        <?= htmlspecialchars($row['no_wa']); ?>
                    </td>
                    <td class="p-4 text-center">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-slate-800/80 text-slate-300 border border-slate-700/60">
                            <i class="fa-solid fa-map-pin text-[9px] text-amber-400"></i>
                            Dusun <?= htmlspecialchars($row['dusun']); ?>
                        </span>
                    </td>
                    <td class="p-4 text-center">
                        <button onclick="kirimPesanSingle('<?= $row['id_pelanggan']; ?>')" id="btn-<?= $row['id_pelanggan']; ?>"
                                data-id="<?= $row['id_pelanggan']; ?>" 
                                data-wa="<?= $row['no_wa']; ?>" 
                                data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                data-paket="<?= htmlspecialchars($row['nama_paket'] ?? 'Reguler', ENT_QUOTES); ?>" 
                                data-jatuhtempo="<?= $jatuh_tempo_tampil; ?>"
                                data-tagihan="Rp <?= number_format($total_t, 0, ',', '.'); ?>,00" 
                                data-tunggakan="Rp <?= number_format(max(0, $tunggakan_awal - $terbayar_bulan_ini), 0, ',', '.'); ?>"
                                data-total="Rp. <?= number_format($sisa_tagihan, 0, ',', '.'); ?>,00" 
                                data-status="<?= $status_bayar; ?>" 
                                data-status-kirim="ready"
                                class="btn-kirim-single w-full py-1.5 px-3 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 hover:text-amber-300 text-xs font-bold rounded-xl transition border border-amber-500/30 flex items-center justify-center gap-1.5 shadow-sm active:scale-95">
                            <i class="fa-solid fa-paper-plane text-[11px]"></i> Kirim WA
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else : ?>
                <tr>
                    <td colspan="5" class="p-8 text-center text-slate-500">
                        <i class="fa-solid fa-folder-open text-3xl mb-2 block text-slate-600"></i>
                        Tidak ada data pelanggan di wilayah ini.
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

<!-- Modal Dialog Confirm Custom -->
<div id="customConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden transition-all duration-300">
    <div class="bg-slate-900 border border-slate-800/80 p-6 rounded-2xl w-full max-w-sm mx-4 text-center shadow-2xl flex flex-col items-center">
        <div class="w-14 h-14 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex items-center justify-center text-amber-400 text-2xl mb-3 shadow-inner"><i class="fa-solid fa-circle-question animate-pulse"></i></div>
        <h4 class="text-lg font-bold text-slate-100 mb-1">Konfirmasi Penyiaran</h4>
        <p id="customConfirmMessage" class="text-xs text-slate-400 mb-5 leading-relaxed"></p>
        <div class="flex gap-3 w-full">
            <button id="btnConfirmBatal" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl border border-slate-700 transition">Batal</button>
            <button id="btnConfirmLanjut" class="flex-1 py-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-bold text-xs rounded-xl shadow-lg shadow-orange-950/40 transition active:scale-95">Lanjutkan</button>
        </div>
    </div>
</div>

<script>
// TEMPLATE PESAN WA JATUH TEMPO KUNCI (STATIC)
const broadcastMessageTemplate = `_Kepada Yth._
_Bapak/Ibu Pelanggan *You-One.net*_

Dengan hormat,
Kami informasikan bahwa tagihan layanan internet bulan ini telah terbit dengan rincian sebagai berikut:

🙎‍♂️ Nama Pelanggan : {nama}
📦 Paket Langganan : {paket}
⏳ Jatuh Tempo Pembayaran : {jatuh_tempo}
🧾 Jumlah Tagihan : {tagihan}
📝 Tagihan Sebelumnya : {tunggakan}
💸 Total pembayaran : *{total_bayar}*
🖊️Status Pembayaran : {status_bayar}

Mohon untuk melakukan pembayaran sebelum tanggal jatuh tempo agar layanan tetap aktif.

🏛️ Apabila pembayaran via transfer bisa melalui : 
BRI - 6100 01 026499 53 4
BPD (Bank Jateng) - 2 159 08207 8
Dana - 0822 4316 7575
Atas Nama = Nur Rochim

_*Notes : Abaikan pesan ini apabila sudah melakukan perpanjangan.*_

Atas perhatian dan kerja samanya, kami ucapkan terima kasih.
Salam,

Info Layanan
📞 Wa  : wa.me/+6282243167575
⌲ Tele : t.me/Nur_Rochiim`;

function filterByDusun(val) { window.location.href = 'index.php?page=jatuhtempo&dusun=' + encodeURIComponent(val); }

function filterTable() {
    let filter = document.getElementById("searchInput").value.toLowerCase();
    let tr = document.getElementById("tabelPengingat").getElementsByClassName("baris-pelanggan");
    let countActive = 0;
    for (let i = 0; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName("td")[1];
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

function bukaCustomConfirm(pesan) {
    return new Promise((resolve) => {
        const modal = document.getElementById("customConfirmModal");
        document.getElementById("customConfirmMessage").innerHTML = pesan; modal.classList.remove("hidden");
        document.getElementById("btnConfirmLanjut").onclick = function() { modal.classList.add("hidden"); resolve(true); };
        document.getElementById("btnConfirmBatal").onclick = function() { modal.classList.add("hidden"); resolve(false); };
    });
}

async function jalankanKirimMassal() {
    const btnMassal = document.getElementById("btnMassal");
    const semuaTombol = [];
    const daftarTombol = document.querySelectorAll(".btn-kirim-single");
    
    daftarTombol.forEach(btn => {
        const idPel = btn.getAttribute("data-id");
        const baris = document.getElementById("row-" + idPel);
        if (baris && baris.style.display !== "none" && btn.getAttribute("data-status-kirim") !== "success") {
            semuaTombol.push(btn);
        }
    });

    if (semuaTombol.length === 0) { bukaCustomAlert('warning', 'Data Kosong', 'Tidak ada target aktif yang tersisa untuk dikirim.'); return; }
    
    const konfirmasi = await bukaCustomConfirm(`Kirim ke <b>${semuaTombol.length}</b> pelanggan area terpilih?`);
    if (!konfirmasi) return;
    
    btnMassal.disabled = true; 
    btnMassal.className = "w-full py-2.5 px-4 bg-slate-800 text-slate-500 text-xs font-bold rounded-xl cursor-not-allowed flex items-center justify-center gap-2";
    
    let sukses = 0;
    let gagal = 0;

    for (let i = 0; i < semuaTombol.length; i++) {
        btnMassal.innerHTML = `<i class="fa-solid fa-spinner animate-spin"></i> Memproses ${i + 1}/${semuaTombol.length}...`;
        const status = await eksekusiKirimAjax(semuaTombol[i].getAttribute("data-id"));
        if(status) sukses++; else gagal++;

        if (i < semuaTombol.length - 1) {
            let jedaAcak = Math.floor(Math.random() * (6000 - 4000 + 1)) + 4000;
            let detik = (jedaAcak / 1000).toFixed(1);
            btnMassal.innerHTML = `<i class="fa-solid fa-clock animate-pulse"></i> Jeda ${detik}s... [${i + 1}/${semuaTombol.length}]`;
            await new Promise(r => setTimeout(r, jedaAcak));
        }
    }
    
    btnMassal.disabled = false; 
    btnMassal.className = "w-full py-2.5 px-4 bg-gradient-to-r from-amber-500 to-orange-600 text-slate-950 font-extrabold text-xs rounded-xl flex items-center justify-center gap-2 shadow-lg shadow-orange-950/40";
    btnMassal.innerHTML = `<i class="fa-solid fa-paper-plane"></i> Kirim Massal Area Ini`;
    
    bukaCustomAlert('success', 'Selesai!', `Proses penyiaran massal selesai.<br>Berhasil: <b class="text-emerald-400">${sukses}</b><br>Gagal: <b class="text-rose-400">${gagal}</b>`);
}

async function kirimPesanSingle(id) {
    const nama = document.getElementById("btn-" + id).getAttribute("data-nama");
    if(await eksekusiKirimAjax(id)) {
        bukaCustomAlert('success', 'Berhasil!', `Pesan jatuh tempo ke <b>${nama}</b> terkirim.`);
    } else {
        bukaCustomAlert('danger', 'Gagal!', `Pesan ke <b>${nama}</b> gagal dikirim.`);
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
        
        fetch('modules/jatuhtempo.php?action=send_broadcast', { method: 'POST', body: fd })
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