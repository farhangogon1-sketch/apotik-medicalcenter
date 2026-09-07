<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

require_once __DIR__ . '/../auth/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

/* ===============================
   ROLE GUARD (NON-STAFF / STAFF MEDIC)
   =============================== */
$userRole = strtolower($_SESSION['user_rh']['role'] ?? '');
$userDiv  = ems_normalize_division($_SESSION['user_rh']['division'] ?? '');
$position = strtolower(trim($_SESSION['user_rh']['position'] ?? ''));

$isStaffMedic = ($userDiv === 'Medis') || in_array($position, ['trainee', 'paramedic', 'co_asst', 'general_practitioner', 'specialist', 'dokter umum', 'dokter spesialis']);

if (ems_is_staff_role($userRole) && !ems_can_access_division_menu($userDiv, 'General Affair') && !$isStaffMedic) {
    http_response_code(403);
    die('Akses ditolak');
}

$user = $_SESSION['user_rh'] ?? [];
$medicName    = $user['name'] ?? '';
$medicJabatan = $user['position'] ?? '';

/* ===============================
   HANDLE CRUD (AJAX)
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {

        /* ===== CREATE PACKAGE ===== */
        if ($_POST['action'] === 'create_package') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                throw new Exception('Nama paket wajib diisi');
            }

            $stmt = $pdo->prepare("
                INSERT INTO packages (name, bandage_qty, ifaks_qty, painkiller_qty, price)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                (int)($_POST['bandage_qty'] ?? 0),
                (int)($_POST['ifaks_qty'] ?? 0),
                (int)($_POST['painkiller_qty'] ?? 0),
                (int)($_POST['price'] ?? 0)
            ]);

            $newId = (int)$pdo->lastInsertId();

            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Paket berhasil ditambahkan']);
            exit;
        }

        /* ===== UPDATE PACKAGE ===== */
        if ($_POST['action'] === 'update_package') {

            $stmt = $pdo->prepare("
                UPDATE packages
                SET
                    name = ?,
                    bandage_qty = ?,
                    ifaks_qty = ?,
                    painkiller_qty = ?,
                    price = ?
                WHERE id = ?
            ");

            $stmt->execute([
                trim($_POST['name']),
                (int)$_POST['bandage_qty'],
                (int)$_POST['ifaks_qty'],
                (int)$_POST['painkiller_qty'],
                (int)$_POST['price'],
                (int)$_POST['id']
            ]);

            echo json_encode(['success' => true, 'message' => 'Data paket berhasil diperbarui']);
            exit;
        }

        /* ===== DELETE PACKAGE ===== */
        if ($_POST['action'] === 'delete_package') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID paket tidak valid');
            }

            $pdo->beginTransaction();
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
                $stmt->execute([$id]);
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            echo json_encode(['success' => true, 'message' => 'Paket berhasil dihapus']);
            exit;
        }

        /* ===== CREATE MEDICAL REGULATION ===== */
        if ($_POST['action'] === 'create_regulation') {
            $category = trim($_POST['category'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');

            if ($category === '' || $name === '') {
                throw new Exception('Kategori dan nama regulasi wajib diisi');
            }

            if ($code === '') {
                $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10));
            }

            $stmt = $pdo->prepare("
                INSERT INTO medical_regulations (
                    category, code, name, location,
                    price_type, price_min, price_max,
                    payment_type, duration_minutes,
                    notes, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $category,
                $code,
                $name,
                trim($_POST['location'] ?? '') !== '' ? trim($_POST['location']) : null,
                $_POST['price_type'] ?? 'FIXED',
                (int)($_POST['price_min'] ?? 0),
                (int)($_POST['price_max'] ?? 0),
                $_POST['payment_type'] ?? 'CASH',
                trim($_POST['duration_minutes'] ?? '') !== '' ? (int)$_POST['duration_minutes'] : null,
                trim($_POST['notes'] ?? '') !== '' ? trim($_POST['notes']) : null,
                isset($_POST['is_active']) ? 1 : 0
            ]);

            $newId = (int)$pdo->lastInsertId();

            echo json_encode(['success' => true, 'id' => $newId, 'code' => $code, 'message' => 'Regulasi medis berhasil ditambahkan']);
            exit;
        }

        /* ===== UPDATE MEDICAL REGULATION ===== */
        if ($_POST['action'] === 'update_regulation') {
            $code = trim($_POST['code'] ?? '');

            if ($code !== '') {
                $stmt = $pdo->prepare("
                    UPDATE medical_regulations
                    SET
                        category = ?,
                        code = ?,
                        name = ?,
                        location = ?,
                        price_type = ?,
                        price_min = ?,
                        price_max = ?,
                        payment_type = ?,
                        duration_minutes = ?,
                        notes = ?,
                        is_active = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    trim($_POST['category']),
                    $code,
                    trim($_POST['name']),
                    $_POST['location'] !== '' ? trim($_POST['location']) : null,
                    $_POST['price_type'],
                    (int)$_POST['price_min'],
                    (int)$_POST['price_max'],
                    $_POST['payment_type'],
                    $_POST['duration_minutes'] !== '' ? (int)$_POST['duration_minutes'] : null,
                    $_POST['notes'] !== '' ? trim($_POST['notes']) : null,
                    isset($_POST['is_active']) ? 1 : 0,
                    (int)$_POST['id']
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE medical_regulations
                    SET
                        category = ?,
                        name = ?,
                        location = ?,
                        price_type = ?,
                        price_min = ?,
                        price_max = ?,
                        payment_type = ?,
                        duration_minutes = ?,
                        notes = ?,
                        is_active = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    trim($_POST['category']),
                    trim($_POST['name']),
                    $_POST['location'] !== '' ? trim($_POST['location']) : null,
                    $_POST['price_type'],
                    (int)$_POST['price_min'],
                    (int)$_POST['price_max'],
                    $_POST['payment_type'],
                    $_POST['duration_minutes'] !== '' ? (int)$_POST['duration_minutes'] : null,
                    $_POST['notes'] !== '' ? trim($_POST['notes']) : null,
                    isset($_POST['is_active']) ? 1 : 0,
                    (int)$_POST['id']
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Data regulasi berhasil diperbarui']);
            exit;
        }

        /* ===== DELETE MEDICAL REGULATION ===== */
        if ($_POST['action'] === 'delete_regulation') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID regulasi tidak valid');
            }

            $pdo->beginTransaction();
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $stmt = $pdo->prepare("DELETE FROM medical_regulations WHERE id = ?");
                $stmt->execute([$id]);
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            echo json_encode(['success' => true, 'message' => 'Regulasi medis berhasil dihapus']);
            exit;
        }

        throw new Exception('Aksi tidak valid');
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

/* ===============================
   LOAD DATA
   =============================== */
$packages = $pdo->query("
    SELECT id, name, bandage_qty, ifaks_qty, painkiller_qty, price
    FROM packages
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$regs = $pdo->query("
    SELECT
        id, category, code, name, location,
        price_type, price_min, price_max,
        payment_type, duration_minutes,
        notes, is_active
    FROM medical_regulations
    ORDER BY category, code
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<section class="content">
    <div class="page page-shell">

	        <h1 class="page-title">Regulasi EMS</h1>
	        <p class="page-subtitle">Manajemen paket & regulasi medis</p>

        <div id="ajaxAlert"></div>

        <!-- ================= PACKAGES ================= -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span>Paket</span>
                <button type="button" class="btn-primary btn-add-package" style="padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 6px;">+ Tambah Paket</button>
            </div>

            <div class="table-wrapper">
                <table id="packageTable" class="table-custom">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Bandage</th>
                            <th>Ifaks</th>
                            <th>Painkiller</th>
                            <th>Harga</th>
                            <th width="140">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $p): ?>
                            <tr
                                data-id="<?= $p['id'] ?>"
                                data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                                data-bandage="<?= $p['bandage_qty'] ?>"
                                data-ifaks="<?= $p['ifaks_qty'] ?>"
                                data-painkiller="<?= $p['painkiller_qty'] ?>"
                                data-price="<?= $p['price'] ?>">
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= $p['bandage_qty'] ?></td>
                                <td><?= $p['ifaks_qty'] ?></td>
                                <td><?= $p['painkiller_qty'] ?></td>
                                <td>$<?= number_format($p['price']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <button class="btn-secondary btn-edit-package">Edit</button>
                                        <button class="btn-danger btn-delete-package">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="regAlert"></div>

        <!-- ================= MEDICAL REGULATIONS ================= -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span>Regulasi Medis</span>
                <button type="button" class="btn-primary btn-add-reg" style="padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 6px;">+ Tambah Regulasi Medis</button>
            </div>

            <div class="table-wrapper">
                <table id="regTable" class="table-custom">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Harga</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th width="140">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regs as $r): ?>
                            <tr
                                data-id="<?= $r['id'] ?>"
                                data-category="<?= htmlspecialchars($r['category'], ENT_QUOTES) ?>"
                                data-code="<?= htmlspecialchars($r['code'] ?? '', ENT_QUOTES) ?>"
                                data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>"
                                data-location="<?= htmlspecialchars($r['location'] ?? '', ENT_QUOTES) ?>"
                                data-price_type="<?= $r['price_type'] ?>"
                                data-min="<?= $r['price_min'] ?>"
                                data-max="<?= $r['price_max'] ?>"
                                data-payment="<?= $r['payment_type'] ?>"
                                data-duration="<?= $r['duration_minutes'] ?>"
                                data-notes="<?= htmlspecialchars($r['notes'] ?? '', ENT_QUOTES) ?>"
                                data-active="<?= $r['is_active'] ?>">
                                <td><?= htmlspecialchars($r['category']) ?></td>
                                <td><?= htmlspecialchars($r['code']) ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td>
                                    <?= $r['price_type'] === 'FIXED'
                                        ? '$' . number_format($r['price_min'])
                                        : '$' . number_format($r['price_min']) . ' - $' . number_format($r['price_max']) ?>
                                </td>
                                <td><?= $r['payment_type'] ?></td>
                                <td><?= $r['is_active'] ? 'Aktif' : 'Nonaktif' ?></td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <button class="btn-secondary btn-edit-reg">Edit</button>
                                        <button class="btn-danger btn-delete-reg">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

<!-- ===============================
     MODAL EDIT / TAMBAH PACKAGE
     =============================== -->
<div id="editPackageModal" class="modal-overlay hidden">
    <div class="modal-box modal-shell modal-frame-md">
        <div class="modal-head">
            <div class="modal-title" id="pkgModalTitle">Ubah Paket</div>
            <button type="button" class="modal-close-btn btn-cancel" aria-label="Tutup modal">
                <?= ems_icon('x-mark', 'h-5 w-5') ?>
            </button>
        </div>

        <form id="editPackageForm" class="form modal-form">
            <div class="modal-content">
                <input type="hidden" name="action" id="pkgAction" value="update_package">
                <input type="hidden" name="id" id="pkgId">

                <label>Nama Paket</label>
                <input type="text" name="name" id="pkgName" required placeholder="Contoh: PAKET C">

                <label>Bandage (Qty)</label>
                <input type="number" name="bandage_qty" id="pkgBandage" min="0" value="0" required>

                <label>Ifaks (Qty)</label>
                <input type="number" name="ifaks_qty" id="pkgIfaks" min="0" value="0" required>

                <label>Painkiller (Qty)</label>
                <input type="number" name="painkiller_qty" id="pkgPainkiller" min="0" value="0" required>

                <label>Harga ($)</label>
                <input type="number" name="price" id="pkgPrice" min="0" value="0" required>
            </div>

            <div class="modal-foot">
                <div class="modal-actions">
                    <button type="button" class="btn-secondary btn-cancel">Batal</button>
                    <button type="submit" class="btn-success">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===============================
     MODAL EDIT / TAMBAH REGULATION
     =============================== -->
<div id="editRegModal" class="modal-overlay hidden">
    <div class="modal-box modal-shell modal-frame-md">
        <div class="modal-head">
            <div class="modal-title" id="regModalTitle">Ubah Regulasi Medis</div>
            <button type="button" class="modal-close-btn btn-cancel" aria-label="Tutup modal">
                <?= ems_icon('x-mark', 'h-5 w-5') ?>
            </button>
        </div>

        <form id="editRegForm" class="form modal-form">
            <div class="modal-content">
                <input type="hidden" name="action" id="regAction" value="update_regulation">
                <input type="hidden" name="id" id="regId">

                <label>Kategori</label>
                <input type="text" name="category" id="regCategory" required placeholder="Contoh: LAYANAN">

                <label>Kode Regulasi</label>
                <input type="text" name="code" id="regCode" placeholder="Contoh: KONSUL-01" required>

                <label>Nama Layanan / Tindakan</label>
                <input type="text" name="name" id="regName" required placeholder="Contoh: Konsultasi Dokter Umum">

                <label>Lokasi</label>
                <input type="text" name="location" id="regLocation" placeholder="Contoh: Klinik / RS">

                <label>Tipe Harga</label>
                <select name="price_type" id="regPriceType">
                    <option value="FIXED">FIXED</option>
                    <option value="RANGE">RANGE</option>
                </select>

                <label>Harga Min ($)</label>
                <input type="number" name="price_min" id="regMin" min="0" value="0" required>

                <label>Harga Max ($)</label>
                <input type="number" name="price_max" id="regMax" min="0" value="0" required>

                <label>Pembayaran</label>
                <select name="payment_type" id="regPayment">
                    <option value="CASH">CASH</option>
                    <option value="INVOICE">INVOICE</option>
                    <option value="BILLING">BILLING</option>
                </select>

                <label>Durasi (menit)</label>
                <input type="number" name="duration_minutes" id="regDuration" placeholder="Opsional">

                <label>Catatan</label>
                <textarea name="notes" id="regNotes" placeholder="Catatan opsional"></textarea>

                <label class="checkbox-label checkbox-pill">
                    <input type="checkbox" name="is_active" id="regActive"> Aktif
                </label>
            </div>

            <div class="modal-foot">
                <div class="modal-actions">
                    <button type="button" class="btn-secondary btn-cancel">Batal</button>
                    <button type="submit" class="btn-success">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        let activeRow = null;

        /* ===============================
           INIT DATATABLES (WAJIB DIPISAH)
           =============================== */
        const packageTable = jQuery('#packageTable').DataTable({
            pageLength: 10,
            language: {
                url: '/assets/design/js/datatables-id.json'
            }
        });

        const regTable = jQuery('#regTable').DataTable({
            pageLength: 10,
            language: {
                url: '/assets/design/js/datatables-id.json'
            }
        });

        /* ===============================
           OPEN ADD PACKAGE MODAL
           =============================== */
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-add-package');
            if (!btn) return;

            activeRow = null;
            document.getElementById('pkgModalTitle').textContent = 'Tambah Paket';
            document.getElementById('pkgAction').value = 'create_package';
            document.getElementById('pkgId').value = '';
            document.getElementById('pkgName').value = '';
            document.getElementById('pkgBandage').value = '0';
            document.getElementById('pkgIfaks').value = '0';
            document.getElementById('pkgPainkiller').value = '0';
            document.getElementById('pkgPrice').value = '0';

            const modal = document.getElementById('editPackageModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
        });

        /* ===============================
           OPEN EDIT PACKAGE MODAL
           =============================== */
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-edit-package');
            if (!btn) return;

            const row = btn.closest('tr');
            activeRow = packageTable.row(row);

            document.getElementById('pkgModalTitle').textContent = 'Ubah Paket';
            document.getElementById('pkgAction').value = 'update_package';
            document.getElementById('pkgId').value = row.dataset.id;
            document.getElementById('pkgName').value = row.dataset.name;
            document.getElementById('pkgBandage').value = row.dataset.bandage;
            document.getElementById('pkgIfaks').value = row.dataset.ifaks;
            document.getElementById('pkgPainkiller').value = row.dataset.painkiller;
            document.getElementById('pkgPrice').value = row.dataset.price;

            const modal = document.getElementById('editPackageModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
        });

        /* ===============================
           DELETE PACKAGE
           =============================== */
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-delete-package');
            if (!btn) return;

            const row = btn.closest('tr');
            const id = row.dataset.id;
            const name = row.dataset.name || 'paket';

            if (!confirm('Apakah Anda yakin ingin menghapus paket "' + name + '"?')) {
                return;
            }

            const fd = new FormData();
            fd.append('action', 'delete_package');
            fd.append('id', id);

            fetch('regulasi.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(r => {
                if (!r.success) {
                    showAlert('error', r.message || 'Gagal menghapus paket', 'ajaxAlert');
                    return;
                }
                packageTable.row(row).remove().draw(false);
                showAlert('success', 'Paket "' + name + '" berhasil dihapus', 'ajaxAlert');
            })
            .catch(err => {
                showAlert('error', 'Terjadi kesalahan: ' + err.message, 'ajaxAlert');
            });
        });

        /* ===============================
           OPEN ADD REGULATION MODAL
           =============================== */
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-add-reg');
            if (!btn) return;

            activeRow = null;
            document.getElementById('regModalTitle').textContent = 'Tambah Regulasi Medis';
            document.getElementById('regAction').value = 'create_regulation';
            document.getElementById('regId').value = '';
            document.getElementById('regCategory').value = '';
            document.getElementById('regCode').value = '';
            document.getElementById('regName').value = '';
            document.getElementById('regLocation').value = '';
            document.getElementById('regPriceType').value = 'FIXED';
            document.getElementById('regMin').value = '0';
            document.getElementById('regMax').value = '0';
            document.getElementById('regPayment').value = 'CASH';
            document.getElementById('regDuration').value = '';
            document.getElementById('regNotes').value = '';
            document.getElementById('regActive').checked = true;

            const modal = document.getElementById('editRegModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
        });

        /* ===============================
           OPEN EDIT REGULATION MODAL
           =============================== */
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-edit-reg');
            if (!btn) return;

            const row = btn.closest('tr');
            activeRow = regTable.row(row);

            document.getElementById('regModalTitle').textContent = 'Ubah Regulasi Medis';
            document.getElementById('regAction').value = 'update_regulation';
            document.getElementById('regId').value = row.dataset.id;
            document.getElementById('regCategory').value = row.dataset.category;
            document.getElementById('regCode').value = row.dataset.code || '';
            document.getElementById('regName').value = row.dataset.name;
            document.getElementById('regLocation').value = row.dataset.location || '';
            document.getElementById('regPriceType').value = row.dataset.price_type;
            document.getElementById('regMin').value = row.dataset.min;
            document.getElementById('regMax').value = row.dataset.max;
            document.getElementById('regPayment').value = row.dataset.payment;
            document.getElementById('regDuration').value = row.dataset.duration || '';
            document.getElementById('regNotes').value = row.dataset.notes || '';
            document.getElementById('regActive').checked = row.dataset.active === '1';

            const modal = document.getElementById('editRegModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
        });

        /* ===============================
           DELETE REGULATION
           =============================== */
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-delete-reg');
            if (!btn) return;

            const row = btn.closest('tr');
            const id = row.dataset.id;
            const name = row.dataset.name || 'regulasi';

            if (!confirm('Apakah Anda yakin ingin menghapus regulasi "' + name + '"?')) {
                return;
            }

            const fd = new FormData();
            fd.append('action', 'delete_regulation');
            fd.append('id', id);

            fetch('regulasi.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(r => {
                if (!r.success) {
                    showAlert('error', r.message || 'Gagal menghapus regulasi', 'regAlert');
                    return;
                }
                regTable.row(row).remove().draw(false);
                showAlert('success', 'Regulasi "' + name + '" berhasil dihapus', 'regAlert');
            })
            .catch(err => {
                showAlert('error', 'Terjadi kesalahan: ' + err.message, 'regAlert');
            });
        });

        /* ===============================
           CLOSE MODAL
           =============================== */
        function closeModal() {
            const editPackageModal = document.getElementById('editPackageModal');
            const editRegModal = document.getElementById('editRegModal');

            editPackageModal.style.display = 'none';
            editPackageModal.classList.add('hidden');
            editRegModal.style.display = 'none';
            editRegModal.classList.add('hidden');
            document.body.classList.remove('modal-open');
            activeRow = null;
        }

        document.body.addEventListener('click', function(e) {
            if (
                e.target.classList.contains('modal-overlay') ||
                e.target.closest('.btn-cancel')
            ) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        /* ===============================
           AJAX SUBMIT - PACKAGE
           =============================== */
        document.getElementById('editPackageForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const isAdd = document.getElementById('pkgAction').value === 'create_package';
            const nameVal = document.getElementById('pkgName').value;
            const bandageVal = document.getElementById('pkgBandage').value;
            const ifaksVal = document.getElementById('pkgIfaks').value;
            const painVal = document.getElementById('pkgPainkiller').value;
            const priceVal = document.getElementById('pkgPrice').value;

            fetch('regulasi.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(r => r.json())
                .then(r => {
                    if (!r.success) {
                        showAlert('error', r.message || 'Gagal menyimpan data', 'ajaxAlert');
                        return;
                    }

                    const actionButtons = '<div style="display: flex; gap: 6px; align-items: center;"><button class="btn-secondary btn-edit-package">Edit</button><button class="btn-danger btn-delete-package">Hapus</button></div>';

                    if (isAdd) {
                        const newRow = packageTable.row.add([
                            nameVal,
                            bandageVal,
                            ifaksVal,
                            painVal,
                            '$' + Number(priceVal).toLocaleString(),
                            actionButtons
                        ]).draw(false);

                        const node = newRow.node();
                        node.dataset.id = r.id;
                        node.dataset.name = nameVal;
                        node.dataset.bandage = bandageVal;
                        node.dataset.ifaks = ifaksVal;
                        node.dataset.painkiller = painVal;
                        node.dataset.price = priceVal;

                        showAlert('success', 'Paket berhasil ditambahkan', 'ajaxAlert');
                    } else {
                        // Update dataset row
                        const node = activeRow.node();
                        const idVal = document.getElementById('pkgId').value;
                        node.dataset.id = idVal;
                        node.dataset.name = nameVal;
                        node.dataset.bandage = bandageVal;
                        node.dataset.ifaks = ifaksVal;
                        node.dataset.painkiller = painVal;
                        node.dataset.price = priceVal;

                        // Update DataTables display
                        activeRow.data([
                            nameVal,
                            bandageVal,
                            ifaksVal,
                            painVal,
                            '$' + Number(priceVal).toLocaleString(),
                            actionButtons
                        ]).draw(false);

                        showAlert('success', 'Data paket berhasil diperbarui', 'ajaxAlert');
                    }

                    closeModal();
                })
                .catch(err => {
                    showAlert('error', 'Terjadi kesalahan: ' + err.message, 'ajaxAlert');
                });
        });

        /* ===============================
           AJAX SUBMIT - REGULATION
           =============================== */
        document.getElementById('editRegForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const isAdd = document.getElementById('regAction').value === 'create_regulation';
            const catVal = document.getElementById('regCategory').value;
            const codeVal = document.getElementById('regCode').value;
            const nameVal = document.getElementById('regName').value;
            const locVal = document.getElementById('regLocation').value;
            const priceTypeVal = document.getElementById('regPriceType').value;
            const minVal = document.getElementById('regMin').value;
            const maxVal = document.getElementById('regMax').value;
            const payVal = document.getElementById('regPayment').value;
            const durVal = document.getElementById('regDuration').value;
            const notesVal = document.getElementById('regNotes').value;
            const activeVal = document.getElementById('regActive').checked;

            fetch('regulasi.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(r => r.json())
                .then(r => {
                    if (!r.success) {
                        showAlert('error', r.message || 'Gagal menyimpan data', 'regAlert');
                        return;
                    }

                    const harga = priceTypeVal === 'FIXED' ?
                        '$' + Number(minVal).toLocaleString() :
                        '$' + Number(minVal).toLocaleString() +
                        ' - $' + Number(maxVal).toLocaleString();

                    const actionButtons = '<div style="display: flex; gap: 6px; align-items: center;"><button class="btn-secondary btn-edit-reg">Edit</button><button class="btn-danger btn-delete-reg">Hapus</button></div>';

                    if (isAdd) {
                        const finalCode = r.code || codeVal;
                        const newRow = regTable.row.add([
                            catVal,
                            finalCode,
                            nameVal,
                            harga,
                            payVal,
                            activeVal ? 'Aktif' : 'Nonaktif',
                            actionButtons
                        ]).draw(false);

                        const node = newRow.node();
                        node.dataset.id = r.id;
                        node.dataset.category = catVal;
                        node.dataset.code = finalCode;
                        node.dataset.name = nameVal;
                        node.dataset.location = locVal;
                        node.dataset.price_type = priceTypeVal;
                        node.dataset.min = minVal;
                        node.dataset.max = maxVal;
                        node.dataset.payment = payVal;
                        node.dataset.duration = durVal;
                        node.dataset.notes = notesVal;
                        node.dataset.active = activeVal ? '1' : '0';

                        showAlert('success', 'Regulasi medis berhasil ditambahkan', 'regAlert');
                    } else {
                        // Update dataset row
                        const node = activeRow.node();
                        const currentData = activeRow.data();
                        const finalCode = codeVal || currentData[1];

                        node.dataset.category = catVal;
                        node.dataset.code = finalCode;
                        node.dataset.name = nameVal;
                        node.dataset.location = locVal;
                        node.dataset.price_type = priceTypeVal;
                        node.dataset.min = minVal;
                        node.dataset.max = maxVal;
                        node.dataset.payment = payVal;
                        node.dataset.duration = durVal;
                        node.dataset.notes = notesVal;
                        node.dataset.active = activeVal ? '1' : '0';

                        // Update DataTables display
                        activeRow.data([
                            catVal,
                            finalCode,
                            nameVal,
                            harga,
                            payVal,
                            activeVal ? 'Aktif' : 'Nonaktif',
                            actionButtons
                        ]).draw(false);

                        showAlert('success', 'Data regulasi berhasil diperbarui', 'regAlert');
                    }

                    closeModal();
                })
                .catch(err => {
                    showAlert('error', 'Terjadi kesalahan: ' + err.message, 'regAlert');
                });
        });
    });

    /* ===============================
       ALERT HANDLER (5 DETIK)
       =============================== */
    function showAlert(type, message, target = 'ajaxAlert') {
        const box = document.getElementById(target);
        if (!box) return;

        box.innerHTML = `
        <div class="alert alert-${type}">
            ${message}
        </div>
    `;

        setTimeout(() => {
            const alert = box.querySelector('.alert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 600);
            }
        }, 5000);
    }
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
