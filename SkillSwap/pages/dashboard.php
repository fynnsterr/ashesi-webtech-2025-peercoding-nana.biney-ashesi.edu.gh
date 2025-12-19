<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$user = getCurrentUser();
$userRole = getUserRole();

// Check for admin role BEFORE including header.php
if ($userRole === 'admin') {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$pageTitle = 'Dashboard';

// Now include header.php AFTER potential redirects
require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();

// Initialize stats with default values
$stats = [
    'total_exchanges' => 0,
    'pending_exchanges' => 0,
    'in_progress_exchanges' => 0,
    'completed_exchanges' => 0,
    'avg_rating' => 0,
    'total_skills' => 0,
    'total_matches' => 0
];

if ($userRole === 'both' || $userRole === 'teacher') {
    // Teacher/Provider dashboard stats (skills and matches)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT us.user_skill_id) as total_skills,
            COUNT(DISTINCT em.match_id) as total_matches,
            COALESCE(AVG(er.rating), 0) as avg_rating
        FROM users u
        LEFT JOIN user_skills us ON u.user_id = us.user_id AND us.can_teach = 1
        LEFT JOIN exchange_matches em ON us.skill_id = em.proposer_skill_id
        LEFT JOIN exchange_proposals ep ON em.exchange_id = ep.exchange_id
        LEFT JOIN exchange_reviews er ON (er.exchange_id = ep.exchange_id AND er.reviewee_id = u.user_id)
        WHERE u.user_id = ?
        GROUP BY u.user_id
    ");
    $stmt->execute([getUserId()]);
    $teacherStats = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($teacherStats) {
        $stats = array_merge($stats, $teacherStats);
    }
}

// Calculate Total Exchanges (Active + Completed) for ALL roles
// This includes exchanges where user is Proposer OR Match User
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_exchanges,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_exchanges,
        SUM(CASE WHEN status IN ('matched', 'in_progress') THEN 1 ELSE 0 END) as in_progress_exchanges,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_exchanges
    FROM exchange_proposals 
    WHERE (proposer_id = ? OR match_user_id = ?)
    AND status IN ('pending', 'matched', 'in_progress', 'completed')
");
$stmt->execute([getUserId(), getUserId()]);
$exchangeStats = $stmt->fetch(PDO::FETCH_ASSOC);

if ($exchangeStats) {
    // For specific cards
    $stats['pending_exchanges'] = $exchangeStats['pending_exchanges'];
    $stats['in_progress_exchanges'] = $exchangeStats['in_progress_exchanges'];
    $stats['completed_exchanges'] = $exchangeStats['completed_exchanges'];
    
    // Total should be Matched + In Progress + Completed (excluding pending/rejected/cancelled)
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM exchange_proposals 
        WHERE (proposer_id = ? OR match_user_id = ?)
        AND status IN ('matched', 'in_progress', 'completed')
    ");
    $stmtTotal->execute([getUserId(), getUserId()]);
    $stats['total_exchanges'] = $stmtTotal->fetchColumn();
    
    $stats['total_matches'] = $stats['total_exchanges'];
}

// Get recent exchanges where user is either proposer or acceptor
$stmt = $pdo->prepare("
    SELECT 
        ep.*,
        sc1.skill_name as skill_to_learn_name,
        sc2.skill_name as skill_to_teach_name,
        u1.full_name as match_user_name
    FROM exchange_proposals ep
    LEFT JOIN skills_catalog sc1 ON ep.skill_to_learn_id = sc1.skill_id
    LEFT JOIN skills_catalog sc2 ON ep.skill_to_teach_id = sc2.skill_id
    LEFT JOIN users u1 ON ep.match_user_id = u1.user_id
    WHERE ep.proposer_id = ? OR ep.match_user_id = ?
    ORDER BY ep.created_at DESC
    LIMIT 5
");
$stmt->execute([getUserId(), getUserId()]);
$recentExchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 style="color: var(--primary-color); font-size: 1.75rem; margin-bottom: 0.25rem;">
                Welcome, <?php echo e($user['full_name']); ?>!
            </h1>
        </div>
    </div>

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <?php if ($userRole === 'both' || $userRole === 'teacher'): ?>
            <a href="<?php echo BASE_URL; ?>/pages/skills.php" class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 12px -1px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
                <div class="card-body" style="padding: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: #dbeafe; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-chalkboard-teacher" style="color: #3b82f6; font-size: 1.5rem;"></i>
                        </div>
                        <div style="text-align: center; flex: 1;">
                            <h3 style="color: #1f2937; font-size: 1.75rem; font-weight: 700; margin: 0;"><?php echo (int)$stats['total_skills']; ?></h3>
                            <p style="color: #6B7280; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Skills to Teach</p>
                        </div>
                    </div>
                </div>
            </a>
            <a href="<?php echo BASE_URL; ?>/pages/exchanges.php" class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 12px -1px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
                <div class="card-body" style="padding: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; background: #d1fae5; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-handshake" style="color: #10b981; font-size: 1.5rem;"></i>
                        </div>
                        <div style="text-align: center; flex: 1;">
                            <h3 style="color: #1f2937; font-size: 1.75rem; font-weight: 700; margin: 0;"><?php echo (int)$stats['total_matches']; ?></h3>
                            <p style="color: #6B7280; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Skill Matches</p>
                        </div>
                    </div>
                </div>
            </a>
        <?php endif; ?>
        
        <a href="<?php echo BASE_URL; ?>/pages/exchanges.php" class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 12px -1px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
            <div class="card-body" style="padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 56px; height: 56px; background: #ede9fe; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-exchange-alt" style="color: #8b5cf6; font-size: 1.5rem;"></i>
                    </div>
                    <div style="text-align: center; flex: 1;">
                        <h3 style="color: #1f2937; font-size: 1.75rem; font-weight: 700; margin: 0;"><?php echo (int)$stats['total_exchanges']; ?></h3>
                        <p style="color: #6B7280; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Total Exchanges</p>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/pages/exchanges.php?status=pending" class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 12px -1px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
            <div class="card-body" style="padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 56px; height: 56px; background: #fef3c7; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-clock" style="color: #f59e0b; font-size: 1.5rem;"></i>
                    </div>
                    <div style="text-align: center; flex: 1;">
                        <h3 style="color: #1f2937; font-size: 1.75rem; font-weight: 700; margin: 0;"><?php echo (int)$stats['pending_exchanges']; ?></h3>
                        <p style="color: #6B7280; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Pending</p>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/pages/exchanges.php?status=in_progress" class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 12px -1px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
            <div class="card-body" style="padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 56px; height: 56px; background: #cffafe; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-check-circle" style="color: #06b6d4; font-size: 1.5rem;"></i>
                    </div>
                    <div style="text-align: center; flex: 1;">
                        <h3 style="color: #1f2937; font-size: 1.75rem; font-weight: 700; margin: 0;"><?php echo (int)$stats['in_progress_exchanges']; ?></h3>
                        <p style="color: #6B7280; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">In Progress</p>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="<?php echo BASE_URL; ?>/pages/profile.php" class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 12px -1px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)';">
            <div class="card-body" style="padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 56px; height: 56px; background: #fce7f3; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-star" style="color: #ec4899; font-size: 1.5rem;"></i>
                    </div>
                    <div style="text-align: center; flex: 1;">
                        <h3 style="color: #1f2937; font-size: 1.75rem; font-weight: 700; margin: 0;">
                            <?php echo number_format($stats['avg_rating'], 1); ?>
                            <span style="color: #f59e0b; font-size: 1rem;">★</span>
                        </h3>
                        <p style="color: #6B7280; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">Rating</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Content Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        <!-- Recent Exchanges -->
        <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px;">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                    <i class="fas fa-exchange-alt me-2"></i>Recent Exchanges
                </h2>
                <a href="<?php echo BASE_URL; ?>/pages/exchanges.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.9rem;">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentExchanges)): ?>
                    <div style="padding: 2rem; text-align: center; color: #6B7280;">
                        <i class="fas fa-exchange-alt fa-3x mb-3" style="opacity: 0.2;"></i>
                        <p class="mb-0">No exchanges yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentExchanges as $exchange): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/exchange-detail.php?id=<?php echo $exchange['exchange_id']; ?>" 
                           style="display: block; padding: 1rem 1.5rem; border-bottom: 1px solid #F3F4F6; text-decoration: none; transition: background 0.2s;"
                           onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500; color: #1f2937; margin-bottom: 0.25rem;"><?php echo e($exchange['title'] ?? 'Exchange #' . $exchange['exchange_id']); ?></div>
                                    <div style="font-size: 0.85rem; color: #6B7280;">
                                        <i class="fas fa-user-circle me-1"></i><?php echo e($exchange['match_user_name'] ?? 'Pending Match'); ?>
                                        <span style="margin: 0 0.5rem;">→</span>
                                        <span><?php echo e($exchange['skill_to_learn_name']); ?></span>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <?php 
                                        $statusColors = [
                                            'pending' => 'color: #f59e0b;',
                                            'proposed' => 'color: #6b7280;',
                                            'matched' => 'color: #3b82f6;',
                                            'in_progress' => 'color: #06b6d4;',
                                            'completed' => 'color: #10b981;',
                                            'cancelled' => 'color: #ef4444;'
                                        ];
                                        $statusStyle = $statusColors[strtolower($exchange['status'])] ?? 'color: #6b7280;';
                                    ?>
                                    <div style="<?php echo $statusStyle; ?> font-size: 0.85rem; font-weight: 500;"><?php echo ucfirst(str_replace('_', ' ', $exchange['status'])); ?></div>
                                    <div style="font-size: 0.75rem; color: #9CA3AF;"><?php echo date('M j', strtotime($exchange['created_at'])); ?></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px;">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                    Quick Actions
                </h2>
            </div>
            <div class="card-body" style="padding: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <?php if ($userRole === 'teacher'): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/skills.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-user-cog" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">Manage Skills</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/exchanges.php?filter=proposed" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-file-alt" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">Proposals</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/exchanges.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-exchange-alt" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">View Exchanges</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/messages.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-envelope" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">Messages</span>
                        </a>
                    <?php elseif ($userRole === 'learner'): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/index.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-search" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">Browse Skills</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/exchanges.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-exchange-alt" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">My Exchanges</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/messages.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-envelope" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">Messages</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/profile.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-user" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">My Profile</span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/pages/index.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-search" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">Browse Skills</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/skills.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-user-cog" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">Manage Skills</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/exchanges.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-exchange-alt" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">View Exchanges</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/messages.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem 0.75rem; background: #f9fafb; border-radius: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f9fafb'; this.style.transform='none'">
                            <i class="fas fa-envelope" style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.85rem; color: #374151; text-align: center;">Messages</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>