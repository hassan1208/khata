<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cash.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create') {
    $name = post('name');
    $start = valid_date(post('start_date'));
    $amount = amount_in('amount');
    if ($name === '' || !$start) {
        flash('Naam aur tareekh zaroori hai.', 'danger');
        redirect('investments.php');
    }
    $id = db_exec('INSERT INTO investments (name, description, start_date) VALUES (?,?,?)', [$name, post('description') ?: null, $start]);
    if ($amount > 0) {
        db_exec("INSERT INTO investment_entries (investment_id, entry_date, type, amount, note) VALUES (?,?, 'invest', ?, 'Pehli raqam')", [$id, $start, $amount]);
    }
    flash('Investment ban gayi. Ab installments aur profit is mein add karte jayein.');
    redirect('investment.php?id=' . $id);
}

$show = ($_GET['show'] ?? 'active') === 'all' ? 'all' : 'active';
$rows = db_all("SELECT i.*,
      COALESCE(SUM(IF(e.type='invest', e.amount, 0)),0) invested,
      COALESCE(SUM(IF(e.type='profit', e.amount, 0)),0) profit,
      COALESCE(SUM(IF(e.type='withdraw', e.amount, 0)),0) withdrawn,
      COALESCE(SUM(e.type='invest'),0) installments,
      MAX(IF(e.type='profit', e.entry_date, NULL)) last_profit
    FROM investments i LEFT JOIN investment_entries e ON e.investment_id = i.id
    " . ($show === 'active' ? "WHERE i.status='active'" : '') . "
    GROUP BY i.id ORDER BY i.status, i.start_date DESC");
$tot = investment_totals();

$pageTitle = 'Investments';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1><i class="bi bi-graph-up-arrow"></i> Investments</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="?show=<?= $show === 'all' ? 'active' : 'all' ?>"><?= $show === 'all' ? 'Sirf active' : 'Band shuda bhi dikhayein' ?></a>
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#newInv"><i class="bi bi-plus-lg"></i> Nayi Investment</button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Kul lagaya</div><div class="value"><?= money($tot['invested']) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Wapas liya</div><div class="value"><?= money($tot['withdrawn']) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Abhi laga hua</div><div class="value"><?= money($tot['outstanding']) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Kul profit (<?= number_format($tot['roi'], 1) ?>%)</div><div class="value text-in"><?= money($tot['profit']) ?></div></div></div></div>
</div>

<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Naam</th><th>Shuru</th><th class="text-center">Installments</th><th class="amt">Lagaya</th><th class="amt">Wapas</th><th class="amt">Profit</th><th class="amt">ROI</th><th>Aakhri profit</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr onclick="location='investment.php?id=<?= $r['id'] ?>'" style="cursor:pointer">
      <td><a href="investment.php?id=<?= $r['id'] ?>" class="fw-semibold"><?= e($r['name']) ?></a>
        <?php if ($r['status'] === 'closed'): ?><span class="badge text-bg-secondary">Band</span><?php endif; ?>
        <?php if ($r['description']): ?><div class="small text-muted"><?= e($r['description']) ?></div><?php endif; ?></td>
      <td class="text-nowrap"><?= fdate($r['start_date']) ?></td>
      <td class="text-center"><?= (int)$r['installments'] ?></td>
      <td class="amt"><?= money($r['invested']) ?></td>
      <td class="amt"><?= money($r['withdrawn']) ?></td>
      <td class="amt text-in"><?= money($r['profit']) ?></td>
      <td class="amt"><?= $r['invested'] > 0 ? number_format($r['profit'] / $r['invested'] * 100, 1) . '%' : '-' ?></td>
      <td class="small"><?= fdate($r['last_profit']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted p-4">Koi investment nahi.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<div class="modal fade" id="newInv" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="modal-header"><h5 class="modal-title">Nayi Investment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label">Naam</label><input name="name" class="form-control" required placeholder="e.g. Plot DHA, Business share, Committee"></div>
    <div class="col-12"><label class="form-label">Tafseel</label><input name="description" class="form-control" maxlength="255"></div>
    <div class="col-6"><label class="form-label">Shuru tareekh</label><input type="date" name="start_date" value="<?= date('Y-m-d') ?>" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Pehli raqam (optional)</label><input type="number" step="0.01" min="0" name="amount" class="form-control"></div>
    <div class="col-12 small text-muted">Installments pehle se fix nahi — baad mein jab jab dein, add karte jayein.</div>
  </div>
  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
</form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
