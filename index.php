<?php
require_once 'header.php';

// Функция для безопасного отображения цен без разрывов строк
function formatPrice($price) {
    return str_replace(' ', '&nbsp;', number_format($price, 0, ',', ' ')) . '&nbsp;₽';
}

$category = trim($_GET['category'] ?? '');
$search   = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($category !== '') { $sql .= " AND category = :cat"; $params['cat'] = $category; }
if ($search !== '') { $sql .= " AND (title LIKE :q OR description LIKE :q2)"; $params['q'] = "%$search%"; $params['q2'] = "%$search%"; }
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$catStmt = $pdo->query("SELECT DISTINCT category FROM products");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<section class="hero">
    <div class="hero-content">
        <h1>Время обновить тяги</h1>
        <p>Кефтеме-качество и гигачад-стиль. Доставка быстрее, чем ты скажешь «Ауф».</p>
    </div>
</section>

<form method="GET" action="index.php" class="toolbar-bar">
    <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
    <div class="search-wrap"><input type="text" name="q" placeholder="Поиск тех самых тяг..." value="<?= htmlspecialchars($search) ?>"></div>
    <div class="sort-wrap"><button type="submit" class="btn-primary search-btn">Найти</button></div>
</form>

<div class="filters">
    <a href="index.php" class="filter-chip <?= $category === '' ? 'active' : '' ?>">Все подкрадули</a>
    <?php foreach ($categories as $cat): ?>
        <a href="index.php?category=<?= urlencode($cat) ?>" class="filter-chip <?= $category === $cat ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
    <?php endforeach; ?>
</div>

<div class="product-grid">
    <?php foreach ($products as $item): 
        $rStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(id) as count_rev FROM reviews WHERE product_id = ?");
        $rStmt->execute([$item['id']]);
        $revData = $rStmt->fetch();
        $avgRating = $revData['count_rev'] > 0 ? round($revData['avg_rating'], 1) : 0;
    ?>
        <article class="product-card">
            <div class="card-img-link">
                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
            </div>
            <div class="card-body">
                <div class="card-meta">
                    <span class="badge"><?= htmlspecialchars($item['category']) ?></span>
                    <?php if($avgRating > 0): ?>
                        <span class="rating">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1" style="vertical-align:-2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg> 
                            <?= $avgRating ?> (<?= $revData['count_rev'] ?>)
                        </span>
                    <?php endif; ?>
                </div>
                <h3>
                    <a href="product.php?id=<?= (int)$item['id'] ?>" class="stretched-link">
                        <?= htmlspecialchars($item['title']) ?>
                    </a>
                </h3>
                <p class="desc"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 85, '...')) ?></p>
                <div class="card-footer">
                    <span class="price"><?= formatPrice($item['price']) ?></span>
                    <button type="button" class="btn-primary" onclick="addToCart(<?= $item['id'] ?>)">В корзину</button>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php require_once 'footer.php'; ?>