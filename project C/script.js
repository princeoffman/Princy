// Global variables
let currentUser = null;
let currentChatUser = null;
let conversations = [];
let activeModal = null;
let originalHomeContent = null; // Store original home content

// Time tracking
let seconds = 0, minutes = 0, hours = 0;
let timeInterval;

// Initialize the app
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing ProjectConnect...');

    // Clear Kid Mode lock state on every page load (non-persistent)
    localStorage.removeItem('kidModeLocked');
    localStorage.removeItem('kidModeStartTime');

    updateTimeDisplay();
    startTimeTracking();
    setupAuthentication();
    setupNavigation();
    loadProjects();
    setupEventListeners();
    setupModalClosers();
    setupUserProfileClick();
    setupThemeToggle();

    // Store original home content
    originalHomeContent = document.querySelector('.main-content').innerHTML;

    // Check if user is already logged in
    checkExistingLogin();
});

// Fix modal closing functionality
function setupModalClosers() {
    console.log('Setting up modal closers...');
    
    // Close modals when clicking X button
    document.querySelectorAll('.close-modal').forEach(closeBtn => {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('Close button clicked');
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
                activeModal = null;
            }
        });
    });
    
    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            console.log('Clicked outside modal');
            e.target.style.display = 'none';
            activeModal = null;
        }
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && activeModal) {
            console.log('Escape key pressed');
            activeModal.style.display = 'none';
            activeModal = null;
        }
    });
}

// Show modal function
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        activeModal = modal;
    }
}

// Authentication functions
function setupAuthentication() {
    const authModal = document.getElementById('authModal');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const authTabs = document.querySelectorAll('.auth-tab');

    // Setup login button
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', function() {
            console.log('Login button clicked');
            showModal('authModal');
        });
    } else {
        console.error('Login button not found');
    }

    // Show auth modal when page loads (if not logged in)
    if (!currentUser) {
        setTimeout(() => {
            showModal('authModal');
        }, 1000);
    }
    
    // Tab switching
    authTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Update active tab
            authTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update modal title
            document.getElementById('authModalTitle').textContent = 
                tabName === 'login' ? 'Login to ProjectConnect' : 'Create Account';
            
            // Show correct form
            document.querySelectorAll('.auth-form').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(tabName + 'Form').classList.add('active');
        });
    });
    
    // Login form submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ username, password })
            });
            
            const result = await response.json();
            
            if (result.success) {
                currentUser = result.user;
                localStorage.setItem('currentUser', JSON.stringify(currentUser));
                updateUIAfterLogin();
                document.getElementById('authModal').style.display = 'none';
                loginForm.reset();
                alert('Login successful! Welcome back, ' + currentUser.full_name);
            } else {
                alert('Login failed: ' + result.message);
            }
        } catch (error) {
            console.error('Login error:', error);
            alert('Could not reach the server. Check your connection and try again.');
        }
    });
    
    // Register form submission
    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const fullName = document.getElementById('regFullName').value;
        const username = document.getElementById('regUsername').value;
        const email = document.getElementById('regEmail').value;
        const password = document.getElementById('regPassword').value;
        
        try {
            const response = await fetch('register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    full_name: fullName,
                    username: username,
                    email: email,
                    password: password
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Registration successful! Please login with your credentials.');
                // Switch to login tab
                authTabs[0].click();
                registerForm.reset();
            } else {
                alert('Registration failed: ' + result.message);
            }
        } catch (error) {
            console.error('Registration error:', error);
            alert('Could not reach the server. Check your connection and try again.');
        }
    });
}

function checkExistingLogin() {
    const savedUser = localStorage.getItem('currentUser');
    if (savedUser) {
        currentUser = JSON.parse(savedUser);
        updateUIAfterLogin();
    } else {
        // Hide user profile if not logged in
        const userProfile = document.querySelector('.user-profile');
        if (userProfile) userProfile.style.display = 'none';
    }
}

function updateUIAfterLogin() {
    // Update header
    const userProfile = document.querySelector('.user-profile');
    if (userProfile) {
        // Clone and replace to clear existing event listeners
        const newUserProfile = userProfile.cloneNode(false);
        newUserProfile.innerHTML = `
            <img src="${currentUser.avatar}" alt="${currentUser.full_name}">
            <div class="user-info">
                <h4>${currentUser.full_name}</h4>
                <p>@${currentUser.username}</p>
            </div>
        `;

        // Add click event to open profile modal
        newUserProfile.addEventListener('click', function() {
            console.log('User profile clicked');
            showProfileSection();
        });

        // Replace the old element
        userProfile.parentNode.replaceChild(newUserProfile, userProfile);
    }

    // Keep login button visible and show user profile
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn) loginBtn.style.display = 'block'; // Keep login button visible
    if (userProfile) userProfile.style.display = 'flex';

    // Store the updated home content with user info
    originalHomeContent = document.querySelector('.main-content').innerHTML;

    // Update navigation active states
    updateNavigation();
}

// Navigation functions
function setupNavigation() {
    const navLinks = document.querySelectorAll('.nav-links a');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            navLinks.forEach(l => l.classList.remove('active'));
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Handle different navigation items
            const linkText = this.querySelector('span').textContent;
            handleNavigation(linkText);
        });
    });
}

function handleNavigation(section) {
    console.log('Navigating to:', section);
    switch(section) {
        case 'Home':
            showHomeSection();
            break;
        case 'Explore':
            showExploreSection();
            break;
        case 'Messages':
            showMessagesSection();
            break;
        case 'Notifications':
            showNotificationsSection();
            break;
        case 'Saved':
            showSavedSection();
            break;
        case 'Profile':
            showProfileSection();
            break;
        case 'Settings':
            showSettingsSection();
            break;
    }
}

function showHomeSection() {
    console.log('Showing home section');
    // Build home section HTML dynamically
    const mainContent = document.querySelector('.main-content');
    mainContent.innerHTML = `
        <div class="header">
            <h1 style="color: var(--text-color); font-size: 28px; font-weight: 700;">🏠 Welcome to ProjectConnect</h1>
            <div class="user-actions">
                <div class="notification">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <button id="themeToggle" class="theme-toggle" title="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="user-profile">
                    <img src="${currentUser ? currentUser.avatar : 'test_image.jpg'}" alt="User Avatar">
                    <div class="user-info">
                        <h4>${currentUser ? currentUser.full_name : 'Guest'}</h4>
                        <p>@${currentUser ? currentUser.username : 'guest'}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="projects-section">
            <div class="projects-grid">
                <!-- Projects will be loaded here -->
            </div>
        </div>
    `;

    // Setup theme toggle
    setupThemeToggle();

    // Update UI after login to attach user profile click handler
    if (currentUser) {
        updateUIAfterLogin();
    }

    // Load projects
    loadProjects();

    // Update navigation
    updateNavigation();
}

function showExploreSection() {
    console.log('Showing explore section');
    // Clear the main content and show explore page
    const mainContent = document.querySelector('.main-content');
    mainContent.innerHTML = `
        <div class="header">
            <h1 style="color: var(--text-color); font-size: 28px; font-weight: 700;">🔍 Explore Projects</h1>
            <div class="user-actions">
                <div class="notification">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <button id="themeToggle" class="theme-toggle" title="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="user-profile">
                    <img src="${currentUser ? currentUser.avatar : 'test_image.jpg'}" alt="User Avatar">
                </div>
            </div>
        </div>

        <div class="explore-section">
            <div class="explore-filters">
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="trending">Trending</button>
                    <button class="filter-tab" data-filter="new">New</button>
                    <button class="filter-tab" data-filter="categories">Categories</button>
                </div>
                <div class="search-explore">
                    <input type="text" placeholder="Search projects..." id="exploreSearch">
                    <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>

            <div class="explore-content">
                <div class="trending-projects">
                    <h2 class="section-title">🔥 Trending This Week</h2>
                    <div class="projects-grid">
                        <!-- Trending projects will be loaded here -->
                    </div>
                </div>

                <div class="project-categories" style="display: none;">
                    <h2 class="section-title">📂 Browse by Category</h2>
                    <div class="categories-grid">
                        <div class="category-card" data-category="technology">
                            <i class="fas fa-laptop-code"></i>
                            <h3>Technology</h3>
                            <p>Innovative tech solutions</p>
                        </div>
                        <div class="category-card" data-category="environment">
                            <i class="fas fa-leaf"></i>
                            <h3>Environment</h3>
                            <p>Sustainable and eco-friendly projects</p>
                        </div>
                        <div class="category-card" data-category="education">
                            <i class="fas fa-graduation-cap"></i>
                            <h3>Education</h3>
                            <p>Learning and teaching innovations</p>
                        </div>
                        <div class="category-card" data-category="health">
                            <i class="fas fa-heartbeat"></i>
                            <h3>Health</h3>
                            <p>Healthcare and wellness solutions</p>
                        </div>
                        <div class="category-card" data-category="social">
                            <i class="fas fa-users"></i>
                            <h3>Social Impact</h3>
                            <p>Projects making a difference</p>
                        </div>
                        <div class="category-card" data-category="design">
                            <i class="fas fa-palette"></i>
                            <h3>Design</h3>
                            <p>Creative design projects</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Re-attach theme toggle functionality
    setupThemeToggle();

    // Setup explore functionality
    setupExploreFilters();

    // Load trending projects
    loadTrendingProjects();

    // Update navigation
    updateNavigation();
}

function showMessagesSection() {
    console.log('Opening messages modal');
    showModal('messagesModal');
    loadConversations();
}

function showNotificationsSection() {
    alert('🔔 Notifications\n\nYou have 3 new notifications:\n• Sarah liked your project\n• Alex commented on your post\n• New message from Maria');
}

async function showSavedSection() {
    console.log('Showing saved projects section');

    if (!currentUser) {
        alert('Please login to view your saved projects');
        showModal('authModal');
        return;
    }

    // Clear the main content and show saved projects
    const mainContent = document.querySelector('.main-content');
    mainContent.innerHTML = `
        <div class="header">
            <h1 style="color: var(--text-color); font-size: 28px; font-weight: 700;">💾 Saved Projects</h1>
            <div class="user-actions">
                <div class="notification">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <button id="themeToggle" class="theme-toggle" title="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="user-profile">
                    <img src="${currentUser.avatar}" alt="${currentUser.full_name}">
                    <div class="user-info">
                        <h4>${currentUser.full_name}</h4>
                        <p>@${currentUser.username}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="saved-projects-section">
            <div class="saved-projects-grid">
                <!-- Saved projects will be loaded here -->
            </div>
            <div class="no-saved-projects" style="display: none;">
                <div class="empty-state">
                    <i class="fas fa-bookmark" style="font-size: 64px; color: var(--gray); margin-bottom: 20px;"></i>
                    <h2 style="color: var(--text-color); margin-bottom: 10px;">No Saved Projects Yet</h2>
                    <p style="color: var(--gray); margin-bottom: 20px;">Start exploring and save projects you love!</p>
                    <button class="btn btn-primary" onclick="showHomeSection()">Explore Projects</button>
                </div>
            </div>
        </div>
    `;

    // Re-attach theme toggle functionality
    setupThemeToggle();

    // Load saved projects
    await loadSavedProjects();

    // Update navigation
    updateNavigation();
}

function showProfileSection() {
    if (!currentUser) {
        alert('Please login to view your profile');
        showModal('authModal');
        return;
    }

    console.log('Showing profile modal...');
    console.log('Current user:', currentUser);

    // Show profile settings modal
    showModal('profileModal');

    // Use multiple checks to ensure modal is fully rendered
    let attempts = 0;
    const maxAttempts = 10;

    const checkModalReady = () => {
        attempts++;
        console.log(`Checking modal ready (attempt ${attempts})...`);

        const modal = document.getElementById('profileModal');
        const currentAvatar = document.getElementById('currentAvatar');
        const form = document.getElementById('profilePictureForm');

        console.log('Modal element:', modal);
        console.log('Modal display:', modal ? modal.style.display : 'null');
        console.log('Current avatar element:', currentAvatar);
        console.log('Form element:', form);

        if (modal && modal.style.display === 'flex' && currentAvatar && form) {
            console.log('Modal is ready, setting up profile...');

            // Set current avatar
            currentAvatar.src = currentUser.avatar;
            console.log('Avatar set to:', currentUser.avatar);

            // Setup profile picture upload with a small delay to ensure DOM is fully ready
            setTimeout(() => {
                setupProfilePictureUpload();
            }, 50);
        } else if (attempts < maxAttempts) {
            setTimeout(checkModalReady, 100);
        } else {
            console.error('Modal failed to render properly after', maxAttempts, 'attempts');
            alert('There was an issue loading the profile modal. Please try again.');
        }
    };

    // Start checking after a short delay
    setTimeout(checkModalReady, 100);
}

function showSettingsSection() {
    console.log('Opening settings modal');
    showModal('settingsModal');
    // Wait for modal to be displayed before setting up navigation
    setTimeout(() => {
        loadSettings();
        setupSettingsNavigation();
    }, 100);
}

function setupSettingsNavigation() {
    const navContainer = document.querySelector('.settings-nav');
    if (!navContainer) return;

    // Clone the entire nav to remove all existing listeners
    const newNav = navContainer.cloneNode(true);
    navContainer.parentNode.replaceChild(newNav, navContainer);

    const navItems = newNav.querySelectorAll('.settings-nav-item');
    const sections = document.querySelectorAll('.settings-section');

    navItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all nav items
            navItems.forEach(nav => nav.classList.remove('active'));
            // Add active class to clicked item
            this.classList.add('active');

            // Hide all sections
            sections.forEach(section => section.classList.remove('active'));

            // Show corresponding section
            const sectionId = this.getAttribute('data-section') + '-section';
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.classList.add('active');
            }
        });
    });
}

function loadSettings() {
    console.log('Loading settings...');

    // Load saved settings or use defaults
    const savedSettings = JSON.parse(localStorage.getItem('userSettings')) || {};

    // Account Settings
    if (currentUser) {
        const accountSection = document.getElementById('account-section');
        if (accountSection) {
            const usernameInput = accountSection.querySelector('input[value="' + currentUser.username + '"]');
            if (usernameInput) usernameInput.value = currentUser.username;

            const emailInput = accountSection.querySelector('input[type="email"]');
            if (emailInput) emailInput.value = currentUser.email;

            const fullNameInput = accountSection.querySelector('input[type="text"]');
            if (fullNameInput) fullNameInput.value = currentUser.full_name || '';

            const bioTextarea = accountSection.querySelector('textarea');
            if (bioTextarea) bioTextarea.value = currentUser.bio || 'Exploring amazing projects on ProjectConnect!';
        }
    }

    // Privacy Controls
    const profileVisibility = document.getElementById('profileVisibility');
    if (profileVisibility) profileVisibility.checked = savedSettings.profileVisibility !== false;

    const showOnlineStatus = document.getElementById('showOnlineStatus');
    if (showOnlineStatus) showOnlineStatus.checked = savedSettings.showOnlineStatus !== false;

    const allowDirectMessages = document.getElementById('allowDirectMessages');
    if (allowDirectMessages) allowDirectMessages.checked = savedSettings.allowDirectMessages !== false;

    // Notification Preferences
    const emailNotifications = document.getElementById('emailNotifications');
    if (emailNotifications) emailNotifications.checked = savedSettings.emailNotifications !== false;

    const pushNotifications = document.getElementById('pushNotifications');
    if (pushNotifications) pushNotifications.checked = savedSettings.pushNotifications !== false;

    const messageNotifications = document.getElementById('messageNotifications');
    if (messageNotifications) messageNotifications.checked = savedSettings.messageNotifications !== false;

    const projectUpdates = document.getElementById('projectUpdates');
    if (projectUpdates) projectUpdates.checked = savedSettings.projectUpdates !== false;

    // Theme Customization
    const currentTheme = localStorage.getItem('theme') || 'light';
    const lightRadio = document.querySelector('input[name="theme"][value="light"]');
    const darkRadio = document.querySelector('input[name="theme"][value="dark"]');
    if (currentTheme === 'dark') {
        if (darkRadio) darkRadio.checked = true;
    } else {
        if (lightRadio) lightRadio.checked = true;
    }

    const autoTheme = document.getElementById('autoTheme');
    if (autoTheme) {
        autoTheme.checked = savedSettings.autoTheme || false;
    }

    // Setup theme change listeners
    setupThemeListeners();

    // Setup save button
    const saveBtn = document.querySelector('#account-section .btn-primary');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveAccountSettings);
    }

    const savePrivacyBtn = document.querySelector('#privacy-section .btn-primary');
    if (savePrivacyBtn) {
        savePrivacyBtn.addEventListener('click', savePrivacySettings);
    }

    const saveNotificationsBtn = document.querySelector('#notifications-section .btn-primary');
    if (saveNotificationsBtn) {
        saveNotificationsBtn.addEventListener('click', saveNotificationSettings);
    }

    const applyThemeBtn = document.getElementById('applyTheme');
    if (applyThemeBtn) {
        applyThemeBtn.addEventListener('click', applyThemeSettings);
    }

    // Kid Mode Settings
    const kidModeEnabled = document.getElementById('kidModeEnabled');
    if (kidModeEnabled) kidModeEnabled.checked = savedSettings.kidModeEnabled || false;

    const kidModeTimeLimit = document.getElementById('kidModeTimeLimit');
    if (kidModeTimeLimit) kidModeTimeLimit.value = savedSettings.kidModeTimeLimit || 60;

    const kidModePassword = document.getElementById('kidModePassword');
    if (kidModePassword) kidModePassword.value = savedSettings.kidModePassword || '';

    const kidModeBlockMessages = document.getElementById('kidModeBlockMessages');
    if (kidModeBlockMessages) kidModeBlockMessages.checked = savedSettings.kidModeBlockMessages || false;

    const kidModeBlockProjects = document.getElementById('kidModeBlockProjects');
    if (kidModeBlockProjects) kidModeBlockProjects.checked = savedSettings.kidModeBlockProjects || false;

    const saveKidModeBtn = document.getElementById('saveKidModeSettings');
    if (saveKidModeBtn) {
        saveKidModeBtn.addEventListener('click', saveKidModeSettings);
    }
}

function setupThemeListeners() {
    // Radio button theme selection
    const themeRadios = document.querySelectorAll('input[name="theme"]');
    themeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                setTheme(this.value);
            }
        });
    });

    // Auto theme toggle
    const autoTheme = document.getElementById('autoTheme');
    if (autoTheme) {
        autoTheme.addEventListener('change', function() {
            if (this.checked) {
                // Detect system preference
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                setTheme(prefersDark ? 'dark' : 'light');
            }
        });
    }
}

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);

    // Update radio buttons
    const lightRadio = document.querySelector('input[name="theme"][value="light"]');
    const darkRadio = document.querySelector('input[name="theme"][value="dark"]');
    if (theme === 'dark') {
        darkRadio.checked = true;
    } else {
        lightRadio.checked = true;
    }
}

function saveAccountSettings() {
    const email = document.getElementById('account-section').querySelector('input[type="email"]').value;
    const fullName = document.getElementById('account-section').querySelector('input[type="text"]').value;
    const bio = document.getElementById('account-section').querySelector('textarea').value;

    if (currentUser) {
        currentUser.email = email;
        currentUser.full_name = fullName;
        currentUser.bio = bio;
        localStorage.setItem('currentUser', JSON.stringify(currentUser));
        updateUIAfterLogin();
    }

    alert('Account settings saved successfully!');
}

function savePrivacySettings() {
    const settings = {
        profileVisibility: document.getElementById('profileVisibility').checked,
        showOnlineStatus: document.getElementById('showOnlineStatus').checked,
        allowDirectMessages: document.getElementById('allowDirectMessages').checked
    };

    localStorage.setItem('userSettings', JSON.stringify({
        ...JSON.parse(localStorage.getItem('userSettings') || '{}'),
        ...settings
    }));

    alert('Privacy settings saved successfully!');
}

function saveNotificationSettings() {
    const settings = {
        emailNotifications: document.getElementById('emailNotifications').checked,
        pushNotifications: document.getElementById('pushNotifications').checked,
        messageNotifications: document.getElementById('messageNotifications').checked,
        projectUpdates: document.getElementById('projectUpdates').checked
    };

    localStorage.setItem('userSettings', JSON.stringify({
        ...JSON.parse(localStorage.getItem('userSettings') || '{}'),
        ...settings
    }));

    alert('Notification settings saved successfully!');
}

function applyThemeSettings() {
    const selectedTheme = document.querySelector('input[name="theme"]:checked').value;
    const autoTheme = document.getElementById('autoTheme').checked;

    setTheme(selectedTheme);

    const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
    settings.autoTheme = autoTheme;
    localStorage.setItem('userSettings', JSON.stringify(settings));

    alert('Theme settings applied successfully!');
}

function saveKidModeSettings() {
    console.log('Saving Kid Mode settings...');

    const kidModeEnabled = document.getElementById('kidModeEnabled').checked;
    const kidModeTimeLimit = parseInt(document.getElementById('kidModeTimeLimit').value) || 60;
    const kidModePassword = document.getElementById('kidModePassword').value;
    const kidModePasswordConfirm = document.getElementById('kidModePasswordConfirm').value;

    // Validate password if Kid Mode is enabled
    if (kidModeEnabled) {
        if (!kidModePassword) {
            alert('Please set a password for Kid Mode.');
            return;
        }
        if (kidModePassword !== kidModePasswordConfirm) {
            alert('Passwords do not match.');
            return;
        }
        if (kidModePassword.length < 4) {
            alert('Password must be at least 4 characters long.');
            return;
        }
    }

    // Save settings
    const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
    settings.kidModeEnabled = kidModeEnabled;
    settings.kidModeTimeLimit = kidModeTimeLimit;
    settings.kidModePassword = kidModeEnabled ? kidModePassword : '';

    localStorage.setItem('userSettings', JSON.stringify(settings));

    // Reset password confirmation field
    document.getElementById('kidModePasswordConfirm').value = '';

    // Update Kid Mode state
    updateKidModeState();

    alert('Kid Mode settings saved successfully!');
}

function setupTestKidModeLock() {
    console.log('Setting up Kid Mode lock...');

    // Load Kid Mode settings
    const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');

    // Check if Kid Mode is already locked (persists across page refreshes)
    const isLocked = localStorage.getItem('kidModeLocked') === 'true';
    console.log('Kid Mode locked status:', isLocked);

    if (isLocked) {
        // Show lock screen immediately if already locked
        showKidModeLockScreen();
        return;
    }

    if (settings.kidModeEnabled) {
        // Start monitoring time usage
        startKidModeMonitoring(settings.kidModeTimeLimit);
    }
}

function startKidModeMonitoring(timeLimitMinutes) {
    console.log('Starting Kid Mode monitoring with time limit:', timeLimitMinutes, 'minutes');

    // Convert minutes to total seconds
    const timeLimitSeconds = timeLimitMinutes * 60;

    // Check if time limit has been exceeded
    const startTime = localStorage.getItem('kidModeStartTime');
    if (startTime) {
        const elapsed = Math.floor((Date.now() - parseInt(startTime)) / 1000);
        if (elapsed >= timeLimitSeconds) {
            lockKidMode();
            return;
        }
    } else {
        // Set start time if not set
        localStorage.setItem('kidModeStartTime', Date.now().toString());
    }

    // Monitor time usage
    const kidModeInterval = setInterval(() => {
        const startTime = parseInt(localStorage.getItem('kidModeStartTime') || Date.now());
        const elapsed = Math.floor((Date.now() - startTime) / 1000);

        if (elapsed >= timeLimitSeconds) {
            clearInterval(kidModeInterval);
            lockKidMode();
        }
    }, 1000); // Check every second

    // Store interval ID for cleanup
    window.kidModeInterval = kidModeInterval;
}

function lockKidMode() {
    console.log('Locking Kid Mode...');
    localStorage.setItem('kidModeLocked', 'true');

    // Clear monitoring interval
    if (window.kidModeInterval) {
        clearInterval(window.kidModeInterval);
        window.kidModeInterval = null;
    }

    showKidModeLockScreen();
}

function showKidModeLockScreen() {
    console.log('Showing Kid Mode lock screen...');

    // Create lock screen overlay
    const lockScreen = document.createElement('div');
    lockScreen.id = 'kidModeLockScreen';
    lockScreen.style.position = 'fixed';
    lockScreen.style.top = '0';
    lockScreen.style.left = '0';
    lockScreen.style.width = '100%';
    lockScreen.style.height = '100%';
    lockScreen.style.backgroundColor = 'rgba(0, 0, 0, 0.9)';
    lockScreen.style.zIndex = '9999';
    lockScreen.style.display = 'flex';
    lockScreen.style.alignItems = 'center';
    lockScreen.style.justifyContent = 'center';
    lockScreen.style.color = 'white';
    lockScreen.style.fontSize = '24px';
    lockScreen.innerHTML = `
        <div style="text-align: center;">
            <h2>Kid Mode Locked</h2>
            <p>Time limit reached. Enter password to unlock:</p>
            <input type="password" id="unlockPassword" placeholder="Enter password" style="padding: 10px; font-size: 18px; margin: 10px;">
            <br>
            <button id="unlockBtn" style="padding: 10px 20px; font-size: 18px; margin: 10px;">Unlock</button>
            <div id="unlockError" style="color: red; margin-top: 10px;"></div>
        </div>
    `;

    document.body.appendChild(lockScreen);

    // Focus password input
    setTimeout(() => {
        const passwordInput = document.getElementById('unlockPassword');
        if (passwordInput) passwordInput.focus();
    }, 100);

    // Setup unlock functionality
    const unlockBtn = document.getElementById('unlockBtn');
    const passwordInput = document.getElementById('unlockPassword');

    const attemptUnlock = () => {
        const enteredPassword = passwordInput.value;
        const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
        const correctPassword = settings.kidModePassword;

        if (enteredPassword === correctPassword) {
            unlockKidMode();
        } else {
            showUnlockError('Incorrect password. Please try again.');
            passwordInput.value = '';
            passwordInput.focus();
        }
    };

    unlockBtn.addEventListener('click', attemptUnlock);
    passwordInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            attemptUnlock();
        }
    });
}

function showUnlockError(message) {
    const errorDiv = document.getElementById('unlockError');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
}

function unlockKidMode() {
    console.log('Unlocking Kid Mode...');

    // Clear lock state
    localStorage.removeItem('kidModeLocked');
    localStorage.setItem('kidModeStartTime', Date.now().toString());

    // Remove lock screen
    const lockScreen = document.getElementById('kidModeLockScreen');
    if (lockScreen) {
        lockScreen.remove();
    }

    // Restart monitoring
    const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');
    if (settings.kidModeEnabled) {
        startKidModeMonitoring(settings.kidModeTimeLimit);
    }

    // Play unlock sound (optional, with error handling)
    playUnlockSound().catch(error => {
        console.warn('Unlock sound failed to play:', error);
        // Continue with unlock even if sound fails
    });

    alert('Kid Mode unlocked! You can continue using the app.');
}

// Helper function to play unlock sound with proper error handling
async function playUnlockSound() {
    try {
        // Create a simple beep sound using Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        // Configure sound
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime); // 800Hz beep
        oscillator.type = 'sine';

        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

        // Play sound
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);

        // Wait for sound to complete
        await new Promise(resolve => {
            oscillator.onended = resolve;
        });

    } catch (error) {
        // Silently fail if audio is not supported or fails
        console.warn('Audio playback failed:', error);
        throw error; // Re-throw to be caught by caller
    }
}

function updateKidModeState() {
    console.log('Updating Kid Mode state...');

    const settings = JSON.parse(localStorage.getItem('userSettings') || '{}');

    if (settings.kidModeEnabled) {
        // Clear any existing lock
        localStorage.removeItem('kidModeLocked');

        // Start monitoring
        startKidModeMonitoring(settings.kidModeTimeLimit);
    } else {
        // Disable Kid Mode
        localStorage.removeItem('kidModeLocked');
        localStorage.removeItem('kidModeStartTime');

        // Clear monitoring interval
        if (window.kidModeInterval) {
            clearInterval(window.kidModeInterval);
            window.kidModeInterval = null;
        }

        // Remove lock screen if present
        const lockScreen = document.getElementById('kidModeLockScreen');
        if (lockScreen) {
            lockScreen.remove();
        }
    }
}

// Messages functionality
async function loadConversations() {
    console.log('Loading conversations...');
    const conversationsContainer = document.querySelector('.conversations');
    if (!conversationsContainer) {
        console.error('Conversations container not found');
        return;
    }

    try {
        // Load real users from database
        const response = await fetch('get_users.php' + (currentUser ? `?current_user_id=${currentUser.id}` : ''));
        const result = await response.json();

        if (result.success) {
            conversations = result.users.map(user => ({
                id: user.id,
                user: {
                    id: user.id,
                    name: user.name,
                    avatar: user.avatar,
                    status: user.status,
                    lastMessage: 'Click to start chatting',
                    timestamp: 'New'
                }
            }));

            conversationsContainer.innerHTML = '';

            conversations.forEach(conversation => {
                const conversationItem = document.createElement('div');
                conversationItem.className = 'conversation-item';
                conversationItem.innerHTML = `
                    <img src="${conversation.user.avatar}" alt="${conversation.user.name}" class="conversation-avatar">
                    <div class="conversation-info">
                        <h4>${conversation.user.name}</h4>
                        <p>${conversation.user.lastMessage}</p>
                    </div>
                `;

                conversationItem.addEventListener('click', () => {
                    console.log('Opening chat with:', conversation.user.name);
                    openChat(conversation.user);
                });

                conversationsContainer.appendChild(conversationItem);
            });
        } else {
            console.error('Failed to load users:', result.message);
            // Fallback to sample conversations
            loadSampleConversations();
        }
    } catch (error) {
        console.error('Error loading conversations:', error);
        // Fallback to sample conversations
        loadSampleConversations();
    }
}

function loadSampleConversations() {
    console.log('Loading sample conversations as fallback...');
    const conversationsContainer = document.querySelector('.conversations');
    if (!conversationsContainer) return;

    const sampleConversations = [
        {
            id: 1,
            user: {
                id: 1,
                name: 'Sarah Johnson',
                avatar: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80',
                status: 'online',
                lastMessage: 'Thanks for your interest in my project!',
                timestamp: '10:24 AM'
            }
        },
        {
            id: 2,
            user: {
                id: 2,
                name: 'Alex Chen',
                avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80',
                status: 'online',
                lastMessage: 'I can share more details about the implementation',
                timestamp: 'Yesterday'
            }
        },
        {
            id: 3,
            user: {
                id: 3,
                name: 'Maria Rodriguez',
                avatar: 'https://images.unsplash.com/photo-1544725176-7c40e5a71c5e?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80',
                status: 'offline',
                lastMessage: 'Let me know if you have any questions',
                timestamp: '2 days ago'
            }
        }
    ];

    conversations = sampleConversations;

    conversationsContainer.innerHTML = '';

    conversations.forEach(conversation => {
        const conversationItem = document.createElement('div');
        conversationItem.className = 'conversation-item';
        conversationItem.innerHTML = `
            <img src="${conversation.user.avatar}" alt="${conversation.user.name}" class="conversation-avatar">
            <div class="conversation-info">
                <h4>${conversation.user.name}</h4>
                <p>${conversation.user.lastMessage}</p>
            </div>
        `;

        conversationItem.addEventListener('click', () => {
            console.log('Opening chat with:', conversation.user.name);
            openChat(conversation.user);
        });

        conversationsContainer.appendChild(conversationItem);
    });
}

function openChat(user) {
    console.log('Opening chat with user:', user);
    currentChatUser = user;

    // Update chat header
    const chatUserAvatar = document.querySelector('#messagesModal .chat-user-avatar');
    const chatUserName = document.querySelector('#messagesModal .chat-user-name');
    const chatUserStatus = document.querySelector('#messagesModal .chat-user-status');

    if (chatUserAvatar) chatUserAvatar.src = user.avatar;
    if (chatUserName) chatUserName.textContent = user.name;
    if (chatUserStatus) {
        chatUserStatus.textContent = user.status === 'online' ? 'Online' : 'Offline';
        chatUserStatus.style.color = user.status === 'online' ? 'var(--success)' : 'var(--gray)';
    }

    // Show chat input
    const chatInputContainer = document.querySelector('#messagesModal .chat-input-container');
    if (chatInputContainer) chatInputContainer.style.display = 'block';

    // Load chat messages
    loadChatMessages(user.id);

    // Set up send message functionality
    setupChatSendMessage();
}

async function loadChatMessages(userId) {
    console.log('Loading messages for user:', userId);
    const chatMessages = document.querySelector('#messagesModal .chat-messages');
    if (!chatMessages) {
        console.error('Chat messages container not found');
        return;
    }

    // Clear any "no chat selected" message
    chatMessages.innerHTML = '';

    if (!currentUser) {
        console.error('No current user logged in');
        return;
    }

    try {
        // Load real messages from database
        const response = await fetch(`get_messages.php?sender_id=${currentUser.id}&receiver_id=${userId}`);
        const result = await response.json();

        if (result.success && result.messages.length > 0) {
            result.messages.forEach(message => {
                const messageElement = document.createElement('div');
                messageElement.className = `message ${message.type}`;
                const messageTime = new Date(message.sent_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                messageElement.innerHTML = `
                    <p>${message.message}</p>
                    <div class="message-time">${messageTime}</div>
                `;
                chatMessages.appendChild(messageElement);
            });
        } else {
            // No messages yet, show a welcome message
            const welcomeMsg = document.createElement('div');
            welcomeMsg.className = 'message received';
            welcomeMsg.innerHTML = `
                <p>Hi! Start a conversation by sending a message.</p>
                <div class="message-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
            `;
            chatMessages.appendChild(welcomeMsg);
        }
    } catch (error) {
        console.error('Error loading messages:', error);
        // Fallback to sample messages
        loadSampleMessages(userId);
    }

    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function loadSampleMessages(userId) {
    console.log('Loading sample messages as fallback for user:', userId);
    const chatMessages = document.querySelector('#messagesModal .chat-messages');
    if (!chatMessages) return;

    let messages = [];

    if (userId === 1) {
        messages = [
            { type: 'received', text: 'Hi there! Thanks for your interest in my Urban Gardening App project.', time: '10:24 AM' },
            { type: 'sent', text: 'Hello! I really like your project concept. Could you tell me more about the technical implementation?', time: '10:25 AM' },
            { type: 'received', text: 'Sure! It\'s built with React Native for cross-platform compatibility and uses a Node.js backend with MongoDB.', time: '10:26 AM' }
        ];
    } else if (userId === 2) {
        messages = [
            { type: 'received', text: 'Thanks for reaching out about my sustainable packaging project!', time: '09:15 AM' },
            { type: 'sent', text: 'The biodegradable packaging idea is brilliant! How long does it take to decompose?', time: '09:16 AM' },
            { type: 'received', text: 'It decomposes within 30 days in industrial composting facilities, or 90 days in home compost.', time: '09:17 AM' }
        ];
    } else {
        messages = [
            { type: 'received', text: 'Hi! I saw you checked out my AI tutoring platform.', time: '11:30 AM' },
            { type: 'sent', text: 'Yes! The adaptive learning feature is impressive. How does it personalize content?', time: '11:31 AM' },
            { type: 'received', text: 'It uses machine learning to analyze student performance and adjusts difficulty in real-time.', time: '11:32 AM' }
        ];
    }

    messages.forEach(message => {
        const messageElement = document.createElement('div');
        messageElement.className = `message ${message.type}`;
        messageElement.innerHTML = `
            <p>${message.text}</p>
            <div class="message-time">${message.time}</div>
        `;
        chatMessages.appendChild(messageElement);
    });
}

function setupChatSendMessage() {
    console.log('Setting up chat send message...');
    const chatInput = document.querySelector('#messagesModal .chat-input input');
    const sendBtn = document.querySelector('#messagesModal .chat-input .send-btn');

    if (!chatInput || !sendBtn) {
        console.error('Chat input or send button not found in messages modal');
        console.log('Available chat inputs:', document.querySelectorAll('.chat-input input'));
        console.log('Available send buttons:', document.querySelectorAll('.chat-input .send-btn'));
        return;
    }
    
    // Clear existing event listeners by cloning and replacing
    const newChatInput = chatInput.cloneNode(true);
    const newSendBtn = sendBtn.cloneNode(true);
    
    chatInput.parentNode.replaceChild(newChatInput, chatInput);
    sendBtn.parentNode.replaceChild(newSendBtn, sendBtn);
    
    const sendMessage = async () => {
        const message = newChatInput.value.trim();
        const chatMessages = document.querySelector('#messagesModal .chat-messages');

        if (message && currentChatUser && chatMessages && currentUser) {
            console.log('Attempting to send message:', message);
            console.log('Current user:', currentUser);
            console.log('Current chat user:', currentChatUser);

            try {
                // Add sent message to UI immediately (optimistic update)
                const now = new Date();
                const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

                const sentMsg = document.createElement('div');
                sentMsg.className = 'message sent';
                sentMsg.innerHTML = `
                    <p>${message}</p>
                    <div class="message-time">${timeString}</div>
                `;
                chatMessages.appendChild(sentMsg);
                chatMessages.scrollTop = chatMessages.scrollHeight;

                newChatInput.value = '';

                // Send message to database
                const response = await fetch('send_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        sender_id: currentUser.id,
                        receiver_id: currentChatUser.id,
                        message: message
                    })
                });

                console.log('Fetch response status:', response.status);
                const result = await response.json();
                console.log('API response:', result);

                if (result.success) {
                    console.log('Message sent successfully to database');
                } else {
                    console.error('API returned error:', result.message);
                    // Optionally show error but keep message in UI
                    alert('Failed to send message: ' + result.message);
                }
            } catch (error) {
                console.error('Send message error:', error);
                alert('Failed to send message. Please try again.');
            }
        } else {
            console.log('Missing required data for sending message');
            console.log('Message:', message);
            console.log('Current user:', currentUser);
            console.log('Current chat user:', currentChatUser);
        }
    };
    
    newSendBtn.addEventListener('click', sendMessage);
    newChatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    // Focus the input
    newChatInput.focus();
}

// Time tracking functions
function updateTimeDisplay() {
    const timeDisplay = document.querySelector('.time-display');
    if (timeDisplay) {
        timeDisplay.textContent = 
            `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    
    if (minutes >= 45) {
        const warning = document.querySelector('.time-warning');
        if (warning) warning.style.display = 'block';
    }
}

function startTimeTracking() {
    timeInterval = setInterval(() => {
        seconds++;
        if (seconds === 60) { 
            seconds = 0; 
            minutes++; 
            
            if (minutes === 45) {
                const warning = document.querySelector('.time-warning');
                if (warning) warning.style.display = 'block';
            }
        }
        if (minutes === 60) { 
            minutes = 0; 
            hours++; 
        }
        updateTimeDisplay();
    }, 1000);
}

// Project loading functions
async function loadProjects() {
    try {
        console.log('Loading projects...');
        const response = await fetch('get_projects.php');
        const result = await response.json();

        if (result.success && result.projects.length > 0) {
            displayProjects(result.projects);
        } else {
            displayProjects(getSampleProjects());
        }
    } catch (error) {
        console.error('Error loading projects:', error);
        displayProjects(getSampleProjects());
    }
}

function getSampleProjects() {
    return [
        {
            id: 1,
            user_id: 1,
            title: "Urban Gardening App",
            description: "A mobile application that helps urban residents grow their own food in small spaces with step-by-step guidance.",
            image: "https://images.unsplash.com/photo-1558655146-9f40138edfeb?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80",
            author_name: "Sarah Johnson",
            author_avatar: "https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80"
        },
        {
            id: 2,
            user_id: 2,
            title: "Sustainable Packaging",
            description: "Biodegradable packaging solution made from agricultural waste that decomposes within 30 days.",
            image: "https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80",
            author_name: "Alex Chen",
            author_avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80"
        },
        {
            id: 3,
            user_id: 3,
            title: "AI-Powered Tutoring",
            description: "An adaptive learning platform that personalizes educational content based on student performance and learning style.",
            image: "https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80",
            author_name: "Maria Rodriguez",
            author_avatar: "https://images.unsplash.com/photo-1544725176-7c40e5a71c5e?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80"
        }
    ];
}

function displayProjects(projects) {
    const grid = document.querySelector('.projects-grid');
    if (!grid) {
        console.error('Projects grid not found');
        return;
    }

    grid.innerHTML = '';

    projects.forEach(project => {
        const card = document.createElement('div');
        card.className = 'project-card';
        card.innerHTML = `
            <img src="${project.image}" alt="${project.title}" class="project-image">
            <div class="project-info">
                <h3 class="project-title">${project.title}</h3>
                <p class="project-description">${project.description}</p>
                <div class="project-meta">
                    <div class="project-author">
                        <img src="${project.author_avatar}" alt="${project.author_name}" class="author-avatar">
                        <span>${project.author_name}</span>
                    </div>
                    <div class="project-likes">
                        <i class="far fa-heart like-btn" data-project-id="${project.id}"></i> ${project.like_count || 0}
                    </div>
                </div>
                <div class="project-actions">
                    <button class="btn btn-outline save-project-btn" data-project-id="${project.id}"><i class="far fa-bookmark"></i> Save</button>
                    <button class="btn btn-primary message-user"
                            data-user="${project.author_name}"
                            data-user-id="${project.user_id}">
                        <i class="far fa-comment"></i> Message
                    </button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });

    attachProjectMessageListeners();
    attachSaveProjectListeners();
    attachLikeProjectListeners();
}

function toggleSidebar() {
    console.log('Toggling sidebar...');
    const rightSidebar = document.getElementById('rightSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleIcon = toggleBtn ? toggleBtn.querySelector('i') : null;

    if (rightSidebar) {
        const isCollapsed = rightSidebar.classList.contains('collapsed');
        rightSidebar.classList.toggle('collapsed');

        // Update icon direction
        if (toggleIcon) {
            if (isCollapsed) {
                // Was collapsed, now expanding - show right chevron (to collapse)
                toggleIcon.className = 'fas fa-chevron-right';
            } else {
                // Was expanded, now collapsing - show left chevron (to expand)
                toggleIcon.className = 'fas fa-chevron-left';
            }
        }

        console.log('Sidebar toggled, collapsed class:', rightSidebar.classList.contains('collapsed'));
    } else {
        console.error('Right sidebar not found');
    }
}

function setupEventListeners() {
    console.log('Setting up event listeners...');

    // Fact-check button
    const factCheckBtn = document.querySelector('.fact-check-btn');
    if (factCheckBtn) {
        factCheckBtn.addEventListener('click', function() {
            const fact = prompt('Enter a statement to fact-check:');
            if (fact) {
                alert(`🔍 Fact-checking: "${fact}"\n\nIn a production environment, this would connect to verification APIs or databases.`);
            }
        });
    }

    // Sidebar toggle button
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            toggleSidebar();
        });
    }

    // Community Hub toggle button
    const communityHubToggle = document.getElementById('communityHubToggle');
    if (communityHubToggle) {
        communityHubToggle.addEventListener('click', function() {
            toggleSidebar();
        });
    }

    // Video conference button
    const startVideoBtn = document.getElementById('startVideoBtn');
    if (startVideoBtn) {
        startVideoBtn.addEventListener('click', function() {
            startVideoConference();
        });
    }

    // Audio conference button
    const startAudioBtn = document.getElementById('startAudioBtn');
    if (startAudioBtn) {
        startAudioBtn.addEventListener('click', function() {
            startAudioConference();
        });
    }

    // Stop media button
    const stopMediaBtn = document.getElementById('stopMediaBtn');
    if (stopMediaBtn) {
        stopMediaBtn.addEventListener('click', function() {
            stopMediaStream();
        });
    }

    // Quick Actions buttons
    const addProjectBtn = document.getElementById('addProjectBtn');
    if (addProjectBtn) {
        addProjectBtn.addEventListener('click', function() {
            showAddProjectModal();
        });
    }

    const addContactBtn = document.getElementById('addContactBtn');
    if (addContactBtn) {
        addContactBtn.addEventListener('click', function() {
            showAddContactModal();
        });
    }

    const viewContactsBtn = document.getElementById('viewContactsBtn');
    if (viewContactsBtn) {
        viewContactsBtn.addEventListener('click', function() {
            showViewContactsModal();
        });
    }

    // Time display click to test Kid Mode lock
    const timeDisplay = document.querySelector('.time-display');
    if (timeDisplay) {
        timeDisplay.style.cursor = 'pointer';
        timeDisplay.title = 'Click to test Kid Mode lock';
        timeDisplay.addEventListener('click', function() {
            if (confirm('Test Kid Mode lock? This will simulate time limit reached.')) {
                lockKidMode();
            }
        });
    }

    // Kid Mode save button
    const saveKidModeBtn = document.getElementById('saveKidMode');
    if (saveKidModeBtn) {
        saveKidModeBtn.addEventListener('click', saveKidModeSettings);
    }

    // Project message buttons
    attachProjectMessageListeners();

    // Save project buttons
    attachSaveProjectListeners();
}

function attachProjectMessageListeners() {
    console.log('Attaching project message listeners...');
    document.querySelectorAll('.message-user').forEach(button => {
        // Remove existing listeners
        button.replaceWith(button.cloneNode(true));
    });

    // Re-attach listeners
    document.querySelectorAll('.message-user').forEach(button => {
        button.addEventListener('click', function() {
            if (!currentUser) {
                alert('Please login to send messages');
                showModal('authModal');
                return;
            }

            const userName = this.getAttribute('data-user');
            const userId = this.getAttribute('data-user-id');

            console.log('Project message button clicked:', userName, userId);

            // Find the user in conversations or create a new one
            let user = conversations.find(conv => conv.user.id == userId)?.user;
            if (!user) {
                user = {
                    id: parseInt(userId),
                    name: userName,
                    avatar: this.closest('.project-card').querySelector('.author-avatar').src,
                    status: 'online'
                };
            }

            openChat(user);
            showModal('messagesModal');
        });
    });
}

function attachSaveProjectListeners() {
    console.log('Attaching save project listeners...');
    document.querySelectorAll('.save-project-btn').forEach(button => {
        // Remove existing listeners
        button.replaceWith(button.cloneNode(true));
    });

    // Re-attach listeners
    document.querySelectorAll('.save-project-btn').forEach(button => {
        button.addEventListener('click', async function() {
            if (!currentUser) {
                alert('Please login to save projects');
                showModal('authModal');
                return;
            }

            const projectId = this.getAttribute('data-project-id');

            try {
                const response = await fetch('save_project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: currentUser.id,
                        project_id: projectId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    const icon = this.querySelector('i');
                    if (result.saved) {
                        this.innerHTML = '<i class="fas fa-bookmark"></i> Saved';
                        this.classList.add('saved');
                    } else {
                        this.innerHTML = '<i class="far fa-bookmark"></i> Save';
                        this.classList.remove('saved');
                    }
                    alert(result.message);
                } else {
                    alert('Failed to save project: ' + result.message);
                }
            } catch (error) {
                console.error('Save project error:', error);
                alert('Failed to save project. Please try again.');
            }
        });
    });
}

function attachLikeProjectListeners() {
    console.log('Attaching like project listeners...');
    document.querySelectorAll('.like-btn').forEach(button => {
        // Remove existing listeners
        button.replaceWith(button.cloneNode(true));
    });

    // Re-attach listeners
    document.querySelectorAll('.like-btn').forEach(button => {
        button.addEventListener('click', async function() {
            if (!currentUser) {
                alert('Please login to like projects');
                showModal('authModal');
                return;
            }

            const projectId = this.getAttribute('data-project-id');
            const likeCountElement = this.nextSibling;

            try {
                const response = await fetch('like_project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: currentUser.id,
                        project_id: projectId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Update the like count
                    if (likeCountElement && likeCountElement.nodeType === Node.TEXT_NODE) {
                        likeCountElement.textContent = ' ' + result.like_count;
                    }

                    // Update the icon
                    if (result.liked) {
                        this.className = 'fas fa-heart like-btn liked';
                    } else {
                        this.className = 'far fa-heart like-btn';
                    }

                    // Optional: Show a brief feedback
                    console.log(result.message);
                } else {
                    alert('Failed to like project: ' + result.message);
                }
            } catch (error) {
                console.error('Like project error:', error);
                alert('Failed to like project. Please try again.');
            }
        });
    });
}

// Update navigation active states
function updateNavigation() {
    const navLinks = document.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        if (link.querySelector('span').textContent === 'Home') {
            link.classList.add('active');
        }
    });
}

// Profile picture upload functionality
function setupProfilePictureUpload() {
    console.log('Setting up profile picture upload...');

    const form = document.getElementById('profilePictureForm');
    const statusDiv = document.getElementById('uploadStatus');

    console.log('Form element:', form);
    console.log('Status div element:', statusDiv);

    if (!form) {
        console.error('Profile picture form not found - checking modal state...');
        const modal = document.getElementById('profileModal');
        console.log('Modal element:', modal);
        console.log('Modal display:', modal ? modal.style.display : 'null');
        console.log('All forms in modal:', modal ? modal.querySelectorAll('form') : 'null');
        return;
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const fileInput = document.getElementById('profilePicture');
        const file = fileInput.files[0];

        if (!file) {
            showUploadStatus('Please select a file to upload.', 'error');
            return;
        }

        // Add user_id to form data for testing
        if (currentUser && currentUser.id) {
            formData.append('user_id', currentUser.id);
        }

        // Show loading status
        showUploadStatus('Uploading...', 'info');

        try {
            const response = await fetch('upload_profile_picture.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Update current user avatar
                currentUser.avatar = result.avatar_url;
                localStorage.setItem('currentUser', JSON.stringify(currentUser));

                // Update UI
                updateUIAfterLogin();

                // Update profile modal avatar
                const currentAvatar = document.getElementById('currentAvatar');
                if (currentAvatar) {
                    currentAvatar.src = result.avatar_url;
                }

                showUploadStatus('Profile picture updated successfully!', 'success');

                // Clear file input
                fileInput.value = '';

                // Auto-close modal after success
                setTimeout(() => {
                    document.getElementById('profileModal').style.display = 'none';
                }, 2000);
            } else {
                showUploadStatus(result.message, 'error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            showUploadStatus('Upload failed. Please try again.', 'error');
        }
    });

    function showUploadStatus(message, type) {
        if (statusDiv) {
            statusDiv.textContent = message;
            statusDiv.className = `upload-status ${type}`;
            statusDiv.style.display = 'block';
        }
    }
}

// User profile click functionality
function setupUserProfileClick() {
    console.log('Setting up user profile click functionality...');
    // This function is called during initialization, but the actual click handler
    // is set up in updateUIAfterLogin() to ensure it's attached after login
}

// Theme toggle functionality
function setupThemeToggle() {
    console.log('Setting up theme toggle...');
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) {
        console.error('Theme toggle button not found');
        return;
    }

    // Load saved theme preference
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);

    // Add click event listener
    themeToggle.addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        setTheme(newTheme);
    });

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);

        // Update button icon
        const icon = themeToggle.querySelector('i');
        if (icon) {
            if (theme === 'dark') {
                icon.className = 'fas fa-sun'; // Sun icon for switching to light mode
                themeToggle.title = 'Switch to Light Mode';
            } else {
                icon.className = 'fas fa-moon'; // Moon icon for switching to dark mode
                themeToggle.title = 'Switch to Dark Mode';
            }
        }
    }
}

// Load saved projects function
async function loadSavedProjects() {
    console.log('Loading saved projects...');

    if (!currentUser) {
        console.error('No current user logged in');
        return;
    }

    const grid = document.querySelector('.saved-projects-grid');
    const noSavedDiv = document.querySelector('.no-saved-projects');

    if (!grid) {
        console.error('Saved projects grid not found');
        return;
    }

    try {
        const response = await fetch(`get_saved_projects.php?user_id=${currentUser.id}`);
        const result = await response.json();

        if (result.success && result.projects.length > 0) {
            grid.innerHTML = '';
            noSavedDiv.style.display = 'none';

            result.projects.forEach(project => {
                const card = document.createElement('div');
                card.className = 'project-card';
                card.innerHTML = `
                    <img src="${project.image}" alt="${project.title}" class="project-image">
                    <div class="project-info">
                        <h3 class="project-title">${project.title}</h3>
                        <p class="project-description">${project.description}</p>
                        <div class="project-meta">
                            <div class="project-author">
                                <img src="${project.author_avatar}" alt="${project.author_name}" class="author-avatar">
                                <span>${project.author_name}</span>
                            </div>
                            <div class="saved-date">
                                <i class="fas fa-calendar-alt"></i> Saved ${new Date(project.saved_at).toLocaleDateString()}
                            </div>
                        </div>
                        <div class="project-actions">
                            <button class="btn btn-outline saved" onclick="unsaveProject(${project.id})">
                                <i class="fas fa-bookmark"></i> Saved
                            </button>
                            <button class="btn btn-primary message-user"
                                    data-user="${project.author_name}"
                                    data-user-id="${project.user_id}">
                                <i class="far fa-comment"></i> Message
                            </button>
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });

            // Re-attach message listeners for saved projects
            attachProjectMessageListeners();
        } else {
            grid.innerHTML = '';
            noSavedDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading saved projects:', error);
        grid.innerHTML = '<p style="color: var(--danger); text-align: center;">Failed to load saved projects. Please try again.</p>';
        noSavedDiv.style.display = 'none';
    }
}

// Function to unsave a project
async function unsaveProject(projectId) {
    if (!currentUser) {
        alert('Please login to unsave projects');
        return;
    }

    try {
        const response = await fetch('save_project.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: currentUser.id,
                project_id: projectId
            })
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            // Reload saved projects
            await loadSavedProjects();
        } else {
            alert('Failed to unsave project: ' + result.message);
        }
    } catch (error) {
        console.error('Unsave project error:', error);
        alert('Failed to unsave project. Please try again.');
    }
}

// Explore functionality
function setupExploreFilters() {
    console.log('Setting up explore filters...');

    // Tab switching
    const filterTabs = document.querySelectorAll('.filter-tab');
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            filterTabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');

            const filter = this.getAttribute('data-filter');

            // Show/hide content based on filter
            const trendingSection = document.querySelector('.trending-projects');
            const categoriesSection = document.querySelector('.project-categories');

            if (filter === 'categories') {
                trendingSection.style.display = 'none';
                categoriesSection.style.display = 'block';
            } else {
                trendingSection.style.display = 'block';
                categoriesSection.style.display = 'none';
                if (filter === 'trending') {
                    loadTrendingProjects();
                } else if (filter === 'new') {
                    loadNewProjects();
                }
            }
        });
    });

    // Search functionality
    const searchInput = document.getElementById('exploreSearch');
    const searchBtn = document.querySelector('.search-explore .btn-primary');

    if (searchInput && searchBtn) {
        const performSearch = () => {
            const query = searchInput.value.trim();
            if (query) {
                searchProjects(query);
            }
        };

        searchBtn.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }

    // Category card clicks
    const categoryCards = document.querySelectorAll('.category-card');
    categoryCards.forEach(card => {
        card.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            filterProjectsByCategory(category);
        });
    });
}

async function loadTrendingProjects() {
    console.log('Loading trending projects...');
    const grid = document.querySelector('.trending-projects .projects-grid');

    if (!grid) {
        console.error('Trending projects grid not found');
        return;
    }

    try {
        // Try to load from database first
        const response = await fetch('get_projects.php?sort=trending');
        const result = await response.json();

        if (result.success && result.projects.length > 0) {
            displayProjectsInGrid(result.projects, grid);
        } else {
            // Fallback to sample trending projects
            const trendingProjects = getSampleProjects().map(project => ({
                ...project,
                trending_score: Math.floor(Math.random() * 100) + 50
            })).sort((a, b) => b.trending_score - a.trending_score);

            displayProjectsInGrid(trendingProjects, grid);
        }
    } catch (error) {
        console.error('Error loading trending projects:', error);
        // Fallback to sample projects
        const trendingProjects = getSampleProjects().map(project => ({
            ...project,
            trending_score: Math.floor(Math.random() * 100) + 50
        })).sort((a, b) => b.trending_score - a.trending_score);

        displayProjectsInGrid(trendingProjects, grid);
    }
}

async function loadNewProjects() {
    console.log('Loading new projects...');
    const grid = document.querySelector('.trending-projects .projects-grid');

    if (!grid) {
        console.error('New projects grid not found');
        return;
    }

    try {
        // Try to load from database first
        const response = await fetch('get_projects.php?sort=new');
        const result = await response.json();

        if (result.success && result.projects.length > 0) {
            displayProjectsInGrid(result.projects, grid);
        } else {
            // Fallback to sample new projects (reverse order to simulate newest first)
            const newProjects = [...getSampleProjects()].reverse();
            displayProjectsInGrid(newProjects, grid);
        }
    } catch (error) {
        console.error('Error loading new projects:', error);
        // Fallback to sample projects
        const newProjects = [...getSampleProjects()].reverse();
        displayProjectsInGrid(newProjects, grid);
    }
}

async function searchProjects(query) {
    console.log('Searching projects:', query);
    const grid = document.querySelector('.trending-projects .projects-grid');

    if (!grid) {
        console.error('Search results grid not found');
        return;
    }

    try {
        // Try to search from database first
        const response = await fetch(`get_projects.php?search=${encodeURIComponent(query)}`);
        const result = await response.json();

        if (result.success && result.projects.length > 0) {
            displayProjectsInGrid(result.projects, grid);
        } else {
            // Fallback to client-side search
            const allProjects = getSampleProjects();
            const filteredProjects = allProjects.filter(project =>
                project.title.toLowerCase().includes(query.toLowerCase()) ||
                project.description.toLowerCase().includes(query.toLowerCase()) ||
                project.author_name.toLowerCase().includes(query.toLowerCase())
            );

            if (filteredProjects.length > 0) {
                displayProjectsInGrid(filteredProjects, grid);
            } else {
                grid.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-search" style="font-size: 64px; color: var(--gray); margin-bottom: 20px;"></i>
                        <h2 style="color: var(--text-color); margin-bottom: 10px;">No Projects Found</h2>
                        <p style="color: var(--gray); margin-bottom: 20px;">Try different keywords or browse categories</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error searching projects:', error);
        // Fallback to client-side search
        const allProjects = getSampleProjects();
        const filteredProjects = allProjects.filter(project =>
            project.title.toLowerCase().includes(query.toLowerCase()) ||
            project.description.toLowerCase().includes(query.toLowerCase()) ||
            project.author_name.toLowerCase().includes(query.toLowerCase())
        );

        displayProjectsInGrid(filteredProjects, grid);
    }
}

function filterProjectsByCategory(category) {
    console.log('Filtering projects by category:', category);
    const grid = document.querySelector('.trending-projects .projects-grid');

    if (!grid) {
        console.error('Category filter grid not found');
        return;
    }

    // Define category keywords
    const categoryKeywords = {
        technology: ['app', 'software', 'tech', 'mobile', 'web', 'ai', 'artificial intelligence', 'machine learning'],
        environment: ['sustainable', 'eco', 'green', 'environment', 'gardening', 'packaging', 'biodegradable'],
        education: ['learning', 'education', 'tutoring', 'teaching', 'ai-powered', 'adaptive'],
        health: ['health', 'medical', 'wellness', 'fitness', 'healthcare'],
        social: ['social', 'community', 'impact', 'help', 'support'],
        design: ['design', 'ui', 'ux', 'creative', 'art', 'graphic']
    };

    const keywords = categoryKeywords[category] || [];
    const allProjects = getSampleProjects();

    const filteredProjects = allProjects.filter(project => {
        const searchText = (project.title + ' ' + project.description).toLowerCase();
        return keywords.some(keyword => searchText.includes(keyword));
    });

    if (filteredProjects.length > 0) {
        displayProjectsInGrid(filteredProjects, grid);
    } else {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-folder-open" style="font-size: 64px; color: var(--gray); margin-bottom: 20px;"></i>
                <h2 style="color: var(--text-color); margin-bottom: 10px;">No Projects in This Category</h2>
                <p style="color: var(--gray); margin-bottom: 20px;">Check back later for new projects</p>
            </div>
        `;
    }

    // Switch to trending tab to show results
    const trendingTab = document.querySelector('.filter-tab[data-filter="trending"]');
    if (trendingTab) {
        trendingTab.click();
    }
}

function displayProjectsInGrid(projects, grid) {
    grid.innerHTML = '';

    projects.forEach(project => {
        const card = document.createElement('div');
        card.className = 'project-card';
        card.innerHTML = `
            <img src="${project.image}" alt="${project.title}" class="project-image">
            <div class="project-info">
                <h3 class="project-title">${project.title}</h3>
                <p class="project-description">${project.description}</p>
                <div class="project-meta">
                    <div class="project-author">
                        <img src="${project.author_avatar}" alt="${project.author_name}" class="author-avatar">
                        <span>${project.author_name}</span>
                    </div>
                    <div class="project-likes">
                        <i class="far fa-heart"></i> ${Math.floor(Math.random() * 200) + 50}
                    </div>
                </div>
                <div class="project-actions">
                    <button class="btn btn-outline save-project-btn" data-project-id="${project.id}"><i class="far fa-bookmark"></i> Save</button>
                    <button class="btn btn-primary message-user"
                            data-user="${project.author_name}"
                            data-user-id="${project.user_id}">
                        <i class="far fa-comment"></i> Message
                    </button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });

    // Re-attach listeners
    attachProjectMessageListeners();
    attachSaveProjectListeners();
}



// Video conference functionality
let currentStream = null;

async function startVideoConference() {
    console.log('Starting video conference...');

    // Check if getUserMedia is supported
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('❌ Video conference is not supported in this browser.');
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 320, height: 240 },
            audio: true
        });

        currentStream = stream;

        // Create video element if it doesn't exist
        let videoElement = document.getElementById('conferenceVideo');
        if (!videoElement) {
            videoElement = document.createElement('video');
            videoElement.id = 'conferenceVideo';
            videoElement.autoplay = true;
            videoElement.muted = true; // Prevent feedback
            videoElement.playsInline = true; // For mobile devices
            videoElement.style.width = '320px';
            videoElement.style.height = '240px';
            videoElement.style.border = '2px solid #007bff';
            videoElement.style.borderRadius = '8px';
            videoElement.style.position = 'fixed';
            videoElement.style.top = '20px';
            videoElement.style.right = '20px';
            videoElement.style.zIndex = '1000';
            videoElement.style.backgroundColor = '#000';

            // Add to body for visibility
            document.body.appendChild(videoElement);
        }

        videoElement.srcObject = stream;

        // Ensure video plays
        videoElement.play().catch(e => console.error('Video play error:', e));

        alert('🎥 Video conference started! Video feed is now active in the top-right corner.');

    } catch (error) {
        console.error('Error starting video conference:', error);

        let errorMessage = '❌ Failed to start video conference. ';
        if (error.name === 'NotAllowedError') {
            errorMessage += 'Camera/microphone access denied. Please allow camera permissions and try again.';
        } else if (error.name === 'NotFoundError') {
            errorMessage += 'No camera/microphone found on this device.';
        } else if (error.name === 'NotReadableError') {
            errorMessage += 'Camera/microphone is already in use by another application.';
        } else {
            errorMessage += 'Please check camera permissions and try again.';
        }

        alert(errorMessage);
    }
}

// Audio conference functionality
async function startAudioConference() {
    console.log('Starting audio conference...');

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: false,
            audio: true
        });

        currentStream = stream;
        alert('🎤 Audio conference started! Microphone is now active.');

    } catch (error) {
        console.error('Error starting audio conference:', error);
        alert('❌ Failed to start audio conference. Please check microphone permissions.');
    }
}

// Stop media stream functionality
function stopMediaStream() {
    console.log('Stopping media stream...');

    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;

        // Remove video element if it exists
        const videoElement = document.getElementById('conferenceVideo');
        if (videoElement) {
            videoElement.remove();
        }

        alert('⏹️ Media stream stopped.');
    } else {
        alert('No active media stream to stop.');
    }
}

// Quick Actions functionality
function showAddProjectModal() {
    console.log('Showing add project modal...');
    if (!currentUser) {
        alert('Please login to add a project');
        showModal('authModal');
        return;
    }
    showModal('addProjectModal');
    setupAddProjectForm();
}

function showAddContactModal() {
    console.log('Showing add contact modal...');
    if (!currentUser) {
        alert('Please login to add a contact');
        showModal('authModal');
        return;
    }
    showModal('addContactModal');
    setupAddContactForm();
}

function showViewContactsModal() {
    console.log('Showing view contacts modal...');
    if (!currentUser) {
        alert('Please login to view your contacts');
        showModal('authModal');
        return;
    }
    showModal('viewContactsModal');
    loadContacts();
}

// Form submission handlers
function setupAddProjectForm() {
    const form = document.getElementById('addProjectForm');
    if (!form) return;

    // Remove existing listeners
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);

    newForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const title = document.getElementById('projectTitle').value.trim();
        const description = document.getElementById('projectDescription').value.trim();
        const image = document.getElementById('projectImage').value.trim() || 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60';

        if (!title || !description) {
            alert('Please fill in all required fields');
            return;
        }

        try {
            const response = await fetch('add_project.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: currentUser.id,
                    title: title,
                    description: description,
                    image: image
                })
            });

            const result = await response.json();

            if (result.success) {
                alert('Project added successfully!');
                newForm.reset();
                document.getElementById('addProjectModal').style.display = 'none';
                // Optionally reload projects
                loadProjects();
            } else {
                alert('Failed to add project: ' + result.message);
            }
        } catch (error) {
            console.error('Add project error:', error);
            alert('Failed to add project. Please try again.');
        }
    });
}

function setupAddContactForm() {
    const form = document.getElementById('addContactForm');
    if (!form) return;

    // Remove existing listeners
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);

    newForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const contactName = document.getElementById('contactName').value.trim();
        const contactEmail = document.getElementById('contactEmail').value.trim();
        const contactPhone = document.getElementById('contactPhone').value.trim();
        const contactNotes = document.getElementById('contactNotes').value.trim();

        if (!contactName) {
            alert('Contact name is required');
            return;
        }

        try {
            const response = await fetch('add_contact.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: currentUser.id,
                    contact_name: contactName,
                    contact_email: contactEmail || null,
                    contact_phone: contactPhone || null,
                    contact_notes: contactNotes || null
                })
            });

            const result = await response.json();

            if (result.success) {
                alert('Contact added successfully!');
                newForm.reset();
                document.getElementById('addContactModal').style.display = 'none';
            } else {
                alert('Failed to add contact: ' + result.message);
            }
        } catch (error) {
            console.error('Add contact error:', error);
            alert('Failed to add contact. Please try again.');
        }
    });
}

async function loadContacts() {
    console.log('Loading contacts...');

    if (!currentUser) {
        console.error('No current user logged in');
        return;
    }

    const contactsContainer = document.getElementById('contactsList');
    if (!contactsContainer) {
        console.error('Contacts container not found');
        return;
    }

    try {
        const response = await fetch(`get_contacts.php?user_id=${currentUser.id}`);
        const result = await response.json();

        if (result.success) {
            contactsContainer.innerHTML = '';

            if (result.contacts.length > 0) {
                result.contacts.forEach(contact => {
                    const contactItem = document.createElement('div');
                    contactItem.className = 'contact-item';
                    contactItem.innerHTML = `
                        <div class="contact-info">
                            <h4>${contact.contact_name}</h4>
                            ${contact.contact_email ? `<p><i class="fas fa-envelope"></i> ${contact.contact_email}</p>` : ''}
                            ${contact.contact_phone ? `<p><i class="fas fa-phone"></i> ${contact.contact_phone}</p>` : ''}
                            ${contact.contact_notes ? `<p><i class="fas fa-sticky-note"></i> ${contact.contact_notes}</p>` : ''}
                            <small>Added: ${new Date(contact.created_at).toLocaleDateString()}</small>
                        </div>
                    `;
                    contactsContainer.appendChild(contactItem);
                });
            } else {
                contactsContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-address-book" style="font-size: 48px; color: var(--gray); margin-bottom: 15px;"></i>
                        <h3>No Contacts Yet</h3>
                        <p>Add some contacts using the "Add Contact" button.</p>
                    </div>
                `;
            }
        } else {
            console.error('Failed to load contacts:', result.message);
            contactsContainer.innerHTML = '<p style="color: var(--danger); text-align: center;">Failed to load contacts.</p>';
        }
    } catch (error) {
        console.error('Error loading contacts:', error);
        contactsContainer.innerHTML = '<p style="color: var(--danger); text-align: center;">Failed to load contacts. Please try again.</p>';
    }
}


