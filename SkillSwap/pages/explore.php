<?php
$pageTitle = 'Explore Skills';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

// Get categories
$stmt = $pdo->query("SELECT * FROM skill_categories WHERE is_active = 1 ORDER BY category_name");
$categories = $stmt->fetchAll();

// Get Ghana cities
$ghanaCities = ['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Ashaiman', 'Sunyani', 'Cape Coast', 'Obuasi', 'Teshie', 'Tema', 'Koforidua', 'Sekondi', 'Techiman', 'Ho', 'Wa', 'Bolgatanga', 'Bawku', 'Nkawkaw', 'Aflao', 'Hohoe'];

// Get filter parameters
$categoryFilter = $_GET['category'] ?? '';
$locationFilter = $_GET['location'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$minSkillLevel = $_GET['min_level'] ?? 'beginner';
$exchangeType = $_GET['exchange_type'] ?? '';
$sortBy = $_GET['sort'] ?? 'recent';

// Build query to find skills available for exchange
$sql = "SELECT 
            sc.*, 
            u.user_id,
            u.full_name as teacher_name,
            u.location,
            u.profile_image,
            scat.category_name,
            COALESCE(AVG(er.rating), 0) as avg_rating,
            COUNT(DISTINCT er.review_id) as review_count
        FROM skills_catalog sc
        JOIN user_skills us ON sc.skill_id = us.skill_id
        JOIN users u ON us.user_id = u.user_id
        JOIN skill_categories scat ON sc.category = scat.category_name
        LEFT JOIN exchange_reviews er ON (er.skill_id = sc.skill_id AND er.reviewee_id = u.user_id)
        WHERE us.willing_to_teach = 1 
        AND us.is_verified = 1
        AND u.verification_status = 'verified'";

$params = [];

if ($categoryFilter) {
    $sql .= " AND scat.category_id = ?";
    $params[] = $categoryFilter;
}

if ($locationFilter) {
    $sql .= " AND u.location = ?";
    $params[] = $locationFilter;
}

if ($searchQuery) {
    $sql .= " AND (sc.skill_name LIKE ? OR sc.description LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($minSkillLevel) {
    $sql .= " AND sc.difficulty_level = ?";
    $params[] = $minSkillLevel;
}

// Group by and order
$sql .= " GROUP BY sc.skill_id, u.user_id";

// Add sorting
switch ($sortBy) {
    case 'rating':
        $sql .= " ORDER BY avg_rating DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY review_count DESC";
        break;
    default: // recent
        $sql .= " ORDER BY sc.created_at DESC";
}

// Add pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT $offset, $perPage";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
// Build count query with same conditions as main query
$countSql = "SELECT COUNT(DISTINCT CONCAT(sc.skill_id, '-', u.user_id)) as total 
             FROM skills_catalog sc
             JOIN user_skills us ON sc.skill_id = us.skill_id
             JOIN users u ON us.user_id = u.user_id
             JOIN skill_categories scat ON sc.category = scat.category_name
             WHERE us.willing_to_teach = 1 
             AND us.is_verified = 1
             AND u.verification_status = 'verified'";

// Add conditions that affect the main query
if ($categoryFilter) {
    $countSql .= " AND scat.category_id = ?";
}
if ($locationFilter) {
    $countSql .= " AND u.location = ?";
}
if ($searchQuery) {
    $countSql .= " AND (sc.skill_name LIKE ? OR sc.description LIKE ?)";
}
if ($minSkillLevel) {
    $countSql .= " AND us.proficiency_level >= ?";
}
if ($exchangeType) {
    $countSql .= " AND us.exchange_type = ?";
}

// Execute count query with specific params
$countParams = [];
if ($categoryFilter) $countParams[] = $categoryFilter;
if ($locationFilter) $countParams[] = $locationFilter;
if ($searchQuery) {
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}
if ($minSkillLevel) $countParams[] = $minSkillLevel;
if ($exchangeType) $countParams[] = $exchangeType;

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalSkills = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$totalPages = ceil($totalSkills / $perPage);
?>

<div class="container">
    <div class="page-header">
        <h1>Explore Skills</h1>
        <p>Find skills you want to learn and connect with people who can teach you</p>
    </div>

    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Filters</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" id="filterForm">
                        <div class="mb-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" 
                                        <?php echo ($categoryFilter == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">Anywhere</option>
                                <?php foreach ($ghanaCities as $city): ?>
                                    <option value="<?php echo $city; ?>" 
                                        <?php echo ($locationFilter === $city) ? 'selected' : ''; ?>>
                                        <?php echo $city; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="min_level" class="form-label">Minimum Skill Level</label>
                            <select class="form-select" id="min_level" name="min_level">
                                <option value="beginner" <?php echo ($minSkillLevel === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" <?php echo ($minSkillLevel === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo ($minSkillLevel === 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        <a href="<?php echo BASE_URL; ?>/pages/explore.php" class="btn btn-outline-secondary w-100 mt-2">Reset</a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Skills List -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="mb-0"><?php echo $totalSkills; ?> skills found</h5>
                </div>
                <div class="d-flex align-items-center">
                    <span class="me-2">Sort by:</span>
                    <div class="btn-group" role="group">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'recent'])); ?>" 
                           class="btn btn-sm btn-outline-secondary <?php echo ($sortBy === 'recent') ? 'active' : ''; ?>">
                            Newest
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'rating'])); ?>" 
                           class="btn btn-sm btn-outline-secondary <?php echo ($sortBy === 'rating') ? 'active' : ''; ?>">
                            Top Rated
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'popular'])); ?>" 
                           class="btn btn-sm btn-outline-secondary <?php echo ($sortBy === 'popular') ? 'active' : ''; ?>">
                            Most Popular
                        </a>
                    </div>
                </div>
            </div>

            <?php if (empty($skills)): ?>
                <div class="alert alert-info">
                    No skills found matching your criteria. Try adjusting your filters.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($skills as $skill): ?>
                        <div class="col">
                            <div class="card h-100 skill-card">
                                <?php if (!empty($skill['profile_image'])): ?>
                                    <img src="<?php echo BASE_URL; ?>/uploads/profiles/<?php echo htmlspecialchars($skill['profile_image']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($skill['teacher_name']); ?>">
                                <?php else: ?>
                                    <div class="skill-image-placeholder">
                                        <?php echo strtoupper(substr($skill['skill_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($skill['skill_name']); ?></h5>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($skill['teacher_name']); ?>
                                    </p>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?php echo htmlspecialchars($skill['location']); ?>
                                    </p>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-tag"></i> 
                                        <?php echo htmlspecialchars($skill['category_name']); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="rating">
                                            <?php
                                            $rating = (float)$skill['avg_rating'];
                                            $fullStars = floor($rating);
                                            $hasHalfStar = $rating - $fullStars >= 0.5;
                                            
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $fullStars) {
                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                } elseif ($i === $fullStars + 1 && $hasHalfStar) {
                                                    echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                                } else {
                                                    echo '<i class="far fa-star text-warning"></i>';
                                                }
                                            }
                                            ?>
                                            <span class="ms-1">(<?php echo $skill['review_count']; ?>)</span>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo match($skill['difficulty_level']) {
                                                'beginner' => 'success',
                                                'intermediate' => 'warning',
                                                'advanced' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($skill['difficulty_level']); ?>
                                        </span>
                                    </div>
                                    <p class="card-text"><?php echo substr(htmlspecialchars($skill['description']), 0, 100); ?>...</p>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <a href="<?php echo BASE_URL; ?>/pages/explore-detail.php?skill_id=<?php echo $skill['skill_id']; ?>&user_id=<?php echo $skill['user_id']; ?>" 
                                       class="btn btn-primary w-100">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                       aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            $startPage = max(1, $endPage - 4);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                       aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.skill-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid rgba(0,0,0,0.125);
}

.skill-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.skill-image-placeholder {
    height: 200px;
    background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 4rem;
    font-weight: bold;
}

.rating {
    color: #ffc107;
}

.card-img-top {
    height: 200px;
    object-fit: cover;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>