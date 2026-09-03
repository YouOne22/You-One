<?php
// modules/maintenance.php

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
        CURLOPT_TIMEOUT => 20, // Membatasi waktu tunggu maksimal 20 detik agar server tidak hang
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
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $curl_error]);
        exit;
    }
    
    $result_api = json_decode($response, true);

    // KOREKSI VALIDASI: Memastikan variasi tipe data response Fonnte dibaca dengan benar
    if (
        (isset($result_api['status']) && ($result_api['status'] === true || $result_api['status'] === 'true' || $result_api['status'] == 1)) ||
        (isset($result_api['detail']) && strpos(strtolower($result_api['detail']), 'success') !== false) ||
        isset($result_api['id'])
    ) {
        echo json_encode(['success' => true]);
    } else {
        $error_msg = $result_api['reason'] ?? ($result_api['message'] ?? 'Ditolak oleh server API Fonnte.');
        echo json_encode(['success' => false, 'message' => $error_msg]);
    }
    exit;
}

// 2. LOAD DATA NORMAL
require_once 'config/koneksi.php';

$role_user = strtoupper($_SESSION['role'] ?? '');
$dusun_pengelola = mysqli_real_escape_string($koneksi, $_SESSION['dusun_pengelola'] ?? '');
$filter_dusun = mysqli_real_escape_string($koneksi, $_GET['dusun'] ?? '');

$q_list_dusun = "SELECT DISTINCT dusun FROM pelanggan WHERE status IN ('AKTIF', 'ISOLIR') AND dusun != '' ORDER BY dusun ASC";
$res_list_dusun = mysqli_query($koneksi, $q_list_dusun);

$q_pelanggan = "SELECT id_pelanggan, nama, no_wa, dusun FROM pelanggan WHERE status IN ('AKTIF', 'ISOLIR')";

if (!empty($filter_dusun)) {
    $q_pelanggan .= " AND dusun = '$filter_dusun'";
} elseif ($role_user !== 'ADMIN' && $role_user !== 'ADMINISTRATOR') {
    $q_pelanggan .= " AND dusun = '$dusun_pengelola'";
}

$q_pelanggan .= " ORDER BY nama ASC";
$result_pelanggan = mysqli_query($koneksi, $q_pelanggan);
$total_pelanggan = mysqli_num_rows($result_pelanggan);
?>

<!-- UI Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <h3 class="text-xl font-bold text-slate-100">Info Maintenance Jaringan</h3>
        <p class="text-xs text-slate-400 mt-1">Kirim pengumuman pemeliharaan massal area ke WhatsApp pelanggan.</p>
    </div>
    <div class="bg-amber-500/10 border border-amber-500/20 px-4 py-2 rounded-xl text-right">
        <span class="text-[10px] text-amber-400 font-bold uppercase block">Penerima Area</span>
        <span class="text-base font-bold text-amber-400" id="textTotalPelanggan"><?= $total_pelanggan; ?> Orang</span>
    </div>
</div>

<!-- Textarea Form Template -->
<div class="glass-card p-5 rounded-2xl border border-slate-800/40 mb-6 bg-slate-900/50">
    <label class="block text-sm font-medium text-slate-300 mb-2.5">Isi Pesan Perbaikan/Maintenance</label>
    <textarea id="broadcastMessage" rows="12" 
              class="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 transition font-sans leading-relaxed">Pemberitahuan Jaringan You-One.net 🛠️

Halo kak *{nama}*,
informasi bahwa saat ini jaringan sedang *DOWN* karena 
        
Mohon maaf atas ketidaknyamanan ini.
Jaringan akan langsung normal kembali setelah perbaikan selesai. Terima kasih. 🙏</textarea>
</div>

<!-- Toolbar Kontrol -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 items-end">
    <div class="flex flex-col gap-1.5">
        <label class="text-xs text-slate-400 font-medium">Pilih Wilayah Dampak:</label>
        <select onchange="filterByDusun(this.value)" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-300 focus:outline-none focus:border-indigo-500 cursor-pointer">
            <option value="">🌍 Semua Wilayah (Global)</option>
            <?php while($d = mysqli_fetch_assoc($res_list_dusun)): ?>
                <option value="<?= htmlspecialchars($d['dusun']); ?>" <?= $filter_dusun === $d['dusun'] ? 'selected' : ''; ?>>📍 Dusun <?= htmlspecialchars($d['dusun']); ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div>
        <button onclick="jalankanKirimMassal()" id="btnMassal" class="w-full py-2.5 px-4 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white text-sm font-bold rounded-xl transition shadow-lg flex items-center justify-center gap-2">
            <i class="fa-solid fa-layer-group"></i> Siarkan ke Wilayah Ini
        </button>
    </div>
    <div class="flex flex-col gap-1.5">
        <label class="text-xs text-slate-400 font-medium">Pencarian Cepat:</label>
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari nama..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-300 focus:outline-none focus:border-indigo-500">
    </div>
</div>

<!-- Tabel Data -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-800/40">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300" id="tabelPengingat">
            <thead class="bg-slate-950/80 text-slate-400 text-xs uppercase border-b border-slate-900">
                <tr>
                    <th class="p-4 text-center w-16">No</th>
                    <th class="p-4">Nama Pelanggan</th>
                    <th class="p-4">Nomor WhatsApp</th>
                    <th class="p-4 text-center">Wilayah</th>
                    <th class="p-4 text-center w-40">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-900/50">
                <?php if ($total_pelanggan > 0) : 
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result_pelanggan)) : ?>
                <tr class="hover:bg-slate-900/20 transition baris-pelanggan" id="row-<?= $row['id_pelanggan']; ?>">
                    <td class="p-4 text-center text-slate-500 font-mono"><?= $no++; ?></td>
                    <td class="p-4 font-bold text-slate-200"><?= htmlspecialchars($row['nama']); ?></td>
                    <td class="p-4 text-slate-400 font-mono text-sm"><?= htmlspecialchars($row['no_wa']); ?></td>
                    <td class="p-4 text-center text-xs text-slate-400 font-semibold">Dusun <?= htmlspecialchars($row['dusun']); ?></td>
                    <td class="p-4 text-center">
                        <button onclick="kirimPesanSingle('<?= $row['id_pelanggan']; ?>')" id="btn-<?= $row['id_pelanggan']; ?>"
                                data-id="<?= $row['id_pelanggan']; ?>" data-wa="<?= $row['no_wa']; ?>" data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                class="btn-kirim-single w-full py-1.5 px-3 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 text-xs font-semibold rounded-xl transition border border-amber-500/20 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> Kirim WA
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php else : ?>
                <tr><td colspan="5" class="p-8 text-center text-slate-500">Tidak ada data pelanggan di wilayah ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Engine Container -->
<div id="customAlertModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-sm hidden">
    <div class="bg-[#131722] border border-slate-800 p-8 rounded-2xl w-full max-w-sm mx-4 text-center shadow-2xl flex flex-col items-center">
        <div id="alertIconWrapper" class="w-16 h-16 rounded-full flex items-center justify-center text-3xl mb-4"><i id="alertIcon" class="fa-solid"></i></div>
        <h4 id="alertTitle" class="text-xl font-bold text-slate-100 mb-2"></h4>
        <p id="customAlertMessage" class="text-sm text-slate-400 mb-6"></p>
        <button onclick="closeCustomAlert()" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg shadow-md">OK</button>
    </div>
</div>
<div id="customConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-sm hidden">
    <div class="bg-[#131722] border border-slate-800 p-8 rounded-2xl w-full max-w-sm mx-4 text-center shadow-2xl flex flex-col items-center">
        <div class="w-16 h-16 bg-blue-500/10 border border-blue-500/20 rounded-full flex items-center justify-center text-blue-400 text-3xl mb-4"><i class="fa-solid fa-circle-question animate-pulse"></i></div>
        <h4 class="text-xl font-bold text-slate-100 mb-2">Konfirmasi Kirim</h4>
        <p id="customConfirmMessage" class="text-sm text-slate-400 mb-6"></p>
        <div class="flex gap-3 w-full justify-center">
            <button id="btnConfirmBatal" class="px-5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold rounded-lg border border-slate-700 w-24">Batal</button>
            <button id="btnConfirmLanjut" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-lg w-24">Oke</button>
        </div>
    </div>
</div>

<script>
function filterByDusun(val) { window.location.href = 'index.php?page=maintenance&dusun=' + encodeURIComponent(val); }
function filterTable() {
    let filter = document.getElementById("searchInput").value.toLowerCase();
    let tr = document.getElementById("tabelPengingat").getElementsByClassName("baris-pelanggan");
    for (let i = 0; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName("td")[1];
        if (td) tr[i].style.display = (td.textContent || td.innerText).toLowerCase().indexOf(filter) > -1 ? "" : "none";
    }
}
function bukaCustomAlert(type, judul, pesan) {
    const modal = document.getElementById("customAlertModal"), iw = document.getElementById("alertIconWrapper"), icon = document.getElementById("alertIcon");
    iw.className = "w-16 h-16 rounded-full flex items-center justify-center text-3xl mb-4"; icon.className = "fa-solid";
    if(type === 'success') { iw.classList.add("bg-emerald-500/10", "border-emerald-500/20", "text-emerald-400"); icon.classList.add("fa-check"); }
    else if(type === 'warning') { iw.classList.add("bg-amber-500/10", "border-amber-500/20", "text-amber-400"); icon.classList.add("fa-exclamation"); }
    else { iw.classList.add("bg-rose-500/10", "border-rose-500/20", "text-rose-400"); icon.classList.add("fa-xmark"); }
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
    const msgGlobal = document.getElementById("broadcastMessage").value.trim(), btnMassal = document.getElementById("btnMassal");
    if (!msgGlobal) { bukaCustomAlert('warning', 'Perhatian', 'Isi pesan tidak boleh kosong!'); return; }
    const semuaTombol = document.querySelectorAll(".btn-kirim-single");
    if (semuaTombol.length === 0) { bukaCustomAlert('warning', 'Data Kosong', 'Tidak ada target.'); return; }
    const konfirmasi = await bukaCustomConfirm(`Kirim ke <b>${semuaTombol.length}</b> pelanggan?`);
    if (!konfirmasi) return;
    
    btnMassal.disabled = true; 
    btnMassal.className = "w-full py-2.5 px-4 bg-slate-800 text-slate-500 text-sm font-bold rounded-xl cursor-not-allowed flex items-center justify-center gap-2";
    
    for (let i = 0; i < semuaTombol.length; i++) {
        if (semuaTombol[i].getAttribute("data-status-kirim") === "success") continue;
        btnMassal.innerHTML = `<i class="fa-solid fa-spinner animate-spin"></i> Memproses ${i + 1}/${semuaTombol.length}...`;
        await eksekusiKirimAjax(semuaTombol[i].getAttribute("data-id"), msgGlobal);
        
        if (i < semuaTombol.length - 1) {
            let jedaAcak = Math.floor(Math.random() * (7000 - 4000 + 1)) + 4000;
            let tampilanDetik = (jedaAcak / 1000).toFixed(1);
            
            btnMassal.innerHTML = `<i class="fa-solid fa-clock animate-pulse"></i> Jeda ${tampilanDetik}s... [${i + 1}/${semuaTombol.length}]`;
            await new Promise(r => setTimeout(r, jedaAcak));
        }
    } // <-- KOREKSI UTAMA: Menutup blok perulangan 'for' dengan benar yang sebelumnya hilang
    
    btnMassal.disabled = false; 
    btnMassal.className = "w-full py-2.5 px-4 bg-gradient-to-r from-amber-600 to-orange-600 text-white text-sm font-bold rounded-xl flex items-center justify-center gap-2";
    btnMassal.innerHTML = `<i class="fa-solid fa-layer-group"></i> Siarkan ke Wilayah Ini`;
    bukaCustomAlert('success', 'Berhasil!', 'Seluruh pesan perbaikan berhasil disiarkan!');
}
async function kirimPesanSingle(id) {
    const msgGlobal = document.getElementById("broadcastMessage").value.trim();
    if (!msgGlobal) { bukaCustomAlert('warning', 'Perhatian', 'Isi pesan tidak boleh kosong!'); return; }
    const nama = document.getElementById("btn-" + id).getAttribute("data-nama");
    
    if(await eksekusiKirimAjax(id, msgGlobal)) {
        bukaCustomAlert('success', 'Berhasil!', `Pesan ke <b>${nama}</b> terkirim.`);
    } else {
        bukaCustomAlert('danger', 'Gagal!', `Pesan ke <b>${nama}</b> gagal terkirim.`);
    }
}
function eksekusiKirimAjax(id, msgGlobal) {
    return new Promise((resolve) => {
        const btn = document.getElementById("btn-" + id);
        let pesanPersonal = msgGlobal.replace(/{nama}/g, btn.getAttribute("data-nama"));
        btn.disabled = true; 
        btn.className = "w-full py-1.5 px-3 bg-slate-800 text-slate-500 text-xs font-semibold rounded-xl flex items-center justify-center gap-2 cursor-not-allowed"; 
        btn.innerHTML = `<i class="fa-solid fa-spinner animate-spin"></i> Proses`;
        
        const fd = new FormData(); 
        fd.append('target', btn.getAttribute("data-wa")); 
        fd.append('message', pesanPersonal);
        
        fetch('index.php?page=maintenance&action=send_broadcast', { method: 'POST', body: fd })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            if (data.success) { 
                btn.className = "w-full py-1.5 px-3 bg-green-500/10 text-green-400 text-xs font-semibold rounded-xl border border-green-500/30 flex items-center justify-center gap-2"; 
                btn.innerHTML = `<i class="fa-solid fa-circle-check"></i> Terkirim`; 
                btn.setAttribute("data-status-kirim", "success"); 
                resolve(true); 
            } else { 
                btn.className = "w-full py-1.5 px-3 bg-rose-500/10 text-rose-400 text-xs font-semibold rounded-xl border border-rose-500/30 flex items-center justify-center gap-2"; 
                btn.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Gagal`; 
                resolve(false); 
            }
        })
        .catch(() => { 
            btn.className = "w-full py-1.5 px-3 bg-rose-500/10 text-rose-400 text-xs font-semibold rounded-xl border border-rose-500/30 flex items-center justify-center gap-2"; 
            btn.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Error`; 
            resolve(false); 
        });
    });
}
</script>