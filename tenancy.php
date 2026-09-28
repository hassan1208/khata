<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/rent.php';
require __DIR__ . '/includes/upload.php';

const ADVANCE_METHOD = 'Advance adjust';

$id = (int)($_GET['id'] ?? 0);
$t = db_one('SELECT t.*, p.name AS property_name FROM tenancies t JOIN properties p ON p.id = t.property_id WHERE t.id = ?', [$id]);
if (!$t) { flash('Kirayedar nahi mila.', 'danger'); redirect('properties.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post('action')) {
        case 'pay':
            $pid = (int)post('pay_id');
            $d = valid_date(post('pay_date')); $amt = amount_in('amount');
            if (!$d || $amt <= 0) { flash('Tareekh aur raqam sahi likhein.', 'danger'); break; }
            $data = [$d, $amt, post('method') ?: 'Cash', post('note') ?: null];
            if ($pid) {
                db_exec('UPDATE rent_payments SET pay_date=?, amount=?, method=?, note=? WHERE id=? AND tenancy_id=?', [...$data, $pid, $id]);
                flash('Payment update ho gayi.');
            } else {
                db_exec('INSERT INTO rent_payments (tenancy_id, pay_date, amount, method, note) VALUES (?,?,?,?,?)', [$id, ...$data]);
                flash('Kiraya ' . money($amt) . ' wusool likh diya.');
            }
            break;

        case 'delete_pay':
            db_exec('DELETE FROM rent_payments WHERE id = ? AND tenancy_id = ?', [(int)post('pay_id'), $id]);
            flash('Payment delete ho gayi.', 'warning');
            break;

        case 'revise':
            $m = month_start(post('effective_month')); $amt = amount_in('rent_amount');
            if (!$m || $amt <= 0) { flash('Maheena aur kiraya sahi likhein.', 'danger'); break; }
            db_exec('INSERT INTO rent_revisions (tenancy_id, effective_month, rent_amount, note) VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE rent_amount = VALUES(rent_amount), note = VALUES(note)', [$id, $m, $amt, post('note') ?: null]);
            flash('Kiraya ' . fmonth($m) . ' se ' . money($amt) . ' ho gaya.');
            break;

        case 'delete_revision':
            if ((int)db_val('SELECT COUNT(*) FROM rent_revisions WHERE tenancy_id = ?', [$id]) > 1) {
                db_exec('DELETE FROM rent_revisions WHERE id = ? AND tenancy_id = ?', [(int)post('rev_id'), $id]);
                flash('Kiraye ki tabdeeli delete ho gayi.', 'warning');
            } else {
                flash('Kam az kam aik kiraya hona zaroori hai.', 'danger');
            }
            break;

        case 'upload':
            $n = save_tenancy_files($id, 'docs', post('doc_title'));
            if ($n) flash("$n file(s) upload ho gayin.");
            break;

        case 'delete_file':
            $f = db_one('SELECT * FROM tenancy_files WHERE id = ? AND tenancy_id = ?', [(int)post('file_id'), $id]);
            if ($f) {
                @unlink(UPLOAD_DIR . basename($f['file_name']));
                db_exec('DELETE FROM tenancy_files WHERE id = ?', [$f['id']]);
                flash('File delete ho gayi.', 'warning');
            }
            break;

        case 'vacate':
            $vd = valid_date(post('vacate_date'));
            $end = month_start(post('billing_end'));
            if (!$vd || !$end || $end < $t['billing_start']) { flash('Chhorne ki tareekh aur aakhri maheena sahi likhein.', 'danger'); break; }
            db()->begin_transaction();
            db_exec("UPDATE tenancies SET status='vacated', vacate_date=?, billing_end=?, deduction_amount=?, deduction_note=?,
                     refund_amount=?, refund_date=?, vacate_note=? WHERE id=?",
                [$vd, $end, amount_in('deduction_amount'), post('deduction_note') ?: null, amount_in('refund_amount'),
                 valid_date(post('refund_date')), post('vacate_note') ?: null, $id]);
            $adj = amount_in('advance_adjust');
            if ($adj > 0) {
                db_exec('INSERT INTO rent_payments (tenancy_id, pay_date, amount, method, note) VALUES (?,?,?,?,?)',
                    [$id, $vd, $adj, ADVANCE_METHOD, 'Baqaya kiraya advance se kaata']);
            }
            db()->commit();
            flash($t['tenant_name'] . ' ka makan chhorna record ho gaya.');
            break;

        case 'reopen':
            db_exec("UPDATE tenancies SET status='active', vacate_date=NULL, billing_end=NULL, deduction_amount=0, deduction_note=NULL,
                     refund_amount=0, refund_date=NULL, vacate_note=NULL WHERE id=?", [$id]);
            db_exec('DELETE FROM rent_payments WHERE tenancy_id = ? AND method = ?', [$id, ADVANCE_METHOD]);
            flash('Kirayedar dobara active kar diya gaya.');
            break;

        case 'delete':
            foreach (db_all('SELECT file_name FROM tenancy_files WHERE tenancy_id = ?', [$id]) as $f) {
                @unlink(UPLOAD_DIR . basename($f['file_name']));
            }
            db_exec('DELETE FROM tenancies WHERE id = ?', [$id]);
            flash('Kirayedar ka poora record delete ho gaya.', 'warning');
            redirect('property.php?id=' . $t['property_id']);
    }
    redirect('tenancy.php?id=' . $id);
}

$l = rent_ledger($t);
$payments = db_all('SELECT * FROM rent_payments WHERE tenancy_id = ? ORDER BY pay_date DESC, id DESC', [$id]);
$files = db_all('SELECT * FROM tenancy_files WHERE tenancy_id = ? ORDER BY uploaded_at DESC', [$id]);
$active = $t['status'] === 'active';
$agreementEnd = $t['agreement_months'] ? date('Y-m-d', strtotime($t['start_date'] . ' +' . (int)$t['agreement_months'] . ' month')) : null;
$monthsList = array_map(fn($m) => ['m' => $m['month'], 'due' => $m['due']], $l['months']);

// WhatsApp reminder
$wa = '';
if ($active && $t['phone'] && $l['balance'] > 0.5) {
    $num = preg_replace('/\D/', '', $t['phone']);
    if (str_starts_with($num, '0')) $num = '92' . substr($num, 1);
    $msg = "Assalam o Alaikum {$t['tenant_name']}, {$t['property_name']} ka kiraya " . money($l['balance']) . " baqi hai. Meharbani farma kar jald ada kar dein. Shukriya.";
    $wa = 'https://wa.me/' . $num . '?text=' . rawurlencode($msg);
}

$pageTitle = $t['tenant_name'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><a href="property.php?id=<?= $t['property_id'] ?>" class="small"><i class="bi bi-arrow-left"></i> <?= e($t['property_name']) ?></a>
    <h1><?= e($t['tenant_name']) ?> <?= $active ? '<span class="badge text-bg-success fs-6">Active</span>' : '<span class="badge text-bg-secondary fs-6">Chhor gaya</span>' ?></h1>
    <div class="small text-muted"><?= e($t['phone']) ?> <?= $t['cnic'] ? '· CNIC ' . e($t['cnic']) : '' ?> · Rent par: <?= fdate($t['start_date']) ?></div></div>
  <div class="d-flex gap-2 flex-wrap no-print">
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal" data-fill="#payForm"
      data-values='<?= e(json_encode(['pay_date' => date('Y-m-d'), 'amount' => $l['balance'] > 0 ? $l['balance'] : $l['current_rent'], 'method' => 'Cash'])) ?>'><i class="bi bi-cash"></i> Kiraya wusool</button>
    <?php if ($active): ?>
      <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#revModal"><i class="bi bi-arrow-up-circle"></i> Kiraya barhayein</button>
      <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#vacateModal"><i class="bi bi-box-arrow-right"></i> Makan chhora</button>
    <?php else: ?>
      <form method="post" data-confirm="Kirayedar dobara active karein? Advance adjust wali payment hat jayegi."><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Dobara active</button></form>
    <?php endif; ?>
    <?php if ($wa): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i> Yaad dehani</a><?php endif; ?>
    <a href="tenancy_form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
    <button class="btn btn-sm btn-outline-secondary" onclick="print()"><i class="bi bi-printer"></i></button>
  </div>
</div>

<?php if ($l['increment_due']): ?>
  <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-arrow-up-circle"></i> Increment due: <strong><?= fmonth($l['next_increment']) ?></strong> se kiraya
      <?= money($l['current_rent']) ?> → <strong><?= money($l['suggested_rent']) ?></strong>
      (<?= $t['increment_type'] === 'percent' ? (float)$t['increment_value'] . '%' : '+' . money($t['increment_value']) ?>)</span>
    <button class="btn btn-sm btn-info no-print" data-bs-toggle="modal" data-bs-target="#revModal">Apply karein</button>
  </div>
<?php endif; ?>
<?php if ($active && $agreementEnd && $agreementEnd <= date('Y-m-d', strtotime('+1 month'))): ?>
  <div class="alert alert-warning py-2"><i class="bi bi-file-earmark-text"></i> Agreement <?= $agreementEnd < date('Y-m-d') ? 'khatam ho chuka' : 'khatam hone wala' ?> hai: <?= fdate($agreementEnd) ?>. Naya stamp paper banwa kar upload karein.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md"><div class="card stat-card"><div class="card-body"><div class="label">Mahana kiraya</div><div class="value"><?= money($l['current_rent']) ?></div><div class="small text-muted">Har mah <?= (int)$t['due_day'] ?> tareekh tak</div></div></div></div>
  <div class="col-6 col-md"><div class="card stat-card"><div class="card-body"><div class="label">Kul banta hai</div><div class="value"><?= money($l['total_due']) ?></div></div></div></div>
  <div class="col-6 col-md"><div class="card stat-card"><div class="card-body"><div class="label">Kul wusool</div><div class="value text-in"><?= money($l['total_paid']) ?></div></div></div></div>
  <div class="col-6 col-md"><div class="card stat-card"><div class="card-body"><div class="label"><?= $l['balance'] >= 0 ? 'Baqi' : 'Pehle se jama' ?></div>
    <div class="value <?= $l['balance'] > 0.5 ? 'text-out' : 'text-in' ?>"><?= money(abs($l['balance'])) ?></div>
    <?php if ($l['balance'] > 0.5): ?><div class="small text-muted">~<?= $l['pending_months'] ?> maheenay ka gap</div><?php endif; ?></div></div></div>
  <div class="col-12 col-md"><div class="card stat-card"><div class="card-body"><div class="label">Advance / Security</div><div class="value"><?= money($t['advance_amount']) ?></div><div class="small text-muted"><?= fdate($t['advance_date']) ?></div></div></div></div>
</div>

<?php if (!$active): ?>
<div class="card mb-3 border-start border-4 border-secondary"><div class="card-body">
  <h6><i class="bi bi-door-open"></i> Makan chhorne ka hisab</h6>
  <div class="row small g-2">
    <div class="col-md-3">Chhora: <strong><?= fdate($t['vacate_date']) ?></strong></div>
    <div class="col-md-3">Aakhri maheena kiraya: <strong><?= fmonth($t['billing_end']) ?></strong></div>
    <div class="col-md-3">Kaat (damage waghera): <strong><?= money($t['deduction_amount']) ?></strong> <?= e($t['deduction_note']) ?></div>
    <div class="col-md-3">Advance wapas diya: <strong><?= money($t['refund_amount']) ?></strong> <?= $t['refund_date'] ? '(' . fdate($t['refund_date']) . ')' : '' ?></div>
    <?php if ($t['vacate_note']): ?><div class="col-12 text-muted"><?= nl2br(e($t['vacate_note'])) ?></div><?php endif; ?>
    <?php if ($l['balance'] > 0.5): ?><div class="col-12 text-out fw-semibold">Abhi bhi <?= money($l['balance']) ?> kiraya baqi hai.</div><?php endif; ?>
  </div>
</div></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Maheena wise hisab</div>
    <div class="table-responsive" style="max-height:520px"><table class="table table-sm mb-0">
      <thead class="table-light sticky-top"><tr><th>Maheena</th><th class="amt">Kiraya</th><th class="amt">Ada</th><th class="amt">Baqi</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($l['months']) as $m): ?>
        <tr class="<?= $m['status'] === 'unpaid' ? 'table-danger' : ($m['status'] === 'partial' ? 'table-warning' : '') ?>">
          <td class="month-cell"><?= fmonth($m['month']) ?></td><td class="amt"><?= money($m['due'], false) ?></td>
          <td class="amt"><?= money($m['paid'], false) ?></td><td class="amt"><?= $m['balance'] > 0 ? money($m['balance'], false) : '' ?></td>
          <td><?= rent_status_badge($m['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($l['opening_due'] > 0): ?>
        <tr class="<?= $l['opening_paid'] < $l['opening_due'] ? 'table-danger' : '' ?>"><td>Pichla baqaya</td><td class="amt"><?= money($l['opening_due'], false) ?></td>
          <td class="amt"><?= money($l['opening_paid'], false) ?></td><td class="amt"><?= $l['opening_due'] - $l['opening_paid'] > 0 ? money($l['opening_due'] - $l['opening_paid'], false) : '' ?></td>
          <td><?= rent_status_badge($l['opening_paid'] >= $l['opening_due'] ? 'paid' : ($l['opening_paid'] > 0 ? 'partial' : 'unpaid')) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
    <div class="card-footer small text-muted">Payment jab bhi aaye, pehle sab se purane baqaya maheenay mein adjust hoti hai.</div></div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Payments</div>
    <div class="table-responsive" style="max-height:320px"><table class="table table-sm mb-0">
      <?php foreach ($payments as $py): ?>
        <tr><td class="text-nowrap"><?= fdate($py['pay_date']) ?></td>
          <td class="small"><?= e($py['method']) ?><?php if ($py['note']): ?><div class="text-muted"><?= e($py['note']) ?></div><?php endif; ?></td>
          <td class="amt text-in"><?= money($py['amount']) ?></td>
          <td class="text-end text-nowrap no-print">
            <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#payModal" data-fill="#payForm"
              data-values='<?= e(json_encode(['pay_id' => $py['id'], 'pay_date' => $py['pay_date'], 'amount' => $py['amount'], 'method' => $py['method'], 'note' => $py['note']])) ?>'><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" data-confirm="Payment delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_pay"><input type="hidden" name="pay_id" value="<?= $py['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
          </td></tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td class="text-muted p-3">Abhi koi payment nahi.</td></tr><?php endif; ?>
    </table></div></div>

    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Kiraye ki history (increments)</div>
    <table class="table table-sm mb-0">
      <?php $prev = null; foreach ($l['revisions'] as $r): ?>
        <tr><td><?= fmonth($r['effective_month']) ?> se</td>
          <td class="amt"><?= money($r['rent_amount']) ?><?php if ($prev): ?> <span class="small text-muted">(+<?= number_format(($r['rent_amount'] - $prev) / $prev * 100, 1) ?>%)</span><?php endif; ?></td>
          <td class="small text-muted"><?= e($r['note']) ?></td>
          <td class="text-end no-print"><?php if (count($l['revisions']) > 1): ?><form method="post" data-confirm="Ye tabdeeli delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_revision"><input type="hidden" name="rev_id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-circle"></i></button></form><?php endif; ?></td></tr>
      <?php $prev = (float)$r['rent_amount']; endforeach; ?>
    </table>
    <div class="card-footer small text-muted">
      <?php if ((int)$t['increment_every'] > 0): ?>Har <?= (int)$t['increment_every'] ?> maheenay baad <?= $t['increment_type'] === 'percent' ? (float)$t['increment_value'] . '%' : money($t['increment_value']) ?> ·
        Agla increment: <?= fmonth($l['next_increment']) ?><?php else: ?>Koi automatic increment set nahi.<?php endif; ?>
    </div></div>

    <div class="card mb-3"><div class="card-header bg-white fw-semibold d-flex justify-content-between"><span>Stamp paper / Documents</span>
      <button class="btn btn-sm btn-outline-primary no-print" data-bs-toggle="modal" data-bs-target="#docModal"><i class="bi bi-upload"></i></button></div>
      <div class="card-body"><div class="row g-2">
      <?php foreach ($files as $f): ?>
        <div class="col-6">
          <a href="file.php?id=<?= $f['id'] ?>" target="_blank">
            <?php if (str_starts_with($f['mime'], 'image/')): ?><img src="file.php?id=<?= $f['id'] ?>" class="doc-thumb" alt="<?= e($f['title']) ?>">
            <?php else: ?><div class="doc-thumb d-flex align-items-center justify-content-center"><i class="bi bi-file-earmark-pdf fs-1 text-danger"></i></div><?php endif; ?>
          </a>
          <div class="d-flex justify-content-between small mt-1"><span class="text-truncate"><?= e($f['title']) ?></span>
            <form method="post" class="no-print" data-confirm="File delete karein?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_file"><input type="hidden" name="file_id" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$files): ?><div class="col-12 text-muted small">Stamp paper ki picture abhi upload nahi ki.</div><?php endif; ?>
      </div></div>
    </div>
    <?php if ($t['note']): ?><div class="card mb-3"><div class="card-body small"><?= nl2br(e($t['note'])) ?></div></div><?php endif; ?>
    <form method="post" class="no-print text-end" data-confirm="Is kirayedar ka POORA record (payments, documents) delete ho jayega. Pakka?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-link text-danger">Record delete karein</button></form>
  </div>
</div>

<!-- Payment -->
<div class="modal fade" id="payModal" tabindex="-1"><div class="modal-dialog"><form method="post" id="payForm" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="pay"><input type="hidden" name="pay_id">
  <div class="modal-header"><h5 class="modal-title">Kiraya wusool</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-6"><label class="form-label">Tareekh</label><input type="date" name="pay_date" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Raqam</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
    <div class="col-6"><label class="form-label">Tareeqa</label><input name="method" class="form-control" list="methods"><datalist id="methods"><option>Cash</option><option>Bank Transfer</option><option>JazzCash</option><option>Easypaisa</option><option>Cheque</option></datalist></div>
    <div class="col-6"><label class="form-label">Note</label><input name="note" class="form-control" placeholder="e.g. Jan + Feb"></div>
    <div class="col-12 small text-muted">Aadha kiraya bhi likh sakte hain; baqi agle maheenay k saath hisab mein rahega.</div>
  </div>
  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
</form></div></div>

<!-- Increment -->
<div class="modal fade" id="revModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="revise">
  <div class="modal-header"><h5 class="modal-title">Kiraya barhayein / badlein</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-6"><label class="form-label">Kis maheenay se</label><input type="month" name="effective_month" class="form-control" required value="<?= substr($l['next_increment'] && $l['increment_due'] ? $l['next_increment'] : date('Y-m-01'), 0, 7) ?>"></div>
    <div class="col-6"><label class="form-label">Naya kiraya</label><input type="number" step="0.01" min="1" name="rent_amount" class="form-control" required value="<?= e($l['suggested_rent']) ?>"></div>
    <div class="col-12"><label class="form-label">Note</label><input name="note" class="form-control" value="Salana increment"></div>
    <div class="col-12 small text-muted">Agar kirayedar ne increment late mana (e.g. 1-2 maheenay baad), to wohi maheena likhein jahan se naya kiraya lagu hua.</div>
  </div>
  <div class="modal-footer"><button class="btn btn-info">Save</button></div>
</form></div></div>

<!-- Documents -->
<div class="modal fade" id="docModal" tabindex="-1"><div class="modal-dialog"><form method="post" enctype="multipart/form-data" class="modal-content"><?= csrf_field() ?>
  <input type="hidden" name="action" value="upload">
  <div class="modal-header"><h5 class="modal-title">Document upload</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-12"><label class="form-label">Title</label><input name="doc_title" class="form-control" value="Stamp paper / Agreement"></div>
    <div class="col-12"><input type="file" name="docs[]" class="form-control" multiple required accept="image/*,application/pdf"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Upload</button></div>
</form></div></div>

<!-- Vacate -->
<?php if ($active): ?>
<div class="modal fade" id="vacateModal" tabindex="-1"><div class="modal-dialog modal-lg"><form method="post" class="modal-content" id="vacateForm"><?= csrf_field() ?>
  <input type="hidden" name="action" value="vacate">
  <div class="modal-header"><h5 class="modal-title">Makan chhorna / Final hisab</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-2">
    <div class="col-md-4"><label class="form-label">Chhorne ki tareekh</label><input type="date" name="vacate_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
    <div class="col-md-4"><label class="form-label">Kiraya kis maheenay tak banta hai</label><input type="month" name="billing_end" class="form-control" required value="<?= date('Y-m') ?>" min="<?= substr($t['billing_start'], 0, 7) ?>" max="<?= date('Y-m') ?>"></div>
    <div class="col-12"><hr class="my-1"></div>
    <div class="col-md-4"><label class="form-label">Baqi kiraya (us maheenay tak)</label><input class="form-control" id="vcDue" readonly></div>
    <div class="col-md-4"><label class="form-label">Advance se kaatna</label><input type="number" step="0.01" min="0" name="advance_adjust" class="form-control" id="vcAdj"></div>
    <div class="col-md-4"><label class="form-label">Advance jo liya tha</label><input class="form-control" value="<?= e(money($t['advance_amount'], false)) ?>" readonly></div>
    <div class="col-md-4"><label class="form-label">Nuqsan / bill kaat</label><input type="number" step="0.01" min="0" name="deduction_amount" class="form-control" id="vcDed" value="0"></div>
    <div class="col-md-8"><label class="form-label">Kaat ki wajah</label><input name="deduction_note" class="form-control" placeholder="e.g. Bijli ka bill, toota darwaza"></div>
    <div class="col-md-4"><label class="form-label">Advance wapas diya</label><input type="number" step="0.01" min="0" name="refund_amount" class="form-control" id="vcRefund"></div>
    <div class="col-md-4"><label class="form-label">Wapsi ki tareekh</label><input type="date" name="refund_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-12"><div class="alert alert-light border small mb-0" id="vcSummary"></div></div>
    <div class="col-12"><label class="form-label">Note</label><textarea name="vacate_note" class="form-control" rows="2"></textarea></div>
  </div>
  <div class="modal-footer"><button class="btn btn-danger">Record karein</button></div>
</form></div></div>
<script>
(function () {
  var months = <?= json_encode($monthsList) ?>, opening = <?= (float)$l['opening_due'] ?>, paid = <?= (float)$l['total_paid'] ?>, advance = <?= (float)$t['advance_amount'] ?>;
  var f = document.getElementById('vacateForm'), fmt = function (n) { return '<?= CURRENCY ?> ' + Math.round(n).toLocaleString(); };
  function calc(ev) {
    var end = f.billing_end.value + '-01', due = opening;
    months.forEach(function (m) { if (m.m <= end) due += +m.due; });
    var bal = Math.max(0, due - paid);
    f.querySelector('#vcDue').value = bal.toFixed(0);
    if (!ev || ev.target.name === 'billing_end') f.advance_adjust.value = Math.min(advance, bal).toFixed(0);
    var adj = +f.advance_adjust.value || 0, ded = +f.deduction_amount.value || 0;
    var left = advance - adj - ded;
    if (!ev || ev.target.name !== 'refund_amount') f.refund_amount.value = Math.max(0, left).toFixed(0);
    var still = bal - adj;
    var s = 'Advance ' + fmt(advance) + ' − kiraya ' + fmt(adj) + ' − kaat ' + fmt(ded) + ' = <strong>' + fmt(left) + '</strong> wapas karna hai.';
    if (left < 0) s += '<br><span class="text-danger">Kirayedar ne ' + fmt(-left) + ' mazeed dene hain (kaat advance se zyada).</span>';
    if (still > 0.5) s += '<br><span class="text-danger">Advance kaatne k baad bhi ' + fmt(still) + ' kiraya baqi rahega.</span>';
    document.getElementById('vcSummary').innerHTML = s;
  }
  f.addEventListener('input', calc);
  calc();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
