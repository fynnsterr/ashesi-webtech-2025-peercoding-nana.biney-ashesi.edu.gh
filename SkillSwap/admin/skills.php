<?php
$pageTitle = 'Manage Skills - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'skills';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_skill') {
                $stmt = $pdo->prepare("INSERT INTO skills_catalog (skill_name, description, difficulty_level, parent_skill_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    trim($_POST['skill_name']),
                    trim($_POST['description'] ?? ''),
                    $_POST['difficulty_level'],
                    !empty($_POST['parent_skill_id']) ? $_POST['parent_skill_id'] : null
                ]);
                $_SESSION['success'] = 'Skill added successfully!';
            } 
            elseif ($_POST['action'] === 'update_skill' && !empty($_POST['skill_id'])) {
                $stmt = $pdo->prepare("UPDATE skills_catalog SET skill_name = ?, description = ?, difficulty_level = ?, parent_skill_id = ? WHERE skill_id = ?");
                $stmt->execute([
                    trim($_POST['skill_name']),
                    trim($_POST['description'] ?? ''),
                    $_POST['difficulty_level'],
                    !empty($_POST['parent_skill_id']) ? $_POST['parent_skill_id'] : null,
                    $_POST['skill_id']
                ]);
                $_SESSION['success'] = 'Skill updated successfully!';
            }
            elseif ($_POST['action'] === 'delete_skill' && !empty($_POST['skill_id'])) {
                $stmt = $pdo->prepare("DELETE FROM skills_catalog WHERE skill_id = ?");
                $stmt->execute([$_POST['skill_id']]);
                $_SESSION['success'] = 'Skill deleted successfully!';
            }
            elseif ($_POST['action'] === 'add_category') {
                $stmt = $pdo->prepare("INSERT INTO skill_categories (category_name, parent_category_id, description, icon_class, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    trim($_POST['category_name']),
                    !empty($_POST['parent_category_id']) ? $_POST['parent_category_id'] : null,
                    trim($_POST['description'] ?? ''),
                    trim($_POST['icon_class'] ?? ''),
                    isset($_POST['is_active']) ? 1 : 0
                ]);
                $_SESSION['success'] = 'Category added successfully!';
            }
            elseif ($_POST['action'] === 'update_category' && !empty($_POST['category_id'])) {
                $stmt = $pdo->prepare("UPDATE skill_categories SET category_name = ?, parent_category_id = ?, description = ?, icon_class = ?, is_active = ? WHERE category_id = ?");
                $stmt->execute([
                    trim($_POST['category_name']),
                    !empty($_POST['parent_category_id']) ? $_POST['parent_category_id'] : null,
                    trim($_POST['description'] ?? ''),
                    trim($_POST['icon_class'] ?? ''),
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['category_id']
                ]);
                $_SESSION['success'] = 'Category updated successfully!';
            }
            elseif ($_POST['action'] === 'delete_category' && !empty($_POST['category_id'])) {
                // Check if category has skills
                $check = $pdo->prepare("SELECT COUNT(*) FROM skills_catalog WHERE category_id = ?");
                $check->execute([$_POST['category_id']]);
                if ($check->fetchColumn() > 0) {
                    $_SESSION['error'] = 'Cannot delete category with associated skills. Please reassign or delete the skills first.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM skill_categories WHERE category_id = ?");
                    $stmt->execute([$_POST['category_id']]);
                    $_SESSION['success'] = 'Category deleted successfully!';
                }
            }
            
            // Redirect to prevent form resubmission
            header('Location: skills.php?tab=' . $tab);
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get all skills
$skills = $pdo->query("SELECT * FROM skills_catalog ORDER BY skill_name")->fetchAll(PDO::FETCH_ASSOC);

// Get all categories
$allCategories = $pdo->query("SELECT * FROM skill_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <h1 style="color: var(--primary-color);">Skills Management</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success']; 
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error']; 
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs Content -->
    <div class="tab-content" id="skillsTabsContent">
        <!-- Skills Tab -->
        <div class="tab-pane fade <?php echo $tab === 'skills' ? 'show active' : ''; ?>" id="skills" role="tabpanel">
            <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); width: 100%; margin: 0; margin-top: 1.5rem;">
                <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left; margin: 0;">
                            <i class="fas fa-tags mr-2" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                            Skills List
                        </h2>
                        <a href="add_skill.php" class="btn btn-outline-secondary">
                            <i class="fas fa-plus"></i> Add New Skill
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                        <table class="table align-middle mb-0 table-hover" style="width: 100%; min-width: 1000px;">
                            <thead style="background-color: #f9fafb;">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Name</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Description</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php if (empty($skills)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-info-circle fa-2x mb-2 d-block"></i>
                                                No skills found. <a href="add_skill.php" class="text-primary">Add a new skill</a> to get started.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($skills as $skill): ?>
                                        <tr class="position-relative hover-bg-light">
                                            <td class="ps-4 py-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="ms-3">
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($skill['skill_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <div class="text-muted small"><?php echo !empty($skill['description']) ? htmlspecialchars($skill['description']) : 'No description'; ?></div>
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

        <!-- Categories Tab -->
        <div class="tab-pane fade <?php echo $tab === 'categories' ? 'show active' : ''; ?>" id="categories" role="tabpanel">
            <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); width: 100%; margin: 0; margin-top: 1.5rem;">
                <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; text-align: left; margin: 0;">
                            <i class="fas fa-tags mr-2" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                            Categories List
                        </h2>
                        <a href="add_category.php" class="btn btn-outline-secondary">
                            <i class="fas fa-plus"></i> Add New Category
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                        <table class="table align-middle mb-0 table-hover" style="width: 100%; min-width: 1000px;">
                            <thead style="background-color: #f9fafb;">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Name</th>
                                    <th class="py-3 text-uppercase text-muted small fw-bold" style="letter-spacing: 0.5px; border-bottom: 1px solid #e5e7eb;">Description</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php if (empty($allCategories)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-info-circle fa-2x mb-2 d-block"></i>
                                                No categories found. Click 'Add New Category' to get started.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allCategories as $category): ?>
                                        <tr class="position-relative hover-bg-light">
                                            <td class="ps-4 py-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="ms-3">
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($category['category_name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <div class="text-muted small"><?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : 'No description'; ?></div>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Function to toggle more skills visibility
function toggleMoreSkills() {
    var moreSkills = document.getElementById('moreSkills');
    var showMoreBtn = document.getElementById('skillsShowMore');
    
    if (moreSkills.style.display === 'none') {
        moreSkills.style.display = 'table-row-group';
        showMoreBtn.querySelector('button').innerHTML = '<i class="fas fa-chevron-up me-1"></i> Show less';
    } else {
        moreSkills.style.display = 'none';
        showMoreBtn.querySelector('button').innerHTML = '<i class="fas fa-chevron-down me-1"></i> Show ' + (<?php echo count($skills); ?> - 10) + ' more skills';
    }
}

// Function to toggle more categories visibility
function toggleMoreCategories() {
    var moreCategories = document.getElementById('moreCategories');
    var showMoreBtn = document.getElementById('categoriesShowMore');
    
    if (moreCategories.style.display === 'none') {
        moreCategories.style.display = 'table-row-group';
        showMoreBtn.querySelector('button').innerHTML = '<i class="fas fa-chevron-up me-1"></i> Show less';
    } else {
        moreCategories.style.display = 'none';
        showMoreBtn.querySelector('button').innerHTML = '<i class="fas fa-chevron-down me-1"></i> Show ' + (<?php echo count($allCategories); ?> - 10) + ' more categories';
    }
}

// Icon preview
const iconInput = document.getElementById('iconClass');
const iconPreview = document.getElementById('iconPreview');

if (iconInput && iconPreview) {
    iconInput.addEventListener('input', function() {
        iconPreview.className = this.value;
    });
}

// Handle tab switching to maintain scroll position
document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function (event) {
        // Reset scroll position when switching tabs
        const tabPane = document.querySelector(event.target.getAttribute('data-bs-target'));
        const scrollableTable = tabPane.querySelector('.scrollable-table');
        if (scrollableTable) {
            scrollableTable.scrollTop = 0;
        }
    });
});
</script>

<style>
.scrollable-table {
    scrollbar-width: thin;
    scrollbar-color: #dee2e6 #f8f9fa;
}

.scrollable-table::-webkit-scrollbar {
    width: 6px;
}

.scrollable-table::-webkit-scrollbar-track {
    background: #f8f9fa;
}

.scrollable-table::-webkit-scrollbar-thumb {
    background-color: #dee2e6;
    border-radius: 3px;
}

.scrollable-table thead.sticky-top th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}
</style>