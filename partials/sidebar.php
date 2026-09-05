<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = strtolower(trim($_SESSION['user_rh']['role'] ?? ''));
$position = strtolower(trim($_SESSION['user_rh']['position'] ?? ''));
$division = ems_normalize_division($_SESSION['user_rh']['division'] ?? '');
$isTrainee = ($position === 'trainee');

require_once __DIR__ . '/../assets/design/ui/icon.php';

if (!function_exists('isActive')) {
    function isActive($page)
    {
        global $currentPage;
        return $currentPage === $page ? 'active' : '';
    }
}

if (!function_exists('sidebarItem')) {
    function sidebarItem(string $href, string $page, string $label, string $icon): array
    {
        return [
            'href' => $href,
            'page' => $page,
            'label' => $label,
            'icon' => $icon,
        ];
    }
}

// Cek hak akses fitur General Affair / Administrasi
$canAccessGA = ems_can_access_division_menu($division, 'General Affair')
    || in_array(strtolower($division), ['general affair', 'administrasi', 'admin', 'executive', 'human resource', 'human capital', 'secretary'])
    || in_array(strtolower($position), ['administrasi', 'admin', 'general affair'])
    || !ems_is_staff_role($userRole);

$groupedNav = [
    'Utama' => [
        sidebarItem('/dashboard/index.php', 'index.php', 'Dashboard', 'home'),
    ],
    'Farmasi' => [
        sidebarItem('/dashboard/rekap_farmasi.php', 'rekap_farmasi.php', 'Rekap Farmasi', 'beaker'),
        sidebarItem('/dashboard/konsumen.php', 'konsumen.php', 'Konsumen', 'user-group'),
        sidebarItem('/dashboard/ranking.php', 'ranking.php', 'Ranking', 'chart-bar'),
    ],
];

if ($canAccessGA) {
    $groupedNav['General Affair'] = [
        sidebarItem('/dashboard/gaji.php', 'gaji.php', 'Gaji', 'archive-box'),
        sidebarItem('/dashboard/regulasi.php', 'regulasi.php', 'Update Regulasi', 'pencil'),
        sidebarItem('/dashboard/validasi.php', 'validasi.php', 'Validasi', 'check-circle'),
        sidebarItem('/dashboard/blacklist_nama.php', 'blacklist_nama.php', 'Blacklist Nama', 'x-circle'),
        sidebarItem('/dashboard/manage_users.php', 'manage_users.php', 'Manajemen User', 'user-group'),
    ];
}

$groupedNav['Pengaturan'] = [
    sidebarItem('/dashboard/setting_akun.php', 'setting_akun.php', 'Setting Akun', 'adjustments-vertical'),
];
?>

<aside id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <div class="avatar-logo" style="background: <?= htmlspecialchars($avatarColor, ENT_QUOTES, 'UTF-8') ?>;">
                <?= htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="brand-text">
                <strong><?= htmlspecialchars($medicName) ?></strong>
                <span><?= htmlspecialchars($medicJabatan) ?></span>
            </div>
        </div>
    </div>

    <nav class="sidebar-menu">
        <?php foreach ($groupedNav as $groupTitle => $items): ?>
            <?php if (empty($items)) continue; ?>
            <div class="sidebar-group-title"><?= htmlspecialchars($groupTitle) ?></div>
            <?php foreach ($items as $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= isActive($item['page']) ?>">
                    <span class="icon"><?= ems_icon($item['icon'], 'h-5 w-5') ?></span>
                    <span class="text"><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <a href="/auth/logout.php"
            onclick="
                if (confirm('Yakin ingin keluar?')) {
                    sessionStorage.removeItem('farmasi_activity_closed');
                    return true;
                }
                return false;
            "
            class="logout">
            <span class="icon"><?= ems_icon('arrow-right-on-rectangle', 'h-5 w-5') ?></span>
            <span class="text">Keluar</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        EMS &copy; <?= date('Y') ?>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="document.body.classList.remove('sidebar-open');"></div>
<main class="main-content">
