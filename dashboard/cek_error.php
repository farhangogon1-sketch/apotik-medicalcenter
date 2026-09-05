<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnostik Error Apotik</title>";
echo "<style>body{font-family:sans-serif;background:#0f172a;color:#f8fafc;padding:20px;line-height:1.5;}";
echo ".box{background:#1e293b;padding:16px;border-radius:8px;margin-bottom:16px;border:1px solid #334155;}";
echo ".ok{color:#10b981;font-weight:bold;} .err{color:#ef4444;font-weight:bold;} pre{background:#020617;padding:10px;border-radius:6px;overflow:auto;}";
echo "</style></head><body>";

echo "<h1>🔍 Diagnostik Sistem Apotik Medical Center</h1>";

// 1. PHP Info
echo "<div class='box'><h3>1. Status PHP</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Server Time: " . date('Y-m-d H:i:s') . "</div>";

// 2. Database Connection
echo "<div class='box'><h3>2. Koneksi Database</h3>";
try {
    require_once __DIR__ . '/../config/database.php';
    if (isset($pdo)) {
        echo "<span class='ok'>✅ Koneksi Database Berhasil!</span><br>";
        $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
        $currDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
        echo "Nama Database Aktif: <strong style='color:#38bdf8; font-size:1.1rem;'>" . htmlspecialchars($currDb) . "</strong><br>";
        echo "MySQL/MariaDB Version: " . htmlspecialchars($ver) . "<br><br>";

        // Tampilkan semua tabel yang ada di database ini
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($allTables)) {
            echo "<span class='err' style='font-size:1.1rem;'>⚠️ DATABASE INI KOSONG (0 TABEL)!</span><br>";
            echo "Database <strong>" . htmlspecialchars($currDb) . "</strong> saat ini belum memiliki tabel sama sekali.<br>";
        } else {
            echo "<strong>Daftar tabel yang ditemukan di database " . htmlspecialchars($currDb) . " (" . count($allTables) . " tabel):</strong><br>";
            echo "<div style='display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; margin: 10px 0; background: #020617; padding: 12px; border-radius: 6px;'>";
            foreach ($allTables as $tbl) {
                $count = 0;
                try {
                    $count = (int)$pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
                } catch (Throwable $e) {}
                echo "<div><strong style='color:#38bdf8;'>• " . htmlspecialchars($tbl) . "</strong> <small style='color:#94a3b8;'>(" . $count . " baris)</small></div>";
            }
            echo "</div>";
        }

        // Tampilkan daftar database lain milik akun ini jika ada
        try {
            $allDbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($allDbs)) {
                echo "<strong>Daftar database yang tersedia untuk user ini:</strong><br>";
                echo "<pre>" . htmlspecialchars(implode(', ', $allDbs)) . "</pre>";
            }
        } catch (Throwable $e) {}
    } else {
        echo "<span class='err'>❌ Variabel \$pdo tidak ditemukan.</span><br>";
    }
} catch (Throwable $e) {
    echo "<span class='err'>❌ Gagal Koneksi: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "</div>";

// 3. Cek User Session
echo "<div class='box'><h3>3. Data Sesi Login User</h3>";
if (isset($_SESSION['user_rh'])) {
    echo "<span class='ok'>User sedang login:</span>";
    echo "<pre>" . htmlspecialchars(print_r($_SESSION['user_rh'], true)) . "</pre>";
} else {
    echo "Tidak ada sesi login (Guest / Belum login).";
}
echo "</div>";

// 4. Test Query-query Utama Rekap Farmasi
echo "<div class='box'><h3>4. Uji Query Rekap Farmasi & Database</h3>";
if (isset($pdo)) {
    // Cek tabel user_rh atau users
    $userCandidates = array_filter($allTables ?? [], fn($t) => str_contains($t, 'user'));
    echo "<strong>Tabel bertema 'user':</strong> " . (empty($userCandidates) ? '<span class=\"err\">Tidak ada</span>' : implode(', ', $userCandidates)) . "<br>";
    foreach ($userCandidates as $ut) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `$ut`")->fetchAll(PDO::FETCH_COLUMN);
            echo "• Kolom <strong>$ut</strong>: <small>" . implode(', ', $cols) . "</small><br>";
        } catch (Throwable $e) {}
    }
    echo "<br>";

    // Cek tabel packages
    try {
        $pkgs = $pdo->query("SELECT id, name FROM packages LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        echo "<span class='ok'>✅ Tabel packages ditemukan!</span> (" . count($pkgs) . " paket sampel)<br>";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ Error cek tabel packages: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }

    // Cek tabel sales
    try {
        $sls = $pdo->query("SELECT id, consumer_name FROM sales LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        echo "<span class='ok'>✅ Tabel sales ditemukan!</span><br>";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ Error cek tabel sales: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }

    // Cek tabel consumer_blacklist atau blacklist_nama
    if (in_array('consumer_blacklist', $allTables ?? [])) {
        echo "<span class='ok'>✅ Tabel consumer_blacklist ADA di database!</span><br>";
        try {
            $blCols = $pdo->query("SHOW COLUMNS FROM consumer_blacklist")->fetchAll(PDO::FETCH_COLUMN);
            echo "• Kolom consumer_blacklist: <small>" . implode(', ', $blCols) . "</small><br>";
        } catch (Throwable $e) {}
    } else {
        echo "<span class='err'>Tabel consumer_blacklist tidak ditemukan.</span><br>";
    }
}
echo "</div>";

// 5. Test Include Sidebar & Helpers
echo "<div class='box'><h3>5. Test Helper & Sidebar</h3>";
try {
    require_once __DIR__ . '/../config/helpers.php';
    echo "<span class='ok'>✅ config/helpers.php berhasil di-load.</span><br>";
    
    require_once __DIR__ . '/../assets/design/ui/icon.php';
    echo "<span class='ok'>✅ assets/design/ui/icon.php berhasil di-load.</span><br>";

    // Test render sidebar secara terisolasi
    ob_start();
    include __DIR__ . '/../partials/sidebar.php';
    $sidebarHtml = ob_get_clean();
    echo "<span class='ok'>✅ partials/sidebar.php berhasil di-render (" . strlen($sidebarHtml) . " bytes)!</span><br>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ Error pada Sidebar/Helper: " . htmlspecialchars($e->getMessage()) . " di baris " . $e->getLine() . " file " . $e->getFile() . "</span><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
echo "</div>";

echo "</body></html>";
