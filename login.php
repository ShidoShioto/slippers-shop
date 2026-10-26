<?php
require_once 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }
    $loginOrEmail = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($loginOrEmail === '' || $password === '') {
        $error = 'Введите логин/Email и пароль.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = :login OR email = :email LIMIT 1");
        $stmt->execute(['login' => $loginOrEmail, 'email' => $loginOrEmail]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_login'] = $user['login'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            header("Location: profile.php"); exit;
        } else { $error = 'Неверный логин или пароль.'; }
    }
}
require_once 'header.php';
?>
<div class="auth-card">
    <h2>Вход в аккаунт</h2>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group"><label>Логин или Email</label><input type="text" name="login" required value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" class="admin-input"></div>
        <div class="form-group"><label>Пароль</label><input type="password" name="password" required class="admin-input"></div>
        <button type="submit" class="btn-primary full-width">Войти</button>
    </form>
    <p class="auth-switch" style="text-align:center; margin-top:14px;">Нет аккаунта? <a href="register.php" style="color:#0284c7; font-weight:bold; text-decoration:none;">Создать аккаунт</a></p>
</div>
<?php require_once 'footer.php'; ?>