/**
 * FARMLINK Logout Confirmation System
 * Provides a beautiful confirmation dialog for logout functionality
 */

// Create and show logout confirmation modal
function showLogoutConfirmation() {
    // Remove existing modal if any
    const existingModal = document.getElementById('logoutModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Create modal HTML
    const modalHTML = `
        <div id="logoutModal" class="logout-modal-overlay">
            <div class="logout-modal">
                <div class="logout-modal-header">
                    <i class="fas fa-sign-out-alt logout-icon"></i>
                    <h3>Confirm Logout</h3>
                </div>
                <div class="logout-modal-body">
                    <p>Are you sure you want to logout?</p>
                    <p class="logout-subtitle">You will need to login again to access your account.</p>
                </div>
                <div class="logout-modal-footer">
                    <button id="cancelLogout" class="btn-cancel">
                        <i class="fas fa-times"></i> No, Stay
                    </button>
                    <button id="confirmLogout" class="btn-confirm">
                        <i class="fas fa-sign-out-alt"></i> Yes, Logout
                    </button>
                </div>
            </div>
        </div>
    `;

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Get modal elements
    const modal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('cancelLogout');
    const confirmBtn = document.getElementById('confirmLogout');

    // Show modal with animation
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);

    // Handle cancel
    cancelBtn.addEventListener('click', function() {
        hideLogoutModal();
    });

    // Handle confirm
    confirmBtn.addEventListener('click', function() {
        // Show loading state
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
        confirmBtn.disabled = true;
        cancelBtn.disabled = true;
        
        // Redirect to logout after short delay for UX
        setTimeout(() => {
            const target = window._logoutTarget || (document.querySelector('a[href*="logout.php"]')?.href) || '/pages/auth/logout.php';
            window.location.href = target;
        }, 800);
    });

    // Handle click outside modal
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            hideLogoutModal();
        }
    });

    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideLogoutModal();
        }
    });
}

// Hide logout modal
function hideLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Initialize logout confirmation for all logout links
function initializeLogoutConfirmation() {
    // Find all logout links and add click handlers
    const logoutLinks = document.querySelectorAll('a[href*="logout.php"], a[href*="logout"]');
    
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            showLogoutConfirmation();
        });
    });
}

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeLogoutConfirmation();
});

// Also initialize if script is loaded after DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLogoutConfirmation);
} else {
    initializeLogoutConfirmation();
}
