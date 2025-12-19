<?php
$pageTitle = 'My Skills';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole('both');

$pdo = getDB();

// Get all available skills with skill_id as key and skill_name as value
$stmt = $pdo->query("SELECT skill_id, skill_name FROM skills_catalog ORDER BY skill_name");
$allSkills = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allSkills[$row['skill_id']] = $row['skill_name'];
}

// Get user's skills
$stmt = $pdo->prepare("
    SELECT us.*, sc.skill_name, sc.category, sc.difficulty_level
    FROM user_skills us
    JOIN skills_catalog sc ON us.skill_id = sc.skill_id
    WHERE us.user_id = ?
    ORDER BY sc.skill_name
");
$stmt->execute([getUserId()]);
$userSkills = $stmt->fetchAll();

// Get skill categories
$stmt = $pdo->query("SELECT DISTINCT category FROM skills_catalog WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get user's skill exchange history
$stmt = $pdo->prepare("
    SELECT 
        ep.exchange_id,
        ep.title,
        ep.status,
        ep.created_at,
        teach_skill.skill_name as teaching_skill,
        learn_skill.skill_name as learning_skill,
        u.full_name as partner_name
    FROM exchange_proposals ep
    LEFT JOIN skills_catalog teach_skill ON ep.skill_to_teach_id = teach_skill.skill_id
    LEFT JOIN skills_catalog learn_skill ON ep.skill_to_learn_id = learn_skill.skill_id
    LEFT JOIN users u ON (ep.match_user_id = u.user_id OR ep.proposer_id = u.user_id) AND u.user_id != ?
    WHERE (ep.proposer_id = ? OR ep.match_user_id = ?)
    ORDER BY ep.created_at DESC
    LIMIT 5
");
$stmt->execute([getUserId(), getUserId(), getUserId()]);
$exchanges = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_skill') {
        if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $skillId = (int)($_POST['skill_id'] ?? 0);
            $proficiency = $_POST['proficiency'] ?? 'intermediate';
            $skillType = $_POST['skill_type'] ?? ''; // 'learn' or 'teach'
            
            // Set flags based on skill type
            $canTeach = ($skillType === 'teach') ? 1 : 0;
            $willingToTeach = ($skillType === 'teach') ? 1 : 0;
            $willingToLearn = ($skillType === 'learn') ? 1 : 0;
            
            if ($skillId > 0) {
                // Check if skill already exists for user
                $stmt = $pdo->prepare("SELECT user_skill_id, can_teach, willing_to_teach, willing_to_learn FROM user_skills WHERE user_id = ? AND skill_id = ?");
                $stmt->execute([getUserId(), $skillId]);
                $existingSkill = $stmt->fetch();
                
                if ($existingSkill) {
                    // Update existing skill
                    $canTeach = $existingSkill['can_teach'] || (($skillType === 'teach') ? 1 : 0);
                    $willingToTeach = $existingSkill['willing_to_teach'] || (($skillType === 'teach') ? 1 : 0);
                    $willingToLearn = $existingSkill['willing_to_learn'] || (($skillType === 'learn') ? 1 : 0);
                    
                    $stmt = $pdo->prepare("
                        UPDATE user_skills 
                        SET proficiency_level = ?, 
                            can_teach = ?, 
                            willing_to_teach = ?, 
                            willing_to_learn = ?,
                            updated_at = NOW()
                        WHERE user_id = ? AND skill_id = ?
                    ");
                    $stmt->execute([
                        $proficiency,
                        $canTeach,
                        $willingToTeach,
                        $willingToLearn,
                        getUserId(),
                        $skillId
                    ]);
                    
                    $message = 'Skill updated successfully';
                } else {
                    // Add new skill
                    $stmt = $pdo->prepare("
                        INSERT INTO user_skills 
                        (user_id, skill_id, proficiency_level, can_teach, willing_to_teach, willing_to_learn, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        getUserId(),
                        $skillId,
                        $proficiency,
                        $canTeach,
                        $willingToTeach,
                        $willingToLearn
                    ]);
                    
                    $message = 'Skill added successfully';
                }
                
                header('Location: ' . BASE_URL . '/pages/profile.php?msg=' . urlencode($message));
                exit;
            } else {
                $error = 'Invalid skill selected';
            }
        } else {
            $error = 'Invalid request';
        }
    } elseif ($_POST['action'] === 'delete_skill') {
        if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $userSkillId = (int)($_POST['user_skill_id'] ?? 0);
            if ($userSkillId > 0) {
                $stmt = $pdo->prepare("DELETE FROM user_skills WHERE user_skill_id = ? AND user_id = ?");
                $stmt->execute([$userSkillId, getUserId()]);
                $message = 'Skill removed successfully';
                header('Location: ' . BASE_URL . '/pages/profile.php?msg=' . urlencode($message));
                exit;
            }
        }
        $error = 'Failed to remove skill';
    }
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <h1 style="color: var(--primary-color); font-size: 1.75rem; margin-bottom: 0;">
            My Skills Profile
        </h1>
        <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <input type="hidden" id="skillType" name="skill_type" value="">

    <?php if ($message): ?>
        <div class="alert alert-success" style="border-radius: 8px; margin-bottom: 1.5rem;"><?php echo e($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="border-radius: 8px; margin-bottom: 1.5rem;"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Skills I Can Teach -->
        <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px;">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                    <i class="fas fa-chalkboard-teacher me-2"></i>Skills I Can Teach
                </h2>
                <button onclick="openAddSkillModal('teach')" class="btn btn-outline-success btn-sm" style="border-radius: 8px;">
                    <i class="fas fa-plus me-1"></i>Add
                </button>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <?php 
                $teachableSkills = array_filter($userSkills, function($skill) {
                    return $skill['can_teach'] == 1 || $skill['willing_to_teach'] == 1;
                });
                
                if (empty($teachableSkills)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6B7280;">
                        <i class="fas fa-chalkboard-teacher fa-3x mb-3" style="opacity: 0.2;"></i>
                        <p class="mb-0">You haven't added any skills you can teach yet.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php foreach ($teachableSkills as $skill): ?>
                            <div style="background: #d1fae5; border: 1px solid #a7f3d0; border-radius: 20px; padding: 0.5rem 1rem; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 500; color: #065f46;"><?php echo e($skill['skill_name']); ?></span>
                                <span style="color: #047857; font-size: 0.8em;">(<?php echo e(ucfirst($skill['proficiency_level'])); ?>)</span>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this skill?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete_skill">
                                    <input type="hidden" name="user_skill_id" value="<?php echo $skill['user_skill_id']; ?>">
                                    <button type="submit" style="background: none; border: none; color: #047857; cursor: pointer; padding: 0.2rem; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center;" title="Remove skill"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Skills I Want to Learn -->
        <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px;">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                    <i class="fas fa-graduation-cap me-2"></i>Skills I Want to Learn
                </h2>
                <button onclick="openAddSkillModal('learn')" class="btn btn-outline-primary btn-sm" style="border-radius: 8px;">
                    <i class="fas fa-plus me-1"></i>Add
                </button>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <?php 
                $learnableSkills = array_filter($userSkills, function($skill) {
                    return $skill['willing_to_learn'] == 1;
                });
                
                if (empty($learnableSkills)): ?>
                    <div style="text-align: center; padding: 2rem; color: #6B7280;">
                        <i class="fas fa-graduation-cap fa-3x mb-3" style="opacity: 0.2;"></i>
                        <p class="mb-0">You haven't added any skills you want to learn yet.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php foreach ($learnableSkills as $skill): ?>
                            <div style="background: #dbeafe; border: 1px solid #bfdbfe; border-radius: 20px; padding: 0.5rem 1rem; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 500; color: #1e40af;"><?php echo e($skill['skill_name']); ?></span>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this skill?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete_skill">
                                    <input type="hidden" name="user_skill_id" value="<?php echo $skill['user_skill_id']; ?>">
                                    <button type="submit" style="background: none; border: none; color: #1e40af; cursor: pointer; padding: 0.2rem; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center;" title="Remove skill"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Skill Modal -->
<div id="addSkillModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div class="modal-content" style="max-width: 600px; margin: 2rem auto; background: white; padding: 2rem; border-radius: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 id="modalTitle">Add a Skill to <span id="skillTypeText">Learn/Teach</span></h2>
            <button onclick="document.getElementById('addSkillModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <form method="POST" id="addSkillForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="add_skill">
            <input type="hidden" name="skill_type" id="formSkillType" value="">
            
            <div class="form-group">
                <label for="skill_id">Skill</label>
                <select name="skill_id" id="skill_id" class="form-control" required>
                    <option value="">-- Select a skill --</option>
                    <?php foreach ($allSkills as $id => $skill): ?>
                        <option value="<?php echo $id; ?>"><?php echo e($skill); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="proficiencySection" class="form-group">
                <label for="proficiency">My Proficiency Level</label>
                <select name="proficiency" id="proficiency" class="form-control" required>
                    <option value="beginner">Beginner - Just starting out</option>
                    <option value="intermediate" selected>Intermediate - Some experience</option>
                    <option value="advanced">Advanced - Very comfortable</option>
                    <option value="expert">Expert - Can teach others</option>
                </select>
            </div>
            
            <div id="teachOptions" style="display: none;">
                <div class="form-check" style="margin: 1rem 0 2rem 0;">
                    <input type="checkbox" name="willing_to_teach" id="willing_to_teach" class="form-check-input" checked>
                    <label for="willing_to_teach" class="form-check-label">I'm available to teach this skill to others</label>
                </div>
            </div>
            
            <div id="learnOptions" style="display: none;">
                <div class="form-check" style="margin: 1rem 0 2rem 0;">
                    <input type="checkbox" name="willing_to_learn" id="willing_to_learn" class="form-check-input" checked>
                    <label for="willing_to_learn" class="form-check-label">I want to learn more about this skill</label>
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addSkillModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitButton">
                    <span id="submitButtonText">Add Skill</span>
                    <span id="submitButtonIcon" class="fas fa-plus"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.skills-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.skill-tag {
    background: #f0f4f8;
    border: 1px solid #d9e2ec;
    border-radius: 20px;
    padding: 0.4rem 0.8rem;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.skill-name {
    font-weight: 500;
}

.skill-level {
    color: #627d98;
    font-size: 0.85em;
}

.btn-icon {
    background: none;
    border: none;
    color: #9fb3c8;
    cursor: pointer;
    padding: 0.2rem;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: #e1e7ed;
    color: #486581;
}

.exchanges-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.exchange-item {
    border: 1px solid #e1e7ed;
    border-radius: 8px;
    padding: 1.25rem;
    transition: all 0.2s;
}

.exchange-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.exchange-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.exchange-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.exchange-details {
    margin-bottom: 1rem;
}

.exchange-details p {
    margin: 0.5rem 0;
    color: #486581;
}

.exchange-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #f0f4f8;
    padding-top: 1rem;
    margin-top: 1rem;
}

.text-muted {
    color: #9fb3c8;
    font-size: 0.9rem;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge-proposed { background-color: #e9ecef; color: #495057; }
.badge-accepted { background-color: #e3f2fd; color: #1565c0; }
.badge-in-progress { background-color: #e3f2fd; color: #1565c0; }
.badge-completed { background-color: #e8f5e9; color: #2e7d32; }
.badge-cancelled { background-color: #fce4ec; color: #c2185b; }
</style>

<script>
// Function to open the add skill modal with the specified type
function openAddSkillModal(type) {
    const modal = document.getElementById('addSkillModal');
    const skillType = document.getElementById('skillType');
    const skillTypeText = document.getElementById('skillTypeText');
    const proficiencySection = document.getElementById('proficiencySection');
    const teachOptions = document.getElementById('teachOptions');
    const learnOptions = document.getElementById('learnOptions');
    
    // Set the skill type in the hidden inputs
    skillType.value = type;
    document.getElementById('formSkillType').value = type;
    
    // Update the modal title
    skillTypeText.textContent = type === 'learn' ? 'Learn' : 'Teach';
    
    // Toggle options based on skill type
    const submitButton = document.getElementById('submitButton');
    const submitButtonText = document.getElementById('submitButtonText');
    const submitButtonIcon = document.getElementById('submitButtonIcon');
    
    if (type === 'learn') {
        proficiencySection.style.display = 'block';
        learnOptions.style.display = 'block';
        teachOptions.style.display = 'none';
        submitButtonText.textContent = 'Add to Learn';
        submitButtonIcon.className = 'fas fa-graduation-cap';
        submitButton.className = 'btn btn-primary';
    } else {
        proficiencySection.style.display = 'block';
        teachOptions.style.display = 'block';
        learnOptions.style.display = 'none';
        submitButtonText.textContent = 'Add to Teach';
        submitButtonIcon.className = 'fas fa-chalkboard-teacher';
        submitButton.className = 'btn btn-success';
    }
    
    // Show the modal
    modal.style.display = 'block';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('addSkillModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Filter skills by category
function filterSkills(category) {
    const skillSelect = document.getElementById('skill_id');
    const options = skillSelect.getElementsByTagName('option');
    
    for (let i = 0; i < options.length; i++) {
        const option = options[i];
        if (option.value === '') continue; // Skip the default option
        
        const skillCategory = option.getAttribute('data-category');
        if (category === 'all' || skillCategory === category) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    }
    
    // Reset to default option
    skillSelect.selectedIndex = 0;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

