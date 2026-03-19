// Simple sidebar toggle for mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.navbar-toggler');
    
    if (sidebar && toggleBtn) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Simple fade-in animation for page content
document.addEventListener('DOMContentLoaded', function() {
    const main = document.querySelector('main');
    if (main) {
        main.classList.add('fade-in');
    }
});

// iOS-style checkbox animations
document.addEventListener('DOMContentLoaded', function() {
    // add animations to checkboxes
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    
    checkboxes.forEach(checkbox => {
        // ripple effect on click
        checkbox.addEventListener('click', function(e) {
            createRipple(e, this);
        });
        
        // bounce on focus
        checkbox.addEventListener('focus', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        checkbox.addEventListener('blur', function() {
            if (!this.checked) {
                this.style.transform = 'scale(1)';
            }
        });
    });
    
    // select-all functionality
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            const isChecked = this.checked;
            
            // animate each checkbox with delay
            productCheckboxes.forEach((checkbox, index) => {
                setTimeout(() => {
                    checkbox.checked = isChecked;
                    checkbox.dispatchEvent(new Event('change'));
                    
                    // pulse effect
                    checkbox.style.animation = 'none';
                    setTimeout(() => {
                        checkbox.style.animation = '';
                    }, 10);
                }, index * 50);
            });
        });
    }
    
    // individual checkbox animations
    const productCheckboxes = document.querySelectorAll('.product-checkbox');
    productCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // scale animation
            this.style.transform = this.checked ? 'scale(1.1)' : 'scale(1)';
            
            // reset after animation
            setTimeout(() => {
                this.style.transform = this.checked ? 'scale(1.05)' : 'scale(1)';
            }, 200);
        });
    });
});

// ripple effect for checkboxes
function createRipple(event, element) {
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    const ripple = document.createElement('div');
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(0, 122, 255, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: rippleEffect 0.6s ease-out;
        pointer-events: none;
        z-index: 1000;
    `;
    
    element.style.position = 'relative';
    element.appendChild(ripple);
    
    // remove ripple after animation
    setTimeout(() => {
        if (ripple.parentNode) {
            ripple.parentNode.removeChild(ripple);
        }
    }, 600);
}

// add ripple animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes rippleEffect {
        0% {
            transform: scale(0);
            opacity: 1;
        }
        100% {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);