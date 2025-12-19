<?php
$pageTitle = 'Exchange Details';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rating_helper.php';

requireLogin();

$exchangeId = (int)($_GET['id'] ?? 0);
$showReview = isset($_GET['review']);

if ($exchangeId <= 0) {
    header('Location: ' . BASE_URL . '/pages/exchanges.php');
    exit;
}

$pdo = getDB();
$userId = getUserId();
$userRole = getUserRole(); // 'both', 'customer', 'provider', 'admin'

// Get exchange details
// Join with users and skills to get all names
$stmt = $pdo->prepare("
    SELECT 
        ep.*,
        em.proposer_confirmed, em.acceptor_confirmed,
        u_prop.full_name as proposer_name, u_prop.phone as proposer_phone, u_prop.location as proposer_location, u_prop.profile_image as proposer_image, u_prop.email as proposer_email,
        u_match.full_name as match_name, u_match.phone as match_phone, u_match.location as match_location, u_match.profile_image as match_image, u_match.email as match_email,
        s_learn.skill_name as skill_to_learn_name,
        s_teach.skill_name as skill_to_teach_name
    FROM exchange_proposals ep
    JOIN exchange_matches em ON ep.exchange_id = em.exchange_id
    JOIN users u_prop ON ep.proposer_id = u_prop.user_id
    LEFT JOIN users u_match ON ep.match_user_id = u_match.user_id
    JOIN skills_catalog s_learn ON ep.skill_to_learn_id = s_learn.skill_id
    JOIN skills_catalog s_teach ON ep.skill_to_teach_id = s_teach.skill_id
    WHERE ep.exchange_id = ?
");
$stmt->execute([$exchangeId]);
$exchange = $stmt->fetch();

if (!$exchange) {
    header('Location: ' . BASE_URL . '/pages/exchanges.php');
    exit;
}

// Check authorization
// User must be admin OR the proposer OR the matched user
$isProposer = ($userId == $exchange['proposer_id']);
$isMatchUser = ($userId == $exchange['match_user_id']);
$authorized = ($userRole === 'admin') || $isProposer || $isMatchUser;

if (!$authorized) {
    header('Location: ' . BASE_URL . '/pages/exchanges.php');
    exit;
}

// Determine the "Other" person relative to the logged-in user
if ($isProposer) {
    $partnerId = $exchange['match_user_id'];
    $otherPerson = [
        'name' => $exchange['match_name'] ?? 'Pending Match',
        'location' => $exchange['match_location'],
        'phone' => $exchange['match_phone'],
        'email' => $exchange['match_email'],
        'image' => $exchange['match_image']
    ];
} else {
    $partnerId = $exchange['proposer_id'];
    $otherPerson = [
        'name' => $exchange['proposer_name'],
        'location' => $exchange['proposer_location'],
        'phone' => $exchange['proposer_phone'],
        'email' => $exchange['proposer_email'],
        'image' => $exchange['proposer_image']
    ];
}

// Check if review exists from current user
$stmt = $pdo->prepare("SELECT * FROM exchange_reviews WHERE exchange_id = ? AND reviewer_id = ?");
$stmt->execute([$exchangeId, $userId]);
$existingReview = $stmt->fetch();

// For admin view: Get all reviews for this exchange
$allReviews = [];
if ($userRole === 'admin') {
    $stmt = $pdo->prepare("
        SELECT er.*, 
               reviewer.full_name as reviewer_name,
               reviewee.full_name as reviewee_name,
               sc.skill_name
        FROM exchange_reviews er
        JOIN users reviewer ON er.reviewer_id = reviewer.user_id
        JOIN users reviewee ON er.reviewee_id = reviewee.user_id
        LEFT JOIN skills_catalog sc ON er.skill_id = sc.skill_id
        WHERE er.exchange_id = ?
        ORDER BY er.created_at DESC
    ");
    $stmt->execute([$exchangeId]);
    $allReviews = $stmt->fetchAll();
}

$message = '';
$error = '';

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $rating = (int)($_POST['rating'] ?? 0);
        $reviewText = trim($_POST['review_text'] ?? '');
        $reviewTitle = trim($_POST['review_title'] ?? 'Exchange Review');
        
        if ($rating >= 1 && $rating <= 5 && $exchange['status'] === 'completed' && !$existingReview) {
            try {
                // Determine who is being reviewed and for which skill
                if ($isProposer) {
                    $revieweeId = $exchange['match_user_id'];
                    // Matches teach 'skill_to_learn' to Proposer
                    $skillId = $exchange['skill_to_learn_id']; 
                } else {
                    $revieweeId = $exchange['proposer_id'];
                    // Proposer teaches 'skill_to_teach' to MatchUser
                    $skillId = $exchange['skill_to_teach_id'];
                }

                $stmt = $pdo->prepare("
                    INSERT INTO exchange_reviews (exchange_id, reviewer_id, reviewee_id, skill_id, rating, title, comment, review_type, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'as_teacher', NOW(), NOW())
                ");
                $stmt->execute([
                    $exchangeId,
                    $userId,
                    $revieweeId,
                    $skillId,
                    $rating,
                    $reviewTitle,
                    $reviewText
                ]);
                
                // Update user's average rating
                updateUserAvgRating($pdo, $revieweeId);

                $message = 'Review submitted successfully!';
                $showReview = false;
                
                // Refresh to avoid resubmission
                echo "<script>window.location.href = '" . BASE_URL . "/pages/exchange-detail.php?id=" . $exchangeId . "';</script>";
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to submit review: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Invalid security token';
    }
}
?>

<div style="max-width: 900px; margin: 0 auto; padding: 2rem 1rem;">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 2rem;">
        <?php if ($userRole === 'admin'): ?>
            <?php if (isset($_GET['source']) && $_GET['source'] === 'admin_exchanges'): ?>
                <a href="<?php echo BASE_URL; ?>/admin/exchanges.php" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                    ← Back to Exchanges
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                    ← Back to Dashboard
                </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/pages/exchanges.php" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                ← Back to My Exchanges
            </a>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo e($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 2rem; border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        <div class="card-header" style="padding: 1.5rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center;">
            <h1 style="margin: 0; font-size: 1.5rem; color: #2d3748;">Exchange Details</h1>
            <span class="badge badge-<?php echo str_replace('_', '-', $exchange['status']); ?>" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                <?php echo ucfirst(str_replace('_', ' ', $exchange['status'])); ?>
            </span>
        </div>
        
        <div class="card-body" style="padding: 2rem;">
            <div style="margin-bottom: 2rem;">
                <h2 style="font-size: 1.25rem; color: #4a5568; margin-bottom: 1rem;"><?php echo e($exchange['title']); ?></h2>
                <p style="color: #718096; line-height: 1.6;"><?php echo e($exchange['description']); ?></p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; background: #f8fafc; padding: 1.5rem; border-radius: 8px;">
                <!-- Left Side: What You Give -->
                <div>
                    <h3 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.5px; color: #718096; margin-bottom: 1rem;">You Teach</h3>
                    <div style="display: flex; align-items: center;">
                        <span style="font-weight: 600; font-size: 1.1rem; color: #2d3748;">
                            <?php echo $isProposer ? e($exchange['skill_to_teach_name']) : e($exchange['skill_to_learn_name']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Right Side: What You Get -->
                <div>
                    <h3 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.5px; color: #718096; margin-bottom: 1rem;">You Learn</h3>
                    <div style="display: flex; align-items: center;">
                        <span style="font-weight: 600; font-size: 1.1rem; color: #2d3748;">
                            <?php echo $isProposer ? e($exchange['skill_to_learn_name']) : e($exchange['skill_to_teach_name']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Exchange Info -->
                <div>
                    <h3 style="margin-bottom: 1rem; border-bottom: 2px solid #edf2f7; padding-bottom: 0.5rem;">Logistics</h3>
                    <p><strong>Created:</strong> <?php echo date('F d, Y', strtotime($exchange['created_at'])); ?></p>
                    <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $exchange['exchange_type'])); ?></p>
                    <?php if ($exchange['completed_at']): ?>
                        <p><strong>Completed:</strong> <?php echo date('F d, Y', strtotime($exchange['completed_at'])); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Partner Info -->
                <div>
                    <h3 style="margin-bottom: 1rem; border-bottom: 2px solid #edf2f7; padding-bottom: 0.5rem;">Partner Info</h3>
                    <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                        <?php if ($otherPerson['image']): ?>
                            <img src="<?php echo BASE_URL; ?>/uploads/profiles/<?php echo e($otherPerson['image']); ?>" 
                                 style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 1rem;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                👤
                            </div>
                        <?php endif; ?>
                        <div>
                            <p style="margin: 0; font-weight: 600;"><?php echo e($otherPerson['name']); ?></p>
                            <p style="margin: 0; font-size: 0.9rem; color: #718096;"><?php echo e($otherPerson['location']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($exchange['status'] === 'matched' || $exchange['status'] === 'in_progress' || $exchange['status'] === 'completed'): ?>
                        <div style="background: #f0fff4; padding: 1rem; border-radius: 6px; border: 1px solid #c6f6d5;">
                            <p style="margin-bottom: 0.5rem;"><strong>Email:</strong> <?php echo e($otherPerson['email']); ?></p>
                            <p style="margin: 0;"><strong>Phone:</strong> <?php echo e($otherPerson['phone'] ?? 'Not shared'); ?></p>
                        </div>
                    <?php else: ?>
                        <p style="font-style: italic; color: #718096;">Contact info available after acceptance.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #edf2f7;">
                <?php if ($userRole === 'admin'): ?>
                    <!-- Admin View: Show Reviews -->
                    <h3 style="margin-bottom: 1rem; font-size: 1.1rem; color: #2d3748;">Exchange Reviews</h3>
                    <?php if (empty($allReviews)): ?>
                        <p style="color: #718096; font-style: italic;">No reviews have been submitted for this exchange yet.</p>
                    <?php else: ?>
                        <div style="display: grid; gap: 1rem;">
                            <?php foreach ($allReviews as $review): ?>
                                <div style="background: #f8fafc; border-radius: 8px; padding: 1.25rem; border: 1px solid #e2e8f0;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                        <div>
                                            <span style="font-weight: 600; color: #2d3748;"><?php echo e($review['reviewer_name']); ?></span>
                                            <span style="color: #718096;"> reviewed </span>
                                            <span style="font-weight: 600; color: #2d3748;"><?php echo e($review['reviewee_name']); ?></span>
                                        </div>
                                        <span style="font-size: 0.8rem; color: #a0aec0;"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                    </div>
                                    <div style="margin-bottom: 0.5rem;">
                                        <span style="font-size: 1.25rem; color: #f6ad55;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php echo $i <= $review['rating'] ? '★' : '☆'; ?>
                                            <?php endfor; ?>
                                        </span>
                                        <span style="margin-left: 0.5rem; font-weight: 500; color: #4a5568;"><?php echo e($review['title'] ?? ''); ?></span>
                                    </div>
                                    <?php if (!empty($review['skill_name'])): ?>
                                        <p style="font-size: 0.85rem; color: #718096; margin-bottom: 0.5rem;">
                                            <i class="fas fa-book" style="margin-right: 0.25rem;"></i> Skill: <?php echo e($review['skill_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($review['comment'])): ?>
                                        <p style="color: #4a5568; margin: 0; line-height: 1.5;"><?php echo e($review['comment']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Regular User View: Action Buttons -->
                    <?php if ($exchange['match_user_id']): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/messages.php?user_id=<?php echo $partnerId; ?>" class="btn btn-primary">
                            <i class="fas fa-envelope"></i> Send Message
                        </a>
                    <?php endif; ?>

                    <?php if ($exchange['status'] === 'in_progress' || $exchange['status'] === 'matched'): ?>
                        <?php 
                            $hasConfirmed = ($isProposer && $exchange['proposer_confirmed']) || ($isMatchUser && $exchange['acceptor_confirmed']);
                            $partnerConfirmed = ($isProposer && $exchange['acceptor_confirmed']) || ($isMatchUser && $exchange['proposer_confirmed']);
                        ?>
                        
                        <?php if (!$hasConfirmed): ?>
                            <button onclick="confirmCompletion()" class="btn btn-success" style="margin-left: 1rem;">
                                <i class="fas fa-check"></i> Mark as Completed
                            </button>
                        <?php else: ?>
                            <button disabled class="btn btn-outline" style="margin-left: 1rem; opacity: 0.7; cursor: not-allowed;">
                                <i class="fas fa-check"></i> You Confirmed
                            </button>
                        <?php endif; ?>

                        <?php if ($partnerConfirmed): ?>
                             <span class="badge badge-success" style="margin-left: 1rem;">Partner Confirmed</span>
                        <?php elseif ($hasConfirmed): ?>
                             <span style="margin-left: 1rem; color: #718096; font-style: italic;">Waiting for partner...</span>
                        <?php endif; ?>

                        <script>
                        async function confirmCompletion() {
                            if (!confirm('Are you sure you want to mark this exchange as completed?')) return;
                            
                            const formData = new FormData();
                            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                            formData.append('exchange_id', <?php echo $exchangeId; ?>);
                            formData.append('mode', 'confirm_completion');
                            
                            try {
                                const response = await fetch('<?php echo BASE_URL; ?>/api/exchanges.php', {
                                    method: 'POST',
                                    body: formData
                                });
                                const result = await response.json();
                                
                                if (result.success) {
                                    if (result.completed) {
                                        alert('Exchange completed! You can now leave a review.');
                                    } else {
                                        alert('Confirmed! Waiting for the other party to confirm.');
                                    }
                                    window.location.reload();
                                } else {
                                    alert('Error: ' + result.message);
                                }
                            } catch (err) {
                                console.error(err);
                                alert('An error occurred.');
                            }
                        }
                        </script>
                    <?php endif; ?>
                    
                    <?php if ($exchange['status'] === 'completed' && !$existingReview): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/exchange-detail.php?id=<?php echo $exchangeId; ?>&review=1" class="btn btn-secondary" style="margin-left: 1rem;">Write Review</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Review Section -->
    <?php if ($showReview && $exchange['status'] === 'completed' && !$existingReview): ?>
        <div class="card" id="review-form">
            <h2 style="margin-bottom: 1rem;">Rate Your Experience</h2>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="review">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Rating</label>
                    <div style="display: flex; gap: 0.5rem; font-size: 2rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label style="cursor: pointer;">
                                <input type="radio" name="rating" value="<?php echo $i; ?>" required style="display: none;">
                                <span class="star" id="star<?php echo $i; ?>" 
                                      onclick="selectRating(<?php echo $i; ?>)" 
                                      onmouseover="highlightStars(<?php echo $i; ?>)" 
                                      onmouseout="resetStars()">★</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="review_title" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Title</label>
                    <input type="text" id="review_title" name="review_title" class="form-control" 
                           placeholder="Summarize your experience (e.g. Great teacher!)" 
                           style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="review_text" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Review</label>
                    <textarea id="review_text" name="review_text" class="form-control" rows="5" 
                              placeholder="Share details about what you learned and how the session went..."
                              style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                    <a href="?id=<?php echo $exchangeId; ?>" class="btn btn-outline" style="margin-left: 1rem;">Cancel</a>
                </div>
            </form>
        </div>

        <script>
        let selectedRating = 0;

        function selectRating(rating) {
            selectedRating = rating;
            updateStars(rating);
            document.querySelector('input[name="rating"][value="' + rating + '"]').checked = true;
        }

        function highlightStars(rating) {
            updateStars(rating);
        }

        function resetStars() {
            updateStars(selectedRating);
        }
        
        function updateStars(rating) {
            for (let i = 1; i <= 5; i++) {
                const star = document.getElementById('star' + i);
                if (i <= rating) {
                    star.style.color = '#f6ad55'; // Orange
                    star.style.opacity = '1';
                } else {
                    star.style.color = '#cbd5e0'; // Gray
                    star.style.opacity = '1';
                }
            }
        }
        </script>
    <?php elseif ($existingReview): ?>
        <div class="card">
            <h2 style="margin-bottom: 1rem;">Your Review</h2>
            <div style="margin-bottom: 1rem;">
                <span style="font-size: 1.5rem; color: #f6ad55;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span><?php echo $i <= $existingReview['rating'] ? '★' : '☆'; ?></span>
                    <?php endfor; ?>
                </span>
                <span style="margin-left: 0.5rem; font-weight: 600;"><?php echo e($existingReview['title']); ?></span>
            </div>
            <?php if ($existingReview['comment']): ?>
                <p style="white-space: pre-wrap; color: #4a5568; margin: 0;"><?php echo e($existingReview['comment']); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.badge {
    display: inline-block;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-pending { background-color: #fefcbf; color: #744210; }
.badge-matched { background-color: #c6f6d5; color: #22543d; }
.badge-in-progress { background-color: #bee3f8; color: #2a4365; }
.badge-completed { background-color: #e9d8fd; color: #44337a; }
.badge-cancelled, .badge-rejected { background-color: #fed7d7; color: #822727; }

.star {
    transition: color 0.1s;
    user-select: none;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
