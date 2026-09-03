<?php
ob_start();
// 1. Mulai session dan cek apakah user sudah login
session_start();

// RULE 1: Jika user belum login dan mencoba buka index.php, dialihkan ke landing.php
if (!isset($_SESSION['login'])) {
    header("Location: landing.php");
    exit;
}

// 2. Ambil data session user & Normalisasi Akses Admin
$nama_user       = $_SESSION['nama'] ?? 'User';
$role_user       = $_SESSION['role'] ?? 'Petugas';
$dusun_pengelola = $_SESSION['dusun_pengelola'] ?? ''; // Mengambil dusun petugas

$role_upper = strtoupper($role_user);
$is_admin   = in_array($role_upper, ['ADMIN', 'ADMINISTRATOR', 'SUPERADMIN'], true);

// 3. Logika untuk menentukan halaman mana yang sedang dibuka
$page        = isset($_GET['page']) ? $_GET['page'] : '';
$dusun_aktif = isset($_GET['dusun']) ? $_GET['dusun'] : '';

if ($page == '') {
    $page = 'dashboard'; // Semua user otomatis diarahkan ke dashboard saat awal masuk
}

$is_tagihan_page   = ($page == 'tagihan');
$is_pengingat_page = in_array($page, ['pengingat', 'maintenance', 'jatuhtempo', 'isolir']);
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You-One.net - Billing & Network Management</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        darkbg: '#060a12',
                        cardbg: '#0b0f17'
                    }
                }
            }
        }
    </script>
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- LEAFLET MAP CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #060a12;
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(249, 115, 22, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 90% 90%, rgba(245, 158, 11, 0.08) 0%, transparent 45%),
                linear-gradient(135deg, rgba(15, 23, 42, 0.6) 0%, rgba(6, 10, 18, 0.95) 100%);
            background-attachment: fixed;
        }

        /* Custom Scrollbar Sleek Dark */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(6, 10, 18, 0.8);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(249, 115, 22, 0.25);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(249, 115, 22, 0.5);
        }

        /* Glassmorphism ultra premium */
        .glass-card { 
            background: rgba(11, 15, 23, 0.7); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.07); 
            box-shadow: 0 10px 30px 0 rgba(0, 0, 0, 0.4);
        }

        .glass-sidebar {
            background: rgba(8, 12, 20, 0.85);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-right: 1px solid rgba(255, 255, 255, 0.06);
        }

        .glass-header {
            background: rgba(6, 10, 18, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        
        /* Highlight kontras menu aktif */
        .nav-active-glow {
            background: linear-gradient(135deg, #f97316 0%, #f59e0b 100%);
            color: #020617 !important;
            box-shadow: 0 4px 20px rgba(249, 115, 22, 0.35);
        }
        .nav-active-glow i {
            color: #020617 !important;
        }

        /* ANIMASI: Indikator Loading Atas */
        @keyframes loadingBar {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .animate-loading-bar {
            animation: loadingBar 1.2s infinite linear;
            transform-origin: left;
        }
    </style>
</head>
<body class="text-slate-200 h-screen overflow-hidden antialiased flex selection:bg-orange-500 selection:text-slate-950">

    <!-- GLOBAL TOP LOADING BAR -->
    <div id="global-loader" class="fixed top-0 left-0 w-full h-1 bg-slate-950 z-[70] overflow-hidden hidden">
        <div class="h-full w-full bg-gradient-to-r from-orange-500 via-amber-400 to-rose-500 animate-loading-bar"></div>
    </div>

    <!-- SIDEBAR OVERLAY FOR MOBILE -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-40 hidden lg:hidden transition-opacity duration-300" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 glass-sidebar flex flex-col shrink-0 fixed inset-y-0 left-0 z-50 transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-300 shadow-2xl">
        <div class="flex-1 overflow-y-auto">
            <!-- Sidebar Header / Logo -->
            <div class="p-5 border-b border-slate-800/60 flex items-center justify-between">
                <a href="index.php?page=dashboard" class="flex items-center gap-3 group">
                    <div class="relative">
                        <img src="You One Creative.png" alt="Logo" class="h-15 w-auto object-contain filter drop-shadow-[0_0_12px_rgba(249,115,22,0.4)] group-hover:scale-105 transition duration-300">
                    </div>
                </a>
                <button onclick="toggleSidebar()" 
                        ontouchstart="this.classList.add('bg-white/10', 'scale-90', 'text-orange-400')" 
                        ontouchend="this.classList.remove('bg-white/10', 'scale-90', 'text-orange-400')"
                        ontouchcancel="this.classList.remove('bg-white/10', 'scale-90', 'text-orange-400')"
                        class="lg:hidden text-slate-400 hover:text-slate-100 w-8 h-8 flex items-center justify-center rounded-xl hover:bg-slate-800/60 transition duration-150 focus:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <div class="p-4 space-y-6">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-500 px-3 mb-3 flex items-center gap-2">
                        <span>Navigasi Utama</span>
                        <span class="h-[1px] flex-1 bg-slate-800/80"></span>
                    </p>
                    <ul class="space-y-1.5">
                        <li>
                            <a href="index.php?page=dashboard" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'dashboard') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-chart-pie w-5 text-center <?= ($page == 'dashboard') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Dashboard
                            </a>
                        </li>
                        
                        <li>
                            <a href="index.php?page=pelanggan" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'pelanggan') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-users w-5 text-center <?= ($page == 'pelanggan') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Pelanggan
                            </a>
                        </li>

                        <!-- MENU PETA NETWORK -->
                        <li>
                            <a href="index.php?page=peta" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'peta') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-map-location-dot w-5 text-center <?= ($page == 'peta') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Peta Network & ODP
                            </a>
                        </li>

                        <!-- MENU DATA ODP / ODC (Hanya Admin) -->
                        <?php if ($is_admin) : ?>
                        <li>
                            <a href="index.php?page=odp" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'odp') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-boxes-stacked w-5 text-center <?= ($page == 'odp') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Data ODP / ODC
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($is_admin) : ?>
                        <li>
                            <a href="index.php?page=paket" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'paket') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-box-open w-5 text-center <?= ($page == 'paket') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Paket Internet
                            </a>
                        </li>
                        <?php endif; ?>

                        <li class="space-y-1">
                            <button onclick="toggleSubMenu('submenu-tagihan')" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition duration-200 border border-transparent <?= $is_tagihan_page ? 'bg-slate-900/90 text-slate-100 shadow-md border-slate-800' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <div class="flex items-center gap-3 font-bold">
                                    <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-orange-400"></i> Tagihan
                                </div>
                                <i id="arrow-tagihan" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-300 <?= $is_tagihan_page ? 'rotate-180 text-orange-400' : 'text-slate-500' ?>"></i>
                            </button>

                            <ul id="submenu-tagihan" class="<?= $is_tagihan_page ? '' : 'hidden' ?> pl-7 space-y-1 mt-1 border-l border-slate-800/80 ml-5">
                                <?php 
                                $list_dusun = ['Kemitir', 'Ngoho', 'Mbalong'];
                                foreach ($list_dusun as $d) : 
                                    if ($is_admin || $d == $dusun_pengelola) :
                                        $is_active = ($page == 'tagihan' && $dusun_aktif == $d);
                                ?>
                                    <li>
                                        <a href="index.php?page=tagihan&dusun=<?= $d ?>" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition duration-150 <?= $is_active ? 'bg-orange-500/10 text-orange-400 border border-orange-500/20 font-bold shadow-sm' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/50' ?>">
                                            <i class="fa-solid fa-location-dot text-[10px] <?= $is_active ? 'text-orange-400' : 'text-slate-600' ?>"></i> <?= $d ?>
                                        </a>
                                    </li>
                                <?php 
                                    endif; 
                                endforeach; 
                                ?>
                            </ul>
                        </li>

                        <li>
                            <a href="index.php?page=pembayaran" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'pembayaran') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-wallet w-5 text-center <?= ($page == 'pembayaran') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Pembayaran
                            </a>
                        </li>
                        
                        <?php if ($is_admin) : ?>
                        <li>
                            <a href="index.php?page=laporan" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'laporan') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-chart-line w-5 text-center <?= ($page == 'laporan') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Laporan
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="space-y-1">
                            <button onclick="toggleSubMenu('submenu-pengingat')" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition duration-200 border border-transparent <?= $is_pengingat_page ? 'bg-slate-900/90 text-slate-100 shadow-md border-slate-800' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <div class="flex items-center gap-3 font-bold">
                                    <i class="fa-solid fa-bell w-5 text-center text-orange-400"></i> Pengingat
                                </div>
                                <i id="arrow-pengingat" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-300 <?= $is_pengingat_page ? 'rotate-180 text-orange-400' : 'text-slate-500' ?>"></i>
                            </button>

                            <ul id="submenu-pengingat" class="<?= $is_pengingat_page ? '' : 'hidden' ?> pl-7 space-y-1 mt-1 border-l border-slate-800/80 ml-5">
                                <li>
                                    <a href="index.php?page=jatuhtempo" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition duration-150 <?= ($page == 'jatuhtempo') ? 'bg-orange-500/10 text-orange-400 border border-orange-500/20 font-bold shadow-sm' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/50' ?>">
                                        <i class="fa-solid fa-clock text-[10px] <?= ($page == 'jatuhtempo') ? 'text-orange-400' : 'text-slate-600' ?>"></i> Jatuh Tempo
                                    </a>
                                </li>
                                <li>
                                    <a href="index.php?page=isolir" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition duration-150 <?= ($page == 'isolir') ? 'bg-orange-500/10 text-orange-400 border border-orange-500/20 font-bold shadow-sm' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/50' ?>">
                                        <i class="fa-solid fa-ban text-[10px] <?= ($page == 'isolir') ? 'text-orange-400' : 'text-slate-600' ?>"></i> Isolir
                                    </a>
                                </li>
                                <li>
                                    <a href="index.php?page=maintenance" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition duration-150 <?= ($page == 'maintenance') ? 'bg-orange-500/10 text-orange-400 border border-orange-500/20 font-bold shadow-sm' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/50' ?>">
                                        <i class="fa-solid fa-screwdriver-wrench text-[10px] <?= ($page == 'maintenance') ? 'text-orange-400' : 'text-slate-600' ?>"></i> Maintenance
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
                
                <?php if ($is_admin) : ?>
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-slate-500 px-3 mb-3 flex items-center gap-2">
                        <span>Konfigurasi Sistem</span>
                        <span class="h-[1px] flex-1 bg-slate-800/80"></span>
                    </p>
                    <ul class="space-y-1.5">
                        <li>
                            <a href="index.php?page=pengaturan" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'pengaturan') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-gear w-5 text-center <?= ($page == 'pengaturan') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Pengaturan
                            </a>
                        </li>
                        <li>
                            <a href="index.php?page=pengguna" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition duration-200 <?= ($page == 'pengguna') ? 'nav-active-glow' : 'text-slate-400 hover:bg-slate-900/80 hover:text-slate-200' ?>">
                                <i class="fa-solid fa-user-gear w-5 text-center <?= ($page == 'pengguna') ? 'text-slate-950' : 'text-orange-400' ?>"></i> Pengguna
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT WRAPPER -->
    <main class="flex-1 flex flex-col min-w-0">
        <!-- HEADER TOP BAR -->
        <header class="h-16 glass-header flex items-center justify-between px-4 lg:px-8 shrink-0 relative z-30">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" 
                        ontouchstart="this.classList.add('bg-white/10', 'scale-90', 'text-orange-400')" 
                        ontouchend="this.classList.remove('bg-white/10', 'scale-90', 'text-orange-400')"
                        ontouchcancel="this.classList.remove('bg-white/10', 'scale-90', 'text-orange-400')"
                        class="lg:hidden text-slate-400 w-9 h-9 -ml-1 rounded-xl flex items-center justify-center hover:bg-slate-800/60 transition-all duration-150 focus:outline-none">
                    <i class="fa-solid fa-bars-staggered text-lg"></i>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-1.5 h-5 bg-gradient-to-b from-orange-500 to-amber-500 rounded-full hidden sm:block"></div>
                    <h2 class="text-base sm:text-lg font-bold capitalize text-slate-100 tracking-wide font-mono">
                        <?= str_replace('_', ' ', $page); ?>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-3 lg:gap-6">
                <!-- Clock Realtime -->
                <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-950/60 border border-slate-800/80 text-xs text-slate-400 font-mono shadow-inner" id="realtime-clock">
                    <i class="fa-regular fa-clock text-orange-400 text-xs"></i>
                    <span>Loading...</span>
                </div>
                
                <?php
                if ($is_admin) {
                    $avatar_theme = "from-orange-500 to-amber-500 ring-2 ring-orange-500/40 shadow-[0_0_15px_rgba(249,115,22,0.35)]";
                    $role_badge = "<span class='absolute -bottom-1 -right-1 bg-slate-950 border border-orange-500 text-[8px] text-orange-400 w-4 h-4 rounded-full flex items-center justify-center shadow-md'><i class='fa-solid fa-crown scale-75'></i></span>";
                    $avatar_icon = "<i class='fa-solid fa-user-gear text-xs text-slate-950'></i>";
                    $role_text_color = "text-orange-400";
                } else {
                    $avatar_theme = "from-amber-500 to-orange-600 ring-2 ring-amber-500/30 shadow-[0_0_15px_rgba(245,158,11,0.25)]";
                    $role_badge = "<span class='absolute -bottom-1 -right-1 bg-slate-950 border border-amber-500 text-[8px] text-amber-400 w-4 h-4 rounded-full flex items-center justify-center shadow-md'><i class='fa-solid fa-user-shield scale-75'></i></span>";
                    $avatar_icon = "<i class='fa-solid fa-user text-xs text-slate-950'></i>";
                    $role_text_color = "text-amber-400";
                }
                ?>
                
                <!-- Profile & Logout Bar -->
                <div class="flex items-center gap-2 sm:gap-3 pl-3 border-l border-slate-800/80">
                    <div class="hidden sm:flex flex-col items-end">
                        <p class="text-xs font-bold text-slate-100 leading-tight"><?= htmlspecialchars($nama_user); ?></p>
                        <p class="text-[9px] <?= $role_text_color; ?> font-extrabold tracking-wider uppercase mt-0.5"><?= htmlspecialchars($role_user); ?></p>
                    </div>
                    
                    <div class="relative cursor-pointer" title="<?= htmlspecialchars($nama_user); ?> (<?= htmlspecialchars($role_user); ?>)">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-tr <?= $avatar_theme; ?> flex items-center justify-center select-none shadow-md">
                            <?= $avatar_icon; ?>
                        </div>
                        <?= $role_badge; ?>
                    </div>
                    
                    <button onclick="pemicuLogout()" class="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 text-rose-400 flex items-center justify-center transition shadow-sm ml-1" title="Keluar dari Sistem">
                        <i class="fa-solid fa-power-off text-xs"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- DYNAMIC MODULE CONTENT -->
        <div class="flex-1 p-4 lg:p-8 pb-24 overflow-y-auto">
            <?php 
                // Modul yang hanya boleh diakses oleh Admin
                $modul_admin_only = ['paket', 'laporan', 'pengaturan', 'pengguna', 'odp'];

                if (!$is_admin && in_array($page, $modul_admin_only)) {
                    echo "
                    <div class='glass-card p-8 rounded-2xl border border-rose-500/20 bg-slate-900/60 text-center max-w-lg mx-auto mt-10 backdrop-blur-md shadow-2xl'>
                        <div class='w-16 h-16 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl shadow-inner'>
                            <i class='fa-solid fa-lock'></i>
                        </div>
                        <h3 class='text-xl font-bold text-slate-100 mb-2'>Akses Ditolak</h3>
                        <p class='text-xs text-slate-400 mb-6 leading-relaxed'>Maaf, Anda tidak memiliki hak akses untuk membuka modul ini. Silahkan hubungi Administrator.</p>
                        <a href='index.php?page=dashboard' class='inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold transition border border-slate-700/50 shadow-md'>
                            <i class='fa-solid fa-arrow-left'></i> Kembali ke Dashboard
                        </a>
                    </div>";
                } else {
                    $file_page = "modules/" . $page . ".php";
                    if (file_exists($file_page)) { 
                        include $file_page; 
                    } else { 
                        echo "
                        <div class='glass-card p-12 text-center rounded-2xl border border-slate-800/80 max-w-md mx-auto mt-10 shadow-2xl'>
                            <div class='w-14 h-14 bg-amber-500/10 border border-amber-500/20 text-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl'>
                                <i class='fa-solid fa-folder-open'></i>
                            </div>
                            <h4 class='text-slate-200 font-bold mb-1'>Modul Belum Tersedia</h4>
                            <p class='text-xs text-slate-400'>Halaman <span class='text-orange-400 font-mono font-bold'>modules/{$page}.php</span> belum dibuat.</p>
                        </div>"; 
                    }
                }
            ?>
        </div>
    </main>

    <!-- Bottom Navigation Bar (Khusus Mobile) -->
    <div class="fixed bottom-0 left-0 right-0 z-40 bg-slate-950/90 backdrop-blur-xl border-t border-slate-800/80 px-3 py-2 flex justify-around items-center lg:hidden shadow-2xl">
        
        <!-- Dashboard -->
        <a href="index.php?page=dashboard" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl transition <?= ($page == 'dashboard') ? 'text-orange-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
            <i class="fa-solid fa-chart-pie text-base"></i>
            <span class="text-[10px]">Dashboard</span>
        </a>

        <!-- Pelanggan -->
        <a href="index.php?page=pelanggan" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl transition <?= ($page == 'pelanggan') ? 'text-orange-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
            <i class="fa-solid fa-users text-base"></i>
            <span class="text-[10px]">Pelanggan</span>
        </a>

        <!-- Tagihan -->
        <a href="index.php?page=tagihan" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl transition <?= ($page == 'tagihan') ? 'text-orange-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
            <i class="fa-solid fa-file-invoice-dollar text-base"></i>
            <span class="text-[10px]">Tagihan</span>
        </a>

        <!-- Peta -->
        <a href="index.php?page=peta" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl transition <?= ($page == 'peta') ? 'text-orange-400 font-bold' : 'text-slate-400 hover:text-slate-200' ?>">
            <i class="fa-solid fa-map-location-dot text-base"></i>
            <span class="text-[10px]">Peta</span>
        </a>

    </div>

    <!-- JAVASCRIPT FUNCTIONS -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function toggleSubMenu(id) {
            const subMenu = document.getElementById(id);
            const arrow = document.getElementById('arrow-' + id.split('-')[1]);
            if (subMenu) subMenu.classList.toggle('hidden');
            if (arrow) arrow.classList.toggle('rotate-180');
        }

        function updateClock() {
            const now = new Date();
            const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
            const clockElem = document.getElementById('realtime-clock');
            if (clockElem) {
                clockElem.innerHTML = '<i class="fa-regular fa-clock text-orange-400 text-xs"></i> <span>' + 
                    now.toLocaleDateString('id-ID', options) + ' | <span class="text-orange-400 font-bold">' + 
                    now.toLocaleTimeString('id-ID') + '</span></span>';
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        function pemicuLogout() {
            Swal.fire({
                title: 'Keluar Sistem?',
                text: 'Apakah Anda yakin ingin mengakhiri sesi administrasi ini?',
                icon: 'warning',
                showCancelButton: true,
                background: '#0b0f17',
                color: '#f1f5f9',
                confirmButtonColor: '#f43f5e', // Rose-500
                cancelButtonColor: '#334155',  // Slate-700
                confirmButtonText: 'Ya, Keluar!',
                cancelButtonText: 'Batal',
                customClass: {
                    popup: 'border border-slate-800 rounded-2xl shadow-2xl backdrop-blur-md',
                    title: 'font-bold tracking-wide text-lg text-slate-100',
                    htmlContainer: 'text-xs text-slate-300 mt-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const loader = document.getElementById("global-loader");
                    if (loader) loader.classList.remove("hidden");
                    window.location.href = 'logout.php';
                }
            });
        }

        // OTOMATISASI INDIKATOR STRIP ATAS PADA LINK MENU UTAMA
        document.addEventListener("DOMContentLoaded", function () {
            const menuLinks = document.querySelectorAll("aside a, .sidebar a, nav a");
            const loader = document.getElementById("global-loader");

            menuLinks.forEach(link => {
                link.addEventListener("click", function (e) {
                    let href = this.getAttribute("href");
                    if (href && href !== "#" && !href.startsWith("javascript:") && !this.getAttribute("target")) {
                        if (loader) {
                            loader.classList.remove("hidden");
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>