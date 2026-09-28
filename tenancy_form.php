<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/upload.php';

$id = (int)($_GET['id'] ?? 0);
$t = $id ? db_one('SELECT * FROM tenancies WHERE id = ?', [$id]) : null;
if ($id && !$t) { flash('Record nahi mila.', 'danger'); redirect('properties.php'); }
$propertyId = $t ? (int)$t['property_id'] : (int)($_GET['property_id'] ?? 0);
$p = db_one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
if (!$p) { flash('Pehle makan chunein.', 'danger'); redirect('properties.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = valid_date(post('start_date'));
    $billing = month_start(post('billing_start')) ?? ($start ? month_start($start) : null);
    $rent = amount_in('rent_amount');
    $f = [
        'tenant_name'      => post('tenant_name'),
        'phone'            => post('phone') ?: null,
        'cnic'             => post('cnic') ?: null,
        'start_date'       => $start,
        'billing_start'    => $billing,
        'opening_due'      => amount_in('opening_due'),
        'due_day'          => max(1, min(28, (int)post('due_day', 5))),
        'advance_amount'   => amount_in('advance_amount'),
        'advance_date'     => valid_date(post('advance_date')),
        'agreement_months' => (int)post('agreement_months') ?: null,
        'increment_type'   => post('increment_type') === 'fixed' ? 'fixed' : 'percent',
        'increment_value'  => amount_in('increment_value'),
        'increment_every'  => max(0, (int)post('increment_every', 12)),
        'note'             => post('note') ?: null,
    ];
    if ($f['tenant_name'] === '') $errors[] = 'Kirayedar ka naam likhein.';
    if (!$start) $errors[] = 'Rent par dene ki tareekh likhein.';
    if (!$t && $rent <= 0) $errors[] = 'Mahana kiraya likhein.';

    if (!$errors) {
        if ($t) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($f)));
            db_exec("UPDATE tenancies SET $sets WHERE id = ?", [...array_values($f), $id]);
            // Hisab pehle shuru kiya to pehla kiraya bhi wahin se lagu ho
            $first = db_one('SELECT id, effective_month FROM rent_revisions WHERE tenancy_id = ? ORDER BY effective_month LIMIT 1', [$id]);
            if ($first && $first['effective_month'] > $billing) {
                db_exec('UPDATE rent_revisions SET effective_month = ? WHERE id = ?', [$billing, $first['id']]);
            }
            flash('Kirayedar ki maloomat update ho gayin.');
        } else {
            $f['property_id'] = $propertyId;
            $cols = implode(', ', array_keys($f));
            $id = db_exec("INSERT INTO tenancies ($cols) VALUES (" . rtrim(str_repeat('?,', count($f)), ',') . ')', array_values($f));
            db_exec('INSERT INTO rent_revisions (tenancy_id, effective_month, rent_amount, note) VALUES (?,?,?,?)', [$id, $billing, $rent, 'Shuru ka kiraya']);
            flash('Kirayedar add ho gaya.');
        }
        save_tenancy_files($id, 'docs', post('doc_title'));
        redirect('tenancy.php?id=' . $id);
    }
}

$v = fn($k, $d = '') => e($_POST[$k] ?? ($t[$k] ?? $d));
$pageTitle = $t ? 'Kirayedar edit' : 'Naya kirayedar';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><a href="property.php?id=<?= $propertyId ?>" class="small"><i class="bi bi-arrow-left"></i> <?= e($p['name']) ?></a><h1><?= e($pageTitle) ?></h1></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="card"><div class="card-body"><?= csrf_field() ?>
  <h6 class="text-muted">Kirayedar</h6>
  <div class="row g-3 mb-3">
    <div class="col-md-4"><label class="form-label">Naam *</label><input name="tenant_name" class="form-control" required value="<?= $v('tenant_name') ?>"></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
    <div class="col-md-4"><label class="form-label">CNIC</label><input name="cnic" class="form-control" placeholder="35202-1234567-1" value="<?= $v('cnic') ?>"></div>
  </div>

  <h6 class="text-muted">Kiraya</h6>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Rent par diya (tareekh) *</label><input type="date" name="start_date" class="form-control" required value="<?= $v('start_date', date('Y-m-d')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Hisab kis maheenay se</label><input type="month" name="billing_start" class="form-control" value="<?= e(substr($_POST['billing_start'] ?? ($t['billing_start'] ?? date('Y-m-01')), 0, 7)) ?>">
      <div class="form-text">Purana kirayedar ho to current maheena rakhein aur pichla baqaya neeche likhein.</div></div>
    <?php if (!$t): ?>
    <div class="col-md-3"><label class="form-label">Mahana kiraya *</label><input type="number" step="0.01" min="1" name="rent_amount" class="form-control" required value="<?= $v('rent_amount') ?>"></div>
    <?php else: ?>
    <div class="col-md-3"><label class="form-label">Mahana kiraya</label><div class="form-control-plaintext small">Kiraya tabdeel karna ho to kirayedar page par "Kiraya barhayein" use karein.</div></div>
    <?php endif; ?>
    <div class="col-md-3"><label class="form-label">Har maheenay kis tareekh tak</label><input type="number" min="1" max="28" name="due_day" class="form-control" value="<?= $v('due_day', 5) ?>"></div>
    <div class="col-md-3"><label class="form-label">Pichla baqaya (agar ho)</label><input type="number" step="0.01" min="0" name="opening_due" class="form-control" value="<?= $v('opening_due', 0) ?>"></div>
    <div class="col-md-3"><label class="form-label">Agreement muddat (maheenay)</label><input type="number" min="0" name="agreement_months" class="form-control" value="<?= $v('agreement_months', $t ? '' : 11) ?>"></div>
  </div>

  <h6 class="text-muted">Advance / Security</h6>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Advance liya</label><input type="number" step="0.01" min="0" name="advance_amount" class="form-control" value="<?= $v('advance_amount', 0) ?>"></div>
    <div class="col-md-3"><label class="form-label">Kab liya</label><input type="date" name="advance_date" class="form-control" value="<?= $v('advance_date', date('Y-m-d')) ?>"></div>
  </div>

  <h6 class="text-muted">Kiraya barhana (increment)</h6>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Har kitne maheenay baad</label><input type="number" min="0" name="increment_every" class="form-control" value="<?= $v('increment_every', 12) ?>"><div class="form-text">0 = koi increment nahi</div></div>
    <div class="col-md-3"><label class="form-label">Kitna</label><input type="number" step="0.01" min="0" name="increment_value" class="form-control" value="<?= $v('increment_value', 10) ?>"></div>
    <div class="col-md-3"><label class="form-label">Type</label><select name="increment_type" class="form-select">
      <option value="percent">% (percent)</option><option value="fixed" <?= ($_POST['increment_type'] ?? $t['increment_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed raqam</option></select></div>
  </div>

  <h6 class="text-muted">Stamp paper / documents</h6>
  <div class="row g-3 mb-3">
    <div class="col-md-4"><label class="form-label">Title</label><input name="doc_title" class="form-control" value="Stamp paper / Agreement"></div>
    <div class="col-md-8"><label class="form-label">Pictures / PDF (aik se zyada bhi)</label><input type="file" name="docs[]" class="form-control" multiple accept="image/*,application/pdf"></div>
  </div>

  <div class="mb-3"><label class="form-label">Note</label><textarea name="note" class="form-control" rows="2"><?= $v('note') ?></textarea></div>
  <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save</button>
</div></form>
<?php require __DIR__ . '/includes/footer.php'; ?>
