<?php
require_once 'header.php';

function formatPrice($price) {
    return str_replace(' ', '&nbsp;', number_format($price, 0, ',', ' ')) . '&nbsp;₽';
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='alert error'>Тяги не найдены.</div>"; require_once 'footer.php'; exit;
}

$userSize = '40';
if (isset($_SESSION['user_id'])) {
    $szStmt = $pdo->prepare("SELECT shoe_size FROM users WHERE id = ?");
    $szStmt->execute([$_SESSION['user_id']]);
    $szRow = $szStmt->fetch();
    if ($szRow && !empty($szRow['shoe_size'])) { $userSize = $szRow['shoe_size']; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Ошибка безопасности: Неверный CSRF-токен.");
    }
    if (isset($_SESSION['user_id'])) {
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);
        if ($rating >= 1 && $rating <= 5 && $comment !== '') {
            $ins = $pdo->prepare("INSERT INTO reviews (product_id, user_id, user_login, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$id, $_SESSION['user_id'], $_SESSION['user_login'], $rating, $comment]);
            header("Location: product.php?id=$id"); exit;
        }
    }
}

$rStmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = :id ORDER BY id DESC");
$rStmt->execute(['id' => $id]);
$reviews = $rStmt->fetchAll();
$avgRating = count($reviews) > 0 ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$availableSizes = ['37', '38', '39', '40', '41', '42', '43', '44-45'];
?>

<div class="product-detail">
    <div class="detail-image">
        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['title']) ?>">
    </div>
    <div class="detail-info">
        <span class="badge"><?= htmlspecialchars($product['category']) ?></span>
        <h1><?= htmlspecialchars($product['title']) ?></h1>
        <div class="detail-rating">
            <span class="star-rating">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1" style="vertical-align:-2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> 
                <?= count($reviews) > 0 ? round($avgRating, 1) : 'Нет оценок' ?>
            </span> 
            <span class="review-count">(Отзывов: <?= count($reviews) ?>)</span>
        </div>
        <p class="detail-price"><?= formatPrice($product['price']) ?></p>
        <p class="detail-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        
        <div class="size-selector">
            <label>Выбери размер тяг (EU):</label>
            <div class="size-chips">
                <?php foreach($availableSizes as $sz): ?>
                    <span class="size-chip <?= ($userSize === $sz) ? 'active' : '' ?>" onclick="selectSize(this)"><?= $sz ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="detail-actions">
            <button type="button" class="btn-primary btn-lg" onclick="addToCart(<?= $product['id'] ?>)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px; margin-right:6px;"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                Залутать в корзину
            </button>
            <a href="index.php" class="btn-secondary btn-lg">Назад</a>
        </div>
    </div>
</div>

<script>
function selectSize(el) {
    document.querySelectorAll('.size-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
}
</script>

<div class="reviews-section">
    <h2 style="margin-bottom: 20px;">Что говорят пацаны (Отзывы)</h2>
    <div class="reviews-layout">
        <div class="reviews-list-col">
            <?php if (empty($reviews)): ?>
                <div class="empty-reviews">
                    <span style="font-size: 3rem;">🦗</span>
                    <p>Тут пока тихо... Оставь отзыв первым!</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $rev): 
                    $filledStars = $rev['rating'];
                    $emptyStars = 5 - $filledStars;
                ?>
                    <div class="review-card">
                        <div class="review-avatar"><?= mb_strtoupper(mb_substr($rev['user_login'], 0, 1)) ?></div>
                        <div class="review-content">
                            <div class="review-header">
                                <strong><?= htmlspecialchars($rev['user_login']) ?></strong>
                                <span class="review-date"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></span>
                            </div>
                            <div class="review-stars">
                                <?php for($i=0; $i<$filledStars; $i++): ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                <?php endfor; ?>
                                <?php for($i=0; $i<$emptyStars; $i++): ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                <?php endfor; ?>
                            </div>
                            <p class="review-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="review-form-col">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="review-form-modern">
                    <h3>Поделись базой</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="form-group">
                            <label>Как оценишь тяги?</label>
                            <div class="star-rating-widget">
                                <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 - Ауф, пушка!">★</label>
                                <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 - Четко">★</label>
                                <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 - Ну такое">★</label>
                                <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 - Кринж">★</label>
                                <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 - Полная шляпа">★</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <textarea name="comment" required placeholder="Раскидай по фактам..." rows="3"></textarea>
                        </div>
                        <button type="submit" name="submit_review" class="btn-primary full-width">Отправить отзыв</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="review-form-modern" style="text-align:center;">
                    <span style="font-size: 3rem;">🕵️</span>
                    <h3>Войди в систему</h3>
                    <p style="color:#64748b; margin-bottom:15px;">Только зарегистрированные гигачады могут оставлять отзывы.</p>
                    <a href="login.php" class="btn-primary">Авторизоваться</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>