<?php
// modules/pengaturan.php
require_once 'config/koneksi.php';

// 1. AUTO-MIGRATION: Cek & Buat tabel pengaturan jika belum ada di database hosting
$cek_tabel = mysqli_query($koneksi, "SHOW TABLES LIKE 'pengaturan'");
if (mysqli_num_rows($cek_tabel) == 0) {
    $query_buat_tabel = "CREATE TABLE pengaturan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_aplikasi VARCHAR(100) NOT NULL DEFAULT 'You-One.net',
        tagline VARCHAR(150) DEFAULT 'Internet Desa & RT/RW Net Solution',
        alamat TEXT,
        no_wa_cs VARCHAR(20) DEFAULT '',
        template_pengingat TEXT
    )";
    mysqli_query($koneksi, $query_buat_tabel);
    
    // Isi data default untuk pertama kali
    $template_default = "Selamat pagi/siang Kak *[NAMA]*,\n\nMengingatkan kembali mengenai iuran bulanan internet *[BISNIS]* untuk periode *[BULAN]*.\n\nJumlah tagihan yang harus dibayarkan sebesar: *[TOTAL]*.\n\nPembayaran bisa dititipkan langsung secara cash/manual ya kak. Terima kasih banyak atas kerjasamanya! 🙏✨";
    $query_default = "INSERT INTO pengaturan (nama_aplikasi, tagline, alamat, no_wa_cs, template_pengingat) 
                      VALUES ('You-One.net', 'Internet Desa & RT/RW Net Solution', 'Kemitir', '082243167575', '$template_default')";
    mysqli_query($koneksi, $query_default);
}

// Pastikan baris data ID 1 tersedia
$cek_row = mysqli_query($koneksi, "SELECT id FROM pengaturan WHERE id = 1");
if (mysqli_num_rows($cek_row) == 0) {
    $template_default = "Selamat pagi/siang Kak *[NAMA]*,\n\nMengingatkan kembali mengenai iuran bulanan internet *[BISNIS]* untuk periode *[BULAN]*.\n\nJumlah tagihan yang harus dibayarkan sebesar: *[TOTAL]*.\n\nPembayaran bisa dititipkan langsung secara cash/manual ya kak. Terima kasih banyak atas kerjasamanya! 🙏✨";
    mysqli_query($koneksi, "INSERT INTO pengaturan (id, nama_aplikasi, tagline, alamat, no_wa_cs, template_pengingat) VALUES (1, 'You-One.net', 'Internet Desa & RT/RW Net Solution', 'Kemitir', '082243167575', '$template_default')");
}

// 2. PROSES UPDATE DATA (Jika tombol simpan ditekan)
$notif = '';
if (isset($_POST['simpan_pengaturan'])) {
    $nama_aplikasi = mysqli_real_escape_string($koneksi, $_POST['nama_aplikasi']);
    $tagline = mysqli_real_escape_string($koneksi, $_POST['tagline']);
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);
    $no_wa_cs = mysqli_real_escape_string($koneksi, $_POST['no_wa_cs']);
    $template_pengingat = mysqli_real_escape_string($koneksi, $_POST['template_pengingat']);

    $q_update = "UPDATE pengaturan SET 
                 nama_aplikasi = '$nama_aplikasi', 
                 tagline = '$tagline', 
                 alamat = '$alamat', 
                 no_wa_cs = '$no_wa_cs', 
                 template_pengingat = '$template_pengingat' 
                 WHERE id = 1";
                 
    if (mysqli_query($koneksi, $q_update)) {
        $notif = '<div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold flex items-center gap-3 shadow-lg backdrop-blur-md">
                    <div class="p-2 bg-emerald-500/20 rounded-xl"><i class="fa-solid fa-circle-check text-emerald-400 text-base"></i></div>
                    <div>
                        <p class="font-bold text-slate-100 text-sm">Berhasil Disimpan!</p>
                        <p class="text-[11px] text-emerald-400/80 mt-0.5">Konfigurasi sistem dan template pesan telah berhasil diperbarui.</p>
                    </div>
                  </div>';
    } else {
        $notif = '<div class="mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold flex items-center gap-3 shadow-lg backdrop-blur-md">
                    <div class="p-2 bg-rose-500/20 rounded-xl"><i class="fa-solid fa-circle-xmark text-rose-400 text-base"></i></div>
                    <div>
                        <p class="font-bold text-slate-100 text-sm">Gagal Menyimpan!</p>
                        <p class="text-[11px] text-rose-400/80 mt-0.5">' . mysqli_error($koneksi) . '</p>
                    </div>
                  </div>';
    }
}

// 3. AMBIL DATA SETTING TERBARU
$q_ambil = mysqli_query($koneksi, "SELECT * FROM pengaturan WHERE id = 1");
$setting = mysqli_fetch_assoc($q_ambil);
?>

<!-- Header Halaman & Status -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <h3 class="text-2xl font-extrabold text-slate-100 tracking-wide flex items-center gap-2.5">
            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 text-lg">
                <i class="fa-solid fa-sliders"></i>
            </span>
            Pengaturan Sistem
        </h3>
        <p class="text-xs text-slate-400 mt-1">
            Kelola identitas aplikasi, profil jaringan internet, dan template pesan penagihan untuk <span class="text-amber-400 font-semibold">YOU-ONE.net</span>
        </p>
    </div>
    
    <div class="bg-slate-900/80 border border-amber-500/30 px-5 py-2.5 rounded-2xl shadow-xl backdrop-blur-md flex items-center gap-3">
        <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400 text-sm">
            <i class="fa-solid fa-shield-halved text-amber-400"></i>
        </div>
        <div>
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Status Sistem</span>
            <span class="text-xs font-bold font-mono text-emerald-400 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Aktif & Terkonfigurasi
            </span>
        </div>
    </div>
</div>

<?= $notif; ?>

<form action="" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Card 1: Profil & Identitas -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-6 shadow-2xl backdrop-blur-md">
            <h4 class="text-xs font-extrabold text-amber-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fa-solid fa-store text-sm"></i> Profil & Identitas Jaringan
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Nama Aplikasi / Brand</label>
                    <div class="relative">
                        <input type="text" name="nama_aplikasi" id="nama_aplikasi" required value="<?= htmlspecialchars($setting['nama_aplikasi']); ?>" oninput="updatePreview()"
                               class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-bold">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Tagline Penjelas</label>
                    <input type="text" name="tagline" value="<?= htmlspecialchars($setting['tagline']); ?>"
                           class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">No. WhatsApp Admin / CS</label>
                    <div class="relative">
                        <input type="text" name="no_wa_cs" value="<?= htmlspecialchars($setting['no_wa_cs']); ?>" placeholder="Contoh: 082243167575"
                               class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner font-mono">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 font-semibold mb-1.5">Alamat Operasional / Wilayah</label>
                    <input type="text" name="alamat" value="<?= htmlspecialchars($setting['alamat']); ?>"
                           class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-200 focus:outline-none focus:border-amber-500/60 transition shadow-inner">
                </div>
            </div>
        </div>

        <!-- Card 2: Kustomisasi Template Pesan WhatsApp -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-6 shadow-2xl backdrop-blur-md">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 gap-2">
                <h4 class="text-xs font-extrabold text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-brands fa-whatsapp text-sm"></i> Kustomisasi Pesan WhatsApp
                </h4>
                <span class="text-[10px] text-slate-500 italic">Klik tag cepat untuk menyisipkan teks dinamis</span>
            </div>
            
            <p class="text-xs text-slate-400 mb-4 leading-relaxed">
                Atur format pesan pengingat tagihan bulanan sesuai kebutuhan Anda. Gunakan kode pintas (placeholder) agar teks terisi otomatis sesuai data pelanggan.
            </p>

            <!-- Quick Tag Toolbar -->
            <div class="flex flex-wrap gap-1.5 mb-2.5">
                <button type="button" onclick="sisipTag('[NAMA]')" class="px-2.5 py-1 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-lg text-[10px] font-mono font-bold transition flex items-center gap-1 active:scale-95">
                    <i class="fa-solid fa-plus text-[9px]"></i> [NAMA]
                </button>
                <button type="button" onclick="sisipTag('[BISNIS]')" class="px-2.5 py-1 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg text-[10px] font-mono font-bold transition flex items-center gap-1 active:scale-95">
                    <i class="fa-solid fa-plus text-[9px]"></i> [BISNIS]
                </button>
                <button type="button" onclick="sisipTag('[BULAN]')" class="px-2.5 py-1 bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 rounded-lg text-[10px] font-mono font-bold transition flex items-center gap-1 active:scale-95">
                    <i class="fa-solid fa-plus text-[9px]"></i> [BULAN]
                </button>
                <button type="button" onclick="sisipTag('[TOTAL]')" class="px-2.5 py-1 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 rounded-lg text-[10px] font-mono font-bold transition flex items-center gap-1 active:scale-95">
                    <i class="fa-solid fa-plus text-[9px]"></i> [TOTAL]
                </button>
            </div>

            <div>
                <textarea name="template_pengingat" id="template_pengingat" rows="7" required oninput="updatePreview()"
                          class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-4 py-3 text-xs font-mono text-slate-200 focus:outline-none focus:border-emerald-500/60 transition shadow-inner leading-relaxed"><?= htmlspecialchars($setting['template_pengingat']); ?></textarea>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit" name="simpan_pengaturan" 
                    class="py-3 px-6 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-extrabold text-xs rounded-xl transition shadow-lg shadow-orange-950/40 flex items-center gap-2 active:scale-95 cursor-pointer">
                <i class="fa-solid fa-floppy-disk text-sm"></i> Simpan Perubahan Sistem
            </button>
        </div>
    </div>

    <!-- Sidebar Tools & Live Preview -->
    <div class="space-y-6">
        <!-- Card 3: Kode Pintas (Tag) -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 shadow-2xl backdrop-blur-md">
            <h5 class="text-xs font-bold text-slate-200 uppercase tracking-wider mb-2 flex items-center gap-1.5 text-amber-400">
                <i class="fa-solid fa-code text-xs"></i> Kode Pintas (Tag Dinamis)
            </h5>
            <p class="text-[11px] text-slate-400 mb-4 leading-relaxed">
                Kata kunci di bawah ini akan digantikan secara otomatis oleh sistem saat pesan dikirimkan:
            </p>
            
            <div class="space-y-2.5 font-mono text-[11px]">
                <div onclick="sisipTag('[NAMA]')" class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 hover:border-amber-500/40 transition cursor-pointer group">
                    <span class="text-amber-400 font-bold block mb-0.5 group-hover:translate-x-0.5 transition">[NAMA]</span>
                    <span class="text-slate-400 text-[10px] font-sans">Akan digantikan nama lengkap pelanggan.</span>
                </div>
                <div onclick="sisipTag('[BISNIS]')" class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 hover:border-emerald-500/40 transition cursor-pointer group">
                    <span class="text-emerald-400 font-bold block mb-0.5 group-hover:translate-x-0.5 transition">[BISNIS]</span>
                    <span class="text-slate-400 text-[10px] font-sans">Mengambil nama brand aplikasi di atas.</span>
                </div>
                <div onclick="sisipTag('[BULAN]')" class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 hover:border-sky-500/40 transition cursor-pointer group">
                    <span class="text-sky-400 font-bold block mb-0.5 group-hover:translate-x-0.5 transition">[BULAN]</span>
                    <span class="text-slate-400 text-[10px] font-sans">Nama bulan & tahun tagihan berjalan.</span>
                </div>
                <div onclick="sisipTag('[TOTAL]')" class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 hover:border-rose-500/40 transition cursor-pointer group">
                    <span class="text-rose-400 font-bold block mb-0.5 group-hover:translate-x-0.5 transition">[TOTAL]</span>
                    <span class="text-slate-400 text-[10px] font-sans">Nominal tagihan/tunggakan rupiah.</span>
                </div>
            </div>
        </div>
        
        <!-- Live WhatsApp Preview Box -->
        <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4 shadow-2xl backdrop-blur-md">
            <h5 class="text-[11px] font-bold text-slate-300 uppercase tracking-wider mb-3 flex items-center justify-between">
                <span class="flex items-center gap-1.5"><i class="fa-brands fa-whatsapp text-emerald-400 text-sm"></i> Simulasi Pesan WA</span>
                <span class="text-[9px] text-slate-500 font-normal">Real-time</span>
            </h5>
            
            <div class="bg-[#0b141a] rounded-xl p-3 border border-slate-800/60 font-sans relative overflow-hidden shadow-inner">
                <div class="bg-[#005c4b] text-slate-100 text-[11px] p-2.5 rounded-lg rounded-tl-none max-w-[95%] shadow-md leading-relaxed whitespace-pre-line" id="waPreviewText">
                    <!-- Text Preview Live via JS -->
                </div>
                <div class="text-[9px] text-slate-500 text-right mt-1 font-mono flex items-center justify-end gap-1">
                    09:41 <i class="fa-solid fa-check-double text-sky-400 text-[10px]"></i>
                </div>
            </div>
        </div>

        <!-- Info Sistem -->
        <div class="p-3.5 rounded-xl border border-slate-800/60 bg-slate-950/40 text-[11px] text-slate-400 leading-relaxed flex items-start gap-2">
            <i class="fa-solid fa-circle-info text-amber-500 text-xs mt-0.5"></i>
            <span>
                Sistem menggunakan enkripsi format URL standar untuk memastikan simbol, emoji, dan spasi terkirim secara tepat ke gateway WhatsApp Fonnte.
            </span>
        </div>
    </div>
</form>

<script>
// Fungsi untuk menyisipkan tag di posisi kursor textarea
function sisipTag(tag) {
    const textarea = document.getElementById("template_pengingat");
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + tag + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    textarea.focus();
    updatePreview();
}

// Fungsi Live Preview Pesan WhatsApp
function updatePreview() {
    const template = document.getElementById("template_pengingat").value;
    const namaBrand = document.getElementById("nama_aplikasi").value || 'You-One.net';
    
    // Format Bulan Saat Ini (Indonesian)
    const options = { month: 'long', year: 'numeric' };
    const bulanSekarang = new Date().toLocaleDateString('id-ID', options);
    
    let result = template
        .replace(/\[NAMA\]/g, '<b>Bpk. Ahmad Subagyo</b>')
        .replace(/\[BISNIS\]/g, '<b>' + namaBrand + '</b>')
        .replace(/\[BULAN\]/g, '<b>' + bulanSekarang + '</b>')
        .replace(/\[TOTAL\]/g, '<b>Rp 150.000</b>')
        .replace(/\*(.*?)\*/g, '<b>$1</b>')
        .replace(/_(.*?)_/g, '<i>$1</i>');
        
    document.getElementById("waPreviewText").innerHTML = result;
}

// Jalankan preview saat halaman pertama kali dimuat
document.addEventListener("DOMContentLoaded", function() {
    updatePreview();
});
</script>