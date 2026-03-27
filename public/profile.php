<?php
// Initialize session and include necessary configurations
session_start();
require_once "../config/timeout.php";
require_once "../config/database.php";

// Redirect unauthenticated users back to the login page
if (!isset($_SESSION["user_id"])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$current_lang = $_SESSION['lang'] ?? 'en';
$lang = require "../lang/" . $current_lang . ".php";
$dir = ($current_lang == "ar") ? "rtl" : "ltr";

// Ensure the user_wallets table exists to store custom wallet names
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_wallets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, name VARCHAR(100))");
} catch (PDOException $e) {}

// Handle the addition of a new custom wallet for the user
if (isset($_POST['add_wallet'])) {
    $wallet_name = trim($_POST['add_wallet']);
    if (!empty($wallet_name)) {
        $stmt = $pdo->prepare("INSERT INTO user_wallets (user_id, name) VALUES (?, ?)");
        $stmt->execute([$user_id, $wallet_name]);
        header("Location: profile.php");
        exit();
    }
}

// Handle the deletion of a specific custom wallet
if (isset($_POST['delete_wallet'])) {
    $wallet_id = $_POST['delete_wallet'];
    $stmt = $pdo->prepare("DELETE FROM user_wallets WHERE id = ? AND user_id = ?");
    $stmt->execute([$wallet_id, $user_id]);
    header("Location: profile.php");
    exit();
}

$msg = "";

// Handle user profile updates (Name, Email, Currency, Password)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['name'])) {
    $currency = $_POST["currency"] ?? 'USD';
    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $new_password = $_POST["new_password"] ?? '';

    // Verify if the requested new email already belongs to someone else
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        $msg = "<p style='color:#ef4444; font-weight:bold;'>Email is already in use by another account!</p>";
    } else {
        // Update user record: hash the new password if provided, otherwise leave it unchanged
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, preferred_currency=?, password=? WHERE id=?");
            $stmt->execute([$name, $email, $currency, $hashed, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, preferred_currency=? WHERE id=?");
            $stmt->execute([$name, $email, $currency, $user_id]);
        }
        
        $_SESSION["user_name"] = $name;
        $msg = "<p style='color:#10b981; font-weight:bold;'>Profile updated successfully!</p>";
    }
}

// Fetch user details to pre-populate the profile form
$stmt = $pdo->prepare("SELECT name, email, preferred_currency FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch custom wallets associated with the user for display or management
$stmt = $pdo->prepare("SELECT * FROM user_wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$myWallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userName = $user['name'];
$userEmail = $user['email'];
$userCurrency = $user['preferred_currency'] ?: 'USD';

?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"> 
    <title><?= $lang['profile'] ?? 'Profile' ?></title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #cbd5e1; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="top-bar">
            <a href="dashboard.php" style="text-decoration:none; color:inherit; font-weight:bold;">
                ← <?= $lang['back'] ?? 'Back' ?>
            </a>
            <h2><?= $lang['profile'] ?? 'Profile' ?></h2>
            <div style="width: 24px;"></div>
        </div>
        
        <div class="wide-card" style="margin-top: 2rem;">
            <h3><?= $lang['profile'] ?? 'Profile Settings' ?></h3>
            <?= $msg ?>
            <form method="POST">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($userName) ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($userEmail) ?>" required>
                </div>

                <div class="form-group">
                    <label>Preferred Currency</label>
                    <select name="currency">
                        <option value="USD" <?= $userCurrency == "USD" ? "selected" : "" ?>>USD ($)</option>
                        <option value="TRY" <?= $userCurrency == "TRY" ? "selected" : "" ?>>TRY (₺)</option>
                        <option value="EUR" <?= $userCurrency == "EUR" ? "selected" : "" ?>>EUR (€)</option>
                    </select>
                </div>

                <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 30px 0;">
                <h4 style="margin-bottom: 15px; color:#94a3b8;">Security (Optional)</h4>

                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="new_password" placeholder="Enter new password">
                </div>

                <button type="submit" class="main-btn" style="margin-top: 20px;">Save Changes</button>
            </form>
        </div>

        <div class="wide-card" style="margin-top: 2rem;">
            <h3>Manage Wallets</h3>
            <p style="color:#94a3b8; font-size:14px; margin-bottom:15px;">Add custom wallets for your transactions. (e.g. Bank Account, Cash, Crypto)</p>
            
            <form method="POST" style="margin-bottom:20px; display:flex; gap:10px;">
                <input type="text" name="add_wallet" placeholder="Wallet Name" required style="margin:0;">
                <button type="submit" class="main-btn" style="width:auto; margin:0; padding:12px 25px;">Add</button>
            </form>

            <div style="display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.05); padding:10px 15px; border-radius:8px;">
                    <span><?= $lang['wallet_main'] ?? 'Main Wallet' ?></span>
                    <small style="color:#64748b;">Default</small>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.05); padding:10px 15px; border-radius:8px;">
                    <span><?= $lang['wallet_credit'] ?? 'Credit Card' ?></span>
                    <small style="color:#64748b;">Default</small>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.05); padding:10px 15px; border-radius:8px;">
                    <span><?= $lang['wallet_business'] ?? 'Business' ?></span>
                    <small style="color:#64748b;">Default</small>
                </div>
                <?php foreach($myWallets as $w): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.05); padding:10px 15px; border-radius:8px;">
                        <span><?= htmlspecialchars($w['name']) ?></span>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="delete_wallet" value="<?= $w['id'] ?>">
                            <button type="submit" style="background:none; border:none; color:#ef4444; padding:0; cursor:pointer; width:auto; font-weight:bold;">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
