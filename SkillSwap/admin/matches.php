<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Get user role from database if not in session
if (!isset($_SESSION['role'])) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['role'] = $user['role'];
    } else {
        // User not found in database
        session_destroy();
        header('Location: ../login.php?error=user_not_found');
        exit();
    }
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php?error=access_denied');
    exit();
}

$message = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $match_id = filter_input(INPUT_POST, 'match_id', FILTER_SANITIZE_NUMBER_INT);
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    $stmt = $pdo->prepare("UPDATE exchange_matches SET match_status = ? WHERE match_id = ?");
    if ($stmt->execute([$new_status, $match_id])) {
        $message = '<div class="alert alert-success">Match status updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error updating match status.</div>';
    }
}

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_match'])) {
    $match_id = filter_input(INPUT_POST, 'match_id', FILTER_SANITIZE_NUMBER_INT);
    $user_type = filter_input(INPUT_POST, 'user_type', FILTER_SANITIZE_STRING);
    
    $column = ($user_type === 'proposer') ? 'proposer_confirmed' : 'acceptor_confirmed';
    $stmt = $pdo->prepare("UPDATE exchange_matches SET $column = 1 WHERE match_id = ?");
    if ($stmt->execute([$match_id])) {
        $message = '<div class="alert alert-success">Confirmation updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error updating confirmation.</div>';
    }
}

// Initialize database connection
$pdo = getDB();

// Fetch all matches with user and skill details
$stmt = $pdo->query("
    SELECT em.*, 
           e.title as exchange_title,
           u1.username as proposer_name,
           u2.username as acceptor_name,
           ps.skill_name as proposer_skill_name,
           aks.skill_name as acceptor_skill_name
    FROM exchange_matches em
    JOIN exchange_proposals e ON em.exchange_id = e.exchange_id
    JOIN users u1 ON e.proposer_id = u1.user_id
    JOIN users u2 ON em.acceptor_id = u2.user_id
    JOIN skills_catalog ps ON em.proposer_skill_id = ps.skill_id
    JOIN skills_catalog aks ON em.acceptor_skill_id = aks.skill_id
    ORDER BY em.created_at DESC
");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = 'Manage Matches - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container-fluid py-4">
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <h1 style="color: var(--primary-color);">Exchange Matches</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <?php if (!empty($message)) echo $message; ?>

    <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left; margin: 0;">
                    <i class="fas fa-exchange-alt mr-2" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                    All Matches
                </h2>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                <table class="table align-middle mb-0 table-hover" style="width: 100%; min-width: 1200px;">
                    <thead style="background-color: #f9fafb;">
                        <tr>
                            <th class="ps-4 py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 80px;">Match ID</th>
                            <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 150px;">Exchange</th>
                            <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 180px;">Parties</th>
                            <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 200px;">Skills Exchange</th>
                            <th class="py-3 text-uppercase text-muted small fw-bold text-center" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 120px;">Status</th>
                            <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb; min-width: 120px;">Date</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php if (empty($matches)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle fa-2x mb-2 d-block"></i>
                                        No matches found.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($matches as $match): ?>
                            <tr class="position-relative hover-bg-light">
                                <td class="ps-4 py-3 align-top">
                                    <div class="fw-bold text-primary">#<?php echo htmlspecialchars($match['match_id']); ?></div>
                                    <div class="text-muted small mt-1"><?php echo date('M j, Y', strtotime($match['created_at'])); ?></div>
                                </td>
                                <td class="py-3 align-top">
                                    <div class="fw-medium mb-1"><?php echo htmlspecialchars($match['exchange_title']); ?></div>
                                    <div class="text-muted small">ID: <?php echo htmlspecialchars($match['exchange_id'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="py-3 align-top">
                                    <div class="d-flex align-items-start mb-2">
                                        <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px;">
                                            <span class="text-muted small"><?php echo strtoupper(substr($match['proposer_name'] ?? 'P', 0, 1)); ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($match['proposer_name']); ?></div>
                                            <div class="text-muted small">Proposer</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start">
                                        <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px;">
                                            <span class="text-muted small"><?php echo strtoupper(substr($match['acceptor_name'] ?? 'R', 0, 1)); ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($match['acceptor_name']); ?></div>
                                            <div class="text-muted small">Recipient</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 align-top">
                                    <div class="d-flex align-items-start mb-2">
                                        <div class="me-2 text-success">
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($match['proposer_skill_name']); ?></div>
                                            <div class="text-muted small">Offered by Proposer</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start">
                                        <div class="me-2 text-primary">
                                            <i class="fas fa-arrow-left"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($match['acceptor_skill_name']); ?></div>
                                            <div class="text-muted small">Requested from Recipient</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-center align-middle">
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="badge rounded-pill bg-<?php 
                                            $status = strtolower($match['match_status']);
                                            switch($status) {
                                                case 'pending':
                                                    echo 'warning';
                                                    $icon = 'clock';
                                                    break;
                                                case 'accepted':
                                                    echo 'success';
                                                    $icon = 'check-circle';
                                                    break;
                                                case 'rejected':
                                                    echo 'danger';
                                                    $icon = 'times-circle';
                                                    break;
                                                case 'completed':
                                                    echo 'info';
                                                    $icon = 'check-double';
                                                    break;
                                                default:
                                                    echo 'secondary';
                                                    $icon = 'info-circle';
                                            }
                                        ?> text-uppercase d-inline-flex align-items-center" style="font-size: 0.65rem; padding: 0.35em 0.8em; letter-spacing: 0.5px;">
                                            <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                            <?php echo ucfirst(htmlspecialchars($match['match_status'])); ?>
                                        </span>
                                        <?php if ($status === 'accepted' || $status === 'completed'): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-light text-muted small fw-normal" data-bs-toggle="tooltip" title="Last updated">
                                                <i class="far fa-clock me-1"></i>
                                                <?php 
                                                    $updated = new DateTime($match['updated_at'] ?? $match['created_at']);
                                                    $now = new DateTime();
                                                    $interval = $now->diff($updated);
                                                    
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
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 align-middle">
                                    <div class="text-muted small">
                                        <div class="d-flex align-items-center">
                                            <i class="far fa-calendar-alt me-2 text-muted"></i>
                                            <div>
                                                <div><?php echo date('M j, Y', strtotime($match['created_at'])); ?></div>
                                                <div class="text-muted"><?php echo date('g:i A', strtotime($match['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
