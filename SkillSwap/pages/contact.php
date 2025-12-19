<?php
// contacts.php
include '../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us | Skill Swap</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include '../includes/header.php'; ?>

<main class="main-content">
    <div class="container py-4">
        <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
            <h1 style="color: var(--primary-color); font-size: 1.75rem; margin-bottom: 0;">Contact Skill Swap</h1>
            <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>            
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="row">
                    <!-- Contact Form -->
                    <div class="col-lg-12 mb-4">
   <div class="contact-form-section">
                    <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px; height: 100%;">
                        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; text-align: left !important;">
                            <h2 class="card-title h5" style="color: var(--primary-color); margin: 0; text-align: left !important;">
                                <i class="fas fa-chalkboard-teacher me-2"></i> Send Us a Message
                            </h2>
                        </div> 
                        <div class="card-body p-4">
                            <?php if (isLoggedIn()): ?>
                                <form action="<?php echo BASE_URL; ?>/pages/process_contact.php" method="POST">
                                    <div class="row">
                                        <div class="col-md-6 form-group mb-3">
                                            <label for="name" class="form-label fw-bold">Full Name</label>
                                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>" required readonly>
                                        </div>
                                        <div class="col-md-6 form-group mb-3">
                                            <label for="email" class="form-label fw-bold">Email Address</label>
                                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required readonly>
                                        </div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="subject" class="form-label fw-bold">Subject</label>
                                        <input type="text" id="subject" name="subject" class="form-control" placeholder="How can we help?" required>
                                    </div>

                                    <div class="form-group mb-4">
                                        <label for="message" class="form-label fw-bold">Message</label>
                                        <textarea id="message" name="message" rows="5" class="form-control" placeholder="Type your message here..." required></textarea>
                                    </div>

                                    <div class="text-center">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                                            <i class="fas fa-paper-plane me-2"></i> Send Message
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="mb-4">
                                        <i class="fas fa-user-lock fa-3x text-muted opacity-50"></i>
                                    </div>
                                    <h4 class="h5 text-dark mb-3">Login Required</h4>
                                    <p class="text-muted mb-4">You need to be logged in to send us a message directly.</p>
                                    <div class="d-flex justify-content-center gap-3">
                                        <a href="<?php echo BASE_URL; ?>/pages/login.php" class="btn btn-primary px-4">Login</a>
                                        <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-outline-primary px-4">Register</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
  
            <!-- Contact Info -->
            <div class="col-lg-12">
                <div class="contact-info-section">
                    <!-- Get in Touch Card -->
                    <div class="card mb-4" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px;">
                        <!-- Get In Touch Card -->
                        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; text-align: left !important;">
                            <h3 class="card-title h5" style="color: var(--primary-color); margin: 0; text-align: left !important;">
                                <i class="fas fa-info-circle me-2"></i> Get In Touch
                            </h3>
                        </div>
                        <div class="card-body p-4">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center;">
                            <div style="border-right: 1px solid #eee;">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="icon-circle bg-primary text-white mb-2">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <span class="opacity-75 small">Email:</span>
                                    <span class="fw-bold small">support@skillswap.com</span>
                                </div>
                            </div>
                            <div style="border-right: 1px solid #eee;">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="icon-circle bg-primary text-white mb-2">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <span class="opacity-75 small">Community:</span>
                                    <span class="fw-bold small">Learn & Teach</span>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex flex-column align-items-center">
                                    <div class="icon-circle bg-primary text-white mb-2">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    <span class="opacity-75 small">Location:</span>
                                    <span class="fw-bold small">Worldwide</span>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>

                    <!-- Connect With Us Card -->
                    <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border-radius: 12px;">
                        <!-- Connect With Us Card -->
                        <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem; border-radius: 12px 12px 0 0; text-align: left !important;">
                            <h3 class="card-title h5" style="color: var(--primary-color); margin: 0; text-align: left !important;">
                                <i class="fas fa-share-alt me-2"></i> Connect With Us
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; text-align: center;">
                                <div style="border-right: 1px solid #eee;">
                                    <div style="display: flex; justify-content: center;">
                                        <a href="#" class="btn btn-outline-primary rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-facebook-f"></i></a>
                                    </div>
                                </div>
                                <div style="border-right: 1px solid #eee;">
                                    <div style="display: flex; justify-content: center;">
                                        <a href="#" class="btn btn-outline-info rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-twitter"></i></a>
                                    </div>
                                </div>
                                <div style="border-right: 1px solid #eee;">
                                    <div style="display: flex; justify-content: center;">
                                        <a href="#" class="btn btn-outline-danger rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-instagram"></i></a>
                                    </div>
                                </div>
                                <div>
                                    <div style="display: flex; justify-content: center;">
                                        <a href="#" class="btn btn-outline-primary rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-linkedin-in"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
           </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

</body>
</html>
