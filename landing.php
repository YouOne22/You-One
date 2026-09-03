<?php
// landing.php - You-One.net Landing Page

session_start();
// Jika user SUDAH login tapi mencoba membuka landing.php, dialihkan ke dashboard (index.php)
if (isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}    

// PENGAMAN ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// SET TIMEZONE
date_default_timezone_set('Asia/Jakarta');

// 1. HANDLER AJAX: Memproses kirim pendaftaran via Fonnte API
if (isset($_GET['action']) && $_GET['action'] === 'send_registration') {
    header('Content-Type: application/json');
    
    $nama = trim($_POST['nama'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $paket = trim($_POST['paket'] ?? '');

    if (empty($nama) || empty($whatsapp) || empty($alamat) || empty($paket)) {
        echo json_encode(['success' => false, 'message' => 'Semua kolom formulir harus diisi.']);
        exit;
    }

    // Nomor utama penerima pesan (Nomor Admin)
    $targetAdmin = "6282243167575"; 
    
    // API Key Fonnte
    $token_fonnte = "eoTkcnhieYpXdHCo7M5H";

    // Format Pesan untuk dikirim ke WhatsApp Admin
    $pesan = "📩 *PENDAFTARAN BARU YOU-ONE.NET*\n\n" .
             "Ada calon pelanggan baru yang mendaftar melalui website:\n\n" .
             "👤 Nama: *{$nama}*\n" .
             "📱 No WhatsApp: *{$whatsapp}*\n" .
             "📍 Alamat: *{$alamat}*\n" .
             "📦 Paket: *{$paket}*\n" .
             "🏷️ Promo: *Biaya Pasang Rp 170rb (Langsung Aktif)*\n\n" .
             "Mohon segera ditindaklanjuti/survey ya kak. Terima kasih!";

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
            'target' => $targetAdmin,
            'message' => $pesan,
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
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You-One.net - Platform Manajemen Internet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- LEAFLET MAP CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body { 
            background-color: #070a11;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(249, 115, 22, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(245, 158, 11, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(244, 63, 94, 0.04) 0%, transparent 60%);
            background-attachment: fixed;
        }
        
        .glass-card { 
            background: rgba(11, 15, 23, 0.75) !important; 
            backdrop-filter: blur(20px) !important; 
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important; 
            box-shadow: 0 10px 30px 0 rgba(0, 0, 0, 0.4) !important;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: rgba(249, 115, 22, 0.3) !important;
            box-shadow: 0 12px 35px 0 rgba(249, 115, 22, 0.08) !important;
        }

        .amber-gradient-text {
            background: linear-gradient(135deg, #fef08a 0%, #f59e0b 40%, #f97316 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-glass {
            background: rgba(7, 10, 17, 0.85) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #070a11; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #f97316; }
    </style>
</head>
<body class="text-slate-100 font-sans antialiased selection:bg-orange-500 selection:text-slate-950">

    <!-- NAVBAR -->
    <nav class="fixed top-0 left-0 right-0 z-50 nav-glass">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- LOGO (KIRI) -->
            <div class="flex items-center gap-3">
                <img src="You One Creative.png" alt="Logo You-One" class="h-12 sm:h-16 w-auto object-contain">
            </div>
                        
            <!-- MENU NAVIGASI & LOGIN (KANAN) -->
            <div class="flex items-center gap-6 sm:gap-8">
                <div class="hidden md:flex items-center gap-6 lg:gap-8 text-sm font-semibold text-slate-300">
                    <a href="#home" class="hover:text-orange-400 transition">Beranda</a>
                    <a href="#layanan" class="hover:text-orange-400 transition">Layanan</a>
                    <a href="#paket" class="hover:text-orange-400 transition">Paket</a>
                    <a href="#coverage" class="hover:text-orange-400 transition">Coverage</a>
                    <a href="#bantuan" class="hover:text-orange-400 transition">Bantuan</a>
                </div>

                <a href="login.php" class="px-5 py-2.5 rounded-xl text-sm font-bold border border-orange-500/30 text-orange-400 hover:bg-orange-500/10 hover:border-orange-500/60 transition flex items-center gap-2 shadow-sm shadow-orange-500/10">
                    <i class="fa-solid fa-right-to-bracket text-xs"></i> Login
                </a>
            </div>

        </div>
    </nav>

    <!-- HERO SECTION -->
    <section id="home" class="pt-32 pb-12 md:pt-40 md:pb-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto relative">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <!-- KIRI: TEKS UTAMA -->
            <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-gradient-to-r from-orange-500/15 to-amber-500/15 border border-orange-500/30 text-orange-400 text-[11px] font-extrabold tracking-widest uppercase shadow-md shadow-orange-500/10">
                    <span class="w-2 h-2 rounded-full bg-orange-400 animate-ping"></span>
                    <span>Platform Manajemen Internet</span>
                </div>
                
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-tight">
                    Kelola Internet Anda <br>
                    <span class="amber-gradient-text">Lebih Mudah & Terpercaya</span>
                </h1>
                
                <p class="text-base sm:text-lg text-slate-300 max-w-xl mx-auto lg:mx-0 leading-relaxed font-medium">
                    Satu platform terpadu untuk mengelola koneksi, pembayaran tagihan, laporan grafik, dan layanan bantuan pelanggan secara canggih.
                </p>

                <!-- PROMO BIAYA PASANG -->
                <div class="pt-1 flex flex-wrap items-center justify-center lg:justify-start">
                    <div class="inline-flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-slate-950/80 border border-orange-500/40 backdrop-blur-md shadow-lg shadow-orange-500/5">
                        <div class="text-xs text-slate-300 font-medium">
                            Biaya Pasang: <span class="text-base font-black text-amber-400 ml-1">Rp 170rb</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-gradient-to-r from-orange-500 to-amber-500 text-slate-950 text-xs font-black shadow-md shadow-orange-500/20">
                            <i class="fa-solid fa-bolt text-[10px]"></i> Langsung Aktif
                        </span>
                    </div>
                </div>
                
                <!-- TOMBOL CTA -->
                <div class="flex flex-col sm:flex-row items-center gap-4 justify-center lg:justify-start pt-2">
                    <button onclick="bukaModalDaftar('Akun Baru')" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-500 hover:from-orange-400 hover:to-amber-400 text-slate-950 font-black text-sm transition shadow-lg shadow-orange-500/25 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-user-plus text-xs"></i> Daftar Sekarang
                    </button>
                    <a href="login.php" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 text-slate-200 border border-slate-700 font-bold text-sm transition flex items-center justify-center gap-2">
                        Login ke Akun <i class="fa-solid fa-arrow-right text-xs text-orange-400"></i>
                    </a>
                </div>
            </div>

            <!-- KANAN: MOCKUP APLIKASI SMARTPHONE -->
            <div class="lg:col-span-5 relative flex justify-center items-center">
                
                <!-- Glow Effect Background -->
                <div class="absolute w-80 h-80 bg-orange-500/20 rounded-full blur-3xl -z-10"></div>
                
                <!-- Frame Smartphone Mockup -->
                <div class="w-full max-w-[285px] bg-slate-950 border-[6px] border-slate-800 rounded-[46px] p-3 shadow-2xl relative overflow-hidden ring-1 ring-orange-500/40">
                    
                    <!-- Dynamic Island / Speaker Notch -->
                    <div class="w-20 h-3.5 bg-slate-900 rounded-full mx-auto mb-3 border border-slate-800 flex items-center justify-end pr-2">
                        <div class="w-2 h-2 rounded-full bg-slate-950 border border-slate-800"></div>
                    </div>

                    <!-- App UI Content -->
                    <div class="space-y-2.5 text-left pb-4">
                        
                        <!-- User Header App -->
                        <div class="flex justify-between items-center pb-0.5">
                            <div>
                                <p class="text-[9px] text-slate-400 font-medium">Selamat datang,</p>
                                <h4 class="text-xs font-extrabold text-white flex items-center gap-1">Hallo 👋<i class="fa-solid fa-hand-wave text-amber-400 text-[10px]"></i></h4>
                            </div>
                            <div class="w-6 h-6 rounded-full bg-slate-900 border border-orange-500/30 flex items-center justify-center text-orange-400 text-[10px] shadow">
                                <i class="fa-regular fa-bell"></i>
                            </div>
                        </div>

                        <!-- Card Status Koneksi -->
                        <div class="bg-slate-900/90 border border-emerald-500/30 rounded-xl p-2.5 flex justify-between items-center shadow-md">
                            <div>
                                <p class="text-[9px] text-slate-400 font-medium">Status Koneksi</p>
                                <p class="text-xs font-extrabold text-emerald-400 flex items-center gap-1.5 mt-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span> Online
                                </p>
                                <p class="text-[8px] text-slate-400 mt-0.5">● Koneksi stabil</p>
                            </div>
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm shadow-inner">
                                <i class="fa-solid fa-wifi"></i>
                            </div>
                        </div>

                        <!-- Card Penggunaan Bulan Ini -->
                        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-2.5 space-y-1.5">
                            <div class="flex justify-between items-center text-[9px]">
                                <span class="text-slate-300 font-medium">Penggunaan Bulan Ini</span>
                                <span class="text-orange-400 font-bold">51%</span>
                            </div>
                            <div class="text-xs font-bold text-white">256 GB <span class="text-[9px] font-normal text-slate-400">/ 500 GB</span></div>
                            <div class="w-full bg-slate-950 h-1.5 rounded-full overflow-hidden border border-slate-800">
                                <div class="bg-gradient-to-r from-orange-500 to-amber-400 h-full w-[51%] rounded-full"></div>
                            </div>
                        </div>

                        <!-- Quick Menu Grid -->
                        <div class="grid grid-cols-3 gap-1.5 text-center pt-0.5">
                            <div class="bg-slate-900/80 border border-slate-800 rounded-lg p-2 flex flex-col items-center hover:border-orange-500/30 transition">
                                <i class="fa-solid fa-file-invoice-dollar text-orange-400 text-xs mb-1"></i>
                                <span class="text-[8px] text-slate-300 font-medium leading-tight">Tagihan</span>
                            </div>
                            <div class="bg-slate-900/80 border border-slate-800 rounded-lg p-2 flex flex-col items-center hover:border-orange-500/30 transition">
                                <i class="fa-solid fa-headset text-orange-400 text-xs mb-1"></i>
                                <span class="text-[8px] text-slate-300 font-medium leading-tight">Bantuan</span>
                            </div>
                            <div class="bg-slate-900/80 border border-slate-800 rounded-lg p-2 flex flex-col items-center hover:border-orange-500/30 transition">
                                <i class="fa-solid fa-clock-rotate-left text-orange-400 text-xs mb-1"></i>
                                <span class="text-[8px] text-slate-300 font-medium leading-tight">Riwayat</span>
                            </div>
                        </div>

                        <!-- Grafik Mini Trafik Jaringan -->
                        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-2.5 space-y-1.5">
                            <div class="flex justify-between items-center text-[9px]">
                                <span class="text-slate-300 font-medium flex items-center gap-1"><i class="fa-solid fa-chart-line text-orange-400"></i> Kecepatan (Mbps)</span>
                                <span class="text-emerald-400 font-bold">10.2 Mbps</span>
                            </div>
                            <div class="h-10 flex items-end gap-1 pt-1 px-1 bg-slate-950/50 rounded border border-slate-800/80">
                                <div class="w-1/6 bg-orange-500/40 h-[40%] rounded-t"></div>
                                <div class="w-1/6 bg-orange-500/60 h-[65%] rounded-t"></div>
                                <div class="w-1/6 bg-orange-500/50 h-[50%] rounded-t"></div>
                                <div class="w-1/6 bg-orange-500/80 h-[85%] rounded-t"></div>
                                <div class="w-1/6 bg-amber-500/70 h-[70%] rounded-t"></div>
                                <div class="w-1/6 bg-amber-400 h-[95%] rounded-t"></div>
                            </div>
                        </div>

                        <!-- Perangkat Terhubung Card -->
                        <div class="bg-slate-900/90 border border-slate-800 rounded-lg p-2 flex justify-between items-center">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-laptop-mobile text-slate-400 text-[10px]"></i>
                                <span class="text-[9px] text-slate-300 font-medium">Perangkat Terhubung</span>
                            </div>
                            <span class="text-[9px] font-bold text-orange-400 bg-orange-500/10 px-1.5 py-0.5 rounded border border-orange-500/20">8 Perangkat</span>
                        </div>

                        <!-- Mini Navbar Bawah Aplikasi -->
                        <div class="pt-1 flex justify-around items-center bg-slate-950/80 border border-slate-800/80 rounded-xl py-1.5">
                            <div class="flex flex-col items-center text-orange-400">
                                <i class="fa-solid fa-house text-[10px]"></i>
                                <span class="text-[7px] mt-0.5">Home</span>
                            </div>
                            <div class="flex flex-col items-center text-slate-500">
                                <i class="fa-solid fa-wallet text-[10px]"></i>
                                <span class="text-[7px] mt-0.5">Tagihan</span>
                            </div>
                            <div class="flex flex-col items-center text-slate-500">
                                <i class="fa-solid fa-ticket text-[10px]"></i>
                                <span class="text-[7px] mt-0.5">Tiket</span>
                            </div>
                            <div class="flex flex-col items-center text-slate-500">
                                <i class="fa-solid fa-user text-[10px]"></i>
                                <span class="text-[7px] mt-0.5">Profil</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>

        <!-- STATISTIK BANNER -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-16 p-6 rounded-3xl glass-card border border-orange-500/20">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center text-lg shadow-inner"><i class="fa-solid fa-users"></i></div>
                <div>
                    <h4 class="font-extrabold text-base text-white">20.000+</h4>
                    <p class="text-xs text-slate-400 font-medium">Pelanggan Aktif</p>
                </div>
            </div>
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center text-lg shadow-inner"><i class="fa-solid fa-shield-halved"></i></div>
                <div>
                    <h4 class="font-extrabold text-base text-white">99,9%</h4>
                    <p class="text-xs text-slate-400 font-medium">Uptime Jaringan</p>
                </div>
            </div>
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center text-lg shadow-inner"><i class="fa-solid fa-headset"></i></div>
                <div>
                    <h4 class="font-extrabold text-base text-white">24/7</h4>
                    <p class="text-xs text-slate-400 font-medium">Support Tersedia</p>
                </div>
            </div>
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center text-lg shadow-inner"><i class="fa-solid fa-bolt"></i></div>
                <div>
                    <h4 class="font-extrabold text-base text-white">Cepat &amp; Stabil</h4>
                    <p class="text-xs text-slate-400 font-medium">Koneksi Terjamin</p>
                </div>
            </div>
        </div>
    </section>

    <!-- KEUNGGULAN / SEMUA LAYANAN DALAM GENGGAMAN -->
    <section id="layanan" class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h2 class="text-2xl sm:text-3xl font-black text-white mb-2 tracking-tight">Semua Layanan dalam Genggaman</h2>
            <p class="text-xs sm:text-sm text-slate-400">Kelola semua kebutuhan internet Anda dengan mudah, akurat, dan transparan.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
            <div class="glass-card p-6 rounded-2xl flex flex-col justify-between hover:border-orange-500/50 transition group">
                <div>
                    <div class="w-11 h-11 rounded-xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center mb-4 text-lg shadow-inner group-hover:scale-110 transition-transform"><i class="fa-solid fa-chart-line"></i></div>
                    <h4 class="font-extrabold text-base text-white mb-1.5">Monitoring Real-time</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Pantau status koneksi dan penggunaan internet secara akurat kapan saja.</p>
                </div>
                <i class="fa-solid fa-arrow-right text-xs text-orange-400 self-end mt-4 group-hover:translate-x-1 transition-transform"></i>
            </div>
            <div class="glass-card p-6 rounded-2xl flex flex-col justify-between hover:border-orange-500/50 transition group">
                <div>
                    <div class="w-11 h-11 rounded-xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center mb-4 text-lg shadow-inner group-hover:scale-110 transition-transform"><i class="fa-solid fa-credit-card"></i></div>
                    <h4 class="font-extrabold text-base text-white mb-1.5">Pembayaran Mudah</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Bayar tagihan dengan berbagai metode pembayaran aman dan praktis.</p>
                </div>
                <i class="fa-solid fa-arrow-right text-xs text-orange-400 self-end mt-4 group-hover:translate-x-1 transition-transform"></i>
            </div>
            <div class="glass-card p-6 rounded-2xl flex flex-col justify-between hover:border-orange-500/50 transition group">
                <div>
                    <div class="w-11 h-11 rounded-xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center mb-4 text-lg shadow-inner group-hover:scale-110 transition-transform"><i class="fa-solid fa-comments"></i></div>
                    <h4 class="font-extrabold text-base text-white mb-1.5">Bantuan Cepat</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Ajukan tiket bantuan dan dapatkan respon cepat langsung dari tim teknis.</p>
                </div>
                <i class="fa-solid fa-arrow-right text-xs text-orange-400 self-end mt-4 group-hover:translate-x-1 transition-transform"></i>
            </div>
            <div class="glass-card p-6 rounded-2xl flex flex-col justify-between hover:border-orange-500/50 transition group">
                <div>
                    <div class="w-11 h-11 rounded-xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center mb-4 text-lg shadow-inner group-hover:scale-110 transition-transform"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <h4 class="font-extrabold text-base text-white mb-1.5">Riwayat Lengkap</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Lihat riwayat transaksi pembayaran dan aktivitas akun secara rinci.</p>
                </div>
                <i class="fa-solid fa-arrow-right text-xs text-orange-400 self-end mt-4 group-hover:translate-x-1 transition-transform"></i>
            </div>
        </div>
    </section>

    <!-- PAKET BULANAN -->
    <section id="paket" class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h2 class="text-2xl sm:text-3xl font-black text-white mb-2 tracking-tight">Pilihan Paket Internet</h2>
            <p class="text-xs sm:text-sm text-slate-400">Pilih paket internet sesuai kebutuhan rumah atau bisnis Anda. Biaya pasang hanya <strong class="text-orange-400">Rp 170rb</strong> langsung aktif!</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Reguler -->
            <div class="glass-card p-8 rounded-3xl flex flex-col justify-between border border-white/10 hover:border-orange-500/40 transition">
                <div>
                    <p class="text-xs font-extrabold text-orange-400 tracking-wider uppercase mb-2">Reguler</p>
                    <div class="flex items-baseline gap-1 mb-4 font-mono">
                        <span class="text-4xl font-black text-white">120rb</span>
                        <span class="text-xs text-slate-400 font-sans">/ bulan</span>
                    </div>
                    <div class="flex items-center gap-2 py-3 border-y border-white/10 text-slate-200 text-sm mb-6 font-medium">
                        <i class="fa-solid fa-wifi text-orange-400"></i> Speed: 5 Mbps Unlimited
                    </div>
                </div>
                <button onclick="bukaModalDaftar('Reguler (120rb)')" class="w-full py-3 rounded-xl bg-slate-900 border border-orange-500/40 hover:bg-gradient-to-r hover:from-orange-500 hover:to-amber-500 hover:text-slate-950 text-orange-400 font-extrabold text-sm transition shadow-md">
                    Pilih Paket
                </button>
            </div>

            <!-- Business (Best Seller) -->
            <div class="glass-card p-8 rounded-3xl flex flex-col justify-between border-2 border-orange-500/80 relative shadow-2xl shadow-orange-500/10">
                <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-gradient-to-r from-orange-500 to-amber-500 text-slate-950 text-[10px] font-black px-3.5 py-1 rounded-full uppercase tracking-wider shadow-md">
                    Paling Populer
                </div>
                <div>
                    <p class="text-xs font-extrabold text-orange-400 tracking-wider uppercase mb-2">Business</p>
                    <div class="flex items-baseline gap-1 mb-4 font-mono">
                        <span class="text-5xl font-black text-white">170rb</span>
                        <span class="text-xs text-slate-400 font-sans">/ bulan</span>
                    </div>
                    <div class="flex items-center gap-2 py-3 border-y border-white/10 text-slate-200 text-sm mb-6 font-medium">
                        <i class="fa-solid fa-wifi text-orange-400"></i> Speed: 10 Mbps Unlimited
                    </div>
                </div>
                <button onclick="bukaModalDaftar('Business (170rb)')" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-500 hover:from-orange-400 hover:to-amber-400 text-slate-950 font-black text-sm shadow-lg shadow-orange-500/20 transition">
                    Pilih Paket
                </button>
            </div>

            <!-- Premium -->
            <div class="glass-card p-8 rounded-3xl flex flex-col justify-between border border-white/10 hover:border-orange-500/40 transition">
                <div>
                    <p class="text-xs font-extrabold text-orange-400 tracking-wider uppercase mb-2">Premium</p>
                    <div class="flex items-baseline gap-1 mb-4 font-mono">
                        <span class="text-4xl font-black text-white">225rb</span>
                        <span class="text-xs text-slate-400 font-sans">/ bulan</span>
                    </div>
                    <div class="flex items-center gap-2 py-3 border-y border-white/10 text-slate-200 text-sm mb-6 font-medium">
                        <i class="fa-solid fa-wifi text-orange-400"></i> Speed: 20 Mbps Unlimited
                    </div>
                </div>
                <button onclick="bukaModalDaftar('Premium (225rb)')" class="w-full py-3 rounded-xl bg-slate-900 border border-orange-500/40 hover:bg-gradient-to-r hover:from-orange-500 hover:to-amber-500 hover:text-slate-950 text-orange-400 font-extrabold text-sm transition shadow-md">
                    Pilih Paket
                </button>
            </div>
        </div>
    </section>

    <!-- COVERAGE AREA & MAP -->
    <section id="coverage" class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center glass-card p-6 sm:p-8 rounded-3xl border border-orange-500/20">
            <div class="space-y-4">
                <h2 class="text-2xl font-black text-white tracking-tight">Coverage Area Jangkauan Kami</h2>
                <p class="text-sm text-slate-300 leading-relaxed">Layanan internet berkecepatan tinggi. You-One.net kini telah menjangkau area Kemitir, Ngoho, Mbalong dan sekitarnya.</p>
                <div class="flex items-center gap-3 text-xs text-orange-400 font-bold bg-orange-500/10 p-3 rounded-xl border border-orange-500/20 w-fit">
                    <i class="fa-solid fa-circle-check text-emerald-400"></i> Sinyal kuat, stabil, dan bergaransi teknis.
                </div>
            </div>
            <div class="h-64 sm:h-80 w-full rounded-2xl overflow-hidden border border-white/10 shadow-inner" id="landing-map"></div>
        </div>
    </section>

    <!-- FOOTER & TENTANG KAMI -->
    <footer id="bantuan" class="border-t border-white/10 bg-slate-950/90 py-12 px-4 sm:px-6 lg:px-8 mt-16">
        <div id="tentang" class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
            <div class="space-y-3">
                <img src="You One Creative.png" alt="Logo" class="h-10 w-auto object-contain">
                <p class="text-xs text-slate-400 max-w-sm leading-relaxed">You-One menyediakan platform manajemen internet dan layanan jaringan yang handal, stabil, serta transparan.</p>
            </div>
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-orange-400 mb-2">Hubungi Kami</p>
                <p class="text-xs text-slate-300 flex items-center gap-2"><i class="fa-brands fa-whatsapp text-emerald-400 text-base"></i> 0822 4316 7575</p>
                <p class="text-xs text-slate-300 flex items-center gap-2 mt-1.5"><i class="fa-solid fa-envelope text-amber-400"></i> info@youone.net</p>
            </div>
            <div class="text-xs text-slate-500 font-medium md:text-right">
                © 2026 You-One. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- MODAL PENDAFTARAN -->
    <div id="modal-daftar" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 hidden flex items-center justify-center p-4">
        <div class="glass-card p-6 sm:p-8 rounded-3xl w-full max-w-md border border-orange-500/30 relative shadow-2xl">
            <button onclick="tutupModalDapat()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
            <h3 class="text-lg font-black text-white mb-1">Form Pendaftaran</h3>
            <p class="text-xs text-slate-400 mb-2">Lengkapi data berikut untuk mengajukan pendaftaran ke Admin You-One.</p>
            <div class="mb-5 px-3 py-2 rounded-xl bg-orange-500/10 border border-orange-500/20 text-[11px] text-orange-300 font-bold flex items-center gap-2">
                <i class="fa-solid fa-bolt text-orange-400"></i> Promo Biaya Pasang Rp 170rb - Langsung Aktif!
            </div>
            
            <form id="form-pendaftaran" onsubmit="kirimPendaftaranAjax(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap</label>
                    <input type="text" id="reg-nama" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-orange-500 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nomor WhatsApp</label>
                    <input type="tel" id="reg-whatsapp" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-orange-500 transition" placeholder="Contoh: 08123456789">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Alamat Lengkap / Dusun</label>
                    <textarea id="reg-alamat" required rows="2" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-orange-500 transition" placeholder="Nama Dusun, RT/RW, Patokan..."></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Pilihan Paket</label>
                    <select id="reg-paket" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-orange-500 transition">
                        <option value="" disabled selected>-- Pilih Paket Internet --</option>
                        <option value="Reguler (120rb)">Reguler (120rb)</option>
                        <option value="Business (170rb)">Business (170rb)</option>
                        <option value="Premium (225rb)">Premium (225rb)</option>
                    </select>
                </div>
                <button type="submit" id="btnSubmitDaftar" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-500 hover:from-orange-400 hover:to-amber-400 text-slate-950 font-black text-sm shadow-lg shadow-orange-500/20 transition flex items-center justify-center gap-2">
                    <i class="fa-brands fa-whatsapp text-lg"></i> Kirim Pendaftaran via WhatsApp
                </button>
            </form>
        </div>
    </div>

    <!-- Custom Alert Modal -->
    <div id="customAlertModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden">
        <div class="glass-card p-8 rounded-3xl w-full max-w-sm mx-4 text-center border border-orange-500/30 shadow-2xl flex flex-col items-center">
            <div id="alertIconWrapper" class="w-16 h-16 rounded-full flex items-center justify-center text-3xl mb-4"><i id="alertIcon" class="fa-solid"></i></div>
            <h4 id="alertTitle" class="text-xl font-bold text-slate-100 mb-2"></h4>
            <p id="customAlertMessage" class="text-sm text-slate-300 mb-6"></p>
            <button onclick="closeCustomAlert()" class="px-6 py-2.5 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-400 hover:to-amber-400 text-slate-950 text-sm font-extrabold rounded-xl shadow-md">OK</button>
        </div>
    </div>

    <script>
        // Inisialisasi Peta Leaflet untuk Area Kemitir & Sekitarnya
        document.addEventListener("DOMContentLoaded", function() {
            var lat = -7.224466377107296;
            var lng = 110.28261396916811;

            var map = L.map('landing-map', {zoomControl: false}).setView([lat, lng], 14);
            
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                maxZoom: 19
            }).addTo(map);

            L.marker([lat, lng]).addTo(map)
                .bindPopup("<b>You-One.net Coverage Area</b><br>Kemitir, Ngoho, Mbalong & Sekitarnya")
                .openPopup();
        });

        function bukaModalDaftar(paket) {
            const selectPaket = document.getElementById('reg-paket');
            if (paket) {
                selectPaket.value = paket;
            } else {
                selectPaket.selectedIndex = 0;
            }
            document.getElementById('modal-daftar').classList.remove('hidden');
        }

        function tutupModalDapat() {
            document.getElementById('modal-daftar').classList.add('hidden');
        }

        function bukaCustomAlert(type, judul, pesan) {
            const modal = document.getElementById("customAlertModal"), iw = document.getElementById("alertIconWrapper"), icon = document.getElementById("alertIcon");
            iw.className = "w-16 h-16 rounded-full flex items-center justify-center text-3xl mb-4"; icon.className = "fa-solid";
            if(type === 'success') { iw.classList.add("bg-emerald-500/10", "border", "border-emerald-500/20", "text-emerald-400"); icon.classList.add("fa-check"); }
            else if(type === 'warning') { iw.classList.add("bg-orange-500/10", "border", "border-orange-500/20", "text-orange-400"); icon.classList.add("fa-exclamation"); }
            else { iw.classList.add("bg-rose-500/10", "border", "border-rose-500/20", "text-rose-400"); icon.classList.add("fa-xmark"); }
            document.getElementById("alertTitle").innerText = judul; document.getElementById("customAlertMessage").innerHTML = pesan; modal.classList.remove("hidden");
        }

        function closeCustomAlert() { document.getElementById("customAlertModal").classList.add("hidden"); }

        // Fungsi Kirim Pendaftaran via AJAX Fonnte API
        function kirimPendaftaranAjax(e) {
            e.preventDefault();
            const btn = document.getElementById("btnSubmitDaftar");
            
            btn.disabled = true;
            btn.className = "w-full py-3.5 rounded-xl bg-slate-800 text-slate-500 text-sm font-bold flex items-center justify-center gap-2 cursor-not-allowed";
            btn.innerHTML = `<i class="fa-solid fa-spinner animate-spin"></i> Mengirim Pesan...`;

            const fd = new FormData();
            fd.append('nama', document.getElementById('reg-nama').value);
            fd.append('whatsapp', document.getElementById('reg-whatsapp').value);
            fd.append('alamat', document.getElementById('reg-alamat').value);
            fd.append('paket', document.getElementById('reg-paket').value);

            fetch('?action=send_registration', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.className = "w-full py-3.5 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-500 hover:from-orange-400 hover:to-amber-400 text-slate-950 font-black text-sm shadow-lg shadow-orange-500/20 transition flex items-center justify-center gap-2";
                btn.innerHTML = `<i class="fa-brands fa-whatsapp text-lg"></i> Kirim Pendaftaran via WhatsApp`;

                if (data.success) {
                    tutupModalDapat();
                    document.getElementById('form-pendaftaran').reset();
                    bukaCustomAlert('success', 'Berhasil!', 'Pendaftaran Anda berhasil dikirim ke WhatsApp Admin. Tim kami akan segera menghubungi Anda.');
                } else {
                    bukaCustomAlert('danger', 'Gagal!', 'Gagal mengirim pesan: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.className = "w-full py-3.5 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-500 hover:from-orange-400 hover:to-amber-400 text-slate-950 font-black text-sm shadow-lg shadow-orange-500/20 transition flex items-center justify-center gap-2";
                btn.innerHTML = `<i class="fa-brands fa-whatsapp text-lg"></i> Kirim Pendaftaran via WhatsApp`;
                bukaCustomAlert('danger', 'Error!', 'Terjadi kesalahan koneksi ke server.');
            });
        }
    </script>
</body>
</html>