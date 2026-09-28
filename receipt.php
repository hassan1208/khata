<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/rent.php';

$p = db_one('SELECT * FROM rent_payments WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
$t = $p ? db_one('SELECT t.*, pr.name AS property_name, pr.address FROM tenancies t JOIN properties pr ON pr.id = t.property_id WHERE t.id = ?', [$p['tenancy_id']]) : null;
if (!$t) { flash('Payment nahi mili.', 'danger'); redirect('properties.php'); }

$l = rent_ledger($t);
$alloc = payment_allocation($t, $l)[$p['id']] ?? [];
$paidTill = (float)db_val('SELECT COALESCE(SUM(amount),0) FROM rent_payments WHERE tenancy_id = ? AND (pay_date < ? OR (pay_date = ? AND id <= ?))',
    [$t['id'], $p['pay_date'], $p['pay_date'], $p['id']]);
$dueTill = $l['opening_due'];
foreach ($l['months'] as $m) if ($m['month'] <= date('Y-m-01', strtotime($p['pay_date']))) $dueTill += $m['due'];

$pageTitle = 'Rent receipt';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between mb-3 no-print">
  <a href="tenancy.php?id=<?= $t['id'] ?>" class="small"><i class="bi bi-arrow-left"></i> Wapas</a>
  <button class="btn btn-sm btn-primary" onclick="print()"><i class="bi bi-printer"></i> Print</button>
</div>
<div class="card mx-auto" style="max-width:620px"><div class="card-body p-4">
  <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-3">
    <div><h4 class="mb-0">Kiraya Raseed</h4><div class="small text-muted">Rent Receipt</div></div>
    <div class="text-end small">No. <strong>R-<?= str_pad((string)$p['id'], 5, '0', STR_PAD_LEFT) ?></strong><br><?= fdate($p['pay_date']) ?></div>
  </div>
  <table class="table table-sm table-borderless mb-3">
    <tr><th style="width:40%">Kirayedar</th><td><?= e($t['tenant_name']) ?> <?= $t['cnic'] ? '<span class="text-muted small">(' . e($t['cnic']) . ')</span>' : '' ?></td></tr>
    <tr><th>Makan</th><td><?= e($t['property_name']) ?><?= $t['address'] ? ', ' . e($t['address']) : '' ?></td></tr>
    <tr><th>Raqam wusool</th><td class="fs-5 fw-semibold"><?= money($p['amount']) ?></td></tr>
    <tr><th>Tareeqa</th><td><?= e($p['method']) ?></td></tr>
    <?php if ($p['note']): ?><tr><th>Note</th><td><?= e($p['note']) ?></td></tr><?php endif; ?>
  </table>
  <h6>Kis maheenay ka</h6>
  <table class="table table-sm">
    <?php foreach ($alloc as $a): ?><tr><td><?= e($a['label']) ?></td><td class="amt"><?= money($a['amount']) ?></td></tr><?php endforeach; ?>
  </table>
  <div class="d-flex justify-content-between small bg-light rounded p-2">
    <span>Is raseed k baad baqaya (<?= fmonth($p['pay_date']) ?> tak):</span>
    <strong><?= money(max(0, $dueTill - $paidTill)) ?></strong>
  </div>
  <div class="d-flex justify-content-between mt-5 small">
    <div class="border-top pt-1 px-4">Kirayedar</div><div class="border-top pt-1 px-4">Malik makan</div>
  </div>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
