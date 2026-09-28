<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/rent.php';

$id = (int)($_GET['id'] ?? 0);
$p = db_one('SELECT * FROM properties WHERE id = ?', [$id]);
if (!$p) { flash('Makan nahi mila.', 'danger'); redirect('properties.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post('action')) {
        case 'update':
            if (post('name') !== '') {
                db_exec('UPDATE properties SET name=?, address=?, note=? WHERE id=?', [post('name'), post('address') ?: null, post('note') ?: null, $id]);
                flash('Makan update ho gaya.');
            }
            break;
        case 'delete':
            foreach (db_all('SELECT f.file_name FROM tenancy_files f JOIN tenancies t ON t.id = f.tenancy_id WHERE t.property_id = ?', [$id]) as $f) {
                @unlink(__DIR__ . '/uploads/tenancy/' . $f['file_name']);
            }
            db_exec('DELETE FROM properties WHERE id = ?', [$id]);
            flash('Makan aur us ka poora record delete ho gaya.', 'warning');
            redirect('properties.php');
        case 'expense':
            $d = valid_date(post('exp_date')); $amt = amount_in('amount');
            if ($d && $amt > 0) {
                db_exec('INSERT INTO property_expenses (property_id, exp_date, amount, note) VALUES (?,?,?,?)', [$id, $d, $amt, post('note') ?: null]);
                flash('Makan ka kharcha add ho gaya.');
            }
            break;
        case 'delete_expense':
            db_exec('DELETE FROM property_expenses WHERE id = ? AND property_id = ?', [(int)post('eid'), $id]);
            flash('Kharcha delete ho gaya.', 'warning');
            break;
    }
    redirect('property.php?id=' . $id);
}

$tenancies = db_all('SELECT * FROM tenancies WHERE property_id = ? ORDER BY status = "active" DESC, start_date DESC', [$id]);
$expenses = db_all('SELECT * FROM property_expenses WHERE property_id = ? ORDER BY exp_date DESC', [$id]);
$totalCollected = (float)db_val('SELECT COALESCE(SUM(r.amount),0) FROM rent_payments r JOIN tenancies t ON t.id = r.tenancy_id WHERE t.property_id = ?', [$id]);
$totalExp = array_sum(array_column($expenses, 'amount'));
$hasActive = (bool)array_filter($tenancies, fn($t) => $t['status'] === 'active');

$pageTitle = $p['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><a href="properties.php" class="small"><i class="bi bi-arrow-left"></i> Makan</a><h1><?= e($p['name']) ?></h1>
    <div class="small text-muted"><?= e($p['address']) ?> <?= $p['note'] ? '· ' . e($p['note']) : '' ?></div></div>
  <div class="d-flex gap-2">
    <a href="tenancy_form.php?property_id=<?= $id ?>" class="btn btn-sm btn-success"><i class="bi bi-person-plus"></i> Naya kirayedar</a>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProp"><i class="bi bi-pencil"></i></button>
  </div>
</div>
<?php if ($hasActive): ?><div class="alert alert-info small py-2">Is makan mein abhi kirayedar mojood hai. Naya kirayedar tab add karein jab purana chhor jaye (ya agar makan ka hissa alag rent par hai).</div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="label">Ab tak kiraya wusool</div><div class="value text-in"><?= money($totalCollected) ?></div></div></div></div>
  <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="label">Makan par kharcha</div><div class="value text-out"><?= money($totalExp) ?></div></div></div></div>
  <div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="label">Net aamdani</div><div class="value"><?= money($totalCollected - $totalExp) ?></div></div></div></div>
</div>

<div class="card mb-3"><div class="card-header bg-white fw-semibold">Kirayedar (history)</div>
<div class="table-responsive"><table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Kirayedar</th><th>Muddat</th><th class="amt">Kiraya</th><th class="amt">Baqi</th><th>Status</th></tr></thead>
  <?php foreach ($tenancies as $t): $l = rent_ledger($t); ?>
    <tr onclick="location='tenancy.php?id=<?= $t['id'] ?>'" style="cursor:pointer">
      <td><a href="tenancy.php?id=<?= $t['id'] ?>" class="fw-semibold"><?= e($t['tenant_name']) ?></a><div class="small text-muted"><?= e($t['phone']) ?></div></td>
      <td class="small"><?= fdate($t['start_date']) ?> – <?= $t['status'] === 'vacated' ? fdate($t['vacate_date']) : 'ab tak' ?></td>
      <td class="amt"><?= money($l['current_rent']) ?></td>
      <td class="amt <?= $l['balance'] > 0.5 ? 'text-out' : '' ?>"><?= money($l['balance']) ?></td>
      <td><?= $t['status'] === 'active' ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Chhor gaya</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$tenancies): ?><tr><td colspan="5" class="text-muted text-center p-4">Abhi koi kirayedar nahi.</td></tr><?php endif; ?>
</table></div></div>

<div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Makan ka kharcha (repair, tax waghera)</span>
  <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#expModal"><i class="bi bi-plus"></i> Add</button></div>
  <table class="table table-sm mb-0">
  <?php foreach ($expenses as $x): ?>
    <tr><td><?= fdate($x['exp_date']) ?></td><td class="small"><?= e($x['note']) ?></td><td class="amt text-out"><?= money($x['amount']) ?></td>
      <td class="text-end"><form method="post" data-confirm="Delete?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="eid" value="<?= $x['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?>
  <?php if (!$expenses): ?><tr><td class="text-muted p-3">Koi kharcha nahi.</td></tr><?php endif; ?>
  </table>
</div>

<div class="modal fade" id="expModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="expense">
  <div class="modal-header"><h5 class="modal-title">Makan ka kharcha</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-6"><input type="date" name="exp_date" value="<?= date('Y-m-d') ?>" class="form-control" required></div>
    <div class="col-6"><input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Raqam" required></div>
    <div class="col-12"><input name="note" class="form-control" placeholder="e.g. Paint, plumber, property tax"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-danger">Save</button></div>
</form></div></div>

<div class="modal fade" id="editProp" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
  <div class="modal-header"><h5 class="modal-title">Makan edit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label">Naam</label><input name="name" class="form-control" value="<?= e($p['name']) ?>" required></div>
    <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($p['address']) ?>"></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control" value="<?= e($p['note']) ?>"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div></form>
  <form method="post" class="px-3 pb-3" data-confirm="Makan, sab kirayedar, payments aur documents delete ho jayenge. Pakka?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">Makan delete karein</button></form>
</div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
