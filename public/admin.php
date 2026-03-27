<?php
// Initialize session and include necessary configurations
session_start();
require_once "../config/timeout.php";
require_once "../config/database.php";

// Restrict access: Redirect non-admin users to the dashboard
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: dashboard.php");
    exit();
}

// Handle administrative actions (Delete user or Toggle admin role)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['target_user'])) {
    $action = $_POST['action'];
    $target_id = $_POST['target_user'];
    
    // Prevent the admin from accidentally deleting or demoting their own account
    if ($target_id != $_SESSION['user_id']) { 
        if ($action === 'delete') {
            // Delete all user data securely including transactions and goals
            $pdo->prepare("DELETE FROM transactions WHERE user_id = ?")->execute([$target_id]);
            $pdo->prepare("DELETE FROM goals WHERE user_id = ?")->execute([$target_id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
        } elseif ($action === 'toggle_role') {
            // Toggle the user's role between 'admin' and 'user'
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $currentRole = $stmt->fetchColumn();
            $newRole = ($currentRole === 'admin') ? 'user' : 'admin';
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $target_id]);
        }
    }
    header("Location: admin.php");
    exit();
}

// Fetch comprehensive statistics for all registered users, including transaction counts and net balances
$stmt = $pdo->query("
    SELECT 
        u.id, u.name, u.email, u.role, u.created_at,
        COUNT(t.id) as tx_count,
        SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END) - 
        SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) as net_balance
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch global site metrics
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
$totalTransactions = $stmt->fetchColumn();

$current_lang = $_SESSION['lang'] ?? 'en';
$lang = require "../lang/" . $current_lang . ".php";
$dir = ($current_lang == "ar") ? "rtl" : "ltr";
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"> 
    <title>Admin Panel</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .table-wrapper { overflow-x: auto; width: 100%; margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); white-space: nowrap; }
        th { color: #888; font-weight: normal; }
        .action-btn { background: rgba(255,255,255,0.1); border:none; padding: 6px 12px; border-radius: 6px; color:#fff; cursor:pointer; font-size:12px; margin-right:5px; transition: 0.2s; }
        .action-btn:hover { background: rgba(255,255,255,0.2); }
        .btn-danger:hover { background: #ef4444; }
    </style>
</head>
<body>
    <div class="app-container" style="max-width: 900px;">
        <div class="top-bar">
            <a href="dashboard.php" style="text-decoration:none; color:inherit; font-weight:bold;">
                ← <?= $lang['back'] ?? 'Back' ?>
            </a>
            <h2>👑 Admin Panel</h2>
            <div style="width: 24px;"></div>
        </div>
        
        <div class="row" style="margin-top:20px;">
            <div class="small-card income">
                <p>Total Users</p>
                <h3><?= $totalUsers ?></h3>
            </div>
            <div class="small-card expense">
                <p>Transactions</p>
                <h3><?= $totalTransactions ?></h3>
            </div>
        </div>

        <div class="wide-card" style="margin-top: 2rem;">
            <h3>Registered Dashboard Users</h3>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Tx Cnt</th>
                            <th>Net (USD)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="category-box" style="background: <?= $u['role'] === 'admin' ? '#ff4d7d' : '#2A2A35' ?>;">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>
                            <td><?= $u['tx_count'] ?></td>
                            <td style="color: <?= $u['net_balance'] >= 0 ? '#10b981' : '#ef4444' ?>">
                                <?= number_format($u['net_balance'] ?: 0, 2) ?>
                            </td>
                            <td>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="target_user" value="<?= $u['id'] ?>">
                                    <button type="submit" name="action" value="toggle_role" class="action-btn">
                                        <?= $u['role'] === 'admin' ? 'Demote' : 'Make Admin' ?>
                                    </button>
                                    <button type="submit" name="action" value="delete" class="action-btn btn-danger" onclick="return confirm('Silmek istediğine emin misin? (Bu işlem tüm verileri siler)');">
                                        Delete
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="font-size:12px; color:#888;">(You)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
