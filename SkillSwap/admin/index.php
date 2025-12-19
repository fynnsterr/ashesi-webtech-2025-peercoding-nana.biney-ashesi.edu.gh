<?php
$pageTitle = 'Admin Dashboard - SkillSwap';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Get statistics
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role IN ('learner', 'teacher', 'both')) as total_users,
        (SELECT COUNT(*) FROM skills_catalog WHERE is_active = 1) as active_skills,
        (SELECT COUNT(*) FROM exchange_proposals WHERE status = 'matched') as active_exchanges,
        (SELECT COUNT(*) FROM exchange_proposals WHERE status = 'pending') as pending_proposals,
        (SELECT COUNT(*) FROM exchange_matches WHERE match_status = 'proposed') as pending_matches,
        (SELECT COUNT(*) FROM exchange_proposals WHERE status = 'in_progress') as exchanges_in_progress,
        (SELECT COUNT(*) FROM exchange_proposals WHERE status = 'completed') as completed_exchanges,
        (SELECT COUNT(*) FROM user_skills us WHERE willing_to_learn = 1 AND NOT EXISTS (
            SELECT 1 FROM exchange_proposals ep 
            WHERE ep.status = 'completed' AND (
                (ep.proposer_id = us.user_id AND ep.skill_to_learn_id = us.skill_id)
                OR
                (ep.match_user_id = us.user_id AND ep.skill_to_teach_id = us.skill_id)
            )
        )) as active_requests
");
$stats = $stmt->fetch();

// Recent exchanges
$stmt = $pdo->query("
    SELECT 
        ep.*,
        u1.full_name as proposer_name,
        u2.full_name as match_user_name,
        s1.skill_name as skill_to_learn,
        s2.skill_name as skill_to_teach
    FROM exchange_proposals ep
    JOIN users u1 ON ep.proposer_id = u1.user_id
    LEFT JOIN users u2 ON ep.match_user_id = u2.user_id
    LEFT JOIN skills_catalog s1 ON ep.skill_to_learn_id = s1.skill_id
    LEFT JOIN skills_catalog s2 ON ep.skill_to_teach_id = s2.skill_id
    ORDER BY ep.created_at DESC
    LIMIT 10
");
$recentExchanges = $stmt->fetchAll();

// Recent skill requests
$stmt = $pdo->query("
    SELECT 
        us.*,
        u.username, u.full_name,
        sc.skill_name
    FROM user_skills us
    JOIN users u ON us.user_id = u.user_id
    JOIN skills_catalog sc ON us.skill_id = sc.skill_id
    WHERE us.willing_to_learn = 1
    AND NOT EXISTS (
        SELECT 1 FROM exchange_proposals ep 
        WHERE ep.status = 'completed' AND (
            (ep.proposer_id = us.user_id AND ep.skill_to_learn_id = us.skill_id)
            OR
            (ep.match_user_id = us.user_id AND ep.skill_to_teach_id = us.skill_id)
        )
    )
    ORDER BY us.created_at DESC
    LIMIT 5
");
$recentRequests = $stmt->fetchAll();
?>

<div class="dashboard-header">
    <h1 style="color: var(--primary-color);">Admin Dashboard</h1>
</div>

<div class="stats-grid">
    <!-- User Stats (Combined) -->
    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="stat-card hover-lift" style="text-decoration: none;">
        <div class="stat-icon" style="background: var(--primary-light); color: white;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$stats['total_users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </a>
    
    <a href="<?php echo BASE_URL; ?>/admin/skills.php" class="stat-card hover-lift" style="text-decoration: none;">
        <div class="stat-icon" style="background: #10B981; color: white;"> <!-- Emerald -->
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$stats['active_skills']; ?></div>
            <div class="stat-label">Skills</div>
        </div>
    </a>
    
    <!-- Exchange Stats -->
    <a href="<?php echo BASE_URL; ?>/admin/exchanges.php?status=matched" class="stat-card hover-lift" style="text-decoration: none;">
        <div class="stat-icon" style="background: #3B82F6; color: white;"> <!-- Blue -->
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$stats['active_exchanges']; ?></div>
            <div class="stat-label">Active Exchanges</div>
        </div>
    </a>
    
    <!-- Pending Items -->
    <a href="<?php echo BASE_URL; ?>/admin/exchanges.php?status=pending" class="stat-card hover-lift" style="text-decoration: none; cursor: pointer;">
        <div class="stat-icon" style="background: #FBBF24; color: white;"> <!-- Amber -->
            <i class="fas fa-handshake"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value" style="color: #D97706;"><?php echo (int)$stats['pending_proposals']; ?></div>
            <div class="stat-label">Pending Proposals</div>
        </div>
    </a>
    
    <a href="<?php echo BASE_URL; ?>/admin/exchanges.php?status=completed" class="stat-card hover-lift" style="text-decoration: none;">
        <div class="stat-icon" style="background: #8B5CF6; color: white;"> <!-- Violet -->
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$stats['completed_exchanges']; ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </a>
    
    <!-- Requests -->
    <a href="<?php echo BASE_URL; ?>/admin/requests.php" class="stat-card hover-lift" style="text-decoration: none;">
        <div class="stat-icon" style="background: #EC4899; color: white;"> <!-- Pink -->
            <i class="fas fa-bullhorn"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?php echo (int)$stats['active_requests']; ?></div>
            <div class="stat-label">Skill Requests</div>
        </div>
    </a>
</div>

<style>
/* Local overrides for Dashboard aesthetics */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

@media (min-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.stat-card {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid rgba(0,0,0,0.05);
}

.stat-card.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-right: 1.25rem;
    flex-shrink: 0;
}

.stat-content {
    flex-grow: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
    color: var(--gray-900);
}

.stat-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.025em;
}
</style>

<div class="dashboard-grid">
    <!-- Main Content Area (Recent Items) -->
    <div class="dashboard-main">
        <!-- Recent Exchanges -->
        <div class="card">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left;">
                        <i class="fas fa-exchange-alt mr-2" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                        Recent Exchanges
                    </h2>
                    <a href="<?php echo BASE_URL; ?>/admin/exchanges.php" style="font-size: 0.85rem; color: var(--primary-color); text-decoration: none; font-weight: 500;">View All</a>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentExchanges)): ?>
                    <div style="padding: 2rem; text-align: center; color: var(--gray-500);">
                        No exchange proposals yet.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($recentExchanges as $exchange): ?>
                            <a href="<?php echo BASE_URL; ?>/pages/exchange-detail.php?id=<?php echo $exchange['exchange_id']; ?>" class="list-item" style="text-decoration: none; color: inherit;">
                                <div style="flex-grow: 1;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                        <span class="font-medium text-dark"><?php echo e($exchange['title']); ?></span>
                                        <span class="badge badge-<?php 
                                            switch($exchange['status']) {
                                                case 'completed': echo 'success'; break;
                                                case 'in_progress': echo 'info'; break;
                                                case 'matched': echo 'primary'; break;
                                                case 'pending': echo 'warning'; break;
                                                default: echo 'secondary';
                                            }
                                        ?> text-xs">
                                            <?php echo ucfirst(str_replace('_', ' ', $exchange['status'])); ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--gray-600); display: flex; align-items: center; gap: 1rem;">
                                        <span><i class="fas fa-user-circle text-gray-400"></i> <?php echo e($exchange['proposer_name']); ?></span>
                                        <?php if ($exchange['match_user_name']): ?>
                                            <span><i class="fas fa-long-arrow-alt-right text-gray-400"></i></span>
                                            <span><?php echo e($exchange['match_user_name']); ?></span>
                                        <?php endif; ?>
                                        <span style="margin-left: auto; font-size: 0.75rem; color: var(--gray-400);">
                                            <?php echo date('M d', strtotime($exchange['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Skill Requests -->
        <div class="card mt-4">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left;">
                        <i class="fas fa-bullhorn mr-2" style="color: #EC4899; margin-right: 0.5rem;"></i>
                        Latest Requests
                    </h2>
                    <a href="<?php echo BASE_URL; ?>/admin/requests.php" style="font-size: 0.85rem; color: var(--primary-color); text-decoration: none; font-weight: 500;">View All</a>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentRequests)): ?>
                    <div style="padding: 2rem; text-align: center; color: var(--gray-500);">
                        No active requests.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($recentRequests as $request): ?>
                            <div class="list-item">
                                <div style="flex-grow: 1;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                        <span class="font-medium text-dark"><?php echo htmlspecialchars($request['skill_name']); ?></span>
                                        <span class="badge badge-info text-xs">
                                            <?php echo ucfirst($request['proficiency_level'] ?? 'Beginner'); ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--gray-600);">
                                        <span style="margin-right: 0.5rem;"><i class="fas fa-user-tag text-gray-400"></i> <?php echo htmlspecialchars($request['full_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Area (Actions only) -->
    <div class="dashboard-sidebar">
        <!-- Quick Actions -->
        <div class="card">
             <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6;">
                <h3 class="card-title" style="color: var(--primary-color); font-size: 1rem; text-align: left; margin: 0;">Quick Actions</h3>
            </div>
            <div class="card-body" style="padding: 1rem;">
                <div class="actions-grid">
                    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="action-btn">
                        <i class="fas fa-users text-primary"></i> 
                        <span>Manage Users</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/skills.php" class="action-btn">
                        <i class="fas fa-layer-group text-success"></i> 
                        <span>Manage Skills Catalog</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/exchanges.php" class="action-btn">
                        <i class="fas fa-exchange-alt text-info"></i> 
                        <span>View Exchanges</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/exchanges.php?status=pending" class="action-btn">
                        <i class="fas fa-handshake text-warning"></i> 
                        <span>Pending Matches</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/reviews.php" class="action-btn">
                        <i class="fas fa-star text-warning"></i> 
                        <span>Moderate Reviews</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/requests.php" class="action-btn">
                        <i class="fas fa-bullhorn text-danger"></i> 
                        <span>View Requests</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional Dashboard Styles */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 992px) {
    .dashboard-grid {
        grid-template-columns: 2fr 1fr;
    }
}

/* List Styles */
.list-group {
    display: flex;
    flex-direction: column;
}

.list-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #F3F4F6;
    transition: background-color 0.15s;
    display: flex;
    align-items: center;
}

.list-item:last-child {
    border-bottom: none;
}

.list-item:hover {
    background-color: #F9FAFB;
}

.font-medium { font-weight: 500; }
.text-dark { color: var(--gray-900); }
.text-xs { font-size: 0.75rem; }
.text-gray-400 { color: #9CA3AF; }

/* Status Widget */
.status-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #E5E7EB;
    font-size: 0.9rem;
    color: var(--gray-700);
}

.status-row:last-child { border-bottom: none; }

.status-badge {
    padding: 0.15rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
}
.bg-warning { background-color: #F59E0B; }
.bg-info { background-color: #3B82F6; }
.bg-success { background-color: #10B981; }
.bg-secondary { background-color: #6B7280; }

/* Action Buttons */
.actions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: #F9FAFB;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    text-decoration: none;
    color: var(--gray-700);
    transition: all 0.2s;
    font-size: 0.85rem;
    font-weight: 500;
}

.action-btn i {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
}

.action-btn:hover {
    background: white;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    transform: translateY(-2px);
    border-color: var(--primary-light);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>