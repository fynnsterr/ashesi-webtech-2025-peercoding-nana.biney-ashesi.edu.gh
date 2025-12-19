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

$pageTitle = 'Add New Agreement - Admin';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$message = '';

// Handle form submission for adding new agreement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_agreement'])) {
    $exchange_id = filter_input(INPUT_POST, 'exchange_id', FILTER_SANITIZE_NUMBER_INT);
    $terms = filter_input(INPUT_POST, 'terms', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO exchange_agreements 
            (exchange_id, terms, agreement_status, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        if ($stmt->execute([$exchange_id, $terms, $status])) {
            $pdo->commit();
            $_SESSION['success'] = 'Agreement created successfully!';
            header('Location: agreements.php');
            exit();
        } else {
            throw new Exception('Failed to create agreement');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">Error creating agreement: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="auth-container" style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
    <div class="card">
        <div class="card-header" style="background-color: var(--primary-color); color: white; padding: 1.5rem; border-radius: 0.5rem 0.5rem 0 0;">
            <h1 class="card-title" style="margin: 0; font-size: 1.5rem; font-weight: 600; text-align: center;">Add New Agreement</h1>
        </div>
        <div class="card-body" style="padding: 2rem;">
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'danger' : 'success'; ?> mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="needs-validation" novalidate>
                <div class="form-group mb-4">
                    <label for="exchange_id" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Exchange <span class="text-danger"></span></label>
                    <select class="form-control" id="category_id" name="category_id" required
                            style="padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #d1d5db; 
                                   width: 100%; font-size: 1.1rem; height: auto; min-height: 50px;
                                   background-color: #f8f9fa; transition: all 0.2s ease;">
                        <option value="" selected disabled>Select exchange</option>
                        <?php
                        $exchanges = $pdo->query("
                            SELECT e.exchange_id, e.title, u1.username as proposer_name, u2.username as acceptor_name
                            FROM exchange_matches em
                            JOIN exchange_proposals e ON em.exchange_id = e.exchange_id
                            JOIN users u1 ON e.proposer_id = u1.user_id
                            JOIN users u2 ON em.acceptor_id = u2.user_id
                            WHERE NOT EXISTS (
                                SELECT 1 FROM exchange_agreements 
                                WHERE exchange_id = e.exchange_id
                            )
                        ")->fetchAll();
                        
                        foreach ($exchanges as $exchange):
                            echo "<option value='{$exchange['exchange_id']}'>";
                            echo htmlspecialchars("#{$exchange['exchange_id']} - {$exchange['title']} ({$exchange['proposer_name']} ↔ {$exchange['acceptor_name']})");
                            echo "</option>";
                        endforeach;
                        ?>
                    </select>
                </div>
                
                <div class="form-group mb-4">
                    <label for="terms" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Terms <span class="text-danger"></span></label>
                    <textarea class="form-control" id="terms" name="terms" rows="8" required 
                              style="padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #d1d5db; width: 100%;"
                              placeholder="Enter agreement terms here..."></textarea>
                </div>
                
                <div class="form-group mb-4">
                    <label for="status" class="form-label" style="font-size: 1.1rem; font-weight: 500;">Status <span class="text-danger"></span></label>
                    <select class="form-control" id="category_id" name="category_id" required
                            style="padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #d1d5db; 
                                   width: 100%; font-size: 1.1rem; height: auto; min-height: 50px;
                                   background-color: #f8f9fa; transition: all 0.2s ease;">
                        <option value="" selected disabled>Select status</option>
                        <option value="draft">Draft</option>
                        <option value="proposed">Proposed</option>
                        <option value="signed">Signed</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" name="add_agreement" class="btn btn-primary" 
                            style="width: 100%; padding: 0.75rem; font-size: 1rem; border-radius: 0.5rem; background-color: var(--primary-color); border: none;">
                        <i class="fas fa-save me-2"></i> Save Agreement
                    </button>
                </div>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="agreements.php" style="color: var(--primary-color); font-weight: 500; text-decoration: none;">
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

<?php include '../includes/footer.php'; ?>
