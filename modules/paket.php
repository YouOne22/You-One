<?php
// Pastikan file dimulai dengan ini agar tidak ada error tampilan
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- PROSES 1: TAMBAH PAKET BARU ---
if (isset($_POST['tambah_paket'])) {
    $nama_paket    = mysqli_real_escape_string($koneksi, $_POST['nama_paket']);
    $kecepatan     = mysqli_real_escape_string($koneksi, $_POST['kecepatan']);
    $tarif_bulanan = intval($_POST['tarif_bulanan']);
    $deskripsi     = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);

    $query_add = "INSERT INTO paket_internet (nama_paket, kecepatan, tarif_bulanan, deskripsi) 
                  VALUES ('$nama_paket', '$kecepatan', '$tarif_bulanan', '$deskripsi')";
    
    if (mysqli_query($koneksi, $query_add)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => 'Paket internet baru berhasil ditambahkan!'];
        echo "<script>window.location='index.php?page=paket';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal Tambah!', 'message' => mysqli_error($koneksi)];
    }
}

// --- PROSES 2: EDIT / UPDATE PAKET ---
if (isset($_POST['edit_paket'])) {
    $id            = intval($_POST['id']); 
    $nama_paket    = mysqli_real_escape_string($koneksi, $_POST['nama_paket']);
    $kecepatan     = mysqli_real_escape_string($koneksi, $_POST['kecepatan']);
    $tarif_bulanan = intval($_POST['tarif_bulanan']);
    $deskripsi     = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);

    $query_edit = "UPDATE paket_internet SET 
                   nama_paket = '$nama_paket', 
                   kecepatan = '$kecepatan', 
                   tarif_bulanan = '$tarif_bulanan', 
                   deskripsi = '$deskripsi' 
                   WHERE id = $id";
    
    if (mysqli_query($koneksi, $query_edit)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Diperbarui!', 'message' => 'Data paket berhasil diperbarui!'];
        echo "<script>window.location='index.php?page=paket';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal Update!', 'message' => mysqli_error($koneksi)];
    }
}

// --- PROSES 3: HAPUS PAKET ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_hapus = intval($_GET['id']);
    
    $query_del = "DELETE FROM paket_internet WHERE id = $id_hapus";
    if (mysqli_query($koneksi, $query_del)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Dihapus!', 'message' => 'Paket internet berhasil dihapus dari sistem.'];
        echo "<script>window.location='index.php?page=paket';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal Hapus!', 'message' => mysqli_error($koneksi)];
    }
}

// --- PROSES 4: AMBIL DATA DARI DATABASE ---
$query_view = "SELECT * FROM paket_internet ORDER BY id DESC";
$result_view = mysqli_query($koneksi, $query_view);
$total_paket = mysqli_num_rows($result_view);
?>

<!-- Header Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-slate-100 tracking-wide flex items-center gap-2">
            <i class="fa-solid fa-cubes text-amber-500"></i> Paket Internet
        </h3>
        <p class="text-xs text-slate-400 mt-1">Kelola daftar layanan bandwidth dan skema tarif jaringan YOU-ONE.net</p>
    </div>
    <button onclick="toggleModal('modal-paket')" class="self-start sm:self-auto bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-bold text-xs px-4 py-2.5 rounded-xl transition-all duration-200 shadow-lg shadow-orange-950/40 flex items-center gap-2 active:scale-95">
        <i class="fa-solid fa-plus text-sm"></i> Tambah Paket Baru
    </button>
</div>

<!-- Kartu Ringkasan (Stat Cards) -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 flex items-center justify-between shadow-xl backdrop-blur-md">
        <div>
            <p class="text-xs font-medium text-slate-400">Total Paket Aktif</p>
            <h4 class="text-2xl font-bold text-slate-100 mt-1"><?= $total_paket; ?> <span class="text-xs font-normal text-slate-400">Layanan</span></h4>
        </div>
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-lg">
            <i class="fa-solid fa-box-archive"></i>
        </div>
    </div>
    
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 flex items-center justify-between shadow-xl backdrop-blur-md">
        <div>
            <p class="text-xs font-medium text-slate-400">Status Server</p>
            <h4 class="text-sm font-bold text-emerald-400 mt-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Normal / Online
            </h4>
        </div>
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg">
            <i class="fa-solid fa-server"></i>
        </div>
    </div>

    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 flex items-center justify-between shadow-xl backdrop-blur-md">
        <div>
            <p class="text-xs font-medium text-slate-400">Jaringan Utama</p>
            <h4 class="text-sm font-bold text-cyan-400 mt-2">YOU-ONE FiberNet</h4>
        </div>
        <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-lg">
            <i class="fa-solid fa-wifi"></i>
        </div>
    </div>
</div>

<!-- Toolbar Pencarian -->
<div class="flex flex-col sm:flex-row justify-between items-center gap-3 mb-4">
    <div class="relative w-full sm:w-80">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
        <input type="text" id="searchPaket" onkeyup="cariPaket()" placeholder="Cari nama paket atau kecepatan..." 
            class="w-full bg-slate-900/90 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/60 transition">
    </div>
    <div class="text-xs text-slate-400 self-end sm:self-auto">
        Menampilkan <span class="text-slate-200 font-semibold"><?= $total_paket; ?></span> opsi paket
    </div>
</div>

<!-- Tabel Data Paket Modern -->
<div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm text-slate-300" id="tabelPaket">
            <thead class="bg-slate-950/90 text-slate-400 text-xs uppercase font-semibold tracking-wider border-b border-slate-800">
                <tr>
                    <th class="p-4 text-center w-14">No</th>
                    <th class="p-4">Nama Paket</th>
                    <th class="p-4">Kecepatan</th>
                    <th class="p-4">Tarif Bulanan</th>
                    <th class="p-4">Deskripsi</th>
                    <th class="p-4 text-center w-28">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
               <?php 
               $no = 1;
               if ($total_paket > 0) :
                   while ($row = mysqli_fetch_assoc($result_view)) : 
               ?>
                   <tr class="hover:bg-slate-800/40 transition-colors">
                       <td class="p-4 text-center text-slate-500 font-mono text-xs"><?= $no++; ?></td>
                       <td class="p-4">
                           <div class="flex items-center gap-2.5">
                               <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-xs shrink-0">
                                   <i class="fa-solid fa-wifi"></i>
                               </div>
                               <span class="font-bold text-slate-100 tracking-wide"><?= htmlspecialchars($row['nama_paket']); ?></span>
                           </div>
                       </td>
                       <td class="p-4">
                           <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                               <i class="fa-solid fa-gauge-high text-[10px]"></i>
                               <?= htmlspecialchars($row['kecepatan']); ?>
                           </span>
                       </td>
                       <td class="p-4">
                           <span class="inline-block px-3 py-1 rounded-lg text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                               Rp <?= number_format($row['tarif_bulanan'], 0, ',', '.'); ?>
                           </span>
                       </td>
                       <td class="p-4 text-slate-400 text-xs max-w-xs truncate"><?= htmlspecialchars($row['deskripsi']); ?></td>
                       
                       <td class="p-4 text-center">
                           <div class="flex justify-center gap-2">
                               <button type="button"
                                   data-id="<?= $row['id']; ?>"
                                   data-nama="<?= htmlspecialchars($row['nama_paket'], ENT_QUOTES); ?>"
                                   data-kecepatan="<?= htmlspecialchars($row['kecepatan'], ENT_QUOTES); ?>"
                                   data-tarif="<?= $row['tarif_bulanan']; ?>"
                                   data-deskripsi="<?= htmlspecialchars($row['deskripsi'], ENT_QUOTES); ?>"
                                   onclick="openEditModal(this)" 
                                   class="p-2 bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 rounded-xl transition border border-blue-500/20 active:scale-90" title="Ubah Data">
                                   <i class="fa-solid fa-pen-to-square"></i>
                               </button>
                               
                               <button type="button" 
                                   onclick="pemicuHapusPaket(<?= $row['id']; ?>, '<?= htmlspecialchars($row['nama_paket'], ENT_QUOTES); ?>')" 
                                   class="p-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-xl transition border border-red-500/20 active:scale-90" title="Hapus">
                                   <i class="fa-solid fa-trash-can"></i>
                               </button>
                           </div>
                       </td>
                   </tr>
               <?php 
                   endwhile; 
               else : 
               ?>
                   <tr>
                       <td colspan="6" class="p-8 text-center text-slate-500">
                           <i class="fa-solid fa-box-open text-2xl mb-2 block text-slate-600"></i>
                           Belum ada data paket internet.
                       </td>
                   </tr>
               <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: TAMBAH PAKET -->
<div id="modal-paket" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-5 border-b border-slate-800 flex justify-between items-center bg-slate-950/40">
            <h4 class="font-bold text-slate-100 text-sm flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-amber-500"></i> Buat Paket Internet Baru
            </h4>
            <button type="button" onclick="toggleModal('modal-paket')" class="text-slate-500 hover:text-slate-300 transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Nama Paket</label>
                <div class="relative">
                    <i class="fa-solid fa-tag absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" name="nama_paket" required placeholder="Contoh: Business M, Reguler"
                        class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kecepatan (Bandwidth)</label>
                <div class="relative">
                    <i class="fa-solid fa-gauge-high absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" name="kecepatan" required placeholder="Contoh: 10 Mbps (Mbalong), 5Mbps"
                        class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Tarif Bulanan (Rp)</label>
                <div class="relative">
                    <i class="fa-solid fa-money-bill-wave absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="number" name="tarif_bulanan" required placeholder="Contoh: 150000"
                        class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Deskripsi Singkat</label>
                <textarea name="deskripsi" rows="3" placeholder="Contoh: Khusus wilayah Mbalong..."
                    class="w-full bg-slate-950/60 border border-slate-800 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition"></textarea>
            </div>
            <div class="pt-2 flex justify-end gap-3">
                <button type="button" onclick="toggleModal('modal-paket')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">Batal</button>
                <button type="submit" name="tambah_paket" class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-bold rounded-xl text-xs transition shadow-lg shadow-orange-950/30">Simpan Paket</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT PAKET -->
<div id="modal-edit-paket" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-5 border-b border-slate-800 flex justify-between items-center bg-slate-950/40">
            <h4 class="font-bold text-slate-100 text-sm flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-amber-500"></i> Ubah Data Paket Internet
            </h4>
            <button type="button" onclick="toggleModal('modal-edit-paket')" class="text-slate-500 hover:text-slate-300 transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="edit-id">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Nama Paket</label>
                <div class="relative">
                    <i class="fa-solid fa-tag absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" name="nama_paket" id="edit-nama" required
                        class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kecepatan (Bandwidth)</label>
                <div class="relative">
                    <i class="fa-solid fa-gauge-high absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" name="kecepatan" id="edit-kecepatan" required
                        class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Tarif Bulanan (Rp)</label>
                <div class="relative">
                    <i class="fa-solid fa-money-bill-wave absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="number" name="tarif_bulanan" id="edit-tarif" required
                        class="w-full bg-slate-950/60 border border-slate-800 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Deskripsi Singkat</label>
                <textarea name="deskripsi" id="edit-deskripsi" rows="3"
                    class="w-full bg-slate-950/60 border border-slate-800 rounded-xl p-3 text-sm focus:outline-none focus:border-amber-500 text-slate-200 transition"></textarea>
            </div>
            <div class="pt-2 flex justify-end gap-3">
                <button type="button" onclick="toggleModal('modal-edit-paket')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">Batal</button>
                <button type="submit" name="edit_paket" class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-bold rounded-xl text-xs transition shadow-lg shadow-orange-950/30">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- SweetAlert Toast -->
<?php if (isset($_SESSION['toast'])): 
    $toast_type  = $_SESSION['toast']['type'];
    $toast_title = $_SESSION['toast']['title'];
    $toast_msg   = $_SESSION['toast']['message'];
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?= $toast_type; ?>',
        title: '<?= htmlspecialchars($toast_title, ENT_QUOTES); ?>',
        text: '<?= htmlspecialchars($toast_msg, ENT_QUOTES); ?>',
        background: '#0c101a',
        color: '#f1f5f9',
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'OK',
        customClass: {
            popup: 'border border-slate-800 rounded-2xl shadow-2xl',
            title: 'font-bold tracking-wide text-xl',
            htmlContainer: 'text-sm text-slate-300 mt-2'
        }
    });
});
</script>
<?php 
    unset($_SESSION['toast']);
endif; 
?>

<!-- JavaScript Interaktif -->
<script>
function toggleModal(id) {
    const modal = document.getElementById(id);
    modal.classList.toggle('hidden');
}

function openEditModal(btn) {
    document.getElementById('edit-id').value = btn.dataset.id;
    document.getElementById('edit-nama').value = btn.dataset.nama;
    document.getElementById('edit-kecepatan').value = btn.dataset.kecepatan;
    document.getElementById('edit-tarif').value = btn.dataset.tarif;
    document.getElementById('edit-deskripsi').value = btn.dataset.deskripsi;
    toggleModal('modal-edit-paket');
}

function pemicuHapusPaket(id, namaPaket) {
    Swal.fire({
        title: 'Hapus Paket Internet?',
        html: `Apakah Anda yakin ingin menghapus paket <span class="text-amber-400 font-semibold">${namaPaket}</span>?<br><span class="text-red-400/90 text-xs block mt-2">Tindakan ini tidak dapat dibatalkan.</span>`,
        icon: 'warning',
        showCancelButton: true,
        background: '#0c101a',
        color: '#f1f5f9',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#1e293b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        customClass: {
            popup: 'border border-slate-800 rounded-2xl shadow-2xl',
            title: 'font-bold tracking-wide text-lg text-slate-100',
            htmlContainer: 'text-xs text-slate-400 leading-relaxed'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "index.php?page=paket&action=delete&id=" + id;
        }
    });
}

// Fungsi Live Search Paket
function cariPaket() {
    const input = document.getElementById('searchPaket').value.toLowerCase();
    const tr = document.querySelectorAll('#tabelPaket tbody tr');

    tr.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}
</script>