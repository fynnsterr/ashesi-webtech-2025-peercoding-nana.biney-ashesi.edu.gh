<?php
$pageTitle = 'Propose Exchange - SkillSwap';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_URL . '/pages/login.php');
    exit();
}

$pdo = getDB();
$current_user_id = $_SESSION['user_id'];

// Get the skill ID and teacher ID from the URL
$skill_id = $_GET['skill_id'] ?? 0;
$teacher_id = $_GET['teacher_id'] ?? 0;

// Validate the skill and teacher
$stmt = $pdo->prepare("
    SELECT us.*, sc.skill_name, u.full_name, u.email
    FROM user_skills us
    JOIN skills_catalog sc ON us.skill_id = sc.skill_id
    JOIN users u ON us.user_id = u.user_id
    WHERE us.user_skill_id = ? AND us.user_id = ? AND us.willing_to_teach = 1
");
$stmt->execute([$skill_id, $teacher_id]);
$skill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$skill) {
    $_SESSION['error'] = 'Invalid skill or teacher selected.';
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit();
}

// Get current user's teachable skills for the exchange
$stmt = $pdo->prepare("
    SELECT us.user_skill_id, sc.skill_name, sc.skill_id

    FROM user_skills us
    JOIN skills_catalog sc ON us.skill_id = sc.skill_id
    WHERE us.user_id = ? AND us.willing_to_teach = 1 AND us.can_teach = 1
    ORDER BY sc.skill_name
");
$stmt->execute([$current_user_id]);
$user_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposed_skill_id = $_POST['proposed_skill_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');
    
    // Validate the proposed skill belongs to the current user
    $valid_skill = false;
    $skill_to_teach_id = 0;
    $skill_to_teach_name = '';
    foreach ($user_skills as $skill_item) {
        if ($skill_item['user_skill_id'] == $proposed_skill_id) {
            $valid_skill = true;
            $skill_to_teach_id = $skill_item['skill_id'];
            $skill_to_teach_name = $skill_item['skill_name'];
            break;
        }
    }
    
    if (!$valid_skill) {
        $error = 'Please select a valid skill to offer in exchange.';
    } elseif (empty($message)) {
        $error = 'Please enter a message to the teacher.';
    } else {
        // Create the exchange proposal
        try {
            $pdo->beginTransaction();
            
            $title = "Exchange: " . $skill['skill_name'] . " for " . $skill_to_teach_name;
            
            // Insert the exchange proposal
            $stmt = $pdo->prepare("
                INSERT INTO exchange_proposals (
                    proposer_id, 
                    match_user_id, 
                    skill_to_teach_id, 
                    skill_to_learn_id,
                    title,
                    description,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $current_user_id,
                $teacher_id,
                $skill_to_teach_id,
                $skill_id,
                $title,
                $message
            ]);
            
            $proposal_id = $pdo->lastInsertId();
            
            // TODO: Send notification to the teacher
            
            $pdo->commit();
            
            $_SESSION['success'] = 'Your exchange proposal has been sent successfully!';
            header('Location: ' . BASE_URL . '/pages/exchanges.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error creating exchange proposal: ' . $e->getMessage());
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <h1 style="color: var(--primary-color); font-size: 1.75rem; margin-bottom: 0;">
            Propose New Skill Exchange
        </h1>
        <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px;">
                <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                            <i class="fas fa-exchange-alt me-2"></i>Propose Skill Exchange
                        </h2>
                    </div>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 8px;">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <h5 style="color: #374151; margin-bottom: 0.75rem;">You're requesting to learn:</h5>
                        <div class="card" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1" style="color: #111827; font-weight: 600;"><?php echo htmlspecialchars($skill['skill_name']); ?></h6>
                                    <p class="mb-0" style="color: #6b7280; font-size: 0.875rem;">
                                        <i class="fas fa-chalkboard-teacher me-1"></i>Taught by <?php echo htmlspecialchars($skill['full_name']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="proposed_skill_id" class="form-label" style="color: #374151; font-weight: 500; margin-bottom: 0.5rem; display: block;">
                                I can teach in exchange:
                            </label>
                            <select class="form-select" id="proposed_skill_id" name="proposed_skill_id" required 
                                style="border-radius: 8px; border-color: #d1d5db; padding: 0.5rem 1rem; height: 44px;">
                                <option value="">-- Select a skill to offer --</option>
                                <?php foreach ($user_skills as $user_skill): ?>
                                    <option value="<?php echo $user_skill['user_skill_id']; ?>" 
                                        <?php echo (isset($_POST['proposed_skill_id']) && $_POST['proposed_skill_id'] == $user_skill['user_skill_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user_skill['skill_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="message" class="form-label" style="color: #374151; font-weight: 500; margin-bottom: 0.5rem; display: block;">
                                Message to <?php echo htmlspecialchars($skill['full_name']); ?>:
                            </label>
                            <textarea class="form-control" id="message" name="message" rows="5" required 
                                placeholder="Tell the teacher why you're interested in learning this skill and any specific details about your proposed exchange."
                                style="border-radius: 8px; border-color: #d1d5db; padding: 0.75rem 1rem;"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                                <i class="fas fa-arrow-left me-2"></i> Back to Home
                            </a>
                            <button type="submit" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                                <i class="fas fa-paper-plane me-2"></i> Send Exchange Proposal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.1);
    }
    
    .btn-primary {
        transition: all 0.2s ease-in-out;
    }
    
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    
    .btn-outline-secondary:hover {
        background-color: #f3f4f6;
        border-color: #9ca3af;
    }
    
    textarea {
        resize: vertical;
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
