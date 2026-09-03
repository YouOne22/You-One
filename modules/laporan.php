<?php
// modules/laporan.php
require_once 'config/koneksi.php';

// Ambil filter bulan & dusun dari URL
$bulan_filter = isset($_GET['bulan']) && !empty($_GET['bulan']) ? mysqli_real_escape_string($koneksi, $_GET['bulan']) : date('Y-m');
$dusun_filter = isset($_GET['dusun']) ? mysqli_real_escape_string($koneksi, $_GET['dusun']) : '';

$tahun_bulan_invoice = str_replace('-', '', $bulan_filter); // Contoh: "2026-07" -> "202607"

// --- AMBIL DAFTAR DUSUN UNTUK DROPDOWN FILTER ---
$q_dusun = mysqli_query($koneksi, "SELECT DISTINCT dusun FROM pelanggan WHERE dusun IS NOT NULL AND dusun != '' ORDER BY dusun ASC");

// =================================================================
// 1. HITUNG REALISASI DANA MASUK (Berdasarkan tanggal bayar di bulan ini & dusun)
// =================================================================
$q_lunas = "SELECT SUM(pb.jumlah_bayar) AS total 
            FROM pembayaran pb
            INNER JOIN tagihan t ON pb.no_invoice = t.no_invoice
            INNER JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
            WHERE pb.tanggal_bayar LIKE '$bulan_filter%'";
if (!empty($dusun_filter)) {
    $q_lunas .= " AND p.dusun = '$dusun_filter'";
}
$total_masuk = mysqli_fetch_assoc(mysqli_query($koneksi, $q_lunas))['total'] ?? 0;

// =================================================================
// 2. HITUNG SISA PIUTANG (Total Tagihan - Total Terbayar pada Invoice Bulan Ini)
// =================================================================
$q_total_tagihan = "SELECT SUM(t.total_tagihan) AS total 
                    FROM tagihan t
                    INNER JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
                    WHERE t.no_invoice LIKE 'INV-$tahun_bulan_invoice%'";
if (!empty($dusun_filter)) {
    $q_total_tagihan .= " AND p.dusun = '$dusun_filter'";
}
$total_tagihan_bulan_ini = mysqli_fetch_assoc(mysqli_query($koneksi, $q_total_tagihan))['total'] ?? 0;

$q_terbayar_invoice_ini = "SELECT SUM(pb.jumlah_bayar) AS total 
                           FROM pembayaran pb
                           INNER JOIN tagihan t ON pb.no_invoice = t.no_invoice
                           INNER JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
                           WHERE t.no_invoice LIKE 'INV-$tahun_bulan_invoice%'";
if (!empty($dusun_filter)) {
    $q_terbayar_invoice_ini .= " AND p.dusun = '$dusun_filter'";
}
$total_terbayar_invoice_ini = mysqli_fetch_assoc(mysqli_query($koneksi, $q_terbayar_invoice_ini))['total'] ?? 0;

$total_piutang = $total_tagihan_bulan_ini - $total_terbayar_invoice_ini;
if ($total_piutang < 0) $total_piutang = 0;

$target_omset = $total_tagihan_bulan_ini;

// =================================================================
// 3. QUERY DETAIL LIST
// =================================================================
$where_detail = " WHERE t.no_invoice LIKE 'INV-$tahun_bulan_invoice%'";
if (!empty($dusun_filter)) {
    $where_detail .= " AND p.dusun = '$dusun_filter'";
}

$q_detail = "SELECT t.*, p.nama AS nama_pelanggan, p.dusun, pk.nama_paket, 
             (SELECT COALESCE(SUM(pb.jumlah_bayar), 0) FROM pembayaran pb WHERE pb.no_invoice = t.no_invoice) AS total_terbayar
             FROM tagihan t
             INNER JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
             LEFT JOIN paket_internet pk ON p.id_paket = pk.id
             $where_detail
             ORDER BY t.no_invoice ASC";
$result_detail = mysqli_query($koneksi, $q_detail);

// Simpan data dalam array untuk rendering ganda (Tampilan Layar & Template Cetak PDF)
$laporan_data = [];
if ($result_detail && mysqli_num_rows($result_detail) > 0) {
    while ($row = mysqli_fetch_assoc($result_detail)) {
        $laporan_data[] = $row;
    }
}
?>

<!-- Dependency HTML2PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<!-- Header Halaman & Filter -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <h3 class="text-2xl font-extrabold text-slate-100 tracking-wide flex items-center gap-2.5">
            <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 text-lg">
                <i class="fa-solid fa-chart-line"></i>
            </span>
            Laporan Keuangan
        </h3>
        <p class="text-xs text-slate-400 mt-1">
            Analisis pembukuan, realisasi kas, dan sisa tunggakan pelanggan <span class="text-amber-400 font-semibold">YOU-ONE.net</span>
        </p>
    </div>
    
    <!-- Filter Bulan & Dusun Modern -->
    <form id="filterForm" method="GET" action="index.php" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-slate-900/80 border border-slate-800/80 p-2 rounded-2xl shadow-xl backdrop-blur-md w-full md:w-auto">
        <input type="hidden" name="page" value="laporan">
        
        <!-- Dropdown Filter Dusun -->
        <div class="relative min-w-[150px]">
            <select name="dusun" class="w-full bg-slate-950/80 border border-slate-800 text-xs text-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-amber-500/60 transition cursor-pointer">
                <option value="">Semua Dusun</option>
                <?php 
                if ($q_dusun && mysqli_num_rows($q_dusun) > 0) {
                    while ($row_dusun = mysqli_fetch_assoc($q_dusun)) {
                        $d_name = $row_dusun['dusun'];
                        $selected = ($dusun_filter === $d_name) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($d_name) . "\" $selected>" . htmlspecialchars($d_name) . "</option>";
                    }
                }
                ?>
            </select>
        </div>

        <!-- Input Filter Bulan -->
        <div class="relative flex items-center">
            <i class="fa-regular fa-calendar absolute left-3 text-amber-500/70 text-xs pointer-events-none"></i>
            <input type="month" name="bulan" value="<?= htmlspecialchars($bulan_filter); ?>" class="w-full bg-slate-950/80 border border-slate-800 text-xs text-slate-200 rounded-xl pl-9 pr-3 py-2 focus:outline-none focus:border-amber-500/60 transition [color-scheme:dark]">
        </div>

        <div class="flex gap-1.5">
            <button type="submit" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-bold text-xs px-4 py-2 rounded-xl transition shadow-lg shadow-orange-950/40 flex items-center justify-center gap-1.5 active:scale-95 w-full sm:w-auto">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            
            <?php if ($bulan_filter !== date('Y-m') || !empty($dusun_filter)) : ?>
                <a href="index.php?page=laporan" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-3 py-2 rounded-xl font-medium transition flex items-center justify-center gap-1 border border-slate-700" title="Reset Filter">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Statistik Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Card Dana Masuk -->
    <div class="bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex items-center gap-4 shadow-xl backdrop-blur-md hover:border-emerald-500/30 transition-all duration-300">
        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-xl shrink-0 shadow-inner">
            <i class="fa-solid fa-money-bill-trend-up"></i>
        </div>
        <div class="overflow-hidden">
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">
                Total Dana Masuk <?= !empty($dusun_filter) ? '(' . htmlspecialchars($dusun_filter) . ')' : ''; ?>
            </span>
            <span class="text-xl font-bold font-mono text-emerald-400 mt-0.5 block truncate">Rp <?= number_format($total_masuk, 0, ',', '.'); ?></span>
        </div>
    </div>
    
    <!-- Card Sisa Piutang -->
    <div class="bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex items-center gap-4 shadow-xl backdrop-blur-md hover:border-rose-500/30 transition-all duration-300">
        <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center text-xl shrink-0 shadow-inner">
            <i class="fa-solid fa-comments-dollar"></i>
        </div>
        <div class="overflow-hidden">
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">
                Sisa Belum Tertagih <?= !empty($dusun_filter) ? '(' . htmlspecialchars($dusun_filter) . ')' : ''; ?>
            </span>
            <span class="text-xl font-bold font-mono text-rose-400 mt-0.5 block truncate">Rp <?= number_format($total_piutang, 0, ',', '.'); ?></span>
        </div>
    </div>
    
    <!-- Card Target Omset -->
    <div class="bg-slate-900/60 border border-slate-800/80 p-5 rounded-2xl flex items-center gap-4 shadow-xl backdrop-blur-md hover:border-amber-500/30 transition-all duration-300">
        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-xl shrink-0 shadow-inner">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
        <div class="overflow-hidden">
            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">
                Target Nilai Invoice <?= !empty($dusun_filter) ? '(' . htmlspecialchars($dusun_filter) . ')' : ''; ?>
            </span>
            <span class="text-xl font-bold font-mono text-slate-100 mt-0.5 block truncate">Rp <?= number_format($target_omset, 0, ',', '.'); ?></span>
        </div>
    </div>
</div>

<!-- Tabel Rincian Invoice -->
<div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md">
    <div class="p-4 bg-slate-950/80 border-b border-slate-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <span class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-2">
            <i class="fa-solid fa-receipt text-amber-500"></i> Rincian Invoice Periode <span class="text-amber-400 underline"><?= date('F Y', strtotime($bulan_filter)); ?></span>
            <?= !empty($dusun_filter) ? ' - <span class="text-slate-200">' . htmlspecialchars($dusun_filter) . '</span>' : ''; ?>
        </span>
        <button onclick="eksporLaporanPDF()" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 text-xs font-bold px-4 py-2 rounded-xl transition shadow-lg shadow-orange-950/40 flex items-center gap-2 active:scale-95">
            <i class="fa-solid fa-file-pdf"></i> Cetak Laporan (PDF)
        </button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs text-slate-300">
            <thead class="bg-slate-950/60 text-slate-400 uppercase font-semibold tracking-wider border-b border-slate-800">
                <tr>
                    <th class="p-4 w-12 text-center">No</th>
                    <th class="p-4">No. Invoice</th>
                    <th class="p-4">Nama Pelanggan</th>
                    <th class="p-4">Paket</th>
                    <th class="p-4 text-right">Nilai Tagihan</th>
                    <th class="p-4 text-right">Sudah Dibayar</th>
                    <th class="p-4 text-center w-28">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                <?php 
                if (!empty($laporan_data)) : 
                    $no = 1;
                    foreach ($laporan_data as $row) : 
                        $tagihan = floatval($row['total_tagihan']);
                        $terbayar = floatval($row['total_terbayar']);
                        
                        if ($terbayar >= $tagihan) {
                            $badge = '<span class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg font-bold uppercase text-[9px] tracking-wider inline-flex items-center gap-1"><i class="fa-solid fa-circle-check text-[8px]"></i> Lunas</span>';
                        } elseif ($terbayar > 0 && $terbayar < $tagihan) {
                            $badge = '<span class="px-2.5 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-lg font-bold uppercase text-[9px] tracking-wider inline-flex items-center gap-1"><i class="fa-solid fa-clock text-[8px]"></i> Dicicil</span>';
                        } else {
                            $badge = '<span class="px-2.5 py-1 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded-lg font-bold uppercase text-[9px] tracking-wider inline-flex items-center gap-1"><i class="fa-solid fa-circle-xmark text-[8px]"></i> Belum Bayar</span>';
                        }
                ?>
                <tr class="hover:bg-slate-800/40 transition-colors">
                    <td class="p-4 text-center text-slate-500 font-mono"><?= $no++; ?></td>
                    <td class="p-4 font-mono font-bold text-amber-400 uppercase tracking-wider"><?= htmlspecialchars($row['no_invoice']); ?></td>
                    <td class="p-4">
                        <div class="text-slate-100 font-bold"><?= htmlspecialchars($row['nama_pelanggan']); ?></div>
                        <?php if (!empty($row['dusun'])): ?>
                            <div class="text-[10px] text-slate-500 font-medium flex items-center gap-1 mt-0.5">
                                <i class="fa-solid fa-location-dot text-slate-600"></i> <?= htmlspecialchars($row['dusun']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-800/80 text-slate-300 border border-slate-700/60">
                            <i class="fa-solid fa-wifi text-[10px] text-amber-400"></i>
                            <?= htmlspecialchars($row['nama_paket'] ?? 'Custom'); ?>
                        </span>
                    </td>
                    <td class="p-4 text-right font-mono text-slate-200">Rp <?= number_format($tagihan, 0, ',', '.'); ?></td>
                    <td class="p-4 text-right font-mono font-bold text-emerald-400 bg-emerald-500/5">Rp <?= number_format($terbayar, 0, ',', '.'); ?></td>
                    <td class="p-4 text-center"><?= $badge; ?></td>
                </tr>
                <?php 
                    endforeach; 
                else : 
                ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-slate-500">
                        <i class="fa-solid fa-folder-open text-3xl mb-2 block text-slate-600"></i>
                        Tidak ada data tagihan yang diterbitkan untuk filter ini.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Template Cetak PDF (Elemen disiapkan untuk proses render) -->
<div id="printArea" class="hidden bg-white text-slate-950 p-8 font-sans" style="color: #0f172a !important; background-color: #ffffff !important; width: 1000px; margin: 0 auto;">
    <div class="flex justify-between items-center border-b-4 border-double border-slate-900 pb-5 mb-6">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">YOU-ONE.NET</h1>
            <p class="text-[11px] text-slate-600 font-medium mt-1">Layanan Internet Cepat, Stabil & Terpercaya</p>
            <p class="text-[10px] text-slate-500">Contact: support@you-one.net | Owner: 0822-4316-7575</p>
        </div>
        <div class="text-right">
            <h2 class="text-lg font-bold text-slate-900 uppercase">Laporan Bulanan Keuangan</h2>
            <p class="text-xs text-slate-700 font-semibold mt-1">
                Periode: <span class="underline"><?= date('F Y', strtotime($bulan_filter)); ?></span>
                <?= !empty($dusun_filter) ? ' | Dusun: <span class="underline">' . htmlspecialchars($dusun_filter) . '</span>' : ' | Dusun: Semua'; ?>
            </p>
            <p class="text-[10px] text-slate-500 mt-1">Dicetak pada: <?= date('d F Y H:i'); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-8 text-center">
        <div class="p-4 rounded-xl border border-slate-300 bg-slate-50">
            <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wider block">1. Total Kas Masuk (Realisasi)</span>
            <span class="text-lg font-bold text-emerald-700 font-mono mt-1 block">Rp <?= number_format($total_masuk, 0, ',', '.'); ?></span>
        </div>
        <div class="p-4 rounded-xl border border-slate-300 bg-slate-50">
            <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wider block">2. Sisa Tunggakan (Piutang)</span>
            <span class="text-lg font-bold text-rose-700 font-mono mt-1 block">Rp <?= number_format($total_piutang, 0, ',', '.'); ?></span>
        </div>
        <div class="p-4 rounded-xl border border-slate-300 bg-slate-50">
            <span class="text-[9px] text-slate-500 font-bold uppercase tracking-wider block">3. Target Omset Bulanan</span>
            <span class="text-lg font-bold text-slate-800 font-mono mt-1 block">Rp <?= number_format($target_omset, 0, ',', '.'); ?></span>
        </div>
    </div>

    <table class="w-full text-left border-collapse text-[11px] border border-slate-400">
        <thead>
            <tr class="bg-slate-100 text-slate-900 font-bold uppercase tracking-wider border-b border-slate-400">
                <th class="p-3 border border-slate-400 text-center w-10">No</th>
                <th class="p-3 border border-slate-400">No. Invoice</th>
                <th class="p-3 border border-slate-400">Nama Pelanggan</th>
                <th class="p-3 border border-slate-400">Dusun</th>
                <th class="p-3 border border-slate-400">Paket</th>
                <th class="p-3 border border-slate-400 text-right">Nilai Tagihan</th>
                <th class="p-3 border border-slate-400 text-right">Jumlah Dibayar</th>
                <th class="p-3 border border-slate-400 text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (!empty($laporan_data)) : 
                $no = 1;
                foreach ($laporan_data as $row) : 
                    $tagihan = floatval($row['total_tagihan']);
                    $terbayar = floatval($row['total_terbayar']);
                    
                    if ($terbayar >= $tagihan) {
                        $p_status = "LUNAS";
                    } elseif ($terbayar > 0 && $terbayar < $tagihan) {
                        $p_status = "DICICIL";
                    } else {
                        $p_status = "BELUM BAYAR";
                    }
            ?>
            <tr class="border-b border-slate-300 text-slate-900">
                <td class="p-2.5 border border-slate-300 text-center font-mono"><?= $no++; ?></td>
                <td class="p-2.5 border border-slate-300 font-mono font-bold"><?= htmlspecialchars($row['no_invoice']); ?></td>
                <td class="p-2.5 border border-slate-300 font-medium"><?= htmlspecialchars($row['nama_pelanggan']); ?></td>
                <td class="p-2.5 border border-slate-300"><?= htmlspecialchars($row['dusun'] ?? '-'); ?></td>
                <td class="p-2.5 border border-slate-300"><?= htmlspecialchars($row['nama_paket'] ?? 'Custom'); ?></td>
                <td class="p-2.5 border border-slate-300 text-right font-mono">Rp <?= number_format($tagihan, 0, ',', '.'); ?></td>
                <td class="p-2.5 border border-slate-300 text-right font-mono font-semibold text-emerald-800">Rp <?= number_format($terbayar, 0, ',', '.'); ?></td>
                <td class="p-2.5 border border-slate-300 text-center font-bold text-[9px]"><?= $p_status; ?></td>
            </tr>
            <?php 
                endforeach; 
            else : 
            ?>
            <tr>
                <td colspan="8" class="p-6 text-center border border-slate-300">Tidak ada data tagihan yang terbit untuk filter ini.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-14 grid grid-cols-2 text-center text-xs font-semibold">
        <div>
            <p class="text-slate-600 mb-16">Dibuat Oleh,<br><span class="font-normal text-[10px]">Staf Admin Operasional</span></p>
            <p class="text-slate-900 underline font-bold">( ............................................ )</p>
        </div>
        <div>
            <p class="text-slate-600 mb-16">Diketahui & Disetujui Oleh,<br><span class="font-normal text-[10px]">Pimpinan / Owner You-One.net</span></p>
            <p class="text-slate-900 underline font-bold">Y u w a n</p>
        </div>
    </div>
</div>

<!-- Script Ekspor PDF -->
<script>
function eksporLaporanPDF() {
    // 0. Cek sinkronisasi filter
    const formBulan = document.querySelector('input[name="bulan"]').value;
    const formDusun = document.querySelector('select[name="dusun"]').value;
    const activeBulan = '<?= $bulan_filter; ?>';
    const activeDusun = '<?= $dusun_filter; ?>';

    if (formBulan !== activeBulan || formDusun !== activeDusun) {
        Swal.fire({
            title: '<span class="text-amber-400 font-bold tracking-wide">Filter Belum Diterapkan!</span>',
            text: 'Pilihan filter telah diubah. Silakan klik tombol Filter terlebih dahulu agar data terbaru dimuat sebelum mencetak PDF.',
            icon: 'warning',
            background: '#090d16',
            color: '#f1f5f9',
            iconColor: '#f59e0b',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#334155',
            confirmButtonText: 'Terapkan Filter Sekarang',
            cancelButtonText: 'Batal',
            customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl backdrop-blur-xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('filterForm').submit();
            }
        });
        return;
    }

    // 1. Loading Alert SweetAlert2
    Swal.fire({
        title: '<span class="text-amber-400 font-bold tracking-wide">Menyusun Berkas Laporan...</span>',
        text: 'Mohon tunggu, sistem sedang memproses lembar dokumen PDF.',
        background: '#090d16',
        color: '#f1f5f9',
        iconColor: '#f59e0b',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // 2. Ambil elemen target & tampilkan sejenak agar dibaca penuh oleh html2canvas
    const element = document.getElementById('printArea');
    element.classList.remove('hidden');

    // 3. Konfigurasi PDF
    const opsi = {
        margin:       10,
        filename:     'Laporan_Keuangan_YouOne_<?= $tahun_bulan_invoice; ?><?= !empty($dusun_filter) ? '_' . preg_replace('/[^a-zA-Z0-9]/', '', $dusun_filter) : ''; ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { 
            scale: 2, 
            useCORS: true, 
            logging: false,
            scrollY: 0, // Mengunci koordinat Y agar tidak terpengaruh scroll halaman
            scrollX: 0
        },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };

    // 4. Ekspor dan sembunyikan kembali elemen setelah proses selesai
    html2pdf().set(opsi).from(element).save().then(() => {
        element.classList.add('hidden'); // Sembunyikan kembali
        
        Swal.fire({
            title: '<span class="text-emerald-400 font-bold tracking-wide">Berhasil Tersimpan!</span>',
            text: 'Berkas laporan PDF berhasil diunduh.',
            icon: 'success',
            background: '#090d16',
            color: '#f1f5f9',
            confirmButtonColor: '#10b981',
            iconColor: '#10b981',
            customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl backdrop-blur-xl' }
        });
    }).catch(err => {
        element.classList.add('hidden'); // Sembunyikan jika terjadi kegagalan
        Swal.fire({
            title: '<span class="text-rose-400 font-bold tracking-wide">Gagal Cetak!</span>',
            text: 'Terjadi kegagalan sistem saat membuat file PDF.',
            icon: 'error',
            background: '#090d16',
            color: '#f1f5f9',
            confirmButtonColor: '#ef4444',
            iconColor: '#f43f5e',
            customClass: { popup: 'rounded-2xl border border-slate-800 shadow-2xl backdrop-blur-xl' }
        });
    });
}
</script>