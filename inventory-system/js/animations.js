// Enhanced Animations and Interactions
document.addEventListener('DOMContentLoaded', function() {
    
    // Add stagger animation classes to elements
    function addStaggerAnimation() {
        const cards = document.querySelectorAll('.card');
        const tableRows = document.querySelectorAll('tbody tr');
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        
        cards.forEach((card, index) => {
            card.classList.add('stagger-item');
            card.style.animationDelay = `${index * 0.1}s`;
        });
        
        tableRows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
        });
        
        navLinks.forEach((link, index) => {
            link.style.animationDelay = `${index * 0.1}s`;
        });
    }
    
    // Scroll reveal animation
    function initScrollReveal() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.scroll-reveal').forEach(el => {
            observer.observe(el);
        });
    }
    
    // Add hover effects to buttons
    function enhanceButtons() {
        const buttons = document.querySelectorAll('.btn');
        
        buttons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.classList.add('micro-bounce');
            });
            
            button.addEventListener('animationend', function() {
                this.classList.remove('micro-bounce');
            });
            
            button.addEventListener('click', function() {
                this.classList.add('scale-click');
                setTimeout(() => {
                    this.classList.remove('scale-click');
                }, 150);
            });
        });
    }
    
    // Add floating animation to icons
    function floatingIcons() {
        const icons = document.querySelectorAll('.card-icon, .bot-avatar');
        
        icons.forEach((icon, index) => {
            icon.style.animationDelay = `${index * 0.5}s`;
            icon.classList.add('float');
        });
    }
    
    // Enhanced form interactions
    function enhanceFormInputs() {
        const inputs = document.querySelectorAll('.form-control, .form-select');
        
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
            
            // Add typing animation to inputs
            input.addEventListener('input', function() {
                this.classList.add('typing');
                clearTimeout(this.typingTimer);
                this.typingTimer = setTimeout(() => {
                    this.classList.remove('typing');
                }, 500);
            });
        });
    }
    
    // Add success animation to form submissions
    function addSuccessAnimation() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<div class="loading-spinner"></div> Processing...';
                    submitBtn.disabled = true;
                }
            });
        });
    }
    
    // Add notification animations
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} notification`;
        notification.innerHTML = `
            <i class="bi bi-check-circle"></i>
            ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'notificationSlide 0.5s reverse';
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    }
    
    // Add ripple effect to clickable elements
    function addRippleEffect() {
        const rippleElements = document.querySelectorAll('.btn, .card, .nav-link');
        
        rippleElements.forEach(element => {
            element.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s ease-out;
                    pointer-events: none;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
    }
    
    // Add CSS for ripple animation
    const rippleCSS = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .typing {
            animation: inputGlow 0.3s ease;
        }
        
        @keyframes inputGlow {
            0% { box-shadow: 0 0 5px rgba(59, 130, 246, 0.3); }
            100% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.1); }
        }
        
        .focused .form-label {
            color: #3b82f6;
            transform: translateY(-2px);
        }
    `;
    
    const style = document.createElement('style');
    style.textContent = rippleCSS;
    document.head.appendChild(style);
    
    // Add loading animation to page transitions
    function addPageTransitions() {
        const links = document.querySelectorAll('a[href]:not([href^="#"]):not([href^="javascript:"]):not([target="_blank"])');
        
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.hostname === window.location.hostname) {
                    e.preventDefault();
                    
                    // Add loading overlay
                    const overlay = document.createElement('div');
                    overlay.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(248, 250, 252, 0.9);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 9999;
                        animation: fadeIn 0.3s ease;
                    `;
                    overlay.innerHTML = '<div class="loading-spinner" style="width: 40px; height: 40px;"></div>';
                    
                    document.body.appendChild(overlay);
                    
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 300);
                }
            });
        });
    }
    
    // Add number counter animation
    function animateNumbers() {
        const numbers = document.querySelectorAll('.card-value, .badge');
        
        numbers.forEach(number => {
            const text = number.textContent;
            const numMatch = text.match(/[\d,]+/);
            
            if (numMatch) {
                const finalNumber = parseInt(numMatch[0].replace(/,/g, ''));
                let currentNumber = 0;
                const increment = finalNumber / 50;
                const timer = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= finalNumber) {
                        currentNumber = finalNumber;
                        clearInterval(timer);
                    }
                    number.textContent = text.replace(/[\d,]+/, Math.floor(currentNumber).toLocaleString());
                }, 30);
            }
        });
    }
    
    // Initialize all animations
    addStaggerAnimation();
    initScrollReveal();
    enhanceButtons();
    floatingIcons();
    enhanceFormInputs();
    addSuccessAnimation();
    addRippleEffect();
    addPageTransitions();
    
    // Animate numbers on page load
    setTimeout(animateNumbers, 500);
    
    // Add smooth scrolling
    document.documentElement.style.scrollBehavior = 'smooth';
    
    // Add page load animation
    document.body.style.animation = 'fadeInBody 0.8s ease-out';
    
    // Expose notification function globally
    window.showNotification = showNotification;
});

// Add CSS animations for enhanced effects
const additionalCSS = `
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .page-loading {
        animation: pageSlideIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .hover-lift:hover {
        transform: translateY(-4px);
        transition: transform 0.3s ease;
    }
    
    .pulse-on-hover:hover {
        animation: pulse 1s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
`;

// Add the additional CSS
const additionalStyle = document.createElement('style');
additionalStyle.textContent = additionalCSS;
document.head.appendChild(additionalStyle);