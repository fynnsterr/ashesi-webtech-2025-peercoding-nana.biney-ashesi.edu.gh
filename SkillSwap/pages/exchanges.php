<?php
$pageTitle = 'My Exchanges';

// Add custom styles to be included in the header
$customCSS = "
<style>
.exchange-card {
    transition: all 0.2s;
}

.exchange-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    border-color: var(--primary-color);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem !important;
    }
    
    .row.g-4 {
        margin-left: -0.5rem !important;
        margin-right: -0.5rem !important;
    }
    
    .col-md-6, .col-lg-4 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
}
</style>";

// Include header after setting custom CSS
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDB();
$userId = getUserId();
$userRole = getUserRole();
$statusFilter = $_GET['status'] ?? '';

$message = '';
$error = '';

// Handle exchange actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        switch ($_POST['action']) {
            case 'accept_exchange':
                $exchangeId = (int)($_POST['exchange_id'] ?? 0);
                if ($exchangeId > 0) {
                    try {
                        $pdo->beginTransaction();
                        
                        // Verify proposal
                        $stmt = $pdo->prepare("SELECT * FROM exchange_proposals WHERE exchange_id = ? AND match_user_id = ? AND status = 'pending'");
                        $stmt->execute([$exchangeId, $userId]);
                        $proposal = $stmt->fetch();
                        
                        if ($proposal) {
                            // Create match record
                            $stmt = $pdo->prepare("
                                INSERT INTO exchange_matches (
                                    exchange_id, acceptor_id, proposer_skill_id, acceptor_skill_id, match_status, created_at
                                ) VALUES (?, ?, ?, ?, 'accepted', NOW())
                            ");
                            
                            // Link skills: Proposer offers skill_to_teach_id, Acceptor offers (Proposer's) skill_to_learn_id
                            $stmt->execute([
                                $exchangeId, 
                                $userId, 
                                $proposal['skill_to_teach_id'], 
                                $proposal['skill_to_learn_id']
                            ]);

                            // Update proposal status
                            $pdo->prepare("
                                UPDATE exchange_proposals 
                                SET status = 'matched', 
                                    updated_at = NOW()
                                WHERE exchange_id = ?
                            ")->execute([$exchangeId]);
                            
                            $pdo->commit();
                            $message = 'Exchange accepted! You can now coordinate learning sessions.';
                        } else {
                            throw new Exception("Invalid or inactive proposal.");
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Failed to accept exchange: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'reject_exchange':
                $exchangeId = (int)($_POST['exchange_id'] ?? 0);
                if ($exchangeId > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE exchange_proposals 
                        SET status = 'rejected', updated_at = NOW() 
                        WHERE exchange_id = ? AND match_user_id = ? AND status = 'pending'
                    ");
                    if ($stmt->execute([$exchangeId, $userId])) {
                        $message = 'Exchange proposal rejected.';
                    } else {
                        $error = 'Failed to reject proposal.';
                    }
                }
                break;
                
            case 'complete_exchange':
                $exchangeId = (int)($_POST['exchange_id'] ?? 0);
                if ($exchangeId > 0) {
                    // Mark exchange as completed
                    $pdo->beginTransaction();
                    try {
                        // Update the exchange status
                        $pdo->prepare("
                            UPDATE exchange_proposals 
                            SET status = 'completed', completed_at = NOW(), updated_at = NOW()
                            WHERE exchange_id = ? AND (proposer_id = ? OR match_user_id = ?)
                        ")->execute([$exchangeId, $userId, $userId]);
                        
                        // Update match status
                        $pdo->prepare("
                            UPDATE exchange_matches 
                            SET match_status = 'completed', updated_at = NOW()
                            WHERE exchange_id = ? AND (proposer_skill_id IN 
                                (SELECT skill_id FROM user_skills WHERE user_id = ?) 
                                OR acceptor_skill_id IN 
                                (SELECT skill_id FROM user_skills WHERE user_id = ?)
                            )
                        ")->execute([$exchangeId, $userId, $userId]);
                        
                        $pdo->commit();
                        $message = 'Exchange marked as completed';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Failed to complete exchange: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get user's exchanges based on their role
$exchanges = [];
$proposedExchanges = [];

// Get exchanges where user is the proposer
$stmt = $pdo->prepare("
    SELECT 
        ep.*, 
        sl.skill_name as skill_to_learn_name,
        st.skill_name as skill_to_teach_name,
        u.full_name as match_user_name,
        ms.skill_name as match_skill_name,
        (SELECT COUNT(*) FROM exchange_sessions WHERE exchange_id = ep.exchange_id) as session_count,
        (SELECT COUNT(*) FROM exchange_sessions WHERE exchange_id = ep.exchange_id AND session_status = 'completed') as completed_sessions
    FROM exchange_proposals ep
    JOIN skills_catalog sl ON ep.skill_to_learn_id = sl.skill_id
    JOIN skills_catalog st ON ep.skill_to_teach_id = st.skill_id
    LEFT JOIN users u ON ep.match_user_id = u.user_id
    LEFT JOIN skills_catalog ms ON ep.match_skill_id = ms.skill_id
    WHERE ep.proposer_id = ?
    ORDER BY ep.created_at DESC
");
$stmt->execute([$userId]);
$proposedExchanges = $stmt->fetchAll();

// Get exchanges where user is the acceptor (matched exchanges)
$stmt = $pdo->prepare("
    SELECT 
        ep.*, 
        sl.skill_name as skill_to_learn_name,
        st.skill_name as skill_to_teach_name,
        u.full_name as proposer_name,
        (SELECT COUNT(*) FROM exchange_sessions WHERE exchange_id = ep.exchange_id) as session_count,
        (SELECT COUNT(*) FROM exchange_sessions WHERE exchange_id = ep.exchange_id AND session_status = 'completed') as completed_sessions
    FROM exchange_proposals ep
    JOIN exchange_matches em ON ep.exchange_id = em.exchange_id
    JOIN skills_catalog sl ON ep.skill_to_learn_id = sl.skill_id
    JOIN skills_catalog st ON ep.skill_to_teach_id = st.skill_id
    JOIN users u ON ep.proposer_id = u.user_id
    WHERE em.acceptor_id = ? AND em.match_status = 'accepted'
    ORDER BY ep.updated_at DESC
");
$stmt->execute([$userId]);
$acceptedExchanges = $stmt->fetchAll();

// Get proposed exchanges to the user (pending acceptance)
$stmt = $pdo->prepare("
    SELECT 
        ep.exchange_id,
        ep.title,
        ep.description,
        ep.status,
        ep.created_at,
        u.full_name as proposer_name,
        st.skill_name as proposer_skill_name,
        sl.skill_name as my_skill_name
    FROM exchange_proposals ep
    JOIN users u ON ep.proposer_id = u.user_id
    JOIN skills_catalog st ON ep.skill_to_teach_id = st.skill_id
    JOIN skills_catalog sl ON ep.skill_to_learn_id = sl.skill_id
    WHERE ep.match_user_id = ? AND ep.status = 'pending'
    ORDER BY ep.created_at DESC
");
$stmt->execute([$userId]);
$pendingProposals = $stmt->fetchAll();
?>

<div class="container-fluid py-4 px-4">
    <div class="dashboard-header mb-4">
        <h1 class="h3 fw-bold mb-4" style="color: var(--primary-color);">
            <i class="fas fa-exchange-alt me-2"></i>My Exchanges
        </h1>
        <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Pending Exchange Proposals -->
    <?php if ((empty($statusFilter) || $statusFilter === 'pending') && !empty($pendingProposals)): ?>
        <div class="card mb-4 border-0" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                    <i class="fas fa-clock me-2"></i>Pending Exchange Proposals
                </h2>
            </div>
            <div class="card-body p-4">
                <?php foreach ($pendingProposals as $proposal): ?>
                    <div class="card mb-3 border-0" style="border-radius: 12px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h3 class="h5 fw-bold mb-1"><?php echo e($proposal['title']); ?></h3>
                                    <p class="text-muted mb-0 small">
                                        From: <span class="text-dark"><?php echo e($proposal['proposer_name']); ?></span>
                                    </p>
                                </div>
                                <span class="badge bg-warning text-dark" style="padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                    Pending
                                </span>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-arrow-down text-success"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small">You'll learn</div>
                                            <div class="fw-medium"><?php echo e($proposal['proposer_skill_name']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-arrow-up text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small">You'll teach</div>
                                            <div class="fw-medium"><?php echo e($proposal['my_skill_name']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center justify-content-between mt-4">
                                <div class="d-flex align-items-center">
                                    <i class="far fa-calendar-alt me-2" style="color: #6c757d; font-size: 1.1rem;"></i>
                                    <span class="text-muted small">
                                        Received <?php echo date('M d, Y', strtotime($proposal['created_at'])); ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="reject_exchange">
                                        <input type="hidden" name="exchange_id" value="<?php echo $proposal['exchange_id']; ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm" 
                                                style="border-radius: 6px; padding: 0.4rem 1rem; font-size: 0.85rem; font-weight: 500;">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="accept_exchange">
                                        <input type="hidden" name="exchange_id" value="<?php echo $proposal['exchange_id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm"
                                                style="border-radius: 6px; padding: 0.4rem 1rem; font-size: 0.85rem; font-weight: 500;">
                                            <i class="fas fa-check me-1"></i> Accept
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sent Proposals (Pending) -->
    <?php 
    $sentProposals = array_filter($proposedExchanges, fn($e) => $e['status'] === 'pending');
    if ((empty($statusFilter) || $statusFilter === 'pending') && !empty($sentProposals)): 
    ?>
        <div class="card mb-4 border-0" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                    <i class="fas fa-paper-plane me-2"></i>Sent Proposals
                </h2>
            </div>
            <div class="card-body p-4">
                <?php foreach ($sentProposals as $proposal): ?>
                    <div class="card mb-3 border-0" style="border-radius: 12px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h3 class="h5 fw-bold mb-1"><?php echo e($proposal['title']); ?></h3>
                                    <p class="text-muted mb-0 small">
                                        To: <span class="text-dark"><?php echo e($proposal['match_user_name'] ?? 'Unknown'); ?></span>
                                    </p>
                                </div>
                                <span class="badge bg-warning text-dark" style="padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                    Pending
                                </span>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-arrow-down text-success"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small">You want to learn</div>
                                            <div class="fw-medium"><?php echo e($proposal['skill_to_learn_name']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-arrow-up text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small">You offer to teach</div>
                                            <div class="fw-medium"><?php echo e($proposal['skill_to_teach_name']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center justify-content-between mt-4">
                                <div class="d-flex align-items-center">
                                    <i class="far fa-calendar-alt me-2" style="color: #6c757d; font-size: 1.1rem;"></i>
                                    <span class="text-muted small">
                                        Sent <?php echo date('M d, Y', strtotime($proposal['created_at'])); ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="<?php echo BASE_URL; ?>/pages/exchange-detail.php?id=<?php echo $proposal['exchange_id']; ?>" 
                                       class="btn btn-outline-primary btn-sm" 
                                       style="border-radius: 6px; padding: 0.4rem 1rem; font-size: 0.85rem; font-weight: 500;">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Active Exchanges -->
    <?php if (empty($statusFilter) || $statusFilter === 'in_progress'): ?>
    <div class="card mb-4 border-0" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                <i class="fas fa-sync-alt me-2"></i> Active Exchanges
            </h2>
            <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                <i class="fas fa-plus me-1"></i> New Exchange
            </a>
        </div>
        <div class="card-body p-4">
            <?php 
            $activeExchanges = array_merge(
                array_filter($proposedExchanges, fn($e) => in_array($e['status'], ['matched', 'in_progress'])),
                array_filter($acceptedExchanges, fn($e) => in_array($e['status'], ['matched', 'in_progress']))
            );
            
            if (!empty($activeExchanges)): 
                foreach ($activeExchanges as $exchange): 
                    $isInitiator = $exchange['proposer_id'] == $userId;
                    $otherUserName = $isInitiator ? 
                        ($exchange['match_user_name'] ?? 'Not matched yet') : 
                        $exchange['proposer_name'];
                    $progressPercentage = $exchange['session_count'] > 0 ? 
                        ($exchange['completed_sessions'] / $exchange['session_count']) * 100 : 0;
            ?>
                <div class="card mb-3 border-0" style="border-radius: 12px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3 class="h5 fw-bold mb-1"><?php echo e($exchange['title']); ?></h3>
                                <p class="text-muted mb-0 small">
                                    With: <span class="text-dark"><?php echo e($otherUserName); ?></span>
                                </p>
                            </div>
                            <span class="badge bg-primary text-white" style="padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                <?php echo ucfirst(str_replace('_', ' ', $exchange['status'])); ?>
                            </span>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <i class="fas fa-arrow-down text-success"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small">You're learning</div>
                                        <div class="fw-medium"><?php echo e($exchange['skill_to_learn_name']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <i class="fas fa-arrow-up text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small">You're teaching</div>
                                        <div class="fw-medium"><?php echo e($exchange['skill_to_teach_name']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($exchange['session_count'] > 0): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Progress</span>
                                    <span class="small fw-medium">
                                        <?php echo $exchange['completed_sessions']; ?>/<?php echo $exchange['session_count']; ?> sessions
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px; border-radius: 3px; background-color: #f0f2f5;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $progressPercentage; ?>%; border-radius: 3px;" 
                                         aria-valuenow="<?php echo $exchange['completed_sessions']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="<?php echo $exchange['session_count']; ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center justify-content-between mt-4">
                            <div class="d-flex align-items-center">
                                <i class="far fa-calendar-alt me-2" style="color: #6c757d; font-size: 1.1rem;"></i>
                                <span class="text-muted small">
                                    Started <?php echo date('M d, Y', strtotime($exchange['created_at'])); ?>
                                </span>
                            </div>   
                            
                            <div class="d-flex gap-2">
                                <a href="<?php echo BASE_URL; ?>/pages/exchange-detail.php?id=<?php echo $exchange['exchange_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" 
                                   style="border-radius: 6px; padding: 0.4rem 1rem; font-size: 0.85rem; font-weight: 500;">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                                
                                <?php if ($exchange['status'] === 'in_progress' && $exchange['user_id'] == $userId): ?>
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" 
                                            data-bs-target="#completeExchangeModal<?php echo $exchange['exchange_id']; ?>"
                                            style="border-radius: 6px; padding: 0.4rem 1rem; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center;">
                                        <i class="fas fa-check-circle me-1"></i> Complete Exchange
                                    </button>
                                    
                                    <!-- Complete Exchange Modal -->
                                    <div class="modal fade" id="completeExchangeModal<?php echo $exchange['exchange_id']; ?>" tabindex="-1" 
                                         aria-labelledby="completeExchangeModalLabel<?php echo $exchange['exchange_id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow" style="border-radius: 12px; overflow: hidden;">
                                                <div class="modal-header bg-primary text-white" style="border-bottom: none; padding: 1.25rem 1.5rem;">
                                                    <h5 class="modal-title d-flex align-items-center" id="completeExchangeModalLabel<?php echo $exchange['exchange_id']; ?>" style="font-size: 1.1rem; font-weight: 600;">
                                                        <i class="fas fa-check-circle me-2"></i> Complete Exchange
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" class="needs-validation" novalidate>
                                                    <div class="modal-body p-4">
                                                        <div class="alert alert-primary d-flex align-items-center mb-4" role="alert" style="background-color: #e6f2ff; border-left: 4px solid var(--primary-color);">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            <div class="small">
                                                                Please rate your experience and provide feedback to help us improve our service.
                                                            </div>
                                                        </div>
                                                        
                                                        <input type="hidden" name="exchange_id" value="<?php echo $exchange['exchange_id']; ?>">
                                                        <input type="hidden" name="action" value="complete_exchange">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        
                                                        <div class="mb-4">
                                                            <label for="rating<?php echo $exchange['exchange_id']; ?>" class="form-label fw-medium mb-2 d-block">
                                                                How would you rate this exchange? <span class="text-danger">*</span>
                                                            </label>
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="text-muted small">Not satisfied</span>
                                                                <span class="text-muted small">Very satisfied</span>
                                                            </div>
                                                            <div class="rating-input d-flex justify-content-between mb-2">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <input type="radio" id="star<?php echo $i; ?>_<?php echo $exchange['exchange_id']; ?>" name="rating" value="<?php echo $i; ?>" class="d-none" required>
                                                                    <label for="star<?php echo $i; ?>_<?php echo $exchange['exchange_id']; ?>" class="star-label" style="cursor: pointer; font-size: 1.75rem; color: #ddd; transition: color 0.2s;">
                                                                        <i class="fas fa-star"></i>
                                                                    </label>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <div class="invalid-feedback">
                                                                Please provide a rating.
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-4">
                                                            <label for="review<?php echo $exchange['exchange_id']; ?>" class="form-label fw-medium mb-2">
                                                                Share your experience (optional)
                                                            </label>
                                                            <textarea class="form-control" id="review<?php echo $exchange['exchange_id']; ?>" 
                                                                    name="review" rows="3" placeholder="What did you like about this exchange? Is there anything we can improve?"></textarea>
                                                        </div>
                                                        
                                                        <div class="form-check mb-4">
                                                            <input class="form-check-input" type="checkbox" id="recommend<?php echo $exchange['exchange_id']; ?>" name="would_recommend" value="1" checked>
                                                            <label class="form-check-label small" for="recommend<?php echo $exchange['exchange_id']; ?>">
                                                                I would recommend this exchange partner to others
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 6px; padding: 0.5rem 1.25rem;">
                                                            <i class="fas fa-times me-1"></i> Cancel
                                                        </button>
                                                        <button type="submit" class="btn btn-primary" style="border-radius: 6px; padding: 0.5rem 1.5rem;">
                                                            <i class="fas fa-check-circle me-1"></i> Submit Review & Complete
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <script>
                                    // Star rating interaction
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const stars = document.querySelectorAll('.star-label');
                                        stars.forEach(star => {
                                            star.addEventListener('click', function() {
                                                const radio = this.previousElementSibling;
                                                const rating = parseInt(radio.value);
                                                const parent = this.parentElement;
                                                const starsInGroup = parent.querySelectorAll('.star-label');
                                                
                                                starsInGroup.forEach((s, index) => {
                                                    if (index < rating) {
                                                        s.style.color = '#ffc107';
                                                    } else {
                                                        s.style.color = '#ddd';
                                                    }
                                                });
                                                
                                                // Trigger validation
                                                const form = this.closest('form');
                                                if (form) {
                                                    const ratingInput = form.querySelector('input[name="rating"]:checked');
                                                    if (ratingInput) {
                                                        ratingInput.setCustomValidity('');
                                                    }
                                                }
                                            });
                                        });
                                        
                                        // Form validation
                                        const form = document.querySelector('#completeExchangeModal<?php echo $exchange['exchange_id']; ?> form');
                                        if (form) {
                                            form.addEventListener('submit', function(event) {
                                                if (!form.checkValidity()) {
                                                    event.preventDefault();
                                                    event.stopPropagation();
                                                    
                                                    // Check if rating is selected
                                                    const ratingInput = form.querySelector('input[name="rating"]:checked');
                                                    if (!ratingInput) {
                                                        const firstStar = form.querySelector('.star-label');
                                                        if (firstStar) {
                                                            firstStar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                                        }
                                                    }
                                                }
                                                
                                                form.classList.add('was-validated');
                                            }, false);
                                        }
                                    });
                                    </script>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-5">
                    <div class="mb-3">
                        <i class="fas fa-exchange-alt fa-3x text-muted opacity-25"></i>
                    </div>
                    <h4 class="h5 text-muted mb-3">No Active Exchanges</h4>
                    <p class="text-muted mb-0">You don't have any active exchanges at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Completed Exchanges -->
    <?php if (empty($statusFilter) || $statusFilter === 'completed'): ?>
    <div class="card mb-4 border-0" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                <i class="fas fa-check-circle me-2"></i> Completed Exchanges
            </h2>
            <a href="/pages/index.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                <i class="fas fa-plus me-1"></i> New Exchange
            </a>
        </div>
        <div class="card-body p-4">
            <?php 
            $completedExchanges = array_merge(
                array_filter($proposedExchanges, fn($e) => $e['status'] === 'completed'),
                array_filter($acceptedExchanges, fn($e) => $e['status'] === 'completed')
            );
            
            if (!empty($completedExchanges)): 
                // Sort by completion date, newest first
                usort($completedExchanges, function($a, $b) {
                    $dateA = strtotime($a['completed_at'] ?? '1970-01-01');
                    $dateB = strtotime($b['completed_at'] ?? '1970-01-01');
                    return $dateB - $dateA;
                });
                
                foreach ($completedExchanges as $exchange): 
                    $isInitiator = $exchange['proposer_id'] == $userId;
                    $otherUserName = $isInitiator ? 
                        ($exchange['match_user_name'] ?? 'Unknown') : 
                        $exchange['proposer_name'];
                    $completionDate = $exchange['completed_at'] ? new DateTime($exchange['completed_at']) : null;
                    $daysAgo = $completionDate ? $completionDate->diff(new DateTime())->days : null;
            ?>
                <div class="card mb-3 border-0" style="border-radius: 12px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                    <div class="card-body p-0">
                        <!-- Profile and Exchange Info in one line -->
                        <div class="d-flex align-items-center justify-content-between p-4" style="border-bottom: 1px solid #f0f0f0;">
                            <div class="d-flex align-items-center">
                                <div class="position-relative me-3">
                                    <img src="<?php echo !empty($exchange['match_user_photo']) ? htmlspecialchars($exchange['match_user_photo']) : 'https://ui-avatars.com/api/?name=' . urlencode($otherUserName) . '&background=random'; ?>" 
                                        class="rounded-circle" 
                                        alt="<?php echo htmlspecialchars($otherUserName); ?>"
                                        style="width: 48px; height: 48px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                    <div class="position-absolute bottom-0 end-0 bg-success rounded-circle" style="width: 12px; height: 12px; border: 2px solid #fff;"></div>
                                </div>
                                <div>
                                    <h5 class="mb-1 fw-bold"><?php echo e($otherUserName); ?></h5>
                                    <span class="text-muted small d-flex align-items-center">
                                        <i class="fas fa-exchange-alt me-1"></i> Exchange completed
                                        <?php if ($completionDate): ?>
                                            <span class="ms-1" title="<?php echo $completionDate->format('F j, Y \a\t g:i A'); ?>">
                                                <?php 
                                                    if ($daysAgo === 0) echo 'Today';
                                                    elseif ($daysAgo === 1) echo 'Yesterday';
                                                    elseif ($daysAgo < 7) echo $daysAgo . 'd ago';
                                                    else echo $completionDate->format('M j, Y');
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-4 pb-4 pt-3">
                            <!-- You learned section in one line -->
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; min-width: 36px;">
                                    <i class="fas fa-arrow-down text-primary" style="font-size: 0.9rem;"></i>
                                </div>
                                <div class="d-flex align-items-center flex-wrap">
                                    <span class="text-muted me-2 small">You learned</span>
                                    <span class="fw-medium"><?php echo e($exchange['skill_to_learn_name']); ?></span>
                                </div>
                            </div>
                            
                            <!-- You taught section in one line -->
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; min-width: 36px;">
                                    <i class="fas fa-arrow-up text-success" style="font-size: 0.9rem;"></i>
                                </div>
                                <div class="d-flex align-items-center flex-wrap">
                                    <span class="text-muted me-2 small">You taught</span>
                                    <span class="fw-medium"><?php echo e($exchange['skill_to_teach_name']); ?></span>
                                </div>
                            </div>
                        
                            <?php if (!empty($exchange['review_received']) || !empty($exchange['review_given'])): ?>
                                <div class="mt-4 pt-3 border-top">
                                    <?php if (!empty($exchange['review_received'])): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small fw-medium text-muted">Feedback received</span>
                                                <?php if (isset($exchange['rating_received']) && $exchange['rating_received'] > 0): ?>
                                                    <div>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i > $exchange['rating_received'] ? '-o' : ''; ?> text-warning" style="font-size: 0.8rem;"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bg-light rounded p-3">
                                                <p class="mb-0 small">"<?php echo e($exchange['review_received']); ?>"</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($exchange['review_given'])): ?>
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small fw-medium text-muted">Your feedback</span>
                                                <?php if (isset($exchange['rating_given']) && $exchange['rating_given'] > 0): ?>
                                                    <div>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i > $exchange['rating_given'] ? '-o' : ''; ?> text-warning" style="font-size: 0.8rem;"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bg-light rounded p-3">
                                                <p class="mb-0 small">"<?php echo e($exchange['review_given']); ?>"</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="mt-3 pt-3 border-top">
                                    <div class="d-flex align-items-center">
                                        <i class="far fa-comment-dots me-2 text-muted" style="font-size: 0.9rem;"></i>
                                        <span class="text-muted small">
                                            No reviews yet
                                            <?php if (isset($exchange['can_review']) && $exchange['can_review']): ?>
                                                <a href="#" class="ms-2" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $exchange['exchange_id']; ?>">
                                                    Be the first to review
                                                </a>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Date and View button in one line -->
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                <div class="d-flex align-items-center">
                                    <i class="far fa-calendar-check me-2 text-muted" style="font-size: 0.9rem;"></i>
                                    <span class="text-muted small">
                                        Completed <?php echo $completionDate ? $completionDate->format('M j, Y') : 'N/A'; ?>
                                    </span>
                                </div>
                                
                                <a href="<?php echo BASE_URL; ?>/pages/exchange-detail.php?id=<?php echo $exchange['exchange_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" 
                                   style="border-radius: 6px; padding: 0.4rem 1rem; font-size: 0.85rem; font-weight: 500;">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                endforeach; 
            else: 
            ?>
                <div class="text-center p-5">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle" style="width: 80px; height: 80px;">
                            <i class="fas fa-check-circle fa-3x text-muted opacity-25"></i>
                        </div>
                    </div>
                    <h4 class="h5 text-muted mb-2">No Completed Exchanges</h4>
                    <p class="text-muted mb-0">Your completed exchanges will appear here once you finish an exchange.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div> <!-- Close container -->

<style>
.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-pending {
    background-color: #ffd700;
    color: #000;
}

.badge-accepted, .badge-confirmed {
    background-color: #4caf50;
    color: white;
}

.badge-rejected, .badge-cancelled {
    background-color: #f44336;
    color: white;
}

.badge-completed {
    background-color: #2196f3;
    color: white;
}

.badge-in-progress {
    background-color: #ff9800;
    color: white;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.dashboard-header h1 {
    margin: 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .exchange-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .btn {
        width: 100%;
        text-align: center;
        margin-bottom: 0.5rem;
    }
}
</style>
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
