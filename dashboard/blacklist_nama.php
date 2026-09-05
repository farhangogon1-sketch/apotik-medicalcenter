<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

require_once __DIR__ . '/../auth/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../assets/design/ui/icon.php';

$user = $_SESSION['user_rh'] ?? [];
$userRole = strtolower(trim($user['role'] ?? ''));
$userDiv  = ems_normalize_division($user['division'] ?? '');
$userPos  = strtolower(trim($user['position'] ?? ''));

$canAccess = ems_can_access_division_menu($userDiv, 'General Affair')
    || in_array(strtolower($userDiv), ['general affair', 'administrasi', 'admin', 'executive', 'human resource'])
    || in_array($userPos, ['administrasi', 'admin', 'general affair'])
    || !ems_is_staff_role($userRole);

if (!$canAccess) {
    $_SESSION['flash_errors'][] = 'Akses ditolak. Halaman khusus administrasi / General Affair.';
    header('Location: /dashboard/index.php');
    exit;
}

$pageTitle = 'Blacklist Nama';

// Pastikan tabel blacklist_nama tersedia
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blacklist_nama (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(255) NOT NULL UNIQUE,
            alasan TEXT NULL,
            created_by VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_nama (nama)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Throwable $e) {
    // Abaikan jika tabel sudah ada atau izin terbatas
}

// Flash messages
$messages = $_SESSION['flash_messages'] ?? [];
$errors   = $_SESSION['flash_errors']   ?? [];
unset($_SESSION['flash_messages'], $_SESSION['flash_errors']);

// Handle POST actions (Tambah / Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'add') {
        $nama = trim($_POST['nama'] ?? '');
        $alasan = trim($_POST['alasan'] ?? '');
        $creator = $user['name'] ?? 'Admin';

        if ($nama === '') {
            $_SESSION['flash_errors'][] = 'Nama konsumen tidak boleh kosong.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO blacklist_nama (nama, alasan, created_by, created_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        alasan = VALUES(alasan),
                        created_by = VALUES(created_by),
                        updated_at = NOW()
                ");
                $stmt->execute([$nama, $alasan, $creator]);
                $_SESSION['flash_messages'][] = "Nama '{$nama}' berhasil dimasukkan ke daftar blacklist.";
            } catch (Throwable $e) {
                $_SESSION['flash_errors'][] = 'Gagal menyimpan blacklist: ' . $e->getMessage();
            }
        }
        header('Location: blacklist_nama.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM blacklist_nama WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_messages'][] = 'Nama berhasil dihapus dari daftar blacklist.';
            } catch (Throwable $e) {
                $_SESSION['flash_errors'][] = 'Gagal menghapus blacklist: ' . $e->getMessage();
            }
        }
        header('Location: blacklist_nama.php');
        exit;
    }
}

// Filter pencarian
$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT id, nama, alasan, created_by, created_at FROM blacklist_nama WHERE 1=1";

if ($q !== '') {
    $sql .= " AND (nama LIKE ? OR alasan LIKE ? OR created_by LIKE ?)";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $blacklists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $blacklists = [];
}

$totalBlacklist = count($blacklists);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<section class="content">
    <div class="page page-shell">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
            <div>
                <h1 class="page-title" style="margin: 0; font-size: 1.6rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <?= ems_icon('x-circle', 'h-6 w-6') ?>
                    </span>
                    Blacklist Nama
                </h1>
                <p class="page-subtitle" style="margin: 6px 0 0; color: rgba(255, 255, 255, 0.6); font-size: 0.9rem;">
                    Daftar nama konsumen yang diblacklist dan diblokir dari transaksi layanan apotik / farmasi.
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-danger" onclick="openAddModal()" style="display: inline-flex; align-items: center; gap: 8px; background: #dc2626; color: #fff; padding: 10px 18px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    <?= ems_icon('plus', 'h-5 w-5') ?>
                    <span>Tambah Blacklist</span>
                </button>
            </div>
        </div>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <?= htmlspecialchars($err) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Search Card -->
        <div class="card" style="background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;">
            <form method="GET" action="blacklist_nama.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 260px; position: relative;">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama konsumen, alasan, atau penambah..."
                        style="width: 100%; background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255, 255, 255, 0.12); color: #fff; padding: 10px 14px; border-radius: 8px; font-size: 0.9rem; outline: none;">
                </div>
                <button type="submit" class="btn btn-primary" style="background: #0284c7; color: #fff; padding: 10px 18px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <?= ems_icon('magnifying-glass', 'h-4 w-4') ?>
                    <span>Cari</span>
                </button>
                <?php if ($q !== ''): ?>
                    <a href="blacklist_nama.php" class="btn btn-secondary" style="background: rgba(255, 255, 255, 0.1); color: #e2e8f0; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 0.9rem;">
                        Reset
                    </a>
                <?php endif; ?>
                <div style="margin-left: auto; color: rgba(255, 255, 255, 0.6); font-size: 0.85rem;">
                    Total Terdata: <strong style="color: #ef4444;"><?= number_format($totalBlacklist, 0, ',', '.') ?></strong> orang
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="card" style="background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; overflow: hidden;">
            <div class="table-responsive" style="overflow-x: auto;">
                <table class="table-custom" style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: rgba(30, 41, 59, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <th style="padding: 14px 16px; font-size: 0.8rem; font-weight: 600; color: rgba(255, 255, 255, 0.7); text-transform: uppercase; width: 60px;">No</th>
                            <th style="padding: 14px 16px; font-size: 0.8rem; font-weight: 600; color: rgba(255, 255, 255, 0.7); text-transform: uppercase;">Nama Konsumen</th>
                            <th style="padding: 14px 16px; font-size: 0.8rem; font-weight: 600; color: rgba(255, 255, 255, 0.7); text-transform: uppercase;">Alasan Blacklist</th>
                            <th style="padding: 14px 16px; font-size: 0.8rem; font-weight: 600; color: rgba(255, 255, 255, 0.7); text-transform: uppercase;">Ditambahkan Oleh</th>
                            <th style="padding: 14px 16px; font-size: 0.8rem; font-weight: 600; color: rgba(255, 255, 255, 0.7); text-transform: uppercase;">Tanggal</th>
                            <th style="padding: 14px 16px; font-size: 0.8rem; font-weight: 600; color: rgba(255, 255, 255, 0.7); text-transform: uppercase; text-align: center; width: 100px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blacklists)): ?>
                            <tr>
                                <td colspan="6" style="padding: 40px 16px; text-align: center; color: rgba(255, 255, 255, 0.4);">
                                    <div style="font-size: 1.1rem; margin-bottom: 4px;">Tidak ada data blacklist</div>
                                    <small>Nama konsumen yang diblacklist akan ditampilkan di sini.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($blacklists as $idx => $b): ?>
                                <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                                    <td style="padding: 14px 16px; color: rgba(255, 255, 255, 0.5); font-size: 0.9rem;"><?= $idx + 1 ?></td>
                                    <td style="padding: 14px 16px; font-size: 0.95rem;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ef4444;"></span>
                                            <strong style="color: #fff;"><?= htmlspecialchars($b['nama']) ?></strong>
                                            <span style="font-size: 0.75rem; background: rgba(239, 68, 68, 0.2); color: #f87171; padding: 2px 8px; border-radius: 9999px; font-weight: 600;">BLACKLIST</span>
                                        </div>
                                    </td>
                                    <td style="padding: 14px 16px; color: rgba(255, 255, 255, 0.8); font-size: 0.9rem;">
                                        <?= htmlspecialchars($b['alasan'] ?: '-') ?>
                                    </td>
                                    <td style="padding: 14px 16px; color: rgba(255, 255, 255, 0.6); font-size: 0.85rem;">
                                        <?= htmlspecialchars($b['created_by'] ?: '-') ?>
                                    </td>
                                    <td style="padding: 14px 16px; color: rgba(255, 255, 255, 0.6); font-size: 0.85rem;">
                                        <?= formatTanggalID($b['created_at']) ?>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: center;">
                                        <form method="POST" action="blacklist_nama.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus nama ini dari daftar blacklist?');" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 6px 10px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 0.8rem; font-weight: 500;" title="Hapus Blacklist">
                                                <?= ems_icon('trash', 'h-4 w-4') ?>
                                                <span>Hapus</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Modal Tambah Blacklist -->
<div id="addModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 14px; width: 100%; max-width: 480px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="margin: 0; font-size: 1.2rem; color: #fff; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <span style="color: #ef4444;"><?= ems_icon('x-circle', 'h-5 w-5') ?></span>
                Tambah Blacklist Nama
            </h3>
            <button type="button" onclick="closeAddModal()" style="background: transparent; border: none; color: rgba(255, 255, 255, 0.5); cursor: pointer; font-size: 1.4rem;">&times;</button>
        </div>

        <form method="POST" action="blacklist_nama.php">
            <input type="hidden" name="action" value="add">

            <div style="margin-bottom: 16px;">
                <label style="display: block; color: rgba(255, 255, 255, 0.8); font-size: 0.85rem; margin-bottom: 6px; font-weight: 500;">
                    Nama Konsumen <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="nama" required placeholder="Masukkan nama lengkap konsumen..."
                    style="width: 100%; background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(255, 255, 255, 0.15); color: #fff; padding: 10px 14px; border-radius: 8px; font-size: 0.9rem; outline: none; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: rgba(255, 255, 255, 0.8); font-size: 0.85rem; margin-bottom: 6px; font-weight: 500;">
                    Alasan Blacklist
                </label>
                <textarea name="alasan" rows="3" placeholder="Tuliskan alasan blacklist (penyalahgunaan kuota, tindakan curang, dsb)..."
                    style="width: 100%; background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(255, 255, 255, 0.15); color: #fff; padding: 10px 14px; border-radius: 8px; font-size: 0.9rem; outline: none; box-sizing: border-box; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeAddModal()" style="background: rgba(255, 255, 255, 0.1); color: #e2e8f0; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Batal
                </button>
                <button type="submit" style="background: #dc2626; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    Simpan Blacklist
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    const m = document.getElementById('addModal');
    m.style.display = 'flex';
}
function closeAddModal() {
    const m = document.getElementById('addModal');
    m.style.display = 'none';
}
window.addEventListener('click', function(e) {
    const m = document.getElementById('addModal');
    if (e.target === m) {
        closeAddModal();
    }
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
