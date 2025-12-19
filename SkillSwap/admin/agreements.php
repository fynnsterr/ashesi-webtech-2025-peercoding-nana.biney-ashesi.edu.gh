<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
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

$pageTitle = 'Manage Exchange Agreements - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $agreement_id = filter_input(INPUT_POST, 'agreement_id', FILTER_SANITIZE_NUMBER_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE exchange_agreements SET agreement_status = ?, notes = ? WHERE agreement_id = ?");
        if ($stmt->execute([$status, $notes, $agreement_id])) {
            $pdo->commit();
            $_SESSION['success'] = 'Agreement status updated successfully!';
        } else {
            throw new Exception('Failed to update agreement status');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error updating agreement: ' . $e->getMessage();
    }
    
    header('Location: agreements.php');
    exit();
}

// Handle agreement deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_agreement'])) {
    $agreement_id = filter_input(INPUT_POST, 'agreement_id', FILTER_SANITIZE_NUMBER_INT);
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM exchange_agreements WHERE agreement_id = ?");
        if ($stmt->execute([$agreement_id])) {
            $pdo->commit();
            $_SESSION['success'] = 'Agreement deleted successfully!';
        } else {
            throw new Exception('Failed to delete agreement');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error deleting agreement: ' . $e->getMessage();
    }
    
    header('Location: agreements.php');
    exit();
}

// Get all agreements with related data
$stmt = $pdo->query("
    SELECT ea.*, 
           em.match_id,
           u1.username as proposer_name, 
           u2.username as acceptor_name,
           e.title as exchange_title
    FROM exchange_agreements ea
    JOIN exchange_matches em ON ea.exchange_id = em.exchange_id
    JOIN exchange_proposals e ON em.exchange_id = e.exchange_id
    JOIN users u1 ON e.proposer_id = u1.user_id
    JOIN users u2 ON em.acceptor_id = u2.user_id
    ORDER BY ea.created_at DESC
");
$agreements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0" style="font-size: 1.1rem; font-weight: 500;">Agreements List</h5>
            <a href="add_agreement.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add New
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Exchange</th>
                            <th>Parties</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agreements as $agreement): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="ms-3">
                                            <p class="fw-bold mb-1"><?= htmlspecialchars($agreement['exchange_title']) ?></p>
                                            <p class="text-muted mb-0 small">#<?= htmlspecialchars($agreement['agreement_id']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex flex-column">
                                            <span class="mb-1"><?= htmlspecialchars($agreement['proposer_name']) ?></span>
                                            <span class="text-muted small">Proposer</span>
                                        </div>
                                        <i class="bi bi-arrow-left-right mx-3 text-muted"></i>
                                        <div class="d-flex flex-column">
                                            <span class="mb-1"><?= htmlspecialchars($agreement['acceptor_name']) ?></span>
                                            <span class="text-muted small">Acceptor</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill bg-<?= 
                                        $agreement['agreement_status'] === 'signed' ? 'success' : 
                                        ($agreement['agreement_status'] === 'proposed' ? 'primary' : 
                                        ($agreement['agreement_status'] === 'cancelled' ? 'danger' : 'secondary')) 
                                    ?> px-3 py-2">
                                        <?= ucfirst(htmlspecialchars($agreement['agreement_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted"><?= date('M j, Y', strtotime($agreement['created_at'])) ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary view-agreement" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewAgreementModal"
                                                data-agreement='<?= htmlspecialchars(json_encode($agreement), ENT_QUOTES, 'UTF-8') ?>'
                                                title="View">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning edit-agreement" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editAgreementModal"
                                                data-id="<?= $agreement['agreement_id'] ?>"
                                                data-status="<?= $agreement['agreement_status'] ?>"
                                                data-notes="<?= htmlspecialchars($agreement['notes'] ?? '') ?>"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-agreement" 
                                                data-id="<?= $agreement['agreement_id'] ?>"
                                                data-title="Agreement #<?= $agreement['agreement_id'] ?>"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($agreements)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">No agreements found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
