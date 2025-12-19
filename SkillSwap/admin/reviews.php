<?php
$pageTitle = 'Manage Reviews - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/rating_helper.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Handle review status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $reviewId = filter_input(INPUT_POST, 'review_id', FILTER_SANITIZE_NUMBER_INT);
    $isApproved = isset($_POST['is_approved']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE exchange_reviews SET is_approved = ? WHERE review_id = ?");
        $stmt->execute([$isApproved, $reviewId]);
        
        // Update user's average rating
        $stmt = $pdo->prepare("SELECT reviewee_id FROM exchange_reviews WHERE review_id = ?");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch();
        if ($review) {
            updateUserAvgRating($pdo, $review['reviewee_id']);
        }

        $_SESSION['success'] = 'Review status updated successfully!';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error updating review status: ' . $e->getMessage();
    }
    
    header('Location: reviews.php');
    exit();
}

// Handle review deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    $reviewId = filter_input(INPUT_POST, 'review_id', FILTER_SANITIZE_NUMBER_INT);
    
    try {
        $pdo->beginTransaction();
        // Get reviewee_id before deletion
        $stmt = $pdo->prepare("SELECT reviewee_id FROM exchange_reviews WHERE review_id = ?");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM exchange_reviews WHERE review_id = ?");
        if ($stmt->execute([$reviewId])) {
            $pdo->commit();
            
            // Update user's average rating
            if ($review) {
                updateUserAvgRating($pdo, $review['reviewee_id']);
            }

            $_SESSION['success'] = 'Review deleted successfully!';
        } else {
            throw new Exception('Failed to delete review');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error deleting review: ' . $e->getMessage();
    }
    
    header('Location: reviews.php');
    exit();
}

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : 'all';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base query
$query = "SELECT er.*, 
          e.title as exchange_title,
          r1.username as reviewer_name,
          r2.username as reviewee_name,
          s.skill_name
          FROM exchange_reviews er
          JOIN exchange_proposals e ON er.exchange_id = e.exchange_id
          JOIN users r1 ON er.reviewer_id = r1.user_id
          JOIN users r2 ON er.reviewee_id = r2.user_id
          LEFT JOIN skills_catalog s ON er.skill_id = s.skill_id
          WHERE 1=1";

$params = [];

// Apply filters
if ($statusFilter !== 'all') {
    $query .= " AND er.is_approved = ?";
    $params[] = ($statusFilter === 'approved') ? 1 : 0;
}

if ($typeFilter !== 'all') {
    $query .= " AND er.review_type = ?";
    $params[] = $typeFilter;
}

if (!empty($searchQuery)) {
    $query .= " AND (e.title LIKE ? OR r1.username LIKE ? OR r2.username LIKE ? OR s.skill_name LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$query .= " ORDER BY er.created_at DESC";

// Get total count for pagination
$countStmt = $pdo->prepare(str_replace('er.*', 'COUNT(*) as total', $query));
$countStmt->execute($params);
$totalReviews = $countStmt->fetch()['total'];

// Pagination
$perPage = 10;
$totalPages = ceil($totalReviews / $perPage);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$query .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0" style="color: var(--primary-color);">Exchange Reviews</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left; margin: 0;">
                    <i class="fas fa-comments me-2" style="color: var(--primary-color);"></i>
                    All Reviews
                </h2>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($reviews)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No reviews found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                    <table class="table align-middle mb-0 table-hover" style="width: 100%; min-width: 1200px;">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-uppercase small fw-bold" style="width: 15%; min-width: 200px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Exchange</th>
                                <th class="py-3 text-uppercase small fw-bold" style="width: 12%; min-width: 120px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Reviewer</th>
                                <th class="py-3 text-uppercase small fw-bold" style="width: 12%; min-width: 120px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Reviewee</th>
                                <th class="py-3 text-uppercase small fw-bold" style="width: 12%; min-width: 120px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Skill</th>
                                <th class="py-3 text-uppercase small fw-bold text-center" style="width: 10%; min-width: 100px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Rating</th>
                                <th class="py-3 text-uppercase small fw-bold text-center" style="width: 10%; min-width: 100px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Type</th>
                                <th class="py-3 text-uppercase small fw-bold text-center" style="width: 12%; min-width: 120px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Status</th>
                                <th class="py-3 text-uppercase small fw-bold text-center" style="width: 10%; min-width: 100px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Date</th>
                                <th class="text-center py-3 text-uppercase small fw-bold" style="width: 7%; min-width: 80px; letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr class="position-relative hover-bg-light">
                                    <td class="ps-4 py-3 align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 32px; height: 32px;">
                                                <i class="fas fa-exchange-alt text-muted" style="font-size: 0.8rem;"></i>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <div class="fw-medium text-truncate" style="max-width: 180px;" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($review['exchange_title']); ?>">
                                                    <?php echo htmlspecialchars($review['exchange_title']); ?>
                                                </div>
                                                <small class="text-muted">#<?php echo $review['exchange_id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 28px; height: 28px;">
                                                <span class="text-muted small"><?php echo strtoupper(substr($review['reviewer_name'], 0, 1)); ?></span>
                                            </div>
                                            <div class="text-truncate" style="max-width: 100px;" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($review['reviewer_name']); ?>">
                                                <?php echo htmlspecialchars($review['reviewer_name']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 28px; height: 28px;">
                                                <span class="text-muted small"><?php echo strtoupper(substr($review['reviewee_name'], 0, 1)); ?></span>
                                            </div>
                                            <div class="text-truncate" style="max-width: 100px;" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($review['reviewee_name']); ?>">
                                                <?php echo htmlspecialchars($review['reviewee_name']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 align-middle">
                                        <span class="text-truncate d-inline-block" style="max-width: 120px;" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($review['skill_name']); ?>">
                                            <?php echo $review['skill_name'] ? htmlspecialchars($review['skill_name']) : '<span class="text-muted">N/A</span>'; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 align-middle text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="text-warning mb-1">
                                                <?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?>
                                            </span>
                                            <small class="text-muted"><?php echo $review['rating']; ?>.0</small>
                                        </div>
                                    </td>
                                    <td class="py-3 align-middle text-center">
                                        <?php 
                                        $typeClass = $review['review_type'] === 'as_teacher' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-success bg-opacity-10 text-success';
                                        $typeIcon = $review['review_type'] === 'as_teacher' ? 'chalkboard-teacher' : 'user-graduate';
                                        $typeText = $review['review_type'] === 'as_teacher' ? 'Teacher' : 'Learner';
                                        ?>
                                        <div class="d-flex justify-content-center">
                                            <span class="badge border border-<?php echo $review['review_type'] === 'as_teacher' ? 'primary' : 'success'; ?> border-opacity-25 d-inline-flex align-items-center" style="font-size: 0.7rem; padding: 0.3em 0.7em;">
                                                <i class="fas fa-<?php echo $typeIcon; ?> me-1"></i>
                                                <?php echo $typeText; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 align-middle text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="badge rounded-pill bg-<?php echo (isset($review['is_approved']) && $review['is_approved']) ? 'success' : 'warning'; ?> text-uppercase d-inline-flex align-items-center mb-1" style="font-size: 0.65rem; padding: 0.35em 0.8em; letter-spacing: 0.5px;">
                                                <i class="fas fa-<?php echo (isset($review['is_approved']) && $review['is_approved']) ? 'check-circle' : 'clock'; ?> me-1"></i>
                                                <?php echo (isset($review['is_approved']) && $review['is_approved']) ? 'Approved' : 'Pending'; ?>
                                            </span>
                                            <small class="text-muted" data-bs-toggle="tooltip" title="<?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?>">
                                                <i class="far fa-clock me-1"></i>
                                                <?php 
                                                $now = new DateTime();
                                                $created = new DateTime($review['created_at']);
                                                $interval = $created->diff($now);
                                                
                                                if ($interval->y > 0) {
                                                    echo $interval->y . 'y ago';
                                                } elseif ($interval->m > 0) {
                                                    echo $interval->m . 'mo ago';
                                                } elseif ($interval->d > 0) {
                                                    echo $interval->d . 'd ago';
                                                } elseif ($interval->h > 0) {
                                                    echo $interval->h . 'h ago';
                                                } else {
                                                    echo 'Just now';
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="py-3 align-middle text-center text-muted small">
                                        <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                    </td>
                                    <td class="py-3 align-middle">
                                        <div class="d-flex justify-content-center gap-1">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to ' + (<?php echo (isset($review['is_approved']) && $review['is_approved']) ? 'unapprove' : 'approve'; ?>) + ' this review?');">
                                                <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                                <input type="hidden" name="is_approved" value="<?php echo (isset($review['is_approved']) && $review['is_approved']) ? '0' : '1'; ?>">
                                                <button type="submit" name="update_status" class="btn btn-sm btn-outline-<?php echo (isset($review['is_approved']) && $review['is_approved']) ? 'warning' : 'success'; ?> rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" data-bs-toggle="tooltip" title="<?php echo (isset($review['is_approved']) && $review['is_approved']) ? 'Unapprove' : 'Approve'; ?>">
                                                    <i class="fas fa-<?php echo (isset($review['is_approved']) && $review['is_approved']) ? 'times' : 'check'; ?>" style="font-size: 0.7rem;"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline delete-form" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');">
                                                <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                                <button type="submit" name="delete_review" class="btn btn-sm btn-outline-danger rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="fas fa-trash-alt" style="font-size: 0.7rem;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <nav class="d-flex justify-content-center mt-4">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                       aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
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
    .hover-bg-light:hover {
        background-color: #f8f9fa !important;
        transition: background-color 0.2s ease;
    }
    .avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 600;
        flex-shrink: 0;
    }
    .text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .btn-sm.rounded-circle {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    table th {
        white-space: nowrap;
        position: relative;
    }
    table th:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 1px;
        height: 1.5rem;
        background-color: #e9ecef;
    }
    .table > :not(:first-child) {
        border-top: 1px solid #f1f3f5;
    }
</style>

<script>
// Initialize tooltips and other interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover',
            placement: 'top',
            container: 'body'
        });
    });

    // Make table rows clickable for viewing details
    document.querySelectorAll('tr[data-href]').forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function() {
            const modalId = this.getAttribute('data-href');
            if (modalId) {
                const modal = new bootstrap.Modal(document.getElementById(modalId));
                modal.show();
            }
        });
    });
});
</script>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>