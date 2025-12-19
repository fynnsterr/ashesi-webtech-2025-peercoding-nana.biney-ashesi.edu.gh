<?php
$pageTitle = 'Add New Skill - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Get all categories for the dropdown
$categories = $pdo->query("SELECT * FROM skill_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_skill') {
        try {
            $pdo->beginTransaction();
            
            // Get category name from the selected category_id
            $categoryName = '';
            if (!empty($_POST['category_id'])) {
                $catStmt = $pdo->prepare("SELECT category_name FROM skill_categories WHERE category_id = ?");
                $catStmt->execute([$_POST['category_id']]);
                $category = $catStmt->fetch(PDO::FETCH_ASSOC);
                $categoryName = $category ? $category['category_name'] : '';
            }
            
            $stmt = $pdo->prepare("INSERT INTO skills_catalog (skill_name, description, difficulty_level, parent_skill_id, category) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['skill_name']),
                trim($_POST['description'] ?? ''),
                $_POST['difficulty_level'],
                !empty($_POST['parent_skill_id']) ? $_POST['parent_skill_id'] : null,
                $categoryName
            ]);
            
            $pdo->commit();
            $_SESSION['success'] = 'Skill added successfully!';
            header('Location: skills.php');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error adding skill: ' . $e->getMessage();
        }
    }
}

// Get all skills for parent skill dropdown
$skills = $pdo->query("SELECT * FROM skills_catalog ORDER BY skill_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="auth-container" style="max-width: 600px; margin: 2rem auto; padding: 0 1rem;">
    <div class="card">
        <div class="card-header" style="background-color: var(--primary-color); color: white; padding: 1.5rem; border-radius: 0.5rem 0.5rem 0 0;">
            <h1 class="card-title" style="margin: 0; font-size: 1.5rem; font-weight: 600; text-align: center;">Add New Skill</h1>
        </div>
        <div class="card-body" style="padding: 2rem;">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add_skill">
                
                <div class="form-group mb-4">
                    <label for="category_id" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Category <span class="text-danger"></span></label>
                    <select class="form-control" id="category_id" name="category_id" required
                            style="padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #d1d5db; 
                                   width: 100%; font-size: 1.1rem; height: auto; min-height: 50px;
                                   background-color: #f8f9fa; transition: all 0.2s ease;">
                        <option value="" disabled selected>Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group mb-4">
                    <label for="skillName" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Skill Name <span class="text-danger"></span></label>
                    <input type="text" class="form-control" id="skillName" name="skill_name" required 
                           style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid #d1d5db;">
                </div>
                
                <div class="form-group mb-4">
                    <label for="skillDescription" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Description</label>
                    <textarea class="form-control" id="skillDescription" name="description" rows="3" 
                              style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid #d1d5db;"></textarea>
                </div>
                
                <div class="form-group mb-4">
                    <label for="difficultyLevel" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Difficulty Level <span class="text-danger"></span></label>
                    <select class="form-control" id="difficultyLevel" name="difficulty_level" required
                            style="padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #d1d5db; 
                                   width: 100%; font-size: 1.1rem; height: auto; min-height: 50px;
                                   background-color: #f8f9fa; transition: all 0.2s ease;">
                        <option value="" disabled selected>Select difficulty level</option>
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1rem; border-radius: 0.375rem; background-color: var(--primary-color); border: none;">
                        Save Skill
                    </button>
                </div>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="skills.php" style="color: var(--primary-color); font-weight: 500; text-decoration: none;">
                        <i class="fas fa-arrow-left me-2"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    
    var forms = document.querySelectorAll('.needs-validation')
    
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
