<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cash.php';

$id = (int)($_GET['id'] ?? 0);
$inv = db_one('SELECT * FROM investments WHERE id = ?', [$id]);
if (!$inv) { flash('Investment nahi mili.', 'danger'); redirect('investments.php'); }

$types = ['invest' => 'Installment / Paisa lagaya', 'profit' => 'Profit mila', 'withdraw' => 'Asal raqam wapas li'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post('action')) {
        case 'entry':
            $eid = (int)post('entry_id');
            $type = array_key_exists(post('type'), $types) ? post('type') : 'invest';
            $d = valid_date(post('entry_date'));
            $amt = amount_in('amount');
            if (!$d || $amt <= 0) { flash('Tareekh aur raqam sahi likhein.', 'danger'); break; }
            if ($eid) {
                db_exec('UPDATE investment_entries SET entry_date=?, type=?, amount=?, note=? WHERE id=? AND investment_id=?', [$d, $type, $amt, post('note') ?: null, $eid, $id]);
                flash('Entry update ho gayi.');
            } else {
                db_exec('INSERT INTO investment_entries (investment_id, entry_date, type, amount, note) VALUES (?,?,?,?,?)', [$id, $d, $type, $amt, post('note') ?: null]);
                flash($types[$type] . ': ' . money($amt) . ' add ho gaya.');
            }
            break;
        case 'delete_entry':
            db_exec('DELETE FROM investment_entries WHERE id = ? AND investment_id = ?', [(int)post('entry_id'), $id]);
            flash('Entry delete ho gayi.', 'warning');
            break;
        case 'update':
            if (post('name') !== '' && valid_date(post('start_date'))) {
                db_exec('UPDATE investments SET name=?, description=?, start_date=?, status=? WHERE id=?',
                    [post('name'), post('description') ?: null, post('start_date'), post('status') === 'closed' ? 'closed' : 'active', $id]);
                flash('Investment update ho gayi.');
            }
            break;
        case 'delete':
            db_exec('DELETE FROM investments WHERE id = ?', [$id]);
            flash('Investment aur us ki sab entries delete ho gayin.', 'warning');
            redirect('investments.php');
    }
    redirect('investment.php?id=' . $id);
}

$entries = db_all('SELECT * FROM investment_entries WHERE investment_id = ? ORDER BY entry_date, id', [$id]);
$t = investment_totals($id);
$monthsRunning = max(1, (int)((time() - strtotime($inv['start_date'])) / (86400 * 30.44)));

// Saal wise profit
$yearly = db_all("SELECT YEAR(entry_date) y, SUM(IF(type='profit', amount, 0)) profit, SUM(IF(type='invest', amount, 0)) invested
                  FROM investment_entries WHERE investment_id = ? GROUP BY y ORDER BY y", [$id]);

$pageTitle = $inv['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><a href="investments.php" class="small"><i class="bi bi-arrow-left"></i> Investments</a>
    <h1><?= e($inv['name']) ?> <?php if ($inv['status'] === 'closed'): ?><span class="badge text-bg-secondary fs-6">Band</span><?php endif; ?></h1>
    <div class="text-muted small"><?= e($inv['description']) ?> · Shuru: <?= fdate($inv['start_date']) ?></div></div>
  <div class="d-flex gap-2 flex-wrap">
    <?php foreach (['invest' => ['danger', 'Installment'], 'profit' => ['success', 'Profit'], 'withdraw' => ['primary', 'Wapsi']] as $k => [$cls, $lbl]): ?>
      <button class="btn btn-sm btn-<?= $cls ?>" data-bs-toggle="modal" data-bs-target="#entryModal" data-fill="#entryForm"
        data-values='<?= e(json_encode(['type' => $k, 'entry_date' => date('Y-m-d')])) ?>'><i class="bi bi-plus-lg"></i> <?= $lbl ?></button>
    <?php endforeach; ?>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editInv"><i class="bi bi-pencil"></i></button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md"><div class="card stat-card"><div class="card-body"><div class="label">Installments (<?= (int)$t['installments'] ?>)</div><div class="value"><?= money($t['invested']) ?></div></div></div></div>
  <div class="col-6 col-md"><div class="card stat-card"><div class="card-body"><div class="label">Wapas liya</div><div class="value"><?= money($t['withdrawn']) ?></div></div></div></div>
  <div class="col-6 col-md"><div class="card stat-card"><div class="card-body"><div class="label">Abhi laga hua</div><div class="value"><?= money($t['outstanding']) ?></div></div></div></div>
  <div class="col-6 col-md"><div class="card stat-card"><div class="card-body"><div class="label">Kul profit</div><div class="value text-in"><?= money($t['profit']) ?></div></div></div></div>
  <div class="col-12 col-md"><div class="card stat-card"><div class="card-body"><div class="label">ROI · Mahana avg</div><div class="value"><?= number_format($t['roi'], 1) ?>% · <?= money($t['profit'] / $monthsRunning) ?></div></div></div></div>
</div>

<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead class="table-light"><tr><th>#</th><th>Tareekh</th><th>Type</th><th>Note</th><th class="amt">Raqam</th><th class="amt">Laga hua</th><th></th></tr></thead>
  <tbody>
  <?php $run = 0; $n = 0; foreach ($entries as $en):
      if ($en['type'] === 'invest') { $run += $en['amount']; $n++; } elseif ($en['type'] === 'withdraw') { $run -= $en['amount']; } ?>
    <tr>
      <td class="text-muted small"><?= $en['type'] === 'invest' ? $n : '' ?></td>
      <td class="text-nowrap"><?= fdate($en['entry_date']) ?></td>
      <td><?= ['invest' => '<span class="badge text-bg-danger">Installment</span>', 'profit' => '<span class="badge text-bg-success">Profit</span>', 'withdraw' => '<span class="badge text-bg-primary">Wapsi</span>'][$en['type']] ?></td>
      <td class="small text-muted"><?= e($en['note']) ?></td>
      <td class="amt <?= $en['type'] === 'invest' ? 'text-out' : 'text-in' ?>"><?= money($en['amount']) ?></td>
      <td class="amt small"><?= money($run, false) ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#entryModal" data-fill="#entryForm"
          data-values='<?= e(json_encode(['entry_id' => $en['id'], 'type' => $en['type'], 'entry_date' => $en['entry_date'], 'amount' => $en['amount'], 'note' => $en['note']])) ?>'><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" data-confirm="Delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_entry"><input type="hidden" name="entry_id" value="<?= $en['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$entries): ?><tr><td colspan="7" class="text-center text-muted p-4">Abhi koi entry nahi.</td></tr><?php endif; ?>
  </tbody>
</table></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header bg-white fw-semibold">Saal wise</div>
  <table class="table table-sm mb-0"><thead><tr><th>Saal</th><th class="amt">Lagaya</th><th class="amt">Profit</th></tr></thead>
  <?php foreach ($yearly as $y): ?><tr><td><?= $y['y'] ?></td><td class="amt"><?= money($y['invested'], false) ?></td><td class="amt text-in"><?= money($y['profit'], false) ?></td></tr><?php endforeach; ?>
  </table></div></div>
</div>

<div class="modal fade" id="entryModal" tabindex="-1"><div class="modal-dialog"><form method="post" id="entryForm" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="entry"><input type="hidden" name="entry_id">
  <div class="modal-header"><h5 class="modal-title">Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label">Type</label><select name="type" class="form-select"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
    <div class="col-6"><label class="form-label">Tareekh</label><input type="date" name="entry_date" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Raqam</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control" maxlength="255"></div>
    <div class="col-12 small text-muted">Installment cash se minus hogi; profit aur wapsi cash mein jama hongi.</div>
  </div>
  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
</form></div></div>

<div class="modal fade" id="editInv" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
  <div class="modal-header"><h5 class="modal-title">Investment edit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label">Naam</label><input name="name" class="form-control" value="<?= e($inv['name']) ?>" required></div>
    <div class="col-12"><label class="form-label">Tafseel</label><input name="description" class="form-control" value="<?= e($inv['description']) ?>"></div>
    <div class="col-6"><label class="form-label">Shuru</label><input type="date" name="start_date" class="form-control" value="<?= e($inv['start_date']) ?>" required></div>
    <div class="col-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="closed" <?= $inv['status'] === 'closed' ? 'selected' : '' ?>>Band (closed)</option></select></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
  </form>
  <form method="post" class="px-3 pb-3" data-confirm="Poori investment aur sab entries delete ho jayengi. Pakka?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">Investment delete karein</button></form>
</div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
