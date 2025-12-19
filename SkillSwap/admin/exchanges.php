<?php
$pageTitle = 'Manage Exchanges - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'proposals';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_proposal_status':
                // Handle status update
                break;
            case 'create_agreement':
                // Handle agreement creation
                break;
            case 'update_session_status':
                // Handle session status update
                break;
        }
    }
}

// Fetch data based on active tab
$items = [];
$totalItems = 0;

try {
    if ($activeTab === 'proposals') {
        $baseQuery = "FROM exchange_proposals ep
            JOIN users u1 ON ep.proposer_id = u1.user_id
            JOIN skills_catalog s1 ON ep.skill_to_learn_id = s1.skill_id
            JOIN skills_catalog s2 ON ep.skill_to_teach_id = s2.skill_id
            WHERE 1=1";
            
        $countQuery = "SELECT COUNT(*) as count $baseQuery";
        $query = "SELECT ep.*, 
            u1.username as proposer_name,
            s1.skill_name as skill_to_learn,
            s2.skill_name as skill_to_teach
            $baseQuery";
            
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (ep.title LIKE ? OR u1.username LIKE ? OR s1.skill_name LIKE ? OR s2.skill_name LIKE ?)";
            $countQuery .= " AND (ep.title LIKE ? OR u1.username LIKE ? OR s1.skill_name LIKE ? OR s2.skill_name LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, array_fill(0, 4, $searchTerm));
        }
        
        if (!empty($status)) {
            $query .= " AND ep.status = ?";
            $countQuery .= " AND ep.status = ?";
            $params[] = $status;
        }
        
        // Get total count
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $totalItems = $stmt->fetchColumn();
        $totalPages = ceil($totalItems / $perPage);
        
        // Add sorting and pagination
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
        
        // Execute query with pagination
        $stmt = $pdo->prepare($query);
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($activeTab === 'agreements') {
        $baseQuery = "FROM exchange_agreements ea
            JOIN exchange_proposals ep ON ea.exchange_id = ep.exchange_id
            JOIN users u1 ON ep.proposer_id = u1.user_id
            LEFT JOIN users u2 ON ep.match_user_id = u2.user_id
            WHERE 1=1";
            
        $countQuery = "SELECT COUNT(*) as count $baseQuery";
        $query = "SELECT ea.*, 
            ep.title as exchange_title,
            u1.username as proposer_name,
            u2.username as acceptor_name
            $baseQuery";
            
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (ep.title LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?)";
            $countQuery .= " AND (ep.title LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, array_fill(0, 3, $searchTerm));
        }
        
        if (!empty($status)) {
            $query .= " AND ea.agreement_status = ?";
            $countQuery .= " AND ea.agreement_status = ?";
            $params[] = $status;
        }
        
        // Get total count
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $totalItems = $stmt->fetchColumn();
        $totalPages = ceil($totalItems / $perPage);
        
        // Add sorting and pagination
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'ea.created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
        
        // Execute query with pagination
        $stmt = $pdo->prepare($query);
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($activeTab === 'sessions') {
        $baseQuery = "FROM exchange_sessions es
            JOIN exchange_proposals ep ON es.exchange_id = ep.exchange_id
            JOIN skills_catalog s ON es.skill_id = s.skill_id
            JOIN users t ON es.teacher_id = t.user_id
            JOIN users l ON es.learner_id = l.user_id
            WHERE 1=1";
            
        $countQuery = "SELECT COUNT(*) as count $baseQuery";
        $query = "SELECT es.*, 
            ep.title as exchange_title,
            s.skill_name,
            t.username as teacher_name,
            l.username as learner_name
            $baseQuery";
            
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (ep.title LIKE ? OR s.skill_name LIKE ? OR t.username LIKE ? OR l.username LIKE ?)";
            $countQuery .= " AND (ep.title LIKE ? OR s.skill_name LIKE ? OR t.username LIKE ? OR l.username LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, array_fill(0, 4, $searchTerm));
        }
        
        if (!empty($status)) {
            $query .= " AND es.session_status = ?";
            $countQuery .= " AND es.session_status = ?";
            $params[] = $status;
        }
        
        // Get total count
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $totalItems = $stmt->fetchColumn();
        $totalPages = ceil($totalItems / $perPage);
        
        // Add sorting and pagination
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'es.scheduled_time';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
        
        // Execute query with pagination
        $stmt = $pdo->prepare($query);
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching exchange data: " . $e->getMessage());
    $error = "Error loading exchange data. Please try again later.";
}
?>

<div class="container-fluid py-4">
    <div class="dashboard-header">
        <h1 style="color: var(--primary-color);">Manage Skill Exchanges</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <div class="table-container" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <table class="table table-hover mb-0" style="min-width: 100%; table-layout: fixed;">
                        <colgroup>
                            <col style="width: 20%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 10%;">
                            <col style="width: 10%;">
                            <col style="width: 10%;">
                            <col style="width: 10%;">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Proposer</th>
                                <th>Skill to Learn</th>
                                <th>Skill to Teach</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">No <?= $activeTab ?> found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="text-truncate" title="<?= htmlspecialchars($item['title']) ?>">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($item['proposer_name']) ?>">
                                            <?= htmlspecialchars($item['proposer_name']) ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($item['skill_to_learn']) ?>">
                                            <?= htmlspecialchars($item['skill_to_learn']) ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($item['skill_to_teach']) ?>">
                                            <?= htmlspecialchars($item['skill_to_teach']) ?>
                                        </td>
                                        <td>
                                            <?= ucfirst(str_replace('_', ' ', $item['exchange_type'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getStatusBadgeClass($item['status']) ?>" style="font-size: 0.8em;">
                                                <?= ucfirst(str_replace('_', ' ', $item['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <i class="far fa-calendar-alt me-1 text-muted"></i>
                                            <?= date('M j, Y', strtotime($item['created_at'])) ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="../pages/exchange-detail.php?id=<?= $item['exchange_id'] ?>&source=admin_exchanges" 
                                               class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" 
                                                       onclick="return confirm('Are you sure you want to delete this item?');">
                                                        <i class="fas fa-trash-alt me-2"></i>Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        Showing <span class="fw-bold"><?= $offset + 1 ?></span> to 
                        <span class="fw-bold"><?= min($offset + $perPage, $totalItems) ?></span> of 
                        <span class="fw-bold"><?= $totalItems ?></span> items
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($activeTab === 'agreements'): ?>
    <div class="card">
        <div class="tab-pane fade <?= $activeTab === 'agreements' ? 'show active' : '' ?>" id="agreements">
            <div class="table-responsive">
                <div class="table-container" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <table class="table table-hover mb-0" style="min-width: 100%; table-layout: fixed;">
                        <colgroup>
                            <col style="width: 10%;">
                            <col style="width: 20%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 10%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th>Agreement ID</th>
                                <th>Exchange Title</th>
                                <th>Proposer</th>
                                <th>Acceptor</th>
                                <th>Status</th>
                                <th>Signed At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No exchange agreements found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $agreement): ?>
                                    <tr>
                                        <td class="text-truncate" title="#<?= $agreement['agreement_id'] ?>">
                                            #<?= $agreement['agreement_id'] ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($agreement['exchange_title']) ?>">
                                            <?= htmlspecialchars($agreement['exchange_title']) ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($agreement['proposer_name']) ?>">
                                            <?= htmlspecialchars($agreement['proposer_name']) ?>
                                        </td>
                                        <td class="text-truncate" title="<?= !empty($agreement['acceptor_name']) ? htmlspecialchars($agreement['acceptor_name']) : 'N/A' ?>">
                                            <?= !empty($agreement['acceptor_name']) ? htmlspecialchars($agreement['acceptor_name']) : 'N/A' ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getAgreementStatusBadgeClass($agreement['agreement_status']) ?>" style="font-size: 0.8em;">
                                                <?= ucfirst($agreement['agreement_status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if ($agreement['signed_at']): ?>
                                                <i class="far fa-calendar-alt me-1 text-muted"></i>
                                                <?= date('M j, Y H:i', strtotime($agreement['signed_at'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not signed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="agreement_details.php?id=<?= $agreement['agreement_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" 
                                                    title="More actions" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-file-pdf me-2"></i>Export PDF
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" 
                                                       onclick="return confirm('Are you sure you want to cancel this agreement?');">
                                                        <i class="fas fa-times-circle me-2"></i>Cancel
                                                    </a>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'sessions'): ?>
    <div class="card">
        <div class="tab-pane fade <?= $activeTab === 'sessions' ? 'show active' : '' ?>" id="sessions">
            <div class="table-responsive">
                <div class="table-container" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <table class="table table-hover mb-0" style="min-width: 100%; table-layout: fixed;">
                        <colgroup>
                            <col style="width: 8%;">
                            <col style="width: 20%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 15%;">
                            <col style="width: 12%;">
                            <col style="width: 10%;">
                            <col style="width: 15%;">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th>Session #</th>
                                <th>Exchange</th>
                                <th>Skill</th>
                                <th>Teacher</th>
                                <th>Learner</th>
                                <th>Scheduled Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">No exchange sessions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $session): ?>
                                    <tr>
                                        <td class="text-truncate" title="#<?= $session['session_number'] ?>">
                                            #<?= $session['session_number'] ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($session['exchange_title']) ?>">
                                            <?= htmlspecialchars($session['exchange_title']) ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($session['skill_name']) ?>">
                                            <?= htmlspecialchars($session['skill_name']) ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($session['teacher_name']) ?>">
                                            <?= htmlspecialchars($session['teacher_name']) ?>
                                        </td>
                                        <td class="text-truncate" title="<?= htmlspecialchars($session['learner_name']) ?>">
                                            <?= htmlspecialchars($session['learner_name']) ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <i class="far fa-calendar-alt me-1 text-muted"></i>
                                            <?= date('M j, Y H:i', strtotime($session['scheduled_time'])) ?>
                                            <div class="small text-muted">
                                                <?= $session['duration_minutes'] ?? 60 ?> mins
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getSessionStatusBadgeClass($session['session_status']) ?>" style="font-size: 0.8em;">
                                                <?= ucfirst(str_replace('_', ' ', $session['session_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if (!empty($session['meeting_url'])): ?>
                                                <a href="<?= htmlspecialchars($session['meeting_url']) ?>" 
                                                   class="btn btn-sm btn-outline-primary mb-1" 
                                                   target="_blank" 
                                                   title="Join Meeting">
                                                    <i class="fas fa-video"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="session_details.php?id=<?= $session['session_id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary mb-1" 
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    title="More actions" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </a>
                                                </li>
                                                <?php if ($session['session_status'] === 'scheduled'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-success" href="#">
                                                            <i class="fas fa-check-circle me-2"></i>Mark as Completed
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-warning" href="#">
                                                            <i class="fas fa-clock me-2"></i>Reschedule
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" 
                                                           onclick="return confirm('Are you sure you want to cancel this session?');">
                                                            <i class="fas fa-times-circle me-2"></i>Cancel
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if (isset($totalPages) && $totalPages > 1): ?>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        Showing <span class="fw-bold"><?= $offset + 1 ?></span> to 
                        <span class="fw-bold"><?= min($offset + $perPage, $totalItems) ?></span> of 
                        <span class="fw-bold"><?= $totalItems ?></span> items
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Helper functions for badge styling
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'searching':
            return 'info';
        case 'matched':
            return 'primary';
        case 'in_progress':
            return 'success';
        case 'completed':
            return 'secondary';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

function getAgreementStatusBadgeClass($status) {
    switch ($status) {
        case 'draft':
            return 'secondary';
        case 'proposed':
            return 'info';
        case 'signed':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

function getSessionStatusBadgeClass($status) {
    switch ($status) {
        case 'scheduled':
            return 'info';
        case 'in_progress':
            return 'primary';
        case 'completed':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
