<?php
require_once 'db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }
    $login = trim($_POST['login'] ?? ''); $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; $password_confirm = $_POST['password_confirm'] ?? '';
    if ($login === '' || $email === '' || $password === '') { $error = 'Заполните все обязательные поля.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Некорректный формат Email.'; }
    elseif (mb_strlen($password) < 6) { $error = 'Пароль минимум 6 символов.'; }
    elseif ($password !== $password_confirm) { $error = 'Пароли не совпадают.'; }
    else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = :login OR email = :email");
        $stmt->execute(['login' => $login, 'email' => $email]);
        if ($stmt->fetch()) { $error = 'Пользователь с таким логином/Email уже существует.'; }
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (login, email, password_hash, role) VALUES (?, ?, ?, 'user')")->execute([$login, $email, $hash]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_login'] = $login;
            $_SESSION['user_role'] = 'user';
            
            header("Location: profile.php"); exit;
        }
    }
}
require_once 'header.php';
?>
<div class="auth-card">
    <h2>Регистрация</h2>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="form-group"><label>Логин</label><input type="text" name="login" required minlength="3" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" class="admin-input"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="admin-input"></div>
        <div class="form-group"><label>Пароль (от 6 символов)</label><input type="password" name="password" required minlength="6" class="admin-input"></div>
        <div class="form-group"><label>Повторите пароль</label><input type="password" name="password_confirm" required minlength="6" class="admin-input"></div>
        <button type="submit" class="btn-primary full-width">Зарегистрироваться</button>
    </form>
    <p class="auth-switch" style="text-align:center; margin-top:14px;">Уже есть аккаунт? <a href="login.php" style="color:#0284c7; font-weight:bold; text-decoration:none;">Войти</a></p>
</div>
<?php require_once 'footer.php'; ?>