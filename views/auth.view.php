<!DOCTYPE html>
<html lang="<?= $current_lang ?>" dir="<?= $dir ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Auth</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>

<body class="dark-auth">

    <div class="center-container">

        <div style="position:absolute; top:20px; right:20px;">
            <a href="?lang=en">EN</a> |
            <a href="?lang=tr">TR</a> |
            <a href="?lang=ar">AR</a>
        </div>
        <div class="auth-card">

            <!-- LEFT IMAGE -->
            <div class="card-left">
                <h1><?= $lang['Money Tracking']; ?></h1>
                <p><?= $lang['Manage your money smartly']; ?></p>
            </div>

            <!-- RIGHT FORM -->
            <div class="card-right">

                <div class="switch-buttons">
                    <button id="loginBtn" class="active" onclick="showLogin()"><?= $lang['login']; ?></button>
                    <button id="registerBtn" onclick="showRegister()"><?= $lang['register']; ?></button>
                </div>

                <!-- LOGIN -->
                <form method="POST" id="loginForm" class="form-box">
                    <input type="hidden" name="action" value="login">
                    <input type="email" name="email" placeholder="<?= $lang['email']; ?>" required>
                    <input type="password" name="password" autocomplete="current-password" placeholder="<?= $lang['password']; ?>" required>
                    <button type="submit" class="main-btn"><?= $lang['login']; ?></button>
                </form>

                <!-- REGISTER -->
                <form method="POST" id="registerForm" class="form-box hidden">
                    <input type="hidden" name="action" value="register">
                    <input type="text" name="name" placeholder="<?= $lang['name']; ?>" required>
                    <input type="email" name="email" placeholder="<?= $lang['email']; ?>" required>
                    <input type="password" name="password" autocomplete="current-password" placeholder="<?= $lang['password']; ?>" required>
                    <button type="submit" class="main-btn"><?= $lang['register']; ?></button>
                </form>

                <?php if (!empty($message)): ?>
                    <p class="error-msg"><?= htmlspecialchars($message) ?></p>
                <?php endif; ?>

            </div>

        </div>

    </div>

    <script>
        // DOM Elements for Login and Registration forms
        const loginBtn = document.getElementById('loginBtn');
        const registerBtn = document.getElementById('registerBtn');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');

        // Event Listener: Switch view to Login form
        loginBtn.addEventListener('click', () => {
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');

            loginBtn.classList.add('active');
            registerBtn.classList.remove('active');
        });

        // Event Listener: Switch view to Registration form
        registerBtn.addEventListener('click', () => {
            registerForm.classList.remove('hidden');
            loginForm.classList.add('hidden');

            registerBtn.classList.add('active');
            loginBtn.classList.remove('active');
        });
    </script>

</body>

</html>
