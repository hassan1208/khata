<?php
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? APP_NAME;
$self = basename($_SERVER['SCRIPT_NAME']);
$navActive = function (array $pages) use ($self) {
    return in_array($self, $pages, true) ? ' active' : '';
};
$bare = $bare ?? false; // login/otp/setup pages: navbar nahi
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
  <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
</head>
<body class="<?= $bare ? 'bare' : '' ?>">
<?php if (!$bare): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="index.php"><i class="bi bi-journal-bookmark-fill me-1"></i><?= e(APP_NAME) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link<?= $navActive(['index.php']) ?>" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle<?= $navActive(['income.php','expenses.php','cashbook.php']) ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-wallet2"></i> Cash</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="income.php"><i class="bi bi-cash-coin"></i> Salary / Income</a></li>
            <li><a class="dropdown-item" href="expenses.php"><i class="bi bi-cart"></i> Expenses</a></li>
            <li><a class="dropdown-item" href="cashbook.php"><i class="bi bi-book"></i> Cash Book</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['investments.php','investment.php']) ?>" href="investments.php"><i class="bi bi-graph-up-arrow"></i> Investments</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['loans.php','loan.php']) ?>" href="loans.php"><i class="bi bi-people"></i> Udhar</a></li>
        <li class="nav-item"><a class="nav-link<?= $navActive(['properties.php','property.php','tenancy.php','tenancy_form.php']) ?>" href="properties.php"><i class="bi bi-house-door"></i> Rent</a></li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link<?= $navActive(['settings.php']) ?>" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
        <li class="nav-item">
          <form method="post" action="logout.php" class="d-inline"><?= csrf_field() ?>
            <button class="btn nav-link"><i class="bi bi-box-arrow-right"></i> Logout</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="<?= $bare ? '' : 'container-lg py-4' ?>">
<?php foreach ($_SESSION['flash'] ?? [] as [$type, $msg]): ?>
  <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
    <?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; unset($_SESSION['flash']); ?>
