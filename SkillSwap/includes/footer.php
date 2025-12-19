</div>
        </main>

        <!-- Footer -->
        <footer class="modern-footer">
            <div class="footer-container">
                <div class="footer-grid">
                    <!-- Column 1: Brand & Mission -->
                    <div class="footer-col brand-col">
                        <a href="<?php echo BASE_URL; ?>/pages/index.php" class="footer-logo">
                            <i class="fas fa-exchange-alt"></i> SkillSwap
                        </a>
                        <p class="footer-desc">
                            A community-driven platform where teaching meets learning. 
                            Exchange skills, expand your horizons, and grow together.
                        </p>
                    </div>

                    <!-- Column 2: Explore -->
                    <div class="footer-col">
                        <h3>Explore</h3>
                        <ul class="footer-links">
                            <li><a href="<?php echo BASE_URL; ?>/pages/index.php">Browse Skills</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/about.php">How It Works</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/register.php">Join Community</a></li>
                        </ul>
                    </div>

                    <!-- Column 3: Support -->
                    <div class="footer-col">
                        <h3>Support</h3>
                        <ul class="footer-links">
                            <li><a href="<?php echo BASE_URL; ?>/pages/contact.php">Contact Us</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/terms.php">Terms & Guidelines</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pages/privacy.php">Privacy Policy</a></li>
                        </ul>
                    </div>

                    <!-- Column 4: Connect -->
                    <div class="footer-col">
                        <h3>Connect</h3>
                        <p class="mb-3">Stay updated with our latest news and features.</p>
                        <div class="social-links">
                            <a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>

                <div class="footer-bottom">
                    <div class="copyright">
                        &copy; <?php echo date('Y'); ?> Skill Swap. All rights reserved.
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom Scripts -->
    <script>
        // Mobile menu toggle
        document.querySelector('.navbar-toggler').addEventListener('click', function() {
            document.querySelector('.navbar-menu').classList.toggle('active');
        });

        // Dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.dropdown-toggle');
            dropdowns.forEach(dropdown => {
                dropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    this.nextElementSibling.classList.toggle('show');
                });
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.matches('.dropdown-toggle')) {
                    const dropdowns = document.querySelectorAll('.dropdown-menu');
                    dropdowns.forEach(dropdown => {
                        dropdown.classList.remove('show');
                    });
                }
            });
        });
    </script>
</body>
</html>
