<?php
// Start user session to retain authentication and preferences
session_start();
// Check for user inactivity timeout configuration
require_once "../config/timeout.php";

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

$current_lang = $_SESSION['lang'];
// Load language translation file into an array
$lang = require "../lang/" . $current_lang . ".php";

// Set direction (Right-To-Left for Arabic, Left-To-Right for others)
$dir = ($current_lang == "ar") ? "rtl" : "ltr";
require_once "../config/database.php";
require_once "../app/models/Transaction.php";

// Redirect to login page if user is not authenticated
if (!isset($_SESSION["user_id"])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$transactionModel = new Transaction($pdo);

$stmt = $pdo->prepare("SELECT preferred_currency FROM users WHERE id=?");
$stmt->execute([$_SESSION["user_id"]]);
$userCurrency = $stmt->fetchColumn();

// Function to fetch or cache live exchange rates relative to USD
function getRates()
{
    $cacheFile = "rates.json";

    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < 3600) {
        $data = json_decode(file_get_contents($cacheFile), true);
        return $data["rates"] ?? [];
    }

    $response = file_get_contents("https://api.exchangerate-api.com/v4/latest/USD");

    if ($response) {
        file_put_contents($cacheFile, $response);
        $data = json_decode($response, true);
        return $data["rates"] ?? [];
    }

    return [];
}

try {
    $pdo->exec("ALTER TABLE transactions ADD COLUMN account_type VARCHAR(50) DEFAULT 'Main'");
} catch (PDOException $e) { }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_wallets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, name VARCHAR(100))");
} catch (PDOException $e) { }

$mainWalletName = $lang['wallet_main'] ?? 'Main';
$stmt = $pdo->prepare("UPDATE transactions SET account_type = ? WHERE account_type = 'Main' OR account_type IS NULL OR trim(account_type) = ''");
$stmt->execute([$mainWalletName]);

$stmt = $pdo->prepare("SELECT name FROM user_wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$userWallets = $stmt->fetchAll(PDO::FETCH_COLUMN);

$defaultWallets = [
    $mainWalletName,
    $lang['wallet_credit'] ?? 'Credit Card',
    $lang['wallet_business'] ?? 'Business'
];
$allWallets = array_values(array_unique(array_merge($defaultWallets, $userWallets)));

$walletIndex = isset($_GET['wallet']) ? (int)$_GET['wallet'] : 0;
$activeWallet = $allWallets[$walletIndex] ?? $allWallets[0];

// Handle Adding a New Transaction
// Processes POST requests when a user submits a new transaction form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["type"])) {
    $type = strtolower($_POST["type"]);
    $amount = floatval($_POST["amount"]);
    $currency = $_POST["currency"];

    $rates = getRates();

    if (isset($rates[$currency])) {
        $amount_usd = $amount / $rates[$currency];
    } else {
        $amount_usd = $amount;
    }
    $description = $_POST["description"];
    // Retrieve the category from form input
    $category = $_POST["category"]; 
    
    // Determine the selected account index and map it to an account type
    $acc_idx = isset($_POST["account_type"]) ? (int)$_POST["account_type"] : 0;
    $account_type = $allWallets[$acc_idx] ?? $activeWallet;

    if (!empty($type) && !empty($amount)) {
        $transactionModel->add($user_id, $type, $amount_usd, $description, $category, $account_type);
        header("Location: dashboard.php");
        exit();
    }
}

// Handle Deleting a specific transaction
if (isset($_GET["delete"]) && $_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_GET["delete"];
    $transactionModel->delete($id, $user_id);
    header("Location: dashboard.php");
    exit();
}
// Handle Goal Deletion
if (isset($_GET["delete_goal"]) && $_SERVER["REQUEST_METHOD"] === "POST") {

    $stmt = $pdo->prepare("DELETE FROM goals WHERE user_id=?");
    $stmt->execute([$user_id]);

    header("Location: dashboard.php");
    exit();
}


$transactions = $transactionModel->getByUser($user_id);


// Initialize totals for computing the user's balance based on the active wallet
$total_income = 0;
$total_expense = 0;

$filteredTransactions = [];

// Define known non-main wallets across all supported languages (EN, TR, AR)
// This logic correctly identifies main wallet transactions vs specific wallet transactions
$knownNonMainWallets = array_merge($userWallets, [
    $defaultWallets[1], $defaultWallets[2],
    'Credit Card', 'Business', 
    'Kredi Kartı', 'İş/Ticari', 
    'بطاقة الائتمان', 'أعمال'
]);

foreach ($transactions as $t) {
    $t_wallet = trim((string)$t["account_type"]);
    
    // If it's not a known custom wallet, and not a secondary default wallet, it's the main wallet
    $isRecordMain = !in_array($t_wallet, $knownNonMainWallets);
    
    $isMatch = false;
    if ($walletIndex === 0 && $isRecordMain) {
        $isMatch = true;
    } else if ($walletIndex === 1 && in_array($t_wallet, ['Credit Card', 'Kredi Kartı', 'بطاقة الائتمان', $activeWallet])) {
        $isMatch = true;
    } else if ($walletIndex === 2 && in_array($t_wallet, ['Business', 'İş/Ticari', 'أعمال', $activeWallet])) {
        $isMatch = true;
    } else if ($t_wallet === $activeWallet) {
        $isMatch = true;
    }
    
    if ($isMatch) {
        $filteredTransactions[] = $t;
        if ($t["type"] == "income") {
            $total_income += $t["amount"];
        } else {
            $total_expense += $t["amount"];
        }
    }
}

$balance = $total_income - $total_expense;
$viewCurrency = $_GET['view_currency'] ?? $userCurrency;
$rates = getRates();

// Manual grouping from the filtered transactions for chart plotting
$expensesByCategory = [];
$incomeByCategory = [];
$categoriesSummary = [];

foreach ($filteredTransactions as $t) {
    if ($t['type'] == 'expense') {
        $expensesByCategory[$t['category']] = ($expensesByCategory[$t['category']] ?? 0) + $t['amount'];
    } else {
        $incomeByCategory[$t['category']] = ($incomeByCategory[$t['category']] ?? 0) + $t['amount'];
    }
}

$formattedExpenses = [];
foreach ($expensesByCategory as $catName => $total) {
    if (isset($rates[$viewCurrency])) {
        $total = $total * $rates[$viewCurrency];
    }
    $formattedExpenses[] = ['category' => $catName, 'total' => $total];
}
$expensesByCategory = $formattedExpenses;

// Fetch user's active saving goals
$stmt = $pdo->prepare("SELECT target_amount, title, deadline FROM goals WHERE user_id=?");
$stmt->execute([$user_id]);
$goalData = $stmt->fetch(PDO::FETCH_ASSOC);

$goal = $goalData['target_amount'] ?? 0;
$goalTitle = $goalData['title'] ?? '';
$goalDeadline = $goalData['deadline'] ?? '';

$goalProgress = 0;
$goalConverted = 0;

if ($goal) {

    if (isset($rates[$viewCurrency])) {
        $goalConverted = $goal * $rates[$viewCurrency];
    } else {
        $goalConverted = $goal;
    }

    if ($goal > 0) {
        $goalProgress = min(($balance / $goal) * 100, 100);
    }
}

if (isset($rates[$viewCurrency])) {
    $balanceConverted = $balance * $rates[$viewCurrency];
} else {
    $balanceConverted = $balance;
}
if (isset($rates[$viewCurrency])) {
    $incomeConverted = $total_income * $rates[$viewCurrency];
    $expenseConverted = $total_expense * $rates[$viewCurrency];
} else {
    $incomeConverted = $total_income;
    $expenseConverted = $total_expense;
}
$formattedIncome = [];
foreach ($incomeByCategory as $catName => $total) {
    if (isset($rates[$viewCurrency])) {
        $total = $total * $rates[$viewCurrency];
    }
    $formattedIncome[] = ['category' => $catName, 'total' => $total];
}
$incomeByCategory = $formattedIncome;

$categoriesMap = [];
foreach ($filteredTransactions as $t) {
    if (!isset($categoriesMap[$t['category']])) {
        $categoriesMap[$t['category']] = ['category' => $t['category'], 'total_income' => 0, 'total_expense' => 0];
    }
    if ($t['type'] == 'income') {
        $categoriesMap[$t['category']]['total_income'] += $t['amount'];
    } else {
        $categoriesMap[$t['category']]['total_expense'] += $t['amount'];
    }
}
$categoriesSummary = array_values($categoriesMap);

foreach ($categoriesSummary as &$cat) {

    $income = $cat["total_income"];
    $expense = $cat["total_expense"];

    if (isset($rates[$viewCurrency])) {
        $income *= $rates[$viewCurrency];
        $expense *= $rates[$viewCurrency];
    }

    $cat["net"] = $income - $expense;
}
unset($cat);

// Handle saving / updating a financial goal via POST request
if (isset($_POST["goal_amount"])) {

    $goalAmount = floatval($_POST["goal_amount"]);
    $goalCurrency = $_POST["goal_currency"];

    $goalTitle = $_POST["goal_title"] ?? null;
    $goalDeadline = !empty($_POST["goal_deadline"]) ? $_POST["goal_deadline"] : null;

    if (isset($rates[$goalCurrency])) {
        $goalAmount = $goalAmount / $rates[$goalCurrency];
    }

    $stmt = $pdo->prepare("
        INSERT INTO goals (user_id, target_amount, title, deadline)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE target_amount=VALUES(target_amount), title=VALUES(title), deadline=VALUES(deadline)
    ");

    $stmt->execute([$user_id, $goalAmount, $goalTitle, $goalDeadline]);

    header("Location: dashboard.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"> 
<title><?= $lang['title_dashboard']; ?></title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <div class="app-container">

        <div class="welcome">
            <h2><?= $lang['welcome']; ?>, <?= $_SESSION["user_name"]; ?> 👋</h2><BR>
        </div>

        <div class="top-bar">

            <div class="logo">
                <?= $lang['logo']; ?>
            </div>
            <form method="GET" style="display:flex; gap:10px;">
                <select name="wallet" onchange="this.form.submit()">
                    <?php foreach ($allWallets as $idx => $w_opt): ?>
                        <option value="<?= $idx ?>" <?= $walletIndex === $idx ? "selected" : "" ?>><?= htmlspecialchars($w_opt) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="view_currency" onchange="this.form.submit()">
                    <option value="USD" <?= $viewCurrency == "USD" ? "selected" : "" ?>>USD</option>
                    <option value="TRY" <?= $viewCurrency == "TRY" ? "selected" : "" ?>>TRY</option>
                    <option value="EUR" <?= $viewCurrency == "EUR" ? "selected" : "" ?>>EUR</option>
                </select>
            </form>
            <a href="export.php" target="_blank" class="settings-btn" style="text-decoration:none; margin-right: 15px; display:flex; align-items:center; justify-content:center;">
                📄
            </a>
            <div class="settings-wrapper">
                <button class="settings-btn" onclick="toggleMenu(event)">
                    ⚙️
                </button>

                <div class="settings-menu" id="settingsMenu">
                    <a href="?lang=en">🇺🇸 English</a>
                    <a href="?lang=tr">🇹🇷 Türkçe</a>
                    <a href="?lang=ar">🇸🇦 العربية</a>

                    <hr>

                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="admin.php">👑 Admin Panel</a>
                    <?php endif; ?>

                    <a href="profile.php">
                        <?= $lang['profile'] ?? 'Profile'; ?>
                    </a>

                    <a href="logout.php" class="logout-link">
                        <?= $lang['logout']; ?>
                    </a>
                </div>
            </div>


        </div>

        <div id="home" class="section active-section">

            <!-- Balance -->
            <div class="big-card">
                <p><?= $lang['total_balance']; ?></p>
                <h1><?= number_format($balanceConverted, 2); ?> <?= $viewCurrency; ?></h1>
            </div>

            <!-- Income & Expense Cards -->
            <div class="row">
                <div class="small-card income">
                    <p><?= $lang['income']; ?></p>
                    <h3><?= number_format($incomeConverted, 2); ?> <?= $viewCurrency; ?></h3>
                </div>

                <div class="small-card expense">
                    <p><?= $lang['expense']; ?></p>
                    <h3><?= number_format($expenseConverted, 2); ?> <?= $viewCurrency; ?></h3>
                </div>
            </div>
            <!-- Recent Transactions -->
            <div class="wide-card">
                <h3><?= $lang['recent_transactions']; ?></h3>

                <?php foreach ($filteredTransactions as $t): ?>
                    <div class="transaction">
                        <div class="left">
                            <span class="category-box">
                                <?= htmlspecialchars($t["category"]); ?>
                            </span>
                            <small><?= htmlspecialchars($t["description"]); ?></small>
                        </div>

                        <div class="right">
                            <?php
                            $displayAmount = $t["amount"];
                            if (isset($rates[$viewCurrency])) {
                                $displayAmount = $t["amount"] * $rates[$viewCurrency];
                            }
                            ?>
                            <span class="<?= $t["type"]; ?>">
                                <?= number_format($displayAmount, 2); ?> <?= $viewCurrency; ?>
                            </span>

                            <a href="edit.php?id=<?= $t["id"]; ?>" class="edit-btn">✏</a>
                            <form method="POST" action="?delete=<?= $t["id"]; ?>" style="display:inline;">
                                <button type="submit" class="delete-btn" style="background:none; border:none; padding:10px; cursor:pointer;">✖</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>

        <div id="add" class="section">
            <div class="wide-card">
                <h3><?= $lang['add_transaction']; ?></h3>

                <form method="POST">
                    <select name="type">
                        <option value="income"><?= $lang['income']; ?></option>
                        <option value="expense"><?= $lang['expense']; ?></option>
                    </select>
                    <select name="account_type" required>
                        <?php foreach ($allWallets as $idx => $w_opt): ?>
                            <option value="<?= $idx ?>" <?= $walletIndex === $idx ? "selected" : "" ?>><?= htmlspecialchars($w_opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="currency">
                        <option value="USD">USD ($)</option>
                        <option value="TRY">TRY (₺)</option>
                        <option value="EUR">EUR (€)</option>
                    </select>
                    <input type="number" step="0.01" name="amount" placeholder="<?= $lang['amount']; ?>">
                    <input type="text" name="description" placeholder="<?= $lang['description']; ?>">
                    <input list="category-options" name="category" placeholder="<?= $lang['category'] ?? 'Category' ?>" required autocomplete="off">
                    <datalist id="category-options">
                        <?php foreach ($categoriesSummary as $cat): ?>
                            <option value="<?= htmlspecialchars($cat["category"]) ?>"></option>
                        <?php endforeach; ?>
                        <option value="<?= $lang['salary'] ?? 'Salary' ?>"></option>
                        <option value="<?= $lang['food'] ?? 'Food' ?>"></option>
                        <option value="<?= $lang['transport'] ?? 'Transport' ?>"></option>
                        <option value="<?= $lang['utilities'] ?? 'Utilities' ?>"></option>
                        <option value="<?= $lang['entertainment'] ?? 'Entertainment' ?>"></option>
                        <option value="<?= $lang['other'] ?? 'Other' ?>"></option>
                    </datalist>

                    <button type="submit"><?= $lang['add_transaction']; ?></button>
                </form>
            </div>
        </div>
        <div id="goal" class="section">

            <div class="wide-card">

                <h3><?= $lang['saving_goal'] ?? 'Saving Goal' ?></h3>

                <form method="POST">

                    <input type="text" name="goal_title" value="<?= htmlspecialchars($goalTitle ?? '') ?>" placeholder="<?= $lang['goal_title_placeholder'] ?? 'Goal Title (e.g. Vacation)' ?>" required>

                    <input type="number"
                        step="0.01"
                        name="goal_amount"
                        value="<?= $goalConverted ? number_format($goalConverted, 2, '.', '') : '' ?>"
                        placeholder="<?= $lang['enter_goal_amount'] ?? 'Enter goal amount' ?>" required>

                    <select name="goal_currency">
                        <option value="USD">USD ($)</option>
                        <option value="TRY">TRY (₺)</option>
                        <option value="EUR">EUR (€)</option>
                    </select>
                    
                    <label style="display:block; text-align:left; color:#ccc; margin-top:5px; font-size:12px;"><?= $lang['deadline_optional'] ?? 'Deadline (Optional)' ?></label>
                    <input type="date" name="goal_deadline" value="<?= htmlspecialchars($goalDeadline ?? '') ?>">

                    <button type="submit">
                        <?= $goal ? ($lang['update_goal'] ?? 'Update Goal') : ($lang['save_goal'] ?? 'Save Goal') ?>
                    </button>

                </form>

                <?php if ($goal): ?>

                    <div class="goal-progress">

                        <h4 style="margin-top:20px; color:#fff;"><?= htmlspecialchars($goalTitle ?: ($lang['my_goal'] ?? 'My Goal')) ?></h4>
                        
                        <?php if ($goalDeadline): ?>
                            <small style="color: #ff9f43; display:block; margin-bottom: 10px;">
                                📅 <?= date('M d, Y', strtotime($goalDeadline)) ?> 
                                <?php
                                    $daysLeft = (strtotime($goalDeadline) - time()) / (60 * 60 * 24);
                                    if ($daysLeft > 0) {
                                        echo "(" . floor($daysLeft) . " " . ($lang['days_left'] ?? 'days left') . ")";
                                    } else {
                                        echo "(" . ($lang['overdue'] ?? 'Overdue') . ")";
                                    }
                                ?>
                            </small>
                        <?php endif; ?>

                        <p>
                            <?= number_format($balanceConverted, 2) ?> /
                            <?= number_format($goalConverted, 2) ?> <?= $viewCurrency ?>
                        </p>

                        <div class="progress-bar">
                            <div class="progress-fill" style="width:<?= $goalProgress ?>%"></div>
                        </div>

                        <p class="progress-text"><?= round($goalProgress) ?>%</p>

                        <form method="POST" action="?delete_goal=1" style="display:inline;">
                            <button type="submit" class="delete-goal" style="background:none; border:none; padding:0; cursor:pointer; font-weight:bold; margin-top:10px;">
                                <?= $lang['delete_goal'] ?? 'Delete Goal' ?>
                            </button>
                        </form>

                    </div>

                <?php endif; ?>

            </div>

        </div>

        <div id="stats" class="section">
            <div class="big-card">
                <p><?= $lang['total_balance']; ?></p>
                <h1><?= number_format($balanceConverted, 2); ?> <?= $viewCurrency; ?></h1>
            </div>
            <div class="dashboard">
                <!-- Income by Category -->
                <div class="category-grid">
                    <h4><?= $lang['income_by_category'] ?? 'Income by Category' ?></h4>

                    <?php if (!empty($incomeByCategory)): ?>
                        <div style="max-width: 300px; margin: 0 auto 20px;">
                            <canvas id="incomeChart"></canvas>
                        </div>
                        <?php foreach ($incomeByCategory as $cat): ?>
                            <div class="category-row">
                                <span><?= $lang[strtolower($cat["category"])] ?? htmlspecialchars($cat["category"]) ?></span>
                                <span style="color: #c0ff9d;"><?= number_format($cat["total"], 2); ?> <?= $viewCurrency ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?= $lang['no_income_categories'] ?? 'No income categories yet' ?></p>
                    <?php endif; ?>
                </div>


                <!-- Expenses by Category -->
                <div class="category-grid">
                    <h4><?= $lang['expenses_by_category'] ?? 'Expenses by Category' ?></h4>

                    <?php if (!empty($expensesByCategory)): ?>
                        <div style="max-width: 300px; margin: 0 auto 20px;">
                            <canvas id="expenseChart"></canvas>
                        </div>
                        <?php foreach ($expensesByCategory as $cat): ?>
                            <div class="category-row">
                                <span><?= $lang[strtolower($cat["category"])] ?? htmlspecialchars($cat["category"]) ?></span>
                                <span style="color: #ff4d7d;"><?= number_format($cat["total"], 2); ?> <?= $viewCurrency ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?= $lang['no_expense_categories'] ?? 'No expense categories yet' ?></p>
                    <?php endif; ?>
                </div>

                <div class="category-grid">
                    <h4><?= $lang['net_by_category'] ?? 'Net by Category' ?></h4>

                    <?php foreach ($categoriesSummary as $cat): ?>
                        <div class="category-row">
                            <span><?= htmlspecialchars($cat["category"]) ?></span>
                            <span class="<?= $cat["net"] >= 0 ? 'income' : 'expense'; ?>">
                                <?= number_format($cat["net"], 2); ?> <?= $viewCurrency ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>


    <div class="bottom-nav">

        <div class="nav-item active" data-target="home" onclick="showSection('home', this); moveIndicator(this);">
            🏠
        </div>

        <div class="nav-item" data-target="add" onclick="showSection('add', this); moveIndicator(this);">
            ➕
        </div>

        <div class="nav-item" data-target="goal" onclick="showSection('goal', this); moveIndicator(this);">
            🎯
        </div>

        <div class="nav-item" data-target="stats" onclick="showSection('stats', this); moveIndicator(this);">
            📊
        </div>

        <div class="indicator"></div>

    </div>

    <script>
        function showSection(id, element) {
            document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active-section'));
            let target = document.getElementById(id);
            if (target) target.classList.add('active-section');
            localStorage.setItem("activeSection", id);
        }

        function toggleMenu(e) {
            e.stopPropagation();
            document.getElementById("settingsMenu").classList.toggle("show-menu");
        }

        document.addEventListener("click", function() {
            let menu = document.getElementById("settingsMenu");
            if (menu) menu.classList.remove("show-menu");
        });

        function moveIndicator(el) {
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            el.classList.add('active');

            const indicator = document.querySelector('.indicator');
            if (indicator) {
                const rect = el.getBoundingClientRect();
                const parentRect = el.parentElement.getBoundingClientRect();
                indicator.style.left = (rect.left - parentRect.left) + "px";
                indicator.style.width = rect.width + "px";
            }
        }

        window.addEventListener("load", function() {
            let saved = localStorage.getItem("activeSection");

            if (saved) {
                document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active-section'));
                let targetSec = document.getElementById(saved);
                if (targetSec) targetSec.classList.add('active-section');
                
                document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
                let targetNav = document.querySelector(`.nav-item[data-target="${saved}"]`);
                
                if (targetNav) {
                    targetNav.classList.add('active');
                    moveIndicator(targetNav);
                } else {
                    const activeItem = document.querySelector('.nav-item.active') || document.querySelector('.nav-item');
                    if (activeItem) moveIndicator(activeItem);
                }
            } else {
                const activeItem = document.querySelector('.nav-item.active');
                if (activeItem) {
                    moveIndicator(activeItem);
                }
            }

            // Charts Initialization
            Chart.defaults.color = "rgba(255, 255, 255, 0.7)";
            
            // Highly distinct colors for charts
            const bgIncome = ['#00e676','#18ffff','#d500f9','#ffea00','#ff3d00','#c6ff00','#2979ff','#f50057','#76ff03','#00e5ff'];
            const bgExpense = ['#ff1744','#ff9100','#f50057','#651fff','#ffd600','#00e5ff','#d500f9','#1de9b6','#ff3d00','#2979ff'];

            <?php if (!empty($incomeByCategory)): ?>
            const incLabels = <?= json_encode(array_map(function($c) use ($lang) { return $lang[strtolower($c["category"])] ?? $c["category"]; }, $incomeByCategory)) ?>;
            const incData = <?= json_encode(array_column($incomeByCategory, "total")) ?>;
            new Chart(document.getElementById('incomeChart'), {
                type: 'doughnut',
                data: {
                    labels: incLabels,
                    datasets: [{
                        data: incData,
                        backgroundColor: bgIncome,
                        borderWidth: 0
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
            <?php endif; ?>

            <?php if (!empty($expensesByCategory)): ?>
            const expLabels = <?= json_encode(array_map(function($c) use ($lang) { return $lang[strtolower($c["category"])] ?? $c["category"]; }, $expensesByCategory)) ?>;
            const expData = <?= json_encode(array_column($expensesByCategory, "total")) ?>;
            new Chart(document.getElementById('expenseChart'), {
                type: 'doughnut',
                data: {
                    labels: expLabels,
                    datasets: [{
                        data: expData,
                        backgroundColor: bgExpense,
                        borderWidth: 0
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
            <?php endif; ?>
        });
    </script>
</body>

</html>
