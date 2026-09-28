<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/rent.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create') {
    if (post('name') === '') { flash('Makan ka naam likhein.', 'danger'); redirect('properties.php'); }
    $id = db_exec('INSERT INTO properties (name, address, note) VALUES (?,?,?)', [post('name'), post('address') ?: null, post('note') ?: null]);
    flash('Makan add ho gaya. Ab kirayedar add karein.');
    redirect('property.php?id=' . $id);
}

$props = db_all('SELECT * FROM properties ORDER BY name');
$active = [];
foreach (active_tenancies() as $t) {
    $active[$t['property_id']][] = ['t' => $t, 'l' => rent_ledger($t)];
}
$totRent = 0; $totDue = 0; $totAdv = 0;
foreach ($active as $list) foreach ($list as $x) { $totRent += $x['l']['current_rent']; $totDue += max(0, $x['l']['balance']); $totAdv += $x['t']['advance_amount']; }

$pageTitle = 'Rent';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1><i class="bi bi-house-door"></i> Makan / Rent</h1>
  <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#newProp"><i class="bi bi-plus-lg"></i> Naya Makan</button>
</div>
<p class="small text-muted">Ye hisab salary/cash se bilkul alag hai.</p>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Makan</div><div class="value"><?= count($props) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Mahana kiraya</div><div class="value"><?= money($totRent) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Kiraya baqi</div><div class="value text-out"><?= money($totDue) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="label">Advance (security) jama</div><div class="value"><?= money($totAdv) ?></div></div></div></div>
</div>

<div class="row g-3">
<?php foreach ($props as $p): $list = $active[$p['id']] ?? []; ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <h5 class="mb-0"><a href="property.php?id=<?= $p['id'] ?>" class="text-reset text-decoration-none"><?= e($p['name']) ?></a></h5>
          <?= $list ? '<span class="badge text-bg-success">Rent par</span>' : '<span class="badge text-bg-secondary">Khali</span>' ?>
        </div>
        <div class="small text-muted mb-2"><?= e($p['address']) ?></div>
        <?php foreach ($list as $x): $t = $x['t']; $l = $x['l']; ?>
          <a href="tenancy.php?id=<?= $t['id'] ?>" class="d-block border rounded p-2 mb-2 text-reset text-decoration-none">
            <div class="d-flex justify-content-between"><strong><?= e($t['tenant_name']) ?></strong><span><?= money($l['current_rent']) ?>/mah</span></div>
            <div class="small">
              <?php if ($l['balance'] > 0.5): ?><span class="text-out"><?= money($l['balance']) ?> baqi (~<?= $l['pending_months'] ?> mah)</span>
              <?php elseif ($l['balance'] < -0.5): ?><span class="text-in"><?= money(-$l['balance']) ?> advance mein</span>
              <?php else: ?><span class="text-in">Sab clear</span><?php endif; ?>
              <?php if ($l['increment_due']): ?> · <span class="badge text-bg-info">Increment due</span><?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if (!$list): ?><a href="tenancy_form.php?property_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-person-plus"></i> Kirayedar add karein</a><?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$props): ?><div class="col-12"><div class="card"><div class="card-body text-muted">Abhi koi makan add nahi kiya.</div></div></div><?php endif; ?>
</div>

<div class="modal fade" id="newProp" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="modal-header"><h5 class="modal-title">Naya Makan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label">Naam</label><input name="name" class="form-control" required placeholder="e.g. Upper portion, Shop #2"></div>
    <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control"></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
</form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
