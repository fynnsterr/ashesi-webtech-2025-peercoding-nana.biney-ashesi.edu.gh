// Update unread message count in navigation
function updateUnreadCount() {
    fetch(window.BASE_URL + '/api/messages.php?action=unread_count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.count;
                // Update UI if unread count element exists
                const unreadElement = document.getElementById('unreadCount');
                if (unreadElement) {
                    unreadElement.textContent = count;
                    unreadElement.style.display = count > 0 ? 'inline' : 'none';
                }
            }
        })
        .catch(err => console.error('Error fetching unread count:', err));
}

// Update unread count on page load and every 30 seconds
if (document.getElementById('unreadCount')) {
    updateUnreadCount();
    setInterval(updateUnreadCount, 30000);
}

// Form validation helpers
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^[0-9]{10,15}$/;
    return re.test(phone.replace(/\s+/g, ''));
}

// Add loading state to buttons
function setButtonLoading(button, loading) {
    if (loading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Loading...';
    } else {
        button.disabled = false;
        button.textContent = button.dataset.originalText || button.textContent;
    }
}

// Handle AJAX form submissions
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.dataset.ajax === 'true') {
        e.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        setButtonLoading(submitButton, true);

        const formData = new FormData(form);
        const action = form.action || form.dataset.action;

        fetch(action, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                setButtonLoading(submitButton, false);

                if (data.success) {
                    if (form.dataset.redirect) {
                        window.location.href = form.dataset.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Something went wrong'));
                }
            })
            .catch(err => {
                setButtonLoading(submitButton, false);
                alert('Error: ' + err.message);
            });
    }
});

// Initialize tooltips and other UI enhancements
document.addEventListener('DOMContentLoaded', function () {
    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
});

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function () {
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');

    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function () {
            navLinks.classList.toggle('active');
            hamburger.setAttribute('aria-expanded',
                hamburger.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'
            );
        });

        // Close menu when clicking outside
        document.addEventListener('click', function (event) {
            if (!navLinks.contains(event.target) && !hamburger.contains(event.target)) {
                navLinks.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
            }
        });

        // Close menu when clicking on a link
        const navItems = navLinks.querySelectorAll('a');
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                navLinks.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
            });
        });
    }
});

