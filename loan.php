<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/loan_types.php';

$id = (int)($_GET['id'] ?? 0);
$p = db_one('SELECT * FROM loan_people WHERE id = ?', [$id]);
if (!$p) { flash('Account nahi mila.', 'danger'); redirect('loans.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post('action')) {
        case 'entry':
            $eid = (int)post('entry_id');
            $type = array_key_exists(post('type'), LOAN_TYPES) ? post('type') : 'gave';
            $d = valid_date(post('entry_date'));
            $amt = amount_in('amount');
            if (!$d || $amt <= 0) { flash('Tareekh aur raqam sahi likhein.', 'danger'); break; }
            if ($eid) {
                db_exec('UPDATE loan_entries SET entry_date=?, type=?, amount=?, note=? WHERE id=? AND person_id=?', [$d, $type, $amt, post('note') ?: null, $eid, $id]);
            } else {
                db_exec('INSERT INTO loan_entries (person_id, entry_date, type, amount, note) VALUES (?,?,?,?,?)', [$id, $d, $type, $amt, post('note') ?: null]);
            }
            flash('Entry save ho gayi.');
            break;
        case 'delete_entry':
            db_exec('DELETE FROM loan_entries WHERE id = ? AND person_id = ?', [(int)post('entry_id'), $id]);
            flash('Entry delete ho gayi.', 'warning');
            break;
        case 'update':
            if (post('name') !== '') {
                db_exec('UPDATE loan_people SET name=?, phone=?, note=? WHERE id=?', [post('name'), post('phone') ?: null, post('note') ?: null, $id]);
                flash('Update ho gaya.');
            }
            break;
        case 'delete':
            db_exec('DELETE FROM loan_people WHERE id = ?', [$id]);
            flash('Account delete ho gaya.', 'warning');
            redirect('loans.php');
    }
    redirect('loan.php?id=' . $id);
}

$entries = db_all('SELECT * FROM loan_entries WHERE person_id = ? ORDER BY entry_date, id', [$id]);

$pageTitle = $p['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><a href="loans.php" class="small"><i class="bi bi-arrow-left"></i> Udhar</a>
    <h1><?= e($p['name']) ?></h1><div class="small text-muted"><?= e($p['phone']) ?> <?= $p['note'] ? '· ' . e($p['note']) : '' ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <?php foreach (LOAN_TYPES as $k => [$lbl, $cls, $icon]): ?>
      <button class="btn btn-sm btn-<?= $cls ?>" data-bs-toggle="modal" data-bs-target="#entryModal" data-fill="#entryForm"
        data-values='<?= e(json_encode(['type' => $k, 'entry_date' => date('Y-m-d')])) ?>'><i class="bi <?= $icon ?>"></i> <?= $lbl ?></button>
    <?php endforeach; ?>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editP"><i class="bi bi-pencil"></i></button>
  </div>
</div>

<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead class="table-light"><tr><th>Tareekh</th><th>Tafseel</th><th class="amt">Diya / Wapas kiya</th><th class="amt">Liya / Wapas mila</th><th class="amt">Balance</th><th></th></tr></thead>
  <tbody>
  <?php $bal = 0; foreach ($entries as $en):
      $mine = in_array($en['type'], ['gave', 'paid_back'], true); // mere haath se gaya
      $bal += $mine ? $en['amount'] : -$en['amount']; ?>
    <tr>
      <td class="text-nowrap"><?= fdate($en['entry_date']) ?></td>
      <td><span class="badge text-bg-<?= LOAN_TYPES[$en['type']][1] ?>"><?= LOAN_TYPES[$en['type']][0] ?></span> <span class="small text-muted"><?= e($en['note']) ?></span></td>
      <td class="amt text-out"><?= $mine ? money($en['amount'], false) : '' ?></td>
      <td class="amt text-in"><?= !$mine ? money($en['amount'], false) : '' ?></td>
      <td class="amt"><?= $bal > 0.004 ? '<span class="text-in">' . money($bal, false) . ' (lena)</span>' : ($bal < -0.004 ? '<span class="text-out">' . money(-$bal, false) . ' (dena)</span>' : '0') ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#entryModal" data-fill="#entryForm"
          data-values='<?= e(json_encode(['entry_id' => $en['id'], 'type' => $en['type'], 'entry_date' => $en['entry_date'], 'amount' => $en['amount'], 'note' => $en['note']])) ?>'><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" data-confirm="Delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_entry"><input type="hidden" name="entry_id" value="<?= $en['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$entries): ?><tr><td colspan="6" class="text-center text-muted p-4">Koi entry nahi.</td></tr><?php endif; ?>
  </tbody>
  <tfoot class="table-light"><tr><th colspan="4">Aakhri hisab</th><th class="amt fs-6">
    <?= $bal > 0.004 ? '<span class="text-in">' . money($bal) . ' ' . e($p['name']) . ' ne dene hain</span>' : ($bal < -0.004 ? '<span class="text-out">' . money(-$bal) . ' maine dene hain</span>' : 'Hisab barabar') ?>
  </th><th></th></tr></tfoot>
</table></div></div>

<div class="modal fade" id="entryModal" tabindex="-1"><div class="modal-dialog"><form method="post" id="entryForm" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="entry"><input type="hidden" name="entry_id">
  <div class="modal-header"><h5 class="modal-title">Udhar entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><select name="type" class="form-select"><?php foreach (LOAN_TYPES as $k => [$lbl]): ?><option value="<?= $k ?>"><?= $lbl ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label">Tareekh</label><input type="date" name="entry_date" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Raqam</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control" maxlength="255"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
</form></div></div>

<div class="modal fade" id="editP" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
  <div class="modal-header"><h5 class="modal-title">Edit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-7"><label class="form-label">Naam</label><input name="name" class="form-control" value="<?= e($p['name']) ?>" required></div>
    <div class="col-5"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($p['phone']) ?>"></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control" value="<?= e($p['note']) ?>"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div></form>
  <form method="post" class="px-3 pb-3" data-confirm="Is bande ka poora hisab delete ho jayega. Pakka?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
</div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
