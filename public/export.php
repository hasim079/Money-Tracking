<?php
// Start user session, check timeout, and initialize database connection
session_start();
require_once "../config/timeout.php";
require_once "../config/database.php";

// Redirect unauthenticated users to the login/auth page
if (!isset($_SESSION["user_id"])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Fetch all distinct categories used by the current user to populate the filter dropdown
$stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = ?");
$stmt->execute([$user_id]);
$categoriesList = $stmt->fetchAll(PDO::FETCH_COLUMN);

$filter_cat = $_GET['category'] ?? '';

// Retrieve transactions filtered by category if a category is selected, otherwise retrieve all
if ($filter_cat) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? AND category = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id, $filter_cat]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
}
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's preferred currency setting, falling back to USD
$stmt = $pdo->prepare("SELECT preferred_currency FROM users WHERE id=?");
$stmt->execute([$user_id]);
$userCurrency = $stmt->fetchColumn() ?: 'USD';
$viewCurrency = $_GET['view_currency'] ?? $userCurrency;

// Function to fetch or cache live exchange rates
function getRates() {
    $cacheFile = "rates.json";
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 3600) {
        $data = json_decode(file_get_contents($cacheFile), true);
        return $data["rates"] ?? [];
    }
    $response = @file_get_contents("https://api.exchangerate-api.com/v4/latest/USD");
    if ($response) {
        @file_put_contents($cacheFile, $response);
        $data = json_decode($response, true);
        return $data["rates"] ?? [];
    }
    return [];
}
$rates = getRates();

// Calculate total income and expense from the fetched transactions
$total_income = 0;
$total_expense = 0;
foreach ($transactions as $t) {
    if ($t["type"] == "income") $total_income += $t["amount"];
    else $total_expense += $t["amount"];
}
$balance = $total_income - $total_expense;

// Retrieve session language and configure direction (RTL/LTR)
$current_lang = $_SESSION['lang'] ?? 'en';
$dir = ($current_lang == "ar") ? "rtl" : "ltr";
$lang = require_once "../lang/" . $current_lang . ".php";
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $lang['title_export'] ?? 'Financial Report' ?></title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #050b1f;
            --text-color: #ffffff;
            --card-bg: rgba(15, 28, 63, 0.65);
            --border-color: rgba(255, 255, 255, 0.1);
            --income-color: #00d285;
            --expense-color: #ff5e5e;
            --muted-text: #94a3b8;
        }
        body { font-family: 'Arial', sans-serif; padding: 40px; color: var(--text-color); background: var(--bg-color); margin: 0; }
        .container { max-width: 800px; margin: 0 auto; background: var(--card-bg); padding: 40px; border-radius: 16px; border: 1px solid var(--border-color); backdrop-filter: blur(10px); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 28px; color: var(--text-color); font-weight: 800; letter-spacing: -0.5px; }
        .header p { margin: 5px 0 0; color: var(--muted-text); font-size: 14px; }
        
        .net-balance-banner { background: rgba(0,0,0,0.3); color: #fff; padding: 30px; border-radius: 12px; text-align: center; margin-bottom: 30px; border: 1px solid var(--border-color); }
        .net-balance-banner p { margin: 0 0 10px; font-size: 16px; color: var(--muted-text); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .net-balance-banner h2 { margin: 0; font-size: 48px; font-weight: 800; letter-spacing: -1px; }
        
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        .stat-card { background: rgba(0,0,0,0.2); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); }
        .stat-card p { margin: 0 0 5px; font-size: 13px; color: var(--muted-text); text-transform: uppercase; font-weight: 600; }
        .stat-card h3 { margin: 0; font-size: 24px; font-weight: 800; }
        .text-income { color: var(--income-color); }
        .text-expense { color: var(--expense-color); }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        th { font-weight: 600; color: var(--muted-text); text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        tr:last-child td { border-bottom: none; }
        
        .no-print-bar { text-align: right; margin-bottom: 20px; }
        .btn { padding: 10px 20px; cursor:pointer; font-weight: 600; border-radius: 8px; border:none; transition: 0.2s; font-family: 'Inter', sans-serif; }
        .btn-print { background: #2563eb; color: #fff; margin-right: 10px; }
        .btn-print:hover { background: #1d4ed8; }
        .btn-close { background: #e2e8f0; color: #334155; }
        
        @media print {
            body { padding: 0; background: #fff !important; color: #000 !important; }
            .container { box-shadow: none; border: none; padding: 0; max-width: 100%; background: #fff !important; color: #000 !important; }
            .no-print-bar { display: none !important; }
            .net-balance-banner { border: 2px solid #000; background: transparent; color: #000; box-shadow: none; }
            .net-balance-banner p { color: #555; }
            .stat-card { border: 1px solid #000; background: transparent; }
            th, td { border-color: #ddd; color: #000; }
            .header h1, h3 { color: #000 !important; }
            .text-income { color: #000 !important; }
            .text-expense { color: #000 !important; }
        }
    </style>
</head>
<body onload="<?= isset($_GET['print']) ? 'javascript:window.print()' : '' ?>">
    <div class="container">
        <div class="no-print-bar" style="display:flex; justify-content:space-between; align-items:center;">
            <form method="GET" style="display:flex; gap:10px;">
                <select name="category" onchange="this.form.submit()" style="padding:10px; border-radius:8px; border:1px solid #ccc; font-family:'Arial', sans-serif; background: rgba(255,255,255,0.1); color: #fff;">
                    <option value="" style="color:#000;"><?= $lang['all_categories'] ?? '-- All Categories --' ?></option>
                    <?php foreach ($categoriesList as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" style="color:#000;" <?= $filter_cat === $c ? "selected" : "" ?>><?= $lang[strtolower($c)] ?? htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="view_currency" onchange="this.form.submit()" style="padding:10px; border-radius:8px; border:1px solid #ccc; font-family:'Arial', sans-serif; background: rgba(255,255,255,0.1); color: #fff;">
                    <option value="USD" style="color:#000;" <?= $viewCurrency == "USD" ? "selected" : "" ?>>USD</option>
                    <option value="TRY" style="color:#000;" <?= $viewCurrency == "TRY" ? "selected" : "" ?>>TRY</option>
                    <option value="EUR" style="color:#000;" <?= $viewCurrency == "EUR" ? "selected" : "" ?>>EUR</option>
                </select>
            </form>
            <div>
                <button onclick="window.print()" class="btn btn-print">🖨️ <?= $lang['print_pdf'] ?? 'Print / Save PDF' ?></button>
                <button onclick="window.close()" class="btn btn-close"><?= $lang['close'] ?? 'Close' ?></button>
            </div>
        </div>

        <div class="header">
            <div>
                <h1><?= $lang['financial_report'] ?? 'Financial Report' ?></h1>
                <p><?= $lang['generated_for'] ?? 'Generated for:' ?> <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></strong></p>
            </div>
            <div style="text-align: right;">
                <p><?= $lang['date'] ?? 'Date:' ?> <?= date('d M Y, H:i') ?></p>
                <p><?= $lang['id'] ?? 'ID:' ?> #<?= strtoupper(substr(md5($user_id . time()), 0, 8)) ?></p>
            </div>
        </div>

        <?php
            $displayBalance = $balance;
            $displayIncome = $total_income;
            $displayExpense = $total_expense;
            
            if (isset($rates[$viewCurrency])) {
                $displayBalance *= $rates[$viewCurrency];
                $displayIncome *= $rates[$viewCurrency];
                $displayExpense *= $rates[$viewCurrency];
            }
        ?>
        <div class="net-balance-banner">
            <p><?= $lang['total_balance'] ?? 'Net Balance' ?></p>
            <h2><?= number_format($displayBalance, 2) ?> <?= $viewCurrency ?></h2>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <p><?= $lang['income'] ?? 'Total Income' ?></p>
                <h3 class="text-income">+ <?= number_format($displayIncome, 2) ?> <?= $viewCurrency ?></h3>
            </div>
            <div class="stat-card">
                <p><?= $lang['expense'] ?? 'Total Expense' ?></p>
                <h3 class="text-expense">- <?= number_format($displayExpense, 2) ?> <?= $viewCurrency ?></h3>
            </div>
        </div>

        <h3 style="margin-bottom: 10px; color: var(--text-color);"><?= $lang['recent_transactions'] ?? 'Transaction History' ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?= $lang['date'] ?? 'Date' ?></th>
                    <th><?= $lang['category'] ?? 'Category' ?></th>
                    <th><?= $lang['description'] ?? 'Description' ?></th>
                    <th style="text-align: right;"><?= $lang['amount'] ?? 'Amount' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($transactions)): ?>
                <tr><td colspan="4" style="text-align:center; color:#94a3b8;"><?= $lang['no_transactions'] ?? 'No transactions found.' ?></td></tr>
                <?php endif; ?>
                <?php foreach($transactions as $t): ?>
                <?php 
                    $t_amount = $t['amount'];
                    if (isset($rates[$viewCurrency])) {
                        $t_amount *= $rates[$viewCurrency];
                    }
                ?>
                <tr>
                    <td style="color:var(--muted-text);"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                    <td style="font-weight:600;"><?= $lang[strtolower($t['category'])] ?? htmlspecialchars($t['category']) ?></td>
                    <td style="color:var(--muted-text);"><?= htmlspecialchars($t['description']) ?></td>
                    <td style="text-align: right; font-weight: 600;" class="<?= $t['type'] === 'income' ? 'text-income' : 'text-expense' ?>">
                        <?= $t['type'] === 'income' ? '+' : '-' ?><?= number_format($t_amount, 2) ?> <?= $viewCurrency ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
