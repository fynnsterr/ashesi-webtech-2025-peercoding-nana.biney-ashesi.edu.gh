<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$pdo = getDB();
$userId = getUserId();

// Get user information
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM user_skills WHERE user_id = u.user_id AND can_teach = 1) as skills_teaching,
           (SELECT COUNT(*) FROM user_skills WHERE user_id = u.user_id AND willing_to_learn = 1) as skills_learning,
           (SELECT COUNT(*) FROM exchange_proposals WHERE (proposer_id = u.user_id OR match_user_id = u.user_id) AND status = 'completed') as completed_exchanges
    FROM users u
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            
            if (!empty($fullName) && !empty($email)) {
                // Check if email is already used by another user
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $userId]);
                if ($stmt->fetch()) {
                    $error = 'This email is already in use by another account.';
                } else {
                    // Handle Profile Image Upload
                    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $maxSize = 2 * 1024 * 1024; // 2MB
                        
                        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
                        $fileName = $_FILES['profile_image']['name'];
                        $fileSize = $_FILES['profile_image']['size'];
                        $fileType = $_FILES['profile_image']['type'];
                        
                        // specific mime type check using finfo
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo->file($fileTmpPath);

                        if (!in_array($mimeType, $allowedTypes)) {
                            $error = 'Invalid file type. Only JPG, PNG, GIF, and WEBP allowed.';
                        } elseif ($fileSize > $maxSize) {
                            $error = 'File is too large. Maximum size is 2MB.';
                        } else {
                            // Create unique filename
                            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                            $newFileName = 'user_' . $userId . '_' . time() . '.' . $extension;
                            $uploadDir = __DIR__ . '/../uploads/profiles/';
                            
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            
                            $destPath = $uploadDir . $newFileName;
                            
                            if (move_uploaded_file($fileTmpPath, $destPath)) {
                                // Update database with new image path
                                $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                                $stmt->execute([$newFileName, $userId]);
                                
                                // Delete old image if it exists and isn't a default/placeholder (optional, strict cleanup)
                                if (!empty($user['profile_image']) && file_exists($uploadDir . $user['profile_image'])) {
                                    @unlink($uploadDir . $user['profile_image']);
                                }

                                $user['profile_image'] = $newFileName; // Update local user array immediately
                            } else {
                                $error = 'There was an error moving the uploaded file.';
                            }
                        }
                    }

                    if (empty($error)) {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET full_name = ?, email = ?, phone = ?, location = ?, bio = ?, updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$fullName, $email, $phone, $location, $bio, $userId]);
                        $message = 'Profile updated successfully!';
                        
                        // Refresh user data
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                        $stmt->execute([$userId]);
                        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                        $user = array_merge($user, $userData);
                    }
                }
            } else {
                $error = 'Full name and email are required.';
            }
        } else {
            $error = 'Invalid request.';
        }
    }
}

// Ghana cities for location dropdown
$ghanaCities = ['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Tema', 'Cape Coast', 'Sunyani', 'Koforidua', 'Ho', 'Wa'];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <h1 style="color: var(--primary-color); font-size: 1.75rem; margin-bottom: 0;">
            My Profile
        </h1>
        <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success" style="border-radius: 8px; margin-bottom: 1.5rem;"><?php echo e($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="border-radius: 8px; margin-bottom: 1.5rem;"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
        <!-- Profile Summary Card -->
        <div class="card" style="border: none; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); border-radius: 16px; overflow: hidden;">
            <!-- Profile Banner -->
            <div style="height: 120px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
            
            <div class="card-body" style="padding: 0 2rem 2rem; text-align: center; position: relative;">
                <!-- Avatar -->
                <div style="width: 100px; height: 100px; background: white; border-radius: 50%; padding: 4px; margin: -50px auto 1rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?php echo BASE_URL; ?>/uploads/profiles/<?php echo e($user['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #4b5563; font-weight: 600;">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 style="color: #1f2937; margin-bottom: 0.25rem; font-weight: 700;"><?php echo e($user['full_name']); ?></h3>
                <p style="color: #6B7280; font-size: 0.95rem; margin-bottom: 0.5rem;"><?php echo e($user['email']); ?></p>
                
                <?php if (!empty($user['location'])): ?>
                    <p style="color: #6B7280; font-size: 0.9rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <i class="fas fa-map-marker-alt" style="color: #ef4444;"></i><?php echo e($user['location']); ?>
                    </p>
                <?php endif; ?>
                
                <!-- Stats -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding-top: 1.5rem; border-top: 1px solid #F3F4F6;">
                    <div style="padding: 0.5rem;">
                        <div style="color: #3b82f6; font-size: 1.25rem; margin-bottom: 0.25rem;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;"><?php echo (int)$user['skills_teaching']; ?></div>
                        <div style="font-size: 0.75rem; color: #6B7280; font-weight: 500;">Teaching</div>
                    </div>
                    <div style="padding: 0.5rem;">
                        <div style="color: #10b981; font-size: 1.25rem; margin-bottom: 0.25rem;">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;"><?php echo (int)$user['completed_exchanges']; ?></div>
                        <div style="font-size: 0.75rem; color: #6B7280; font-weight: 500;">Exchanges</div>
                    </div>
                    <div style="padding: 0.5rem;">
                        <div style="color: #f59e0b; font-size: 1.25rem; margin-bottom: 0.25rem;">
                            <i class="fas fa-star"></i>
                        </div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: #1f2937;">
                            <?php echo number_format((float)$user['rating_avg'], 1); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #6B7280; font-weight: 500;">Rating</div>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <a href="<?php echo BASE_URL; ?>/pages/skills.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                        <i class="fas fa-cog me-2"></i> Manage Skills
                    </a>
                </div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px; margin-top: 2rem;">
            <div class="card-header" style="background: white; border-bottom: 1px solid #F3F4F6; padding: 1.5rem; text-align: center;">
                <h2 class="card-title" style="color: #1f2937; font-size: 1.25rem; margin: 0; font-weight: 600;">
                    <i class="fas fa-user-edit me-2" style="color: var(--primary-color);"></i>Edit Profile
                </h2>
            </div>
            <div class="card-body" style="padding: 3rem 4rem;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <!-- Profile Image Field -->
                    <div class="form-group mb-3 d-flex align-items-center">
                        <label for="profile_image" class="form-label mb-0" style="font-weight: 600; color: #374151; width: 180px; min-width: 180px; flex-shrink: 0; padding-right: 1rem;">Profile Picture</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*"
                            style="border-radius: 6px; padding: 0.5rem; font-size: 0.9rem; border: 1px solid #D1D5DB; background-color: #fff; flex: 1;">
                    </div>
                    
                    <!-- Full Name Field -->
                    <div class="form-group mb-3 d-flex align-items-center">
                        <label for="full_name" class="form-label mb-0" style="font-weight: 600; color: #374151; width: 180px; min-width: 180px; flex-shrink: 0; padding-right: 1rem;">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                            value="<?php echo e($user['full_name']); ?>" required
                            style="border-radius: 6px; padding: 0.5rem; font-size: 0.9rem; border: 1px solid #D1D5DB; background-color: #fff; flex: 1;">
                    </div>
                    
                    <!-- Email Field -->
                    <div class="form-group mb-3 d-flex align-items-center">
                        <label for="email" class="form-label mb-0" style="font-weight: 600; color: #374151; width: 180px; min-width: 180px; flex-shrink: 0; padding-right: 1rem;">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                            value="<?php echo e($user['email']); ?>" required
                            style="border-radius: 6px; padding: 0.5rem; font-size: 0.9rem; border: 1px solid #D1D5DB; background-color: #fff; flex: 1;">
                    </div>
                    
                    <!-- Phone Field -->
                    <div class="form-group mb-3 d-flex align-items-center">
                        <label for="phone" class="form-label mb-0" style="font-weight: 600; color: #374151; width: 180px; min-width: 180px; flex-shrink: 0; padding-right: 1rem;">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                            value="<?php echo e($user['phone'] ?? ''); ?>"
                            placeholder="e.g., 0244123456"
                            style="border-radius: 6px; padding: 0.5rem; font-size: 0.9rem; border: 1px solid #D1D5DB; background-color: #fff; flex: 1;">
                    </div>

                    <!-- Location Field (Select Box) -->
                    <div class="form-group mb-3 d-flex align-items-center">
                        <label for="location" class="form-label mb-0" style="font-weight: 600; color: #374151; width: 180px; min-width: 180px; flex-shrink: 0; padding-right: 1rem;">Location</label>
                        <select class="form-select" id="location" name="location" 
                                style="border-radius: 6px; padding: 0.5rem; font-size: 0.9rem; border: 1px solid #D1D5DB; background-color: #fff; flex: 1;">
                            <option value="">Select your city</option>
                            <?php foreach ($ghanaCities as $city): ?>
                                <option value="<?php echo e($city); ?>" <?php echo ($user['location'] ?? '') === $city ? 'selected' : ''; ?>>
                                    <?php echo e($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Current Password Field -->
                    <div class="form-group mb-3 d-flex align-items-center">
                        <label for="current_password" class="form-label mb-0" style="font-weight: 600; color: #374151; width: 180px; min-width: 180px; flex-shrink: 0; padding-right: 1rem;">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" 
                            placeholder="Enter current password to change it"
                            style="border-radius: 6px; padding: 0.5rem; font-size: 0.9rem; border: 1px solid #D1D5DB; background-color: #fff; flex: 1;">
                    </div>

                    <!-- New Password Field -->
                    <div class="form-group mb-3 d-flex align-items-center">
                        <label for="new_password" class="form-label mb-0" style="font-weight: 600; color: #374151; width: 180px; min-width: 180px; flex-shrink: 0; padding-right: 1rem;">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                            placeholder="Enter new password"
                            style="border-radius: 6px; padding: 0.5rem; font-size: 0.9rem; border: 1px solid #D1D5DB; background-color: #fff; flex: 1;">
                    </div>

                    <!-- Bio Field -->
                    <div class="form-group mb-5 d-flex align-items-start">
                        <label for="bio" class="form-label mb-0" style="font-weight: 600; color: #374151; width: 180px; min-width: 180px; flex-shrink: 0; padding-right: 1rem; padding-top: 0.5rem;">Bio / About Me</label>
                        <textarea class="form-control" id="bio" name="bio" rows="4" 
                                placeholder="Tell others about yourself..."
                                style="border-radius: 6px; padding: 0.5rem; font-size: 0.9rem; border: 1px solid #D1D5DB; background-color: #fff; resize: none; flex: 1;"><?php echo e($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid #f3f4f6;">
                        <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">Cancel</a>
                        <button type="submit" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                            Save Changes
                        </button>  
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
