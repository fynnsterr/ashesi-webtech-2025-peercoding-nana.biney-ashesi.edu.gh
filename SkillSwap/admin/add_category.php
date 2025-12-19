<?php
$pageTitle = 'Add New Category - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Get all parent categories for the dropdown
$parentCategories = $pdo->query("SELECT * FROM skill_categories WHERE parent_category_id IS NULL ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_category') {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO skill_categories (category_name, parent_category_id, description, icon_class, is_active) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['category_name']),
                !empty($_POST['parent_category_id']) ? $_POST['parent_category_id'] : null,
                trim($_POST['description'] ?? ''),
                trim($_POST['icon_class'] ?? ''),
                isset($_POST['is_active']) ? 1 : 0
            ]);
            
            $pdo->commit();
            $_SESSION['success'] = 'Category added successfully!';
            header('Location: skills.php?tab=categories');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error adding category: ' . $e->getMessage();
        }
    }
}
?>

<div class="auth-container" style="max-width: 600px; margin: 2rem auto; padding: 0 1rem;">
    <div class="card">
        <div class="card-header" style="background-color: var(--primary-color); color: white; padding: 1.5rem; border-radius: 0.5rem 0.5rem 0 0;">
            <h1 class="card-title" style="margin: 0; font-size: 1.5rem; font-weight: 600; text-align: center;">Add New Category</h1>
        </div>
        <div class="card-body" style="padding: 2rem;">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add_category">
                
                <div class="form-group mb-4">
                    <label for="category_name" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Category Name <span class="text-danger"></span></label>
                    <input type="text" class="form-control" id="category_name" name="category_name" required 
                           style="padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #d1d5db; width: 100%;">
                </div>
                
                <div class="form-group mb-4">
                    <label for="description" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              style="padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #d1d5db; width: 100%;"></textarea>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1rem; border-radius: 0.5rem; background-color: var(--primary-color); border: none;">
                        <i class="fas fa-save me-2"></i> Save Category
                    </button>
                </div>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="skills.php?tab=categories" style="color: var(--primary-color); font-weight: 500; text-decoration: none;">
                        <i class="fas fa-arrow-left me-2"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Live icon preview
document.getElementById('icon_class').addEventListener('input', function() {
    const iconClass = this.value.trim();
    const iconPreview = document.querySelector('#iconPreview i');
    if (iconClass) {
        // Clear all classes and add the new ones
        iconPreview.className = '';
        iconClass.split(' ').forEach(cls => {
            if (cls) iconPreview.classList.add(cls);
        });
    } else {
        iconPreview.className = '';
    }
});

// Form validation
(function () {
    'use strict';
    
    var forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>