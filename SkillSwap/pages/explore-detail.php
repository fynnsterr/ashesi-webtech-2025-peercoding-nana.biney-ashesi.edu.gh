<?php
$pageTitle = 'Skill Details';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$teacherId = (int)($_GET['user_id'] ?? 0);
$skillId = (int)($_GET['skill_id'] ?? 0);

if ($teacherId <= 0 || $skillId <= 0) {
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit;
}

$pdo = getDB();

// Get teacher and skill details
$stmt = $pdo->prepare("
    SELECT 
        u.user_id, u.full_name, u.location, u.profile_image, u.bio, u.phone,
        us.proficiency_level, us.experience_years, us.certification_url,
        sc.skill_name, sc.category, sc.description as skill_description,
        (SELECT COUNT(*) FROM exchange_reviews er WHERE er.reviewee_id = u.user_id AND er.skill_id = us.skill_id) as review_count,
        (SELECT COALESCE(AVG(er.rating), 0) FROM exchange_reviews er WHERE er.reviewee_id = u.user_id AND er.skill_id = us.skill_id) as avg_rating
    FROM users u
    JOIN user_skills us ON u.user_id = us.user_id
    JOIN skills_catalog sc ON us.skill_id = sc.skill_id
    WHERE u.user_id = ? AND us.skill_id = ? AND us.can_teach = 1
");
$stmt->execute([$teacherId, $skillId]);
$details = $stmt->fetch();

if (!$details) {
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit;
}

// Get reviews
$stmt = $pdo->prepare("
    SELECT er.*, u.full_name as reviewer_name, u.profile_image
    FROM exchange_reviews er
    JOIN users u ON er.reviewer_id = u.user_id
    WHERE er.reviewee_id = ? AND er.skill_id = ?
    ORDER BY er.created_at DESC
    LIMIT 10
");
$stmt->execute([$teacherId, $skillId]);
$reviews = $stmt->fetchAll();

// Get Availability
$stmt = $pdo->prepare("
    SELECT * FROM user_availability 
    WHERE user_id = ? 
    ORDER BY FIELD(day_of_week, 1, 2, 3, 4, 5, 6, 7)
");
$stmt->execute([$teacherId]);
$availability = $stmt->fetchAll();

$days = [
    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 
    5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
];
?>

<div style="max-width: 1000px; margin: 0 auto; padding: 2rem 1rem;">
    <div class="card" style="margin-bottom: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div>
                <?php if ($details['profile_image']): ?>
                    <img src="<?php echo BASE_URL; ?>/uploads/profiles/<?php echo e($details['profile_image']); ?>" 
                         alt="<?php echo e($details['full_name']); ?>" 
                         style="width: 100%; height: 400px; object-fit: cover; border-radius: 8px;">
                <?php else: ?>
                    <div style="width: 100%; height: 400px; background-color: var(--gray-light); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <span style="color: var(--gray-dark); font-size: 3rem;">👤</span>
                    </div>
                <?php endif; ?>
            </div>
            <div style="display: flex; flex-direction: column;">
                <div style="margin-bottom: 1rem;">
                    <span class="badge badge-primary" style="background-color: #e2e8f0; color: #4a5568; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem;">
                        <?php echo e($details['category'] ?? 'General'); ?>
                    </span>
                </div>
                
                <h1 style="margin-bottom: 0.5rem; font-size: 2rem; color: #2d3748;"><?php echo e($details['skill_name']); ?></h1>
                
                <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span class="rating" style="color: #f6ad55;">
                        <?php 
                        $rating = round($details['avg_rating']);
                        for ($i = 1; $i <= 5; $i++): 
                        ?>
                            <span style="font-size: 1.25rem;"><?php echo $i <= $rating ? '★' : '☆'; ?></span>
                        <?php endfor; ?>
                    </span>
                    <span style="color: #718096; font-size: 0.95rem;">(<?php echo number_format($details['avg_rating'], 1); ?> - <?php echo (int)$details['review_count']; ?> reviews)</span>
                </div>

                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <h3 style="margin-top: 0; font-size: 1.1rem; color: #4a5568; margin-bottom: 1rem;">Teacher Details</h3>
                    <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <strong style="margin-right: 0.5rem; font-size: 1.1rem;"><?php echo e($details['full_name']); ?></strong>
                    </div>
                    <p style="margin-bottom: 0.5rem; color: #4a5568;">📍 <?php echo e($details['location']); ?></p>
                    <p style="margin-bottom: 0.5rem; color: #4a5568;">🎓 Proficiency: <strong><?php echo ucfirst($details['proficiency_level']); ?></strong></p>
                    <?php if($details['experience_years'] > 0): ?>
                        <p style="margin-bottom: 0; color: #4a5568;">⏳ Experience: <?php echo $details['experience_years']; ?> years</p>
                    <?php endif; ?>
                </div>

                <div style="margin-top: auto;">
                    <?php if (isLoggedIn()): ?>
                        <?php if (getUserId() != $teacherId): ?>
                            <a href="#proposal-form" class="btn btn-primary" style="width: 100%; text-align: center; padding: 1rem; font-size: 1.1rem;">Request to Learn</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/pages/login.php" class="btn btn-primary" style="width: 100%; text-align: center; padding: 1rem;">Login to Connect</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card" style="margin-bottom: 2rem; padding: 2rem;">
        <h2 style="margin-bottom: 1.5rem; font-size: 1.5rem; border-bottom: 2px solid #edf2f7; padding-bottom: 0.5rem;">About the Teacher</h2>
        <p style="white-space: pre-wrap; line-height: 1.8; color: #4a5568;"><?php echo e($details['bio'] ?: 'No bio available.'); ?></p>
        
        <?php if ($availability): ?>
            <div style="margin-top: 2rem;">
                <h3 style="margin-bottom: 1rem; font-size: 1.25rem;">Availability</h3>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php foreach ($availability as $slot): ?>
                        <div style="background: #edf2f7; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.9rem; color: #4a5568;">
                            <strong><?php echo $days[$slot['day_of_week']] ?? 'Unknown'; ?>:</strong> 
                            <?php echo date('g:i A', strtotime($slot['start_time'])); ?> - <?php echo date('g:i A', strtotime($slot['end_time'])); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (isLoggedIn() && getUserId() != $teacherId): ?>
        <div class="card" id="proposal-form" style="margin-bottom: 2rem; padding: 2rem;">
            <h2 style="margin-bottom: 1.5rem;">Propose an Exchange</h2>
            <form method="POST" action="<?php echo BASE_URL; ?>/api/proposals.php?mode=create" id="proposalForm">
                <input type="hidden" name="match_user_id" value="<?php echo $teacherId; ?>">
                <input type="hidden" name="skill_to_learn_id" value="<?php echo $skillId; ?>">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="title" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Proposal Title</label>
                    <input type="text" id="title" name="title" class="form-control" required 
                           placeholder="e.g. I can teach you Photoshop in exchange for Baking lessons"
                           style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box; font-size: 1rem; line-height: 1.5; min-height: 48px;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="skill_to_teach_id" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">What can you teach in return?</label>
                    <select name="skill_to_teach_id" id="skill_to_teach_id" class="form-control" required
                            style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box; font-size: 1rem; line-height: 1.5; min-height: 48px;">
                        <option value="">Select a skill...</option>
                        <?php
                        // Fetch current user's teachable skills
                        $mySkillsStmt = $pdo->prepare("
                            SELECT us.skill_id, sc.skill_name 
                            FROM user_skills us 
                            JOIN skills_catalog sc ON us.skill_id = sc.skill_id
                            WHERE us.user_id = ? AND us.can_teach = 1
                        ");
                        $mySkillsStmt->execute([getUserId()]);
                        $mySkills = $mySkillsStmt->fetchAll();
                        foreach ($mySkills as $skill): ?>
                            <option value="<?php echo $skill['skill_id']; ?>"><?php echo e($skill['skill_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="description" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Message / Details</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required
                              style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box; font-size: 1rem; line-height: 1.5;"></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">Send Proposal</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="card" style="padding: 2rem;">
        <h2 style="margin-bottom: 1.5rem;">Reviews (<?php echo (int)$details['review_count']; ?>)</h2>
        
        <?php if (empty($reviews)): ?>
            <p style="color: var(--gray-dark); font-style: italic;">No reviews yet. Be the first to share your experience!</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div style="padding: 1.5rem 0; border-bottom: 1px solid #edf2f7;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                        <div style="display: flex; align-items: center;">
                            <img src="<?php echo $review['profile_image'] ? BASE_URL . '/uploads/profiles/' . e($review['profile_image']) : BASE_URL . '/assets/images/default-avatar.png'; ?>" 
                                 style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; object-fit: cover;">
                            <div>
                                <strong style="display: block;"><?php echo e($review['reviewer_name']); ?></strong>
                                <span style="font-size: 0.85rem; color: #718096;"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="rating" style="color: #f6ad55;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="font-size: 1rem;"><?php echo $i <= $review['rating'] ? '★' : '☆'; ?></span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if ($review['title']): ?>
                        <strong style="display: block; margin-bottom: 0.5rem;"><?php echo e($review['title']); ?></strong>
                    <?php endif; ?>
                    <?php if ($review['comment']): ?>
                        <p style="color: #4a5568; line-height: 1.6; margin: 0;"><?php echo e($review['comment']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('proposalForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerText;
    submitBtn.disabled = true;
    submitBtn.innerText = 'Sending...';
    
    try {
        const formData = new FormData(this);
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Proposal sent successfully!');
            window.location.href = '<?php echo BASE_URL; ?>/pages/exchanges.php';
        } else {
            alert('Error: ' + (result.message || 'Unknown error occurred'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while sending the proposal.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = originalText;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
