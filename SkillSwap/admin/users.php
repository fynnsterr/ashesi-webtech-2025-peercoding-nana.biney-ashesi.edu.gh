<?php
$pageTitle = 'Manage Users - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Initialize filter variables from GET parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

// Validate sort column
$allowedSorts = ['full_name', 'email', 'created_at', 'role', 'verification_status'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'created_at';
}

// Validate sort direction
$sortDir = ($order === 'ASC') ? 'ASC' : 'DESC';

// Build base query
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM exchange_reviews WHERE reviewee_id = u.user_id) as review_count,
          (SELECT AVG(rating) FROM exchange_reviews WHERE reviewee_id = u.user_id) as real_avg_rating,
          (SELECT COUNT(*) FROM exchange_proposals WHERE status = 'completed' AND (proposer_id = u.user_id OR match_user_id = u.user_id)) as real_completed_exchanges
          FROM users u WHERE u.role != 'admin'";
$params = [];

// Apply filters
if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($role)) {
    $query .= " AND u.role = ?";
    $params[] = $role;
}

if (!empty($status)) {
    $query .= " AND u.verification_status = ?";
    $params[] = $status;
}

// Get total count for pagination
$countQuery = str_replace("u.*, 
          (SELECT COUNT(*) FROM exchange_reviews WHERE reviewee_id = u.user_id) as review_count,
          (SELECT AVG(rating) FROM exchange_reviews WHERE reviewee_id = u.user_id) as real_avg_rating,
          (SELECT COUNT(*) FROM exchange_proposals WHERE status = \"completed\" AND (proposer_id = u.user_id OR match_user_id = u.user_id)) as real_completed_exchanges", 'COUNT(*)', $query);
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalUsers = $stmt->fetchColumn();

// Pagination logic
$perPage = 10;
$totalPages = ceil($totalUsers / $perPage);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Add sorting and limits
$query .= " ORDER BY u.$sort $sortDir LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <h1 style="color: var(--primary-color);">Manage Users</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <!-- Search and Filter Card -->
    <div class="card mb-4" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                 <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left; margin: 0;">
                    <i class="fas fa-filter mr-2" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                    Filters
                </h2>
            </div>
        </div>
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-lg">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted ps-3" style="border-radius: 8px 0 0 8px; border-color: #e5e7eb;">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" 
                               style="border-radius: 0 8px 8px 0; border-color: #e5e7eb; box-shadow: none;"
                               placeholder="Search users..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); width: 100%; margin: 0;">
        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                 <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left; margin: 0;">
                    <i class="fas fa-users mr-2" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                    Registered Users
                </h2>
                <span class="badge rounded-pill bg-light text-dark border"><?php echo $totalUsers; ?> Total</span>
            </div>
        </div>
        <div class="card-body p-0">
        <div class="table-responsive" style="width: 100%; overflow-x: auto;">
            <table class="table align-middle mb-0 table-hover" style="width: 100%; min-width: 1000px;">
                <thead style="background-color: #f9fafb;">
                    <tr>
                        <th class="ps-4 py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">
                            <a href="?<?php 
                                $params = $_GET;
                                $params['sort'] = 'full_name';
                                $params['order'] = ($sort === 'full_name' && $order === 'ASC') ? 'DESC' : 'ASC';
                                echo http_build_query($params);
                            ?>" class="text-decoration-none text-muted d-flex align-items-center">
                                Name
                                <?php if ($sort === 'full_name'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">
                            <a href="?<?php 
                                $params = $_GET;
                                $params['sort'] = 'email';
                                $params['order'] = ($sort === 'email' && $order === 'ASC') ? 'DESC' : 'ASC';
                                echo http_build_query($params);
                            ?>" class="text-decoration-none text-muted d-flex align-items-center">
                                Contact Info
                                <?php if ($sort === 'email'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Role</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Status</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">
                             <a href="?<?php 
                                $params = $_GET;
                                $params['sort'] = 'avg_rating';
                                $params['order'] = ($sort === 'avg_rating' && $order === 'DESC') ? 'ASC' : 'DESC';
                                echo http_build_query($params);
                            ?>" class="text-decoration-none text-muted d-flex align-items-center">
                                Rating
                                <?php if ($sort === 'avg_rating'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Exchanges</th>
                        <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">
                            <a href="?<?php 
                                $params = $_GET;
                                $params['sort'] = 'created_at';
                                $params['order'] = ($sort === 'created_at' && $order === 'DESC') ? 'ASC' : 'DESC';
                                echo http_build_query($params);
                            ?>" class="text-decoration-none text-muted d-flex align-items-center">
                                Joined
                                <?php if ($sort === 'created_at'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                                <?php endif; ?>
                            </a>
                        </th>

                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="fas fa-users fa-3x opacity-50"></i></div>
                                <p class="h6 text-muted">No users found matching your criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php 
                                // SMART VERIFICATION LOGIC
                                // If pending + completed > 0 + rating > 2.5 => Treat as verified
                                $displayStatus = $user['verification_status'];
                                $realExchanges = $user['real_completed_exchanges'] ?? 0;
                                $realRating = $user['real_avg_rating'] ?? 0;
                                
                                if ($user['verification_status'] === 'pending' && $realExchanges >= 1 && $realRating > 2.5) {
                                    $displayStatus = 'verified';
                                }
                            ?>
                            <tr class="position-relative hover-bg-light">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                            // Generate initials
                                            $names = explode(' ', trim($user['full_name']));
                                            $initials = isset($names[0]) ? strtoupper(substr($names[0], 0, 1)) : '';
                                            if (count($names) > 1) {
                                                $initials .= strtoupper(substr(end($names), 0, 1));
                                            }
                                            // Random elegant background color based on name length
                                            $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                                            $bgCheck = $colors[strlen($user['full_name']) % count($colors)];
                                        ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white me-3 text-uppercase shadow-sm fw-bold" 
                                             style="width: 40px; height: 40px; min-width: 40px; font-size: 0.9rem; background-color: <?php echo $bgCheck; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                            <!-- <small class="text-muted">ID: <?php echo $user['user_id']; ?></small> -->
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex flex-column">
                                        <div class="text-dark mb-1" style="font-size: 0.9rem;">
                                            <i class="far fa-envelope text-muted me-2" style="width: 14px;"></i>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-phone-alt text-muted me-2" style="width: 14px;"></i>
                                            <?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span class="fst-italic text-muted">No phone</span>'; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <?php 
                                        $roleStyles = [
                                            // 'admin' => 'background-color: #fee2e2; color: #991b1b;', // Red (Admins hidden)
                                            'teacher' => 'background-color: #dbeafe; color: #1e40af;', // Blue
                                            'learner' => 'background-color: #f3f4f6; color: #374151;', // Gray
                                            'both' => 'background-color: #e0e7ff; color: #3730a3;' // Indigo
                                        ];
                                        $style = $roleStyles[$user['role']] ?? 'background-color: #f3f4f6; color: #374151;';
                                        
                                        // Rename "Both" to "User"
                                        $displayRole = $user['role'] === 'both' ? 'User' : ucfirst($user['role']);
                                    ?>
                                    <span class="badge rounded-pill fw-normal px-3 py-2" style="<?php echo $style; ?> font-size: 0.8rem;">
                                        <?php echo $displayRole; ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <?php 
                                        $statusStyles = [
                                            'verified' => 'background-color: #d1fae5; color: #065f46;', // Emerald
                                            'pending' => 'background-color: #fef3c7; color: #92400e;', // Amber
                                            'rejected' => 'background-color: #fce7f3; color: #9d174d;' // Pink
                                        ];
                                        $statusStyle = $statusStyles[$displayStatus] ?? 'background-color: #f3f4f6; color: #374151;';
                                        
                                        $statusIcons = [
                                            'verified' => 'fas fa-check-circle',
                                            'pending' => 'fas fa-clock',
                                            'rejected' => 'fas fa-times-circle'
                                        ];
                                        $statusIcon = $statusIcons[$displayStatus] ?? 'fas fa-circle';
                                    ?>
                                    <span class="badge rounded-pill fw-normal px-2 py-1" style="<?php echo $statusStyle; ?> font-size: 0.8rem;">
                                        <i class="<?php echo $statusIcon; ?> me-1"></i><?php echo ucfirst($displayStatus); ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        <span class="fw-bold me-2" style="font-size: 1rem; color: #374151;"><?php echo number_format((float)$realRating, 1); ?></span>
                                        <div class="d-flex text-warning small">
                                            <?php 
                                            // Use real rating
                                            $rating = (float)$realRating;
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= floor($rating)) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } elseif ($i == ceil($rating) && $rating - floor($rating) >= 0.5) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                } else {
                                                    echo '<i class="far fa-star text-muted opacity-25"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-1"><?php echo $user['review_count']; ?> reviews</div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex flex-column justify-content-center" style="max-width: 120px;">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">Completed</span>
                                            <span class="fw-bold"><?php echo $realExchanges; ?></span>
                                        </div>
                                        <div class="progress" style="height: 6px; border-radius: 3px; background-color: #e5e7eb;">
                                            <?php 
                                            // Simple visual progress: cap at 10 exchanges = 100% for visual feedback
                                            $progress = min(100, ($realExchanges * 10)); 
                                            ?>
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $progress; ?>%; background-color: var(--primary-color); border-radius: 3px;" 
                                                 aria-valuenow="<?php echo $progress; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <div class="small text-muted mt-1 text-nowrap">
                                            <?php echo $realExchanges; ?> exchanges done
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-muted small">
                                    <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                    <div><?php echo date('h:i A', strtotime($user['created_at'])); ?></div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-3 px-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing <span class="fw-bold text-dark"><?php echo ($offset + 1); ?></span> to <span class="fw-bold text-dark"><?php echo min($offset + $perPage, $totalUsers); ?></span> of <span class="fw-bold text-dark"><?php echo $totalUsers; ?></span> results
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link border-0 text-muted" href="?<?php 
                                    $params = $_GET;
                                    $params['page'] = $page - 1;
                                    echo http_build_query($params);
                                ?>">
                                    <i class="fas fa-chevron-left me-1"></i> Prev
                                </a>
                            </li>
                            
                            <?php 
                            $range = 2;
                            $start = max(1, $page - $range);
                            $end = min($totalPages, $page + $range);
                            
                            if ($start > 1) {
                                echo '<li class="page-item"><a class="page-link border-0 text-muted" href="?page=1'. '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) .'">1</a></li>';
                                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link border-0 text-muted">...</span></li>';
                            }
                            
                            for ($i = $start; $i <= $end; $i++): 
                                $isActive = $i === $page;
                            ?>
                                <li class="page-item">
                                    <a class="page-link border-0 <?php echo $isActive ? 'fw-bold text-white shadow-sm' : 'text-muted'; ?>" 
                                       style="<?php echo $isActive ? 'background-color: var(--primary-color); border-radius: 6px;' : ''; ?>"
                                       href="?<?php 
                                        $params = $_GET;
                                        $params['page'] = $i;
                                        echo http_build_query($params);
                                    ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php 
                            if ($end < $totalPages) {
                                if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link border-0 text-muted">...</span></li>';
                                echo '<li class="page-item"><a class="page-link border-0 text-muted" href="?page='.$totalPages . '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) .'">'.$totalPages.'</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link border-0 text-muted" href="?<?php 
                                    $params = $_GET;
                                    $params['page'] = $page + 1;
                                    echo http_build_query($params);
                                ?>">
                                    Next <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 1050; min-width: 300px;">
        <?php 
        echo $_SESSION['success']; 
        unset($_SESSION['success']);
        
        // Show the new password if it was just reset
        if (isset($_SESSION['reset_password'])) {
            $reset = $_SESSION['reset_password'];
            echo "<div class='mt-2'><strong>New Password for {$reset['email']}:</strong> {$reset['password']}</div>";
            unset($_SESSION['reset_password']);
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 1050; min-width: 300px;">
        <?php 
        echo $_SESSION['error']; 
        unset($_SESSION['error']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<script>
// Handle clicks on action links
document.addEventListener('DOMContentLoaded', function() {
    // Prevent dropdown from closing when clicking inside it
    document.querySelectorAll('.dropdown-menu').forEach(function(dropdown) {
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });

    // Handle action link clicks
    document.querySelectorAll('.action-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const message = this.getAttribute('data-message');
            const url = this.getAttribute('data-url');
            
            if (confirm(message)) {
                console.log('Proceeding to:', url);
                window.location.href = url;
            } else {
                console.log('Action cancelled by user');
            }
            
            // Close the dropdown
            const dropdown = this.closest('.dropdown-menu');
            if (dropdown) {
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdown.previousElementSibling);
                if (dropdownInstance) {
                    dropdownInstance.hide();
                }
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
