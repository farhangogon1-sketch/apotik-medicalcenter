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
        echo "MySQL/MariaDB Version: " . htmlspecialchars($ver) . "<br>";
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
    // Cek kolom user_rh
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM user_rh")->fetchAll(PDO::FETCH_COLUMN);
        echo "<span class='ok'>✅ Tabel user_rh ditemukan!</span> Kolom: <small>" . implode(', ', $cols) . "</small><br><br>";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ Error cek tabel user_rh: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }

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

    // Cek tabel blacklist_nama
    try {
        $bl = $pdo->query("SELECT id, nama FROM blacklist_nama LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        echo "<span class='ok'>✅ Tabel blacklist_nama ditemukan!</span><br>";
    } catch (Throwable $e) {
        echo "<span class='err'>⚠️ Tabel blacklist_nama belum ada / error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
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
