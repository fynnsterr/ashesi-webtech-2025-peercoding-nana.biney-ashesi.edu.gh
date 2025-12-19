<?php
$pageTitle = 'Privacy Policy - SkillSwap';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 900px; margin: 2rem auto; padding: 20px;">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Privacy Policy</h1>
            <p class="card-subtitle">Last Updated: <?php echo date('F d, Y'); ?></p>
        </div>
        <div class="card-body" style="line-height: 1.6;">
            <h2>1. Introduction</h2>
            <p>SkillSwap ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our Platform.</p>
            
            <h2>2. Information We Collect</h2>
            <h3>Personal Information</h3>
            <p>When you register, we collect:</p>
            <ul>
                <li>Full name</li>
                <li>Email address</li>
                <li>Phone number</li>
                <li>Location (city)</li>
                <li>Biographical information</li>
                <li>Skills you can teach</li>
                <li>Skills you want to learn</li>
            </ul>
            
            <h3>Usage Information</h3>
            <p>We automatically collect:</p>
            <ul>
                <li>IP address</li>
                <li>Browser type</li>
                <li>Device information</li>
                <li>Pages visited</li>
                <li>Time spent on Platform</li>
            </ul>
            
            <h2>3. How We Use Your Information</h2>
            <p>We use your information to:</p>
            <ul>
                <li>Create and manage your account</li>
                <li>Facilitate skill exchanges between users</li>
                <li>Match you with compatible skill exchange partners</li>
                <li>Send important notifications about your account</li>
                <li>Improve our Platform and services</li>
                <li>Ensure Platform security and prevent fraud</li>
                <li>Comply with legal obligations</li>
            </ul>
            
            <h2>4. Information Sharing</h2>
            <p>We may share your information:</p>
            <ul>
                <li><strong>With Other Users:</strong> Your name, skills, and location are visible to other users to facilitate exchanges</li>
                <li><strong>With Service Providers:</strong> Third parties who help us operate our Platform (hosting, analytics)</li>
                <li><strong>For Legal Reasons:</strong> When required by law or to protect our rights</li>
            </ul>
            <p>We do not sell your personal information to third parties.</p>
            
            <h2>5. Data Security</h2>
            <p>We implement security measures to protect your information:</p>
            <ul>
                <li>Passwords are hashed using bcrypt</li>
                <li>Secure HTTPS connections</li>
                <li>Regular security updates</li>
                <li>Limited employee access to personal data</li>
            </ul>
            <p>However, no method of transmission over the Internet is 100% secure.</p>
            
            <h2>6. Data Retention</h2>
            <p>We retain your personal information for as long as your account is active or as needed to provide services. You may request account deletion at any time.</p>
            
            <h2>7. Your Rights</h2>
            <p>You have the right to:</p>
            <ul>
                <li>Access your personal information</li>
                <li>Correct inaccurate information</li>
                <li>Request deletion of your information</li>
                <li>Opt-out of marketing communications</li>
                <li>Export your data</li>
            </ul>
            <p>To exercise these rights, contact us at support@skillswap.com</p>
            
            <h2>8. Cookies and Tracking</h2>
            <p>We use cookies to:</p>
            <ul>
                <li>Maintain your login session</li>
                <li>Remember your preferences</li>
                <li>Analyze Platform usage</li>
            </ul>
            <p>You can disable cookies in your browser settings, but this may affect Platform functionality.</p>
            
            <h2>9. Children's Privacy</h2>
            <p>Our Platform is not intended for children under 18. We do not knowingly collect information from children under 18. If we learn we have collected such information, we will delete it promptly.</p>
            
            <h2>10. Changes to Privacy Policy</h2>
            <p>We may update this Privacy Policy periodically. We will notify you of significant changes via email or Platform notification.</p>
            
            <h2>11. Contact Us</h2>
            <p>If you have questions about this Privacy Policy, contact us:</p>
            <p>Email: privacy@skillswap.com<br>
            Address: Accra, Ghana</p>
            
            <div class="alert alert-info" style="margin-top: 30px;">
                <strong>Note:</strong> By using SkillSwap, you consent to the collection and use of information as described in this Privacy Policy.
            </div>
        </div>
        <div class="card-footer">
            <p style="text-align: center;">
                <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">Back to Registration</a>
            </p>
        </div>
    </div>
</div>

<style>
    .container h2 {
        color: #333;
        margin-top: 25px;
        padding-bottom: 8px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .container h3 {
        color: #555;
        margin-top: 15px;
        font-size: 1.2em;
    }
    
    .container ul {
        margin-left: 20px;
        margin-bottom: 15px;
    }
    
    .container li {
        margin-bottom: 8px;
    }
    
    .alert {
        padding: 15px;
        border-radius: 5px;
        background-color: #f8f9fa;
        border-left: 4px solid #4e73df;
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>