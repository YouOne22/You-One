<?php
// =========================================================================
// 1. KREDENSIAL DATABASE & SISTEM
// =========================================================================
define('DB_HOST', 'sql113.infinityfree.com');
define('DB_USER', 'if0_42313434'); 
define('DB_PASS', 'EtbD4w1WLx9Lmvd'); 
define('DB_NAME', 'if0_42313434_db_billing'); 

// =========================================================================
// 2. KONFIGURASI API FONNTE & WHATSAPP OWNER
// =========================================================================
define('TOKEN_FONNTE', 'eoTkcnhieYpXdHCo7M5H');
define('NO_OWNER', '082243167575');

// =========================================================================
// 3. MENGAKTIFKAN MODE PELAPORAN EROR UNTUK MYSQLI
// =========================================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Membuat Koneksi ke Database
    $koneksi = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Mengatur Karakter Set ke UTF-8
    mysqli_set_charset($koneksi, "utf8mb4");

} catch (mysqli_sql_exception $e) {
    // Penanganan Eror jika Gagal Terhubung
    die("
    <div style='background-color: #0b0e14; color: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: sans-serif; padding: 20px;'>
        <div style='background-color: rgba(239, 68, 68, 0.1); border: 1px dashed #ef4444; padding: 24px; border-radius: 8px; max-width: 500px; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,0.5);'>
            <h3 style='color: #fca5a5; margin-top: 0; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;'>
                ❌ Gagal Terhubung ke Database
            </h3>
            <p style='color: #9ca3af; font-size: 14px; margin-bottom: 0; line-height: 1.5;'>
                Sistem tidak dapat terhubung ke server MySQL. Silakan periksa kembali konfigurasi host atau nama database di file <code>config/koneksi.php</code>.<br><br>
                <span style='color: #ef4444; font-family: monospace; font-size: 12px;'>Detail: " . $e->getMessage() . "</span>
            </p>
        </div>
    </div>
    ");
}

// =========================================================================
// 4. FUNGSI INTEGRASI GOOGLE SHEETS (Single Update - Dusun Mbalong, Kemitir, Ngoho)
// =========================================================================
if (!function_exists('update_google_sheet')) {
    function update_google_sheet($dusun, $data_transaksi, $action = 'update') {
        // Pemetaan URL Web App Apps Script per Dusun
        $webhook_urls = [
            'Mbalong' => 'https://script.google.com/macros/s/AKfycbzgs9qeVN0eM8S5ZHbZJ5sFmQS58gdfeI1eOgE-C-hkWF9rhxm9O_VMtvCvoQZZbkP2TA/exec',
            'Kemitir' => 'https://script.google.com/macros/s/AKfycbwOg4yjejvYvwIrWfC1AsgPb2GRAJIn2f1MICV-l_qKAsk1vyfyP6z46kTcLYuwDWn9Dg/exec',
            'Ngoho'   => 'https://script.google.com/macros/s/AKfycbwIRSmlFf2D6Db11QALKW5Bw03MqTMP9aNu2JIB4Ov8ZJV8HTDDGx1_MCc_Mru2qtnj7w/exec'
        ];

        // Cek apakah URL untuk dusun tersebut tersedia
        if (!isset($webhook_urls[$dusun]) || empty($webhook_urls[$dusun])) {
            return false;
        }

        $url = $webhook_urls[$dusun];
        
        // Sisipkan parameter 'action' ke dalam payload agar Google Apps Script tahu apakah ini penambahan baris baru atau update
        if (is_array($data_transaksi)) {
            $data_transaksi['action'] = $action;
        }

        $payload = json_encode($data_transaksi);

        // Kirim data via cURL ke Google Apps Script
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // PENGAMAN ANTI-MACET (Dibatasi maksimal 3 detik)
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);        

        $response = @curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}

// =========================================================================
// 5. FUNGSI INTEGRASI BATCH GOOGLE SHEETS (Mencegah Limit CPU Hosting Free)
// =========================================================================
if (!function_exists('update_google_sheet_batch')) {
    function update_google_sheet_batch($data_batch) {
        $webhook_urls = [
            'Mbalong' => 'https://script.google.com/macros/s/AKfycbzgs9qeVN0eM8S5ZHbZJ5sFmQS58gdfeI1eOgE-C-hkWF9rhxm9O_VMtvCvoQZZbkP2TA/exec',
            'Kemitir' => 'https://script.google.com/macros/s/AKfycbwOg4yjejvYvwIrWfC1AsgPb2GRAJIn2f1MICV-l_qKAsk1vyfyP6z46kTcLYuwDWn9Dg/exec',
            'Ngoho'   => 'https://script.google.com/macros/s/AKfycbxDZc8y16qqyTdn2sln_733xxTfPgDwtkOYSD3eZvM5-v_uynVKAkeWuiZ5yNVYa7wXuw/exec'
        ];

        // Grouping data berdasarkan Dusun
        $grouped = [];
        foreach ($data_batch as $item) {
            $dusun = $item['dusun'] ?? '';
            if (!empty($dusun)) {
                $grouped[$dusun][] = $item;
            }
        }

        // Kirim 1 request per Dusun (Total maksimal 3x request HTTP)
        foreach ($grouped as $dusun => $items) {
            if (isset($webhook_urls[$dusun]) && !empty($webhook_urls[$dusun])) {
                $url = $webhook_urls[$dusun];
                
                $payload = json_encode([
                    'action' => 'batch_update',
                    'items'  => $items
                ]);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload)
                ]);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);

                @curl_exec($ch);
                curl_close($ch);
            }
        }
        return true;
    }
}
?>