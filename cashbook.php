<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/cash.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'adjust') {
        $d = valid_date(post('adj_date'));
        $amt = amount_in('amount');
        if (post('direction') === 'minus') $amt = -$amt;
        if ($d && $amt != 0) {
            db_exec('INSERT INTO cash_adjustments (adj_date, amount, note) VALUES (?,?,?)', [$d, $amt, post('note') ?: null]);
            flash('Cash adjustment save ho gaya.');
        }
    } elseif (post('action') === 'set_actual') {
        // Hath mein jitna cash hai wo likho, farq khud adjustment ban jayega
        $actual = amount_in('actual');
        $diff = round($actual - cash_balance(), 2);
        if ($diff != 0) {
            db_exec('INSERT INTO cash_adjustments (adj_date, amount, note) VALUES (CURDATE(),?,?)', [$diff, 'Cash ginti se barabar kiya']);
            flash('Farq ' . money($diff) . ' adjust kar diya gaya.');
        } else {
            flash('Cash pehle se barabar hai.');
        }
    } elseif (post('action') === 'delete_adj') {
        db_exec('DELETE FROM cash_adjustments WHERE id = ?', [(int)post('id')]);
        flash('Adjustment delete ho gaya.', 'warning');
    }
    redirect('cashbook.php');
}

$from = valid_date($_GET['from'] ?? '') ?? date('Y-m-01');
$to   = valid_date($_GET['to'] ?? '') ?? date('Y-m-d');
$src  = $_GET['src'] ?? '';

$openBal = cash_balance(date('Y-m-d', strtotime("$from -1 day")));
$sql = 'SELECT * FROM (' . cashbook_sql() . ') t WHERE d BETWEEN ? AND ?';
$params = [$from, $to];
$rows = db_all($sql . ' ORDER BY d, created_at', $params);

$pageTitle = 'Cash Book';
require __DIR__ . '/includes/header.php';
$links = ['income' => 'income.php', 'expense' => 'expenses.php', 'investment' => 'investment.php?id=', 'loan' => 'loan.php?id=', 'adjust' => ''];
?>
<div class="page-head">
  <h1><i class="bi bi-book"></i> Cash Book</h1>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#actualModal"><i class="bi bi-calculator"></i> Cash ginti se milayein</button>
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adjModal"><i class="bi bi-plus-slash-minus"></i> Adjustment</button>
  </div>
</div>

<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small">Se</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3"><label class="form-label small">Tak</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3"><label class="form-label small">Type</label><select name="src" class="form-select form-select-sm">
      <option value="">Sab</option><?php foreach (['income' => 'Income', 'expense' => 'Kharcha', 'investment' => 'Investment', 'loan' => 'Udhar', 'adjust' => 'Adjustment'] as $k => $v): ?><option value="<?= $k ?>" <?= $src === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-3"><button class="btn btn-sm btn-primary w-100">Dekhein</button></div>
  </form>
  <div class="small text-muted mt-2">Opening cash (setup/settings): <?= money(setting('opening_cash')) ?> · Abhi ka cash: <strong><?= money(cash_balance()) ?></strong></div>
</div></div>

<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
  <thead class="table-light"><tr><th>Tareekh</th><th>Tafseel</th><th class="amt">Aamad (+)</th><th class="amt">Kharch (-)</th><th class="amt">Balance</th><th></th></tr></thead>
  <tbody>
    <tr class="table-secondary"><td><?= fdate($from) ?></td><td colspan="3"><em>Pichla balance</em></td><td class="amt fw-semibold"><?= money($openBal) ?></td><td></td></tr>
    <?php $bal = $openBal; $in = 0; $out = 0; foreach ($rows as $r): $bal += $r['amt'];
      if ($src && $r['src'] !== $src) continue;
      $r['amt'] >= 0 ? $in += $r['amt'] : $out += -$r['amt']; ?>
      <tr>
        <td class="text-nowrap"><?= fdate($r['d']) ?></td>
        <td><?php $lnk = $links[$r['src']]; if ($lnk): ?><a class="text-reset" href="<?= $lnk . (str_ends_with($lnk, '=') ? $r['ref_id'] : '') ?>"><?= e($r['descr']) ?></a><?php else: ?><?= e($r['descr']) ?><?php endif; ?>
          <?php if ($r['note']): ?><div class="small text-muted"><?= e($r['note']) ?></div><?php endif; ?></td>
        <td class="amt text-in"><?= $r['amt'] >= 0 ? money($r['amt'], false) : '' ?></td>
        <td class="amt text-out"><?= $r['amt'] < 0 ? money(-$r['amt'], false) : '' ?></td>
        <td class="amt"><?= money($bal, false) ?></td>
        <td><?php if ($r['src'] === 'adjust'): ?><form method="post" data-confirm="Adjustment delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_adj"><input type="hidden" name="id" value="<?= $r['ref_id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-circle"></i></button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted p-4">Is muddat mein koi entry nahi.</td></tr><?php endif; ?>
  </tbody>
  <tfoot class="table-light"><tr><th colspan="2">Total</th><th class="amt text-in"><?= money($in, false) ?></th><th class="amt text-out"><?= money($out, false) ?></th><th class="amt"><?= money($bal, false) ?></th><th></th></tr></tfoot>
</table></div></div>

<div class="modal fade" id="adjModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="adjust">
  <div class="modal-header"><h5 class="modal-title">Cash Adjustment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-6"><label class="form-label">Tareekh</label><input type="date" name="adj_date" value="<?= date('Y-m-d') ?>" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Raqam</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
    <div class="col-12"><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="direction" value="plus" id="dp" checked><label class="form-check-label" for="dp">Cash barhao (+)</label></div>
      <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="direction" value="minus" id="dm"><label class="form-check-label" for="dm">Cash kam karo (-)</label></div></div>
    <div class="col-12"><label class="form-label">Wajah</label><input name="note" class="form-control" maxlength="255" required></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
</form></div></div>

<div class="modal fade" id="actualModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="set_actual">
  <div class="modal-header"><h5 class="modal-title">Cash ginti se milayein</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="small text-muted">System k hisab se cash: <strong><?= money(cash_balance()) ?></strong>. Asal mein jitna cash hai wo likhein, farq adjustment ban jayega.</p>
    <input type="number" step="0.01" name="actual" class="form-control" required>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Barabar karein</button></div>
</form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
