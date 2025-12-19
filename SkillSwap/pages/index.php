<?php
$pageTitle = 'Home - SkillSwap';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

// Get skill categories for filter
$stmt = $pdo->query("SELECT * FROM skill_categories WHERE is_active = 1 ORDER BY category_name");
$categories = $stmt->fetchAll();

// Get Ghana cities
$ghanaCities = ['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Ashaiman', 'Sunyani', 'Cape Coast', 'Obuasi', 'Teshie', 'Tema', 'Koforidua', 'Sekondi', 'Techiman', 'Ho', 'Wa', 'Bolgatanga', 'Bawku', 'Nkawkaw', 'Aflao', 'Hohoe'];

// Get filter parameters
$categoryFilter = $_GET['category'] ?? '';
$locationFilter = $_GET['location'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$difficultyFilter = $_GET['difficulty'] ?? '';

// Build query - Get skills that users are willing to teach
$sql = "SELECT 
            us.user_skill_id,
            us.proficiency_level,
            us.experience_years,
            us.can_teach,
            us.willing_to_teach,
            sc.skill_id,
            sc.skill_name,
            sc.category,
            sc.description as skill_description,
            sc.difficulty_level,
            u.user_id,
            u.full_name,
            u.location,
            u.profile_image,
            u.bio,
            COALESCE((SELECT AVG(er.rating) FROM exchange_reviews er WHERE er.reviewee_id = u.user_id), 0) as avg_rating,
            (SELECT COUNT(*) FROM exchange_proposals ep 
             WHERE (ep.proposer_id = u.user_id OR ep.match_user_id = u.user_id) 
             AND ep.status = 'completed') as completed_exchanges,
            (SELECT COUNT(*) FROM exchange_reviews WHERE reviewee_id = u.user_id) as review_count
        FROM user_skills us
        JOIN skills_catalog sc ON us.skill_id = sc.skill_id
        JOIN users u ON us.user_id = u.user_id
        WHERE us.willing_to_teach = 1 
          AND us.can_teach = 1
          AND sc.is_active = 1
          AND u.role != 'admin'
          AND u.user_id != :current_user_id";

$params = [];

if ($categoryFilter) {
    $sql .= " AND sc.category = ?";
    $params[] = $categoryFilter;
}

if ($locationFilter) {
    $sql .= " AND u.location = ?";
    $params[] = $locationFilter;
}

if ($difficultyFilter) {
    $sql .= " AND sc.difficulty_level = ?";
    $params[] = $difficultyFilter;
}

if ($searchQuery) {
    $sql .= " AND (sc.skill_name LIKE ? OR sc.description LIKE ? OR u.full_name LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY u.avg_rating DESC, u.completed_exchanges DESC LIMIT 24";

// Add current user ID to parameters if user is logged in
if (isset($_SESSION['user_id'])) {
    $params[':current_user_id'] = $_SESSION['user_id'];
} else {
    // If not logged in, use 0 which won't match any user_id
    $params[':current_user_id'] = 0;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teachableSkills = $stmt->fetchAll();

// Get unique categories from skills_catalog for filter
$stmt = $pdo->query("SELECT DISTINCT category FROM skills_catalog WHERE is_active = 1 AND category IS NOT NULL ORDER BY category");
$skillCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container-fluid py-4 px-4">
    <!-- Hero Section with Admin Card Styling -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body p-0 overflow-hidden">
            <div class="p-4" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                <div class="row align-items-center">
                    <div class="col-lg-6 text-center text-lg-start mb-4 mb-lg-0">
                        <h1 class="h3 fw-bold mb-3" style="color: var(--primary-color);">
                            <i class="fas fa-exchange-alt me-2"></i>SkillSwap
                        </h1>
                        <p class="text-muted mb-4">
                            Connect with skilled individuals in your community. Share your expertise and learn new skills in return.
                        </p>
                    </div>
                </div>
                
                <!-- Search Form -->
                <div class="mt-4 p-3 bg-white rounded-3 shadow-sm">
                    <form method="GET" action="" style="display: flex; gap: 0.75rem; align-items: center;">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search skills or teachers..." 
                               value="<?php echo e($searchQuery); ?>"
                               style="flex: 1; height: 44px;">
                        <select name="category" class="form-select" style="flex: 1; height: 55px;">
                            <option value="">All Categories</option>
                            <?php foreach ($skillCategories as $cat): ?>
                                <option value="<?php echo e($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
                                    <?php echo e($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="location" class="form-select" style="flex: 1; height: 55px;">
                            <option value="">All Locations</option>
                            <?php foreach ($ghanaCities as $city): ?>
                                <option value="<?php echo e($city); ?>" <?php echo $locationFilter === $city ? 'selected' : ''; ?>>
                                    <?php echo e($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary" style="white-space: nowrap; height: 55px;">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Skills Grid -->
    <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px;">
        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Skills Available to Learn
                </h2>
                <span class="badge rounded-pill bg-light text-dark border"><?php echo count($teachableSkills); ?> Skills</span>
            </div>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <?php if (empty($teachableSkills)): ?>
                <div style="text-align: center; padding: 3rem; color: #6B7280;">
                    <i class="fas fa-search fa-4x mb-3" style="opacity: 0.2;"></i>
                    <h3 style="color: #4B5563; margin-bottom: 0.5rem;">No skills found</h3>
                    <p style="margin: 0;">Try adjusting your search filters or check back later.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($teachableSkills as $skill): 
                        // Generate initials
                        $names = explode(' ', trim($skill['full_name']));
                        $initials = isset($names[0]) ? strtoupper(substr($names[0], 0, 1)) : '';
                        if (count($names) > 1) {
                            $initials .= strtoupper(substr(end($names), 0, 1));
                        }
                        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                        $bgColor = $colors[strlen($skill['full_name']) % count($colors)];
                        
                        // Difficulty badge colors
                        $difficultyColors = [
                            'beginner' => 'background: #d1fae5; color: #065f46;',
                            'intermediate' => 'background: #fef3c7; color: #92400e;',
                            'advanced' => 'background: #fee2e2; color: #991b1b;'
                        ];
                        $diffStyle = $difficultyColors[$skill['difficulty_level']] ?? 'background: #f3f4f6; color: #374151;';
                    ?>
                        <div class="skill-card" style="background: white; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <!-- Card Header -->
                            <div style="padding: 1.25rem; border-bottom: 1px solid #f3f4f6;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                    <h3 style="margin: 0; font-size: 1.1rem; color: #1f2937; font-weight: 600;"><?php echo e($skill['skill_name']); ?></h3>
                                    <span class="badge" style="<?php echo $diffStyle; ?> font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 500;">
                                        <?php echo ucfirst($skill['difficulty_level']); ?>
                                    </span>
                                </div>
                                <?php if ($skill['category']): ?>
                                    <span style="display: inline-block; background: #f3f4f6; color: #6b7280; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                        <i class="fas fa-folder me-1"></i><?php echo e($skill['category']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Teacher Info -->
                            <div style="padding: 1.25rem; background: #fafafa;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <?php if (!empty($skill['profile_image'])): ?>
                                        <img src="<?php echo BASE_URL; ?>/uploads/profiles/<?php echo e($skill['profile_image']); ?>" 
                                             alt="<?php echo e($skill['full_name']); ?>"
                                             class="rounded-circle"
                                             style="width: 45px; height: 45px; min-width: 45px; object-fit: cover; border: 1px solid #e5e7eb;">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                             style="width: 45px; height: 45px; min-width: 45px; font-size: 0.9rem; background-color: <?php echo $bgColor; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; color: #1f2937;"><?php echo e($skill['full_name']); ?></div>
                                        <div style="font-size: 0.85rem; color: #6b7280;">
                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo e($skill['location'] ?? 'Not specified'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Stats -->
                                <div style="display: flex; gap: 1rem; margin-bottom: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                                        <span style="color: #f59e0b;">
                                            <?php 
                                            $rating = (float)$skill['avg_rating'];
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= round($rating) ? '★' : '☆';
                                            }
                                            ?>
                                        </span>
                                        <span style="font-size: 0.85rem; color: #6b7280;">(<?php echo (int)$skill['review_count']; ?>)</span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">
                                        <i class="fas fa-exchange-alt me-1"></i><?php echo (int)$skill['completed_exchanges']; ?> exchanges
                                    </div>
                                </div>
                                
                                <!-- Action Button -->
                                <?php if (isLoggedIn()): ?>
                                    <?php if (!hasRole('admin')): ?>
                                        <a href="<?php echo BASE_URL; ?>/pages/propose-exchange.php?skill_id=<?php echo $skill['user_skill_id']; ?>&teacher_id=<?php echo $skill['user_id']; ?>" 
                                           class="btn btn-primary btn-sm" style="border-radius: 6px; padding: 0.5rem 1.5rem; min-width: 100px;">
                                            <i class="fas fa-exchange-alt me-2"></i> Propose Exchange
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?php echo BASE_URL; ?>/pages/login.php" 
                                       class="btn btn-outline-primary btn-sm" style="border-radius: 6px; padding: 0.5rem 1.5rem; display: block; width: fit-content; margin: 0 auto; min-width: 100px;">
                                        <i class="fas fa-sign-in-alt me-2"></i> Connect to Propose Exchange
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.skill-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    border-color: var(--primary-color);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>