/**
 * Unified Notification Management JavaScript
 * Handles notification count updates and real-time notifications
 */

// Global notification management
window.NotificationManager = {
    // Update all notification badges
    updateNotificationCount: function(count) {
        // Ensure count is a valid number
        count = parseInt(count) || 0;
        
        // Update navbar badge (notification-count)
        const navbarBadge = document.getElementById('notification-count');
        const navbarBell = document.getElementById('notification-bell');
        
        if (navbarBell) {
            // Remove any existing badges to prevent duplicates
            const existingBadges = navbarBell.querySelectorAll('.notification-badge');
            existingBadges.forEach(badge => badge.remove());
            
            if (count > 0) {
                // Create new badge
                const badge = document.createElement('span');
                badge.id = 'notification-count';
                badge.className = 'notification-badge';
                badge.textContent = count > 99 ? '99+' : count;
                navbarBell.appendChild(badge);
            }
        }
        
        // Remove sidebar badge completely - no longer needed
        const sidebarLink = document.querySelector('.sidebar a[href="notifications.php"]');
        if (sidebarLink) {
            const existingSidebarBadges = sidebarLink.querySelectorAll('.badge');
            existingSidebarBadges.forEach(badge => badge.remove());
        }
    },

    // Check for critical notifications and apply styling
    checkCriticalNotifications: function() {
        const bellElement = document.getElementById('notification-bell');
        if (!bellElement) return;
        
        // Skip this check - it's causing 400 errors and not essential
        // The badge count is sufficient for notification indication
    },

    // Fetch and update notification count
    refreshCount: function() {
        fetch('api/notifications.php?action=get_unread_count', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                if (response.status === 400 || response.status === 401) {
                    return null;
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data) return;
            
            if (data.success) {
                const count = data.data ? data.data.count : data.count;
                this.updateNotificationCount(count);
            }
        })
        .catch(error => {
            if (!error.message.includes('400') && !error.message.includes('401')) {
                console.error('Error fetching notification count:', error);
            }
        });
    },

    // Initialize notification system
    init: function() {
        // Only initialize if we have notification elements
        if (!document.getElementById('notification-bell') && !document.querySelector('.sidebar a[href="notifications.php"]')) {
            return;
        }
        
        // Test API accessibility first
        fetch('api/notifications.php?action=get_unread_count', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (response.ok) {
                    // Initial update
                    this.refreshCount();
                    
                    // Set up regular updates
                    setInterval(() => {
                        this.refreshCount();
                    }, 30000); // Check every 30 seconds
                }
            })
            .catch(() => {
                // API not accessible, skip updates
                console.log('Notifications API not accessible');
            });
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're logged in and have notification elements
    if (document.querySelector('.sidebar') || document.querySelector('.navbar')) {
        // Small delay to ensure PHP has rendered the initial elements
        setTimeout(() => {
            NotificationManager.init();
        }, 100);
    }
});

// Legacy support - make it available globally for other scripts
window.updateNotificationCount = function(count) {
    NotificationManager.updateNotificationCount(count);
};