<?php
$pageTitle = 'Terms of Service - SkillSwap';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 900px; margin: 2rem auto; padding: 20px;">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Terms of Service</h1>
            <p class="card-subtitle">Last Updated: <?php echo date('F d, Y'); ?></p>
        </div>
        <div class="card-body" style="line-height: 1.6;">
            <h2>1. Acceptance of Terms</h2>
            <p>By accessing and using SkillSwap, you accept and agree to be bound by the terms and provisions of this agreement. If you do not agree to these terms, please do not use our Platform.</p>
            
            <h2>2. Description of Service</h2>
            <p>SkillSwap is a platform that enables users to exchange skills with one another. Users can offer skills they possess and request skills they wish to learn from other users.</p>
            
            <h2>3. User Accounts</h2>
            <p>To use SkillSwap, you must register for an account. You agree to:</p>
            <ul>
                <li>Provide accurate, current, and complete information</li>
                <li>Maintain the security of your password</li>
                <li>Accept all risks of unauthorized access to your account</li>
                <li>Notify us immediately if you discover any security breach</li>
            </ul>
            
            <h2>4. Skill Exchange Guidelines</h2>
            <p>When participating in skill exchanges, you agree to:</p>
            <ul>
                <li>Accurately represent your skill level and experience</li>
                <li>Respect other users' time and commitments</li>
                <li>Complete agreed-upon exchanges in good faith</li>
                <li>Provide honest feedback about your exchange experiences</li>
                <li>Not engage in any illegal or harmful activities</li>
            </ul>
            
            <h2>5. User Conduct</h2>
            <p>You agree not to:</p>
            <ul>
                <li>Use the Platform for any unlawful purpose</li>
                <li>Harass, threaten, or intimidate other users</li>
                <li>Post false, misleading, or fraudulent information</li>
                <li>Share personal information of other users without consent</li>
                <li>Attempt to gain unauthorized access to the Platform</li>
            </ul>
            
            <h2>6. Safety Guidelines</h2>
            <p>For in-person exchanges, we recommend:</p>
            <ul>
                <li>Meeting in public places</li>
                <li>Informing someone about your exchange plans</li>
                <li>Trusting your instincts and leaving if uncomfortable</li>
                <li>Reporting any safety concerns to us immediately</li>
            </ul>
            
            <h2>7. Limitation of Liability</h2>
            <p>SkillSwap is not responsible for:</p>
            <ul>
                <li>The quality of skills exchanged between users</li>
                <li>Personal injuries or damages during exchanges</li>
                <li>Any disputes between users</li>
                <li>Loss of data or service interruptions</li>
            </ul>
            
            <h2>8. Termination</h2>
            <p>We reserve the right to terminate or suspend your account at our sole discretion, without notice, for conduct that we believe violates these Terms or is harmful to other users, us, or third parties, or for any other reason.</p>
            
            <h2>9. Changes to Terms</h2>
            <p>We may modify these Terms at any time. We will provide notice of significant changes. Your continued use of the Platform after changes constitutes acceptance of the new Terms.</p>
            
            <h2>10. Contact Information</h2>
            <p>If you have any questions about these Terms, please contact us at:</p>
            <p>Email: support@skillswap.com<br>
            Address: Accra, Ghana</p>
            
            <div class="alert alert-info" style="margin-top: 30px;">
                <strong>Note:</strong> These Terms constitute the entire agreement between you and SkillSwap regarding your use of the Platform.
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
    
    .container h2:first-child {
        margin-top: 0;
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