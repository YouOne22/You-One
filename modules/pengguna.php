<?php
// modules/pengguna.php
// PENGAMAN ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Pastikan session aktif untuk menampung data toast
}

$tabel_user = 'users';

// 1. PROSES HAPUS USER
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_hapus = (int)$_GET['id'];
    
    if (mysqli_query($koneksi, "DELETE FROM `$tabel_user` WHERE id = $id_hapus")) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Dihapus!', 'message' => 'Pengguna berhasil dihapus dari sistem.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal Hapus!', 'message' => mysqli_error($koneksi)];
    }
    // Redirect agar URL bersih kembali
    echo "<script>window.location.href='index.php?page=pengguna';</script>";
    exit();
}

// 2. PROSES TAMBAH USER BARU
if (isset($_POST['tambah_user'])) {
    $nama     = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password_input = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = mysqli_real_escape_string($koneksi, $_POST['role']);
    $dusun    = mysqli_real_escape_string($koneksi, $_POST['dusun']); 
    
    // Sesuaikan query dengan kolom 'dusun'
    $q_insert = "INSERT INTO `$tabel_user` (nama, username, password, role, dusun) 
                 VALUES ('$nama', '$username', '$password_input', '$role', '$dusun')";
    
    if (mysqli_query($koneksi, $q_insert)) {
        $_SESSION['toast'] = ['type' => 'success', 'title' => 'Berhasil!', 'message' => 'Pengguna baru berhasil didaftarkan!'];
        echo "<script>window.location.href='index.php?page=pengguna';</script>";
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'title' => 'Gagal Tambah!', 'message' => mysqli_error($koneksi)];
    }
}

// 3. AMBIL DATA USER
$query = "SELECT * FROM `$tabel_user` WHERE role = 'Petugas' ORDER BY id ASC";
$result_user = mysqli_query($koneksi, $query);
$total_petugas = $result_user ? mysqli_num_rows($result_user) : 0;
?>

<!-- Header Halaman & Badge Statistik -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <h3 class="text-2xl font-extrabold text-slate-100 tracking-wide flex items-center gap-2.5">
            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 text-lg">
                <i class="fa-solid fa-users-gear"></i>
            </span>
            Manajemen Pengguna
        </h3>
        <p class="text-xs text-slate-400 mt-1">
            Kelola data akun petugas lapangan dan hak akses wilayah kelola di <span class="text-amber-400 font-semibold">YOU-ONE.net</span>
        </p>
    </div>
    
    <div class="bg-slate-900/80 border border-amber-500/30 px-5 py-2.5 rounded-2xl shadow-xl backdrop-blur-md flex items-center gap-3">
        <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400 text-sm">
            <i class="fa-solid fa-user-shield text-amber-400"></i>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Total Petugas</span>
            <span class="text-base font-bold font-mono text-amber-400" id="textTotalPetugas"><?= $total_petugas; ?> Orang</span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form Tambah Akun Baru -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-6 shadow-2xl backdrop-blur-md h-fit">
        <h4 class="text-xs font-extrabold text-amber-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <i class="fa-solid fa-user-plus text-sm"></i> Tambah Akun Baru
        </h4>
        
        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Nama Lengkap</label>
                <div class="relative">
                    <input type="text" name="nama" required placeholder="Contoh: Ahmad Subagyo" 
                           class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-semibold">
                    <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Username Login</label>
                <div class="relative">
                    <input type="text" name="username" required placeholder="Contoh: ahmad_petugas" 
                           class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                    <i class="fa-solid fa-at absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="inputPassword" required placeholder="••••••••" 
                           class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-9 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                    <i class="fa-solid fa-key absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    <button type="button" onclick="togglePassVisibility()" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 hover:text-amber-400 transition focus:outline-none">
                        <i class="fa-solid fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Role / Hak Akses</label>
                <div class="relative">
                    <select name="role" required class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer">
                        <option value="Admin">Admin</option>
                        <option value="Petugas" selected>Petugas</option>
                    </select>
                    <i class="fa-solid fa-id-badge absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 font-semibold mb-1.5">Dusun Kelola / Wilayah</label>
                <div class="relative">
                    <select name="dusun" required class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-8 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner appearance-none cursor-pointer">
                        <option value="">-- Pilih Dusun Kelola --</option>
                        <option value="Administrator">Administrator</option>
                        <option value="Ngoho">Ngoho</option>
                        <option value="Kemitir">Kemitir</option>
                        <option value="Mbalong">Mbalong</option>
                    </select>
                    <i class="fa-solid fa-location-dot absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                    <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none"></i>
                </div>
            </div>

            <button type="submit" name="tambah_user" class="w-full mt-2 py-3 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-lg shadow-orange-950/40 flex items-center justify-center gap-2 active:scale-95 cursor-pointer">
                <i class="fa-solid fa-user-check text-sm"></i> Daftarkan Pengguna
            </button>
        </form>
    </div>

    <!-- Tabel Daftar Pengguna (Petugas) -->
    <div class="lg:col-span-2 space-y-4">
        <!-- Quick Search Bar -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-3 shadow-xl backdrop-blur-md flex items-center justify-between gap-4">
            <div class="relative w-full">
                <input type="text" id="searchUserInput" onkeyup="filterUserTable()" placeholder="Cari nama atau username petugas..." class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
            </div>
        </div>

        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs text-slate-300" id="tabelPengguna">
                    <thead class="bg-slate-950/80 text-slate-400 uppercase font-semibold tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="p-4 w-12 text-center">No</th>
                            <th class="p-4">Nama Lengkap</th>
                            <th class="p-4">Username</th>
                            <th class="p-4">Role</th>
                            <th class="p-4">Dusun Kelola</th>
                            <th class="p-4 text-center w-20">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php 
                        $no = 1;
                        if($result_user && mysqli_num_rows($result_user) > 0) :
                            while ($row = mysqli_fetch_assoc($result_user)) :
                        ?>
                            <tr class="hover:bg-slate-800/40 transition-colors baris-user">
                                <td class="p-4 text-center font-mono text-xs text-slate-500"><?= $no++; ?></td>
                                <td class="p-4 font-bold text-slate-100">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-circle-user text-slate-500 text-sm"></i>
                                        <span><?= htmlspecialchars($row['nama']); ?></span>
                                    </div>
                                </td>
                                <td class="p-4 text-amber-400 font-mono font-semibold">
                                    @<?= htmlspecialchars($row['username']); ?>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-bold tracking-wide uppercase bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        <i class="fa-solid fa-shield-cat text-[9px]"></i>
                                        <?= htmlspecialchars($row['role']); ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700/60">
                                        <i class="fa-solid fa-map-pin text-[9px] text-orange-400"></i>
                                        <?= htmlspecialchars($row['dusun']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <button onclick="pemicuHapusUser(<?= $row['id']; ?>, '<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>')" 
                                            class="p-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-xl transition border border-rose-500/30 active:scale-95 shadow-sm" 
                                            title="Hapus Pengguna">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else : 
                        ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500">
                                    <i class="fa-solid fa-users-slash text-3xl mb-2 block text-slate-700"></i>
                                    Tidak ada data pengguna khusus petugas saat ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Toast Alert Session Handler -->
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
    unset($_SESSION['toast']); // Bersihkan flash session
endif; 
?>

<script>
// Toggle Password Visibility
function togglePassVisibility() {
    const input = document.getElementById('inputPassword');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Quick Search Table Filter
function filterUserTable() {
    let filter = document.getElementById("searchUserInput").value.toLowerCase();
    let tr = document.getElementById("tabelPengguna").getElementsByClassName("baris-user");
    let countActive = 0;
    for (let i = 0; i < tr.length; i++) {
        let tdNama = tr[i].getElementsByTagName("td")[1];
        let tdUser = tr[i].getElementsByTagName("td")[2];
        if (tdNama || tdUser) {
            let namaText = tdNama ? (tdNama.textContent || tdNama.innerText) : "";
            let userText = tdUser ? (tdUser.textContent || tdUser.innerText) : "";
            let matches = (namaText.toLowerCase().indexOf(filter) > -1) || (userText.toLowerCase().indexOf(filter) > -1);
            tr[i].style.display = matches ? "" : "none";
            if(matches) countActive++;
        }
    }
    document.getElementById("textTotalPetugas").innerText = countActive + " Orang";
}

// Trigger Hapus Modal SweetAlert2
function pemicuHapusUser(id, namaUser) {
    Swal.fire({
        title: 'Hapus Pengguna?',
        html: `Apakah Anda yakin ingin menghapus akun milik <b class="text-amber-400">${namaUser}</b>?<br><span class="text-rose-400/90 text-xs block mt-2">Akses login petugas tersebut akan langsung dicabut secara permanen.</span>`,
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
            window.location.href = "index.php?page=pengguna&action=delete&id=" + id;
        }
    });
}
</script>