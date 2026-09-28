<?php
/*
 * Rent ka hisab (salary/cash se alag).
 * Har maheenay ka kiraya "banta" hai (rent_revisions k mutabiq), aur payments
 * jab bhi aayein, pehle purane baqaye mein adjust hoti hain (FIFO).
 * Is tarah agar kirayedar 1-1.5 maheenay late ho jaye to wo gap khud nazar aata hai.
 */

function add_months(string $monthStart, int $n): string
{
    return date('Y-m-01', strtotime("$monthStart +$n month"));
}

function tenancy_revisions(int $tenancyId): array
{
    return db_all('SELECT * FROM rent_revisions WHERE tenancy_id = ? ORDER BY effective_month', [$tenancyId]);
}

function rent_for_month(array $revisions, string $month): float
{
    $rent = 0.0;
    foreach ($revisions as $r) {
        if ($r['effective_month'] <= $month) $rent = (float)$r['rent_amount'];
        else break;
    }
    return $rent;
}

function suggested_increment(array $t, float $currentRent): float
{
    $v = (float)$t['increment_value'];
    $new = $t['increment_type'] === 'percent' ? $currentRent * (1 + $v / 100) : $currentRent + $v;
    return round($new);
}

/**
 * Poora ledger: months (due/paid/status), totals, increment info.
 */
function rent_ledger(array $t): array
{
    $revisions = tenancy_revisions((int)$t['id']);
    $thisMonth = date('Y-m-01');
    $last = $t['status'] === 'vacated' && $t['billing_end'] ? $t['billing_end'] : $thisMonth;

    $months = [];
    for ($m = $t['billing_start']; $m <= $last; $m = add_months($m, 1)) {
        $months[] = ['month' => $m, 'due' => rent_for_month($revisions, $m), 'paid' => 0.0];
        if (count($months) > 600) break; // hifazat
    }

    $totalPaid = (float)db_val('SELECT COALESCE(SUM(amount),0) FROM rent_payments WHERE tenancy_id = ?', [$t['id']]);

    // FIFO: pehle pichla baqaya, phir purane maheenay
    $pool = $totalPaid;
    $openingDue = (float)$t['opening_due'];
    $openingPaid = min($pool, $openingDue);
    $pool -= $openingPaid;
    foreach ($months as &$row) {
        $row['paid'] = min($pool, $row['due']);
        $pool -= $row['paid'];
        $row['balance'] = $row['due'] - $row['paid'];
        $row['status'] = $row['due'] <= 0 ? 'nil' : ($row['balance'] <= 0.004 ? 'paid' : ($row['paid'] > 0 ? 'partial' : 'unpaid'));
    }
    unset($row);

    $totalDue = $openingDue + array_sum(array_column($months, 'due'));
    $balance = $totalDue - $totalPaid;
    $pendingMonths = 0.0;
    foreach ($months as $row) {
        if ($row['due'] > 0) $pendingMonths += $row['balance'] / $row['due'];
    }

    $currentRent = rent_for_month($revisions, $thisMonth > $last ? $last : $thisMonth);
    $lastRev = $revisions ? end($revisions) : null;
    $nextIncrement = $lastRev && (int)$t['increment_every'] > 0 ? add_months($lastRev['effective_month'], (int)$t['increment_every']) : null;

    return [
        'months'          => $months,
        'revisions'       => $revisions,
        'opening_due'     => $openingDue,
        'opening_paid'    => $openingPaid,
        'total_due'       => $totalDue,
        'total_paid'      => $totalPaid,
        'balance'         => $balance,          // + baqaya, - advance me jama
        'pending_months'  => round($pendingMonths, 1),
        'current_rent'    => $currentRent,
        'next_increment'  => $nextIncrement,
        'increment_due'   => $t['status'] === 'active' && $nextIncrement && $nextIncrement <= $thisMonth,
        'suggested_rent'  => suggested_increment($t, $currentRent),
    ];
}

function active_tenancies(): array
{
    return db_all("SELECT t.*, p.name AS property_name FROM tenancies t JOIN properties p ON p.id = t.property_id
                   WHERE t.status = 'active' ORDER BY p.name");
}

function rent_status_badge(string $s): string
{
    return [
        'paid'    => '<span class="badge text-bg-success">Paid</span>',
        'partial' => '<span class="badge text-bg-warning">Partial</span>',
        'unpaid'  => '<span class="badge text-bg-danger">Baqi</span>',
        'nil'     => '<span class="badge text-bg-secondary">-</span>',
    ][$s] ?? '';
}

/**
 * Har payment kis maheenay (ya pichle baqaye) mein adjust hui — FIFO k mutabiq.
 * Return: [payment_id => [['label' => 'Jul 2026', 'amount' => 15000], ...]]
 */
function payment_allocation(array $t, array $ledger): array
{
    $buckets = [];
    if ($ledger['opening_due'] > 0) $buckets[] = ['label' => 'Pichla baqaya', 'left' => $ledger['opening_due']];
    foreach ($ledger['months'] as $m) {
        if ($m['due'] > 0) $buckets[] = ['label' => fmonth($m['month']), 'left' => $m['due']];
    }
    $out = [];
    $i = 0;
    foreach (db_all('SELECT id, amount FROM rent_payments WHERE tenancy_id = ? ORDER BY pay_date, id', [$t['id']]) as $p) {
        $amt = (float)$p['amount'];
        $out[$p['id']] = [];
        while ($amt > 0.004 && $i < count($buckets)) {
            $take = min($amt, $buckets[$i]['left']);
            $out[$p['id']][] = ['label' => $buckets[$i]['label'], 'amount' => $take];
            $buckets[$i]['left'] -= $take;
            $amt -= $take;
            if ($buckets[$i]['left'] <= 0.004) $i++;
        }
        if ($amt > 0.004) $out[$p['id']][] = ['label' => 'Advance (aglay maheenon k liye)', 'amount' => $amt];
    }
    return $out;
}
