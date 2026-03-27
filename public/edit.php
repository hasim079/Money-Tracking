<?php
// Initialize session and connection settings
session_start();
require_once "../config/timeout.php";
require_once "../config/database.php";
require_once "../app/models/Transaction.php";

// Redirection block to ensure the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: auth.php");
    exit();
}

$current_lang = $_SESSION['lang'] ?? 'en';
$lang = require "../lang/" . $current_lang . ".php";
$dir = ($current_lang == "ar") ? "rtl" : "ltr";

$user_id = $_SESSION["user_id"];
$transactionModel = new Transaction($pdo);

// Ensure a transaction ID is provided via query parameter to edit
if (!isset($_GET["id"])) {
    header("Location: dashboard.php");
    exit();
}

// Fetch the specific transaction associated with the user
$id = $_GET["id"];
$transaction = $transactionModel->getById($id, $user_id);

// Validate wallet table exists and retrieve user's custom wallets to populate the dropdown
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_wallets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, name VARCHAR(100))");
} catch (PDOException $e) {}

$stmt = $pdo->prepare("SELECT name FROM user_wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$userWallets = $stmt->fetchAll(PDO::FETCH_COLUMN);

$defaultWallets = [
    $lang['wallet_main'] ?? 'Main',
    $lang['wallet_credit'] ?? 'Credit Card',
    $lang['wallet_business'] ?? 'Business'
];
$allWallets = array_values(array_unique(array_merge($defaultWallets, $userWallets)));

// Redirect back if transaction doesn't exist or isn't owned by the user
if (!$transaction) {
    header("Location: dashboard.php");
    exit();
}

// Handle saving updated transaction details from POST data
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $type = $_POST["type"];
    $amount = floatval($_POST["amount"]);
    $description = $_POST["description"];
    $category = $_POST["category"];
    $account_type = $_POST["account_type"] ?? 'Main';

    $transactionModel->update($id, $user_id, $type, $amount, $description, $category, $account_type);

    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"> 
    <title><?= $lang['edit_transaction'] ?? 'Edit Transaction' ?></title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-container">
    <div class="top-bar">
        <a href="dashboard.php" style="text-decoration:none; color:inherit; font-weight:bold;">
            ← <?= $lang['back'] ?? 'Back' ?>
        </a>
        <h2><?= $lang['edit_transaction'] ?? 'Edit Transaction' ?></h2>
        <div style="width: 24px;"></div>
    </div>
    <div class="wide-card" style="margin-top: 2rem;">

        <form method="POST">
            <select name="type">
                <option value="income" <?= $transaction["type"]=="income"?"selected":"" ?>><?= $lang['income'] ?? 'Income' ?></option>
                <option value="expense" <?= $transaction["type"]=="expense"?"selected":"" ?>><?= $lang['expense'] ?? 'Expense' ?></option>
            </select>

            <select name="account_type" required>
                <?php foreach ($allWallets as $w_opt): ?>
                    <option value="<?= htmlspecialchars($w_opt) ?>" <?= (isset($transaction['account_type']) && $transaction['account_type'] == $w_opt) ? "selected" : "" ?>><?= htmlspecialchars($w_opt) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="number" step="0.01" name="amount" value="<?= $transaction["amount"]; ?>">

            <input type="text" name="description" value="<?= htmlspecialchars($transaction["description"]); ?>">

            <input list="category-options" name="category" placeholder="<?= $lang['category'] ?? 'Category' ?>" value="<?= htmlspecialchars($transaction['category']); ?>" required autocomplete="off">
            <datalist id="category-options">
                <option value="<?= $lang['salary'] ?? 'Salary' ?>"></option>
                <option value="<?= $lang['food'] ?? 'Food' ?>"></option>
                <option value="<?= $lang['transport'] ?? 'Transport' ?>"></option>
                <option value="<?= $lang['utilities'] ?? 'Utilities' ?>"></option>
                <option value="<?= $lang['entertainment'] ?? 'Entertainment' ?>"></option>
                <option value="<?= $lang['other'] ?? 'Other' ?>"></option>
            </datalist>

            <button type="submit" class="main-btn">Update</button>
        </form>
    </div>
</div>

</body>
</html>
