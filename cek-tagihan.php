<?php
// cek-tagihan.php - Portal Mandiri Cek Tagihan Pelanggan
require_once 'config/koneksi.php';

$hasil_pencarian = null;
$pesan_error = '';

if (isset($_POST['cari_tagihan'])) {
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    
    if (!empty($whatsapp)) {
        $wa_clean = $whatsapp;
        if (strpos($wa_clean, '0') === 0) {
            $wa_clean = '62' . substr($wa_clean, 1);
        }

        $stmt = $koneksi->prepare("
            SELECT p.id_pelanggan, p.nama, p.no_wa, p.dusun, t.no_invoice, t.total_tagihan, t.bulan_tagihan,
                   (SELECT SUM(jumlah_bayar) FROM pembayaran WHERE no_invoice = t.no_invoice) as total_bayar
            FROM pelanggan p
            LEFT JOIN tagihan t ON p.id_pelanggan = t.id_pelanggan
            WHERE p.no_wa LIKE ? OR p.no_wa LIKE ?
            ORDER BY t.created_at DESC LIMIT 1
        ");
        
        $param1 = "%" . $whatsapp . "%";
        $param2 = "%" . $wa_clean . "%";
        $stmt->bind_param("ss", $param1, $param2);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $hasil_pencarian = $result->fetch_assoc();
            $total_tagihan = floatval($hasil_pencarian['total_tagihan'] ?? 0);
            $total_bayar = floatval($hasil_pencarian['total_bayar'] ?? 0);
            
            if ($total_tagihan > 0 && $total_bayar >= $total_tagihan) {
                $hasil_pencarian['status_tagihan'] = 'Lunas';
            } elseif ($total_bayar > 0) {
                $hasil_pencarian['status_tagihan'] = 'Dicicil';
            } else {
                $hasil_pencarian['status_tagihan'] = 'Belum Lunas';
            }
        } else {
            $pesan_error = "Nomor WhatsApp tidak ditemukan atau belum terdaftar sebagai pelanggan.";
        }
        $stmt->close();
    } else {
        $pesan_error = "Masukkan nomor WhatsApp Anda dengan benar.";
    }
}
?>

<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tagihan - You-One.net</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background-color: #060a12;
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(16, 185, 129, 0.25) 0%, transparent 35%),
                radial-gradient(circle at 90% 90%, rgba(139, 92, 246, 0.25) 0%, transparent 35%),
                linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(139, 92, 246, 0.05) 50%);
            background-attachment: fixed;
        }
        .glass-card { 
            background: rgba(15, 23, 42, 0.65); 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08); 
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
    </style>
</head>
<body class="text-slate-200 font-sans antialiased min-h-screen flex flex-col justify-between p-4">

    <!-- TOP BAR -->
    <div class="max-w-md mx-auto w-full pt-8">
        <div class="flex items-center justify-between mb-8">
            <a href="landing.php" class="text-slate-400 hover:text-white text-sm flex items-center gap-2 transition">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
            </a>
            <img src="You One Creative.png" alt="Logo" class="h-10 w-auto object-contain">
        </div>

        <!-- CARD UTAMA PENCARIAN -->
        <div class="glass-card p-6 sm:p-8 rounded-3xl space-y-6">
            <div class="text-center space-y-2">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-blue-500/10 border border-blue-500/30 text-blue-400 flex items-center justify-center text-xl shadow-lg">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <h1 class="text-xl font-bold text-white">Cek Tagihan Bulanan</h1>
                <p class="text-xs text-slate-400">Masukkan nomor WhatsApp yang terdaftar untuk melihat status tagihan internet Anda.</p>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Nomor WhatsApp</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500 text-sm">
                            <i class="fa-brands fa-whatsapp"></i>
                        </span>
                        <input type="text" name="whatsapp" required 
                               class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-11 pr-4 py-3 text-sm text-slate-200 focus:outline-none focus:border-purple-500 transition" 
                               placeholder="Contoh: 08123456789">
                    </div>
                </div>

                <button type="submit" name="cari_tagihan" 
                        class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-sm shadow-lg shadow-emerald-600/30 transition">
                    Cek Status Tagihan
                </button>
            </form>

            <?php if (!empty($pesan_error)) : ?>
                <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs text-center">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= $pesan_error; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- HASIL PENCARIAN (JIKA KETEMU) -->
        <?php if ($hasil_pencarian) : ?>
            <div class="glass-card p-6 rounded-3xl mt-6 space-y-4 border border-emerald-500/30 animate-fade-in">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-slate-400">Nama Pelanggan</p>
                        <h3 class="text-base font-bold text-white"><?= htmlspecialchars($hasil_pencarian['nama']); ?></h3>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider 
                        <?= ($hasil_pencarian['status_tagihan'] == 'Lunas') ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'; ?>">
                        <?= $hasil_pencarian['status_tagihan'] ?? 'Belum Ada Tagihan'; ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="text-slate-400">Dusun / Area</p>
                        <p class="font-semibold text-slate-200"><?= htmlspecialchars($hasil_pencarian['dusun'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <p class="text-slate-400">Periode Tagihan</p>
                        <p class="font-semibold text-slate-200"><?= !empty($hasil_pencarian['bulan_tagihan']) ? date('F Y', strtotime($hasil_pencarian['bulan_tagihan'])) : '-'; ?></p>
                    </div>
                </div>

                <div class="pt-3 border-t border-white/10 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] text-slate-400 uppercase">Total Tagihan</p>
                        <p class="text-lg font-extrabold text-emerald-400">Rp <?= number_format($hasil_pencarian['total_tagihan'] ?? 0, 0, ',', '.'); ?></p>
                    </div>
                    <a href="https://wa.me/<?= NO_OWNER; ?>?text=Halo%20Admin,%20saya%20<?= urlencode($hasil_pencarian['nama']); ?>%20ingin%20konfirmasi%20pembayaran%20tagihan%20untuk%20Invoice%20<?= urlencode($hasil_pencarian['no_invoice'] ?? ''); ?>" 
                       target="_blank" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition flex items-center gap-2 shadow-lg">
                        <i class="fa-brands fa-whatsapp text-sm"></i> Konfirmasi Bayar
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="text-center text-xs text-slate-500 py-6">
        © 2026 You-One. All rights reserved.
    </footer>
</body>
</html>