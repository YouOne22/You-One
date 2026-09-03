<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);    
    
// Set durasi cookie session menjadi 1 tahun (365 hari = 31.536.000 detik)
$session_lifetime = 365 * 24 * 60 * 60;

// Konfigurasi cookie session sebelum session_start()
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Set waktu penyimpanan data session di server
ini_set('session.gc_maxlifetime', $session_lifetime);

// Mulai session PHP
session_start();

// Jika user sudah login sebelumnya, langsung lempar ke halaman dashboard utama
if (isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Hubungkan ke koneksi database
require 'config/koneksi.php';

$error = '';

// Proses ketika tombol Login ditekan
if (isset($_POST['submit_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $onesignal_id = trim($_POST['onesignal_id'] ?? '');

    if (!empty($username) && !empty($password)) {
        // Menggunakan Prepared Statement untuk keamanan dari SQL Injection
        $stmt = $koneksi->prepare("SELECT id, username, password, nama, role, dusun FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();

            // Verifikasi password yang di-hash di database
            if (password_verify($password, $row['password'])) {
                // Mencegah Session Fixation Attack
                session_regenerate_id(true);

                // Set data session untuk hak akses
                $_SESSION['login'] = true;
                $_SESSION['id_user'] = $row['id'];
                $_SESSION['nama'] = $row['nama'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['dusun_pengelola'] = $row['dusun']; 

                // Update onesignal_id ke database menggunakan Prepared Statement jika ID tidak kosong
                if (!empty($onesignal_id)) {
                    $user_id = $row['id'];
                    $update_stmt = $koneksi->prepare("UPDATE users SET onesignal_id = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $onesignal_id, $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }

                $stmt->close();

                // Redirect ke halaman utama
                header("Location: index.php");
                exit;
            } else {
                $error = "Password yang Anda masukkan salah!";
            }
        } else {
            $error = "Username tidak terdaftar di sistem!";
        }
        $stmt->close();
    } else {
        $error = "Username dan Password wajib diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator - You-One.net</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0b0f19;
            background-image: 
                radial-gradient(circle at 85% 50%, rgba(245, 158, 11, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 15% 20%, rgba(234, 88, 12, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
        }

        /* Ambient Glow Effect */
        .glow-input:focus-within {
            box-shadow: 0 0 20px -3px rgba(245, 158, 11, 0.3);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between antialiased selection:bg-amber-500 selection:text-slate-950 text-slate-100">

    <!-- TOP NAVBAR (Matching Landing Page Header) -->
    <header class="w-full border-b border-slate-800/60 bg-slate-950/40 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="landing.php" class="flex items-center gap-3 group">
                <img src="You One Creative.png" alt="You-One.net Logo" class="h-10 sm:h-12 w-auto object-contain transition duration-300 group-hover:scale-105">
            </a>

            <!-- Navigation / Back to Landing Button -->
            <a href="landing.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 border border-slate-700/80 text-xs font-bold text-slate-200 hover:text-amber-400 hover:border-amber-500/50 transition duration-200 shadow-sm active:scale-95">
                <i class="fa-solid fa-arrow-left text-amber-400"></i>
                <span>Kembali ke Beranda</span>
            </a>
        </div>
    </header>

    <!-- MAIN HERO CONTENT AREA (Full Bleed Layout) -->
    <main class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 my-auto">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            
            <!-- SISI KIRI: TEXT HERO & BRANDING (7 Kolom) -->
            <div class="lg:col-span-7 space-y-6 text-left pr-0 lg:pr-6">
                
                <!-- Badge Platform -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider">
                    <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                    Portal Akses Administrator
                </div>

                <!-- Main Title Header -->
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-[1.15] text-white">
                    Kelola Sistem Billing & Mapping Jaringan <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-amber-300 to-orange-400">Secara Terpusat.</span>
                </h1>

                <p class="text-sm sm:text-base text-slate-400 leading-relaxed max-w-2xl">
                    Masuk ke dasbor utama untuk mengontrol otomatisasi penagihan bulanan, isolir pelanggan, pemantauan status ODP/ODC, serta manajemen tiket dukungan teknis.
                </p>

                <!-- Quick Highlight Indicators -->
                <div class="pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-xl">
                    <div class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="fa-solid fa-shield-halved text-amber-400 text-sm"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-200">Keamanan Terenkripsi</h4>
                            <p class="text-[11px] text-slate-400 mt-0.5">Otentikasi bertingkat & pencegahan SQLi/XSS.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-900/60 border border-slate-800/80">
                        <div class="w-8 h-8 rounded-lg bg-orange-500/10 border border-orange-500/20 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="fa-solid fa-bolt text-orange-400 text-sm"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-200">Real-time Monitoring</h4>
                            <p class="text-[11px] text-slate-400 mt-0.5">Integrasi peta GIS & status tagihan otomatis.</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- SISI KANAN: FORM LOGIN CARD (5 Kolom - Menggantikan posisi Mockup HP) -->
            <div class="lg:col-span-5">
                <div class="w-full max-w-md mx-auto p-8 rounded-3xl bg-slate-900/80 border border-slate-800 backdrop-blur-xl shadow-2xl shadow-black/60 relative overflow-hidden">
                    
                    <!-- Top Accent Light Bar -->
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>

                    <!-- Header Form -->
                    <div class="mb-8 text-left space-y-1">
                        <h3 class="text-2xl font-bold text-white tracking-tight">Selamat Datang</h3>
                        <p class="text-xs text-slate-400">Silakan masukkan kredensial akun Anda untuk melanjutkannya.</p>
                    </div>

                    <!-- Form Login -->
                    <form action="" method="POST" class="space-y-5">
                        <!-- Input Hidden untuk Kodular / OneSignal ID -->
                        <input type="hidden" name="onesignal_id" id="onesignal_id_input" value="">

                        <!-- Field Username -->
                        <div class="space-y-1.5 text-left">
                            <label for="username" class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Username Administrator</label>
                            <div class="relative glow-input rounded-xl transition duration-200">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-500">
                                    <i class="fa-solid fa-user text-xs"></i>
                                </span>
                                <input type="text" name="username" id="username" required autocomplete="off"
                                    class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-10 pr-4 py-3.5 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition duration-200"
                                    placeholder="Masukkan username">
                            </div>
                        </div>

                        <!-- Field Password -->
                        <div class="space-y-1.5 text-left">
                            <label for="password" class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Kata Sandi</label>
                            <div class="relative glow-input rounded-xl transition duration-200">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-500">
                                    <i class="fa-solid fa-lock text-xs"></i>
                                </span>
                                <input type="password" name="password" id="password" required
                                    class="w-full bg-slate-950/80 border border-slate-800 rounded-xl pl-10 pr-12 py-3.5 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition duration-200"
                                    placeholder="••••••••">
                                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-amber-400 focus:outline-none transition">
                                    <i class="fa-solid fa-eye text-xs" id="eye-icon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button (Gaya persis tombol utama Landing Page) -->
                        <button type="submit" name="submit_login"
                            class="w-full mt-4 bg-gradient-to-r from-amber-500 via-amber-400 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-extrabold text-xs uppercase tracking-wider py-4 rounded-xl transition duration-300 shadow-lg shadow-amber-500/20 active:scale-[0.98] flex items-center justify-center gap-2 group cursor-pointer">
                            <span>Login Administrator</span>
                            <i class="fa-solid fa-arrow-right-to-bracket text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </main>

    <!-- FOOTER (Matching Landing Page Footer Style) -->
    <footer class="w-full border-t border-slate-800/60 py-6 bg-slate-950/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
            <p class="font-medium">&copy; 2026 YOU-ONE.NET. ALL RIGHTS RESERVED.</p>
            <div class="flex items-center gap-2 text-amber-400 font-semibold">
                <i class="fa-solid fa-shield-halved text-xs"></i>
                <span>Protected Administrator Portal</span>
            </div>
        </div>
    </footer>

    <!-- JavaScript Kodular & Toggle Password -->
    <script>
    function tampungID(id) {
        if(id) {
            document.getElementById('onesignal_id_input').value = id;
            console.log("OneSignal ID berhasil dimasukkan ke form: " + id);
        }
    }

    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
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
    </script>

    <!-- SweetAlert2 Alert jika terjadi kegagalan Login -->
    <?php if (!empty($error)) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Akses Ditolak!',
            text: '<?= htmlspecialchars($error, ENT_QUOTES); ?>',
            background: '#0f172a',
            color: '#f8fafc',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'Coba Lagi',
            customClass: {
                popup: 'border border-amber-500/30 rounded-2xl shadow-2xl backdrop-blur-xl',
                title: 'font-bold tracking-wide text-amber-500 text-lg',
                htmlContainer: 'text-sm text-slate-300 mt-2',
                confirmButton: 'font-bold px-5 py-2.5 rounded-xl text-xs uppercase tracking-wider text-slate-950 shadow-md'
            }
        });
    });
    </script>
    <?php endif; ?>

</body>
</html>