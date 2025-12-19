<?php
$pageTitle = 'Manage Skill Requests - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Get filter parameters
$searchQuery = trim($_GET['search'] ?? '');

// Build the base query for user_skills (Skills I Want to Learn)
$baseQuery = "FROM user_skills us
          JOIN users u ON us.user_id = u.user_id
          JOIN skills_catalog sc ON us.skill_id = sc.skill_id
          LEFT JOIN skill_categories cat ON sc.category = cat.category_name
          WHERE us.willing_to_learn = 1
          AND NOT EXISTS (
              SELECT 1 FROM exchange_proposals ep 
              WHERE ep.status = 'completed' AND (
                  (ep.proposer_id = us.user_id AND ep.skill_to_learn_id = us.skill_id)
                  OR
                  (ep.match_user_id = us.user_id AND ep.skill_to_teach_id = us.skill_id)
              )
          )";

$params = [];

if (!empty($searchQuery)) {
    $baseQuery .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR sc.skill_name LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

// Get total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) as total " . $baseQuery);
$countStmt->execute($params);
$countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
$totalRequests = $countResult ? (int)$countResult['total'] : 0;

// Build final data query
$query = "SELECT us.*, 
          u.username, u.email, u.full_name,
          sc.skill_name, sc.category,
          cat.category_name " . $baseQuery . " ORDER BY us.created_at DESC";

$perPage = 10;
$totalPages = ceil($totalRequests / $perPage);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$query .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="dashboard-header">
        <h1 class="h3 mb-0" style="color: var(--primary-color);">Skill Requests</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left; margin: 0;">
                    <i class="fas fa-hand-holding-heart me-2" style="color: var(--primary-color);"></i>
                    All Requests
                </h2>
                <div>
                    <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="fas fa-filter me-1"></i> Filter
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No skill requests found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                    <table class="table align-middle mb-0 table-hover" style="width: 100%; min-width: 1000px;">
                        <thead style="background-color: #f9fafb;">
                            <tr>
                                <th class="ps-4 py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 100px;">ID</th>
                                <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 150px;">User</th>
                                <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 150px;">Skill to Learn</th>
                                <th class="py-3 text-uppercase text-muted small fw-bold text-center" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 100px;">Proficiency</th>
                                <th class="py-3 text-uppercase text-muted small fw-bold text-center" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 120px;">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr class="position-relative hover-bg-light">
                                    <td class="ps-4 py-3 align-top">
                                        <div class="fw-bold text-primary">#<?php echo $request['user_skill_id']; ?></div>
                                    </td>
                                    <td class="py-3 align-top">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <span class="text-muted small"><?php echo strtoupper(substr($request['full_name'] ?? 'U', 0, 1)); ?></span>
                                            </div>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($request['full_name']); ?></div>
                                                <div class="text-muted small">@<?php echo htmlspecialchars($request['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 align-top">
                                        <div class="fw-medium"><?php echo htmlspecialchars($request['skill_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($request['category_name'] ?? 'Uncategorized'); ?></div>
                                    </td>
                                    <td class="py-3 text-center align-middle">
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                            <?php echo ucfirst($request['proficiency_level']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-center align-middle">
                                        <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
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
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            $startPage = max(1, $endPage - 4);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
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

<?php 
// Initialize tooltips
$js = <<<EOT
// Initialize all tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover',
            placement: 'top',
            container: 'body'
        });
    });
});
EOT;

echo '<script>' . $js . '</script>';

require_once __DIR__ . '/../includes/footer.php'; 
?>