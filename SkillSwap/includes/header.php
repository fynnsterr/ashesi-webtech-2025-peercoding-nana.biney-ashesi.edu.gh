<?php
require_once __DIR__ . '/auth.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?>SkillSwap - Exchange Skills, Grow Together</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <script>
        window.BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
</head>
<body>
    <header>
        <div class="container">
            <nav class="nav-container">
                <a href="<?php echo BASE_URL; ?>/pages/index.php" class="logo">
                    <i class="fas fa-exchange-alt"></i>
                    SkillSwap
                </a>
                
                <button class="hamburger" id="hamburger" aria-label="Toggle navigation">
                    <i class="fas fa-bars"></i>
                </button>
                
                <ul class="nav-links" id="navLinks">
                    <?php if (isLoggedIn()): ?>
                        <?php if (hasRole('admin')): ?>
                            <li><a href="<?php echo BASE_URL; ?>/pages/index.php"><i class="fas fa-home me-1"></i> Home</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/admin/index.php"><i class="fas fa-tachometer-alt me-1"></i> Admin Dashboard</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/messages.php"><i class="fas fa-envelope me-1"></i> Messages</a></li>
                            <li>
                                <a href="<?php echo BASE_URL; ?>/pages/logout.php" class="btn btn-outline-danger btn-sm ms-2">
                                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                                </a>
                            </li>
                        <?php else: ?>
                            <li><a href="<?php echo BASE_URL; ?>/pages/index.php">Home</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/exchanges.php">Exchanges</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/messages.php">Messages</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/skills.php">My Skills</a></li>                            
                            <li><a href="<?php echo BASE_URL; ?>/pages/profile.php">Profile</a></li>
                            
                            <li class="user-menu">
                                <div class="user-avatar">   
                                    <?php echo strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <span class="user-name"><?php echo e($currentUser['full_name'] ?? 'User'); ?></span>
                            </li>
                            <li>
                                <a href="<?php echo BASE_URL; ?>/pages/logout.php" class="btn btn-outline-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>/pages/index.php">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/about.php">How It Works</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/login.php" class="btn btn-primary">Login</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-primary">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">