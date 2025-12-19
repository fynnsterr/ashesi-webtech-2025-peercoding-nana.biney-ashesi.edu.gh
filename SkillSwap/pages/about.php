<?php
$pageTitle = 'About SkillSwap';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="about-hero text-center mb-5">
        <h1>About SkillSwap</h1>
        <p class="lead">Connecting people through the power of shared knowledge</p>
    </div>

    <div class="row mb-5">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h2 class="mb-4">What is SkillSwap?</h2>
                    <p class="lead">
                        SkillSwap is a community-driven platform that enables people to exchange skills and knowledge 
                        without the need for money. Whether you're looking to learn something new or share your expertise, 
                        SkillSwap connects you with like-minded individuals in your community.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-exchange-alt fa-3x text-primary"></i>
                    </div>
                    <h3>Skill Exchange</h3>
                    <p>Trade your skills with others in your community. Teach what you know and learn what you don't.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-users fa-3x text-primary"></i>
                    </div>
                    <h3>Community Building</h3>
                    <p>Connect with like-minded individuals and build meaningful relationships through shared learning.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center p-4">
                    <div class="icon-box mb-3">
                        <i class="fas fa-graduation-cap fa-3x text-primary"></i>
                    </div>
                    <h3>Lifelong Learning</h3>
                    <p>Expand your knowledge and discover new passions through our diverse community of learners.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-lg-10 mx-auto">
            <div class="card border-0 bg-light">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">How It Works</h2>
                    <div class="row">
                        <div class="col-md-3 text-center mb-4 mb-md-0">
                            <div class="step-number">1</div>
                            <h4>Create a Profile</h4>
                            <p>Sign up and list the skills you can offer and what you'd like to learn.</p>
                        </div>
                        <div class="col-md-3 text-center mb-4 mb-md-0">
                            <div class="step-number">2</div>
                            <h4>Find a Match</h4>
                            <p>Browse skills or search for specific ones you're interested in learning.</p>
                        </div>
                        <div class="col-md-3 text-center mb-4 mb-md-0">
                            <div class="step-number">3</div>
                            <h4>Connect & Exchange</h4>
                            <p>Message other members and arrange your skill exchange.</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="step-number">4</div>
                            <h4>Grow Together</h4>
                            <p>Learn new skills and share your knowledge with the community.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto text-center">
            <h2 class="mb-4">Ready to Start Swapping Skills?</h2>
            <p class="lead mb-4">Join our community of lifelong learners and start exchanging knowledge today.</p>
            <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-primary btn-lg">Join Now</a>
            <p class="mt-3">Already have an account? <a href="<?php echo BASE_URL; ?>/pages/login.php">Sign in</a></p>
        </div>
    </div>
</div>

<style>
.about-hero {
    padding: 4rem 0;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    margin-bottom: 3rem;
}

.icon-box {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(74, 111, 165, 0.1);
    border-radius: 50%;
}

.step-number {
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0 auto 1rem;
}

.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

h1, h2, h3, h4 {
    color: var(--primary-color);
}

.btn-primary {
    background-color: var(--accent-color);
    border-color: var(--accent-color);
    padding: 0.75rem 2rem;
}

.btn-primary:hover {
    background-color: #e66b4d;
    border-color: #e66b4d;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>