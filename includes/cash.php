<?php
/*
 * Personal cash ka hisab.
 * Cash = Opening cash + Income - Expenses - Investment + Profit + Wapsi
 *        - Udhar diya + Udhar wapas mila + Udhar liya - Udhar wapas kiya +/- Adjustments
 * Rent module is mein shamil NAHI hai.
 */

function cashbook_sql(): string
{
    return "
      SELECT received_on AS d, 'income' AS src, id AS ref_id,
             CONCAT(type, ' (', DATE_FORMAT(income_month, '%b %Y'), ')') AS descr, amount AS amt, note, created_at
        FROM incomes
      UNION ALL
      SELECT e.exp_date, 'expense', e.id, CONCAT('Kharcha: ', COALESCE(c.name, 'Other')), -e.amount, e.note, e.created_at
        FROM expenses e LEFT JOIN expense_categories c ON c.id = e.category_id
      UNION ALL
      SELECT ie.entry_date, 'investment', ie.investment_id,
             CONCAT(CASE ie.type WHEN 'invest' THEN 'Investment/Installment: ' WHEN 'profit' THEN 'Profit: ' ELSE 'Wapsi: ' END, i.name),
             IF(ie.type = 'invest', -ie.amount, ie.amount), ie.note, ie.created_at
        FROM investment_entries ie JOIN investments i ON i.id = ie.investment_id
      UNION ALL
      SELECT le.entry_date, 'loan', le.person_id,
             CONCAT(CASE le.type WHEN 'gave' THEN 'Udhar diya: ' WHEN 'got_back' THEN 'Udhar wapas mila: '
                                 WHEN 'took' THEN 'Udhar liya: ' ELSE 'Udhar wapas kiya: ' END, p.name),
             IF(le.type IN ('gave','paid_back'), -le.amount, le.amount), le.note, le.created_at
        FROM loan_entries le JOIN loan_people p ON p.id = le.person_id
      UNION ALL
      SELECT adj_date, 'adjust', id, 'Cash adjustment', amount, note, created_at FROM cash_adjustments
    ";
}

function cash_balance(?string $upto = null): float
{
    $opening = (float)setting('opening_cash', 0);
    $sql = 'SELECT COALESCE(SUM(amt), 0) FROM (' . cashbook_sql() . ') t';
    $params = [];
    if ($upto) {
        $sql .= ' WHERE d <= ?';
        $params[] = $upto;
    }
    return $opening + (float)db_val($sql, $params);
}

function month_totals(string $monthStart): array
{
    $end = date('Y-m-t', strtotime($monthStart));
    return [
        'income'  => (float)db_val('SELECT COALESCE(SUM(amount),0) FROM incomes WHERE received_on BETWEEN ? AND ?', [$monthStart, $end]),
        'expense' => (float)db_val('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE exp_date BETWEEN ? AND ?', [$monthStart, $end]),
    ];
}

function investment_totals(?int $id = null): array
{
    $sql = "SELECT
              COALESCE(SUM(IF(type='invest', amount, 0)),0) AS invested,
              COALESCE(SUM(IF(type='profit', amount, 0)),0) AS profit,
              COALESCE(SUM(IF(type='withdraw', amount, 0)),0) AS withdrawn,
              SUM(type='invest') AS installments
            FROM investment_entries ie JOIN investments i ON i.id = ie.investment_id";
    $params = [];
    if ($id) {
        $sql .= ' WHERE ie.investment_id = ?';
        $params[] = $id;
    } else {
        $sql .= " WHERE i.status = 'active'";
    }
    $r = db_one($sql, $params);
    $r = array_map('floatval', $r);
    $r['outstanding'] = $r['invested'] - $r['withdrawn'];  // abhi kitna paisa laga hua hai
    $r['roi'] = $r['invested'] > 0 ? $r['profit'] / $r['invested'] * 100 : 0;
    return $r;
}

/** + matlab wo mujhe dega, - matlab mujhe dena hai */
function loan_balance_sql(): string
{
    return "SUM(CASE type WHEN 'gave' THEN amount WHEN 'got_back' THEN -amount WHEN 'took' THEN -amount ELSE amount END)";
}

function loan_totals(): array
{
    $rows = db_all('SELECT person_id, ' . loan_balance_sql() . ' AS bal FROM loan_entries GROUP BY person_id');
    $recv = 0; $pay = 0;
    foreach ($rows as $r) {
        if ($r['bal'] > 0) $recv += $r['bal']; else $pay += -$r['bal'];
    }
    return ['receivable' => $recv, 'payable' => $pay];
}
