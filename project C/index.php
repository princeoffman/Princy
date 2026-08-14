<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProjectConnect - Share & Explore Creative Projects</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0"
          crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-project-diagram"></i>
                <h1>ProjectConnect</h1>
            </div>
            <ul class="nav-links">
                <li><a href="#" class="active"><i class="fas fa-home"></i> <span>Home</span></a></li>
                <li><a href="#"><i class="fas fa-compass"></i> <span>Explore</span></a></li>
                <li><a href="#"><i class="fas fa-envelope"></i> <span>Messages</span></a></li>
                <li><a href="#"><i class="fas fa-bell"></i> <span>Notifications</span></a></li>
                <li><a href="#"><i class="fas fa-bookmark"></i> <span>Saved</span></a></li>
                <li><a href="#"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                <li><a href="#"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
            
            <div class="time-tracker">
                <h3>Time Spent Today</h3>
                <div class="time-display">00:45:12</div>
                <div class="time-warning">
                    <i class="fas fa-exclamation-triangle"></i> You've been online for 45 minutes
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search projects, people, or topics...">
                </div>
                <div class="user-actions">
                    <div class="notification">
                        <i class="far fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </div>
                <button id="themeToggle" class="theme-toggle" title="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
                <button id="loginBtn" class="btn btn-outline login-btn">Login</button>
                    <div class="user-profile">
                        <img src="DSC02194.JPG" alt="User Avatar">
                    </div>
                </div>
            </div>
            
            <div class="feed">
                <div class="projects-section">
                    <h2 class="section-title">Featured Projects</h2>
                    <div class="projects-grid">
                        <!-- Project Card 1 -->
                        <div class="project-card">
                            <img src="https://images.unsplash.com/photo-1558655146-9f40138edfeb?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Project Image" class="project-image">
                            <div class="project-info">
                                <h3 class="project-title">Urban Gardening App</h3>
                                <p class="project-description">A mobile application that helps urban residents grow their own food in small spaces with step-by-step guidance.</p>
                                <div class="project-meta">
                                    <div class="project-author">
                                        <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" alt="Author" class="author-avatar">
                                        <span>Sarah Johnson</span>
                                    </div>
                                    <div class="project-likes">
                                        <i class="far fa-heart"></i> 124
                                    </div>
                                </div>
                                <div class="project-actions">
                                    <button class="btn btn-outline"><i class="far fa-bookmark"></i> Save</button>
                                    <button class="btn btn-primary message-user" data-user="Sarah Johnson"><i class="far fa-comment"></i> Message</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Card 2 -->
                        <div class="project-card">
                            <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Project Image" class="project-image">
                            <div class="project-info">
                                <h3 class="project-title">Sustainable Packaging</h3>
                                <p class="project-description">Biodegradable packaging solution made from agricultural waste that decomposes within 30 days.</p>
                                <div class="project-meta">
                                    <div class="project-author">
                                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" alt="Author" class="author-avatar">
                                        <span>Alex Chen</span>
                                    </div>
                                    <div class="project-likes">
                                        <i class="far fa-heart"></i> 89
                                    </div>
                                </div>
                                <div class="project-actions">
                                    <button class="btn btn-outline"><i class="far fa-bookmark"></i> Save</button>
                                    <button class="btn btn-primary message-user" data-user="Alex Chen"><i class="far fa-comment"></i> Message</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Card 3 -->
                        <div class="project-card">
                            <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Project Image" class="project-image">
                            <div class="project-info">
                                <h3 class="project-title">AI-Powered Tutoring</h3>
                                <p class="project-description">An adaptive learning platform that personalizes educational content based on student performance and learning style.</p>
                                <div class="project-meta">
                                    <div class="project-author">
                                        <img src="https://images.unsplash.com/photo-1544725176-7c40e5a71c5e?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" alt="Author" class="author-avatar">
                                        <span>Maria Rodriguez</span>
                                    </div>
                                    <div class="project-likes">
                                        <i class="far fa-heart"></i> 203
                                    </div>
                                </div>
                                <div class="project-actions">
                                    <button class="btn btn-outline"><i class="far fa-bookmark"></i> Save</button>
                                    <button class="btn btn-primary message-user" data-user="Maria Rodriguez"><i class="far fa-comment"></i> Message</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Card 4 -->
                        <div class="project-card">
                            <img src="https://images.unsplash.com/photo-1551434678-e076c223a692?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" alt="Project Image" class="project-image">
                            <div class="project-info">
                                <h3 class="project-title">Community Cleanup Initiative</h3>
                                <p class="project-description">A platform connecting volunteers with local cleanup events and providing tools for organizing community efforts.</p>
                                <div class="project-meta">
                                    <div class="project-author">
                                        <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" alt="Author" class="author-avatar">
                                        <span>James Wilson</span>
                                    </div>
                                    <div class="project-likes">
                                        <i class="far fa-heart"></i> 67
                                    </div>
                                </div>
                                <div class="project-actions">
                                    <button class="btn btn-outline"><i class="far fa-bookmark"></i> Save</button>
                                    <button class="btn btn-primary message-user" data-user="James Wilson"><i class="far fa-comment"></i> Message</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Sidebar -->
                <div class="right-sidebar">
                    <div class="online-users">
                        <h2 class="section-title">Online Now</h2>
                        <ul class="user-list">
                            <li class="user-item">
                                <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" alt="User" class="user-avatar">
                                <div class="user-details">
                                    <h4>Sarah Johnson</h4>
                                    <p>Product Designer</p>
                                </div>
                                <div class="status-online"></div>
                            </li>
                            <li class="user-item">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" alt="User" class="user-avatar">
                                <div class="user-details">
                                    <h4>Alex Chen</h4>
                                    <p>Sustainability Engineer</p>
                                </div>
                                <div class="status-online"></div>
                            </li>
                            <li class="user-item">
                                <img src="https://images.unsplash.com/photo-1544725176-7c40e5a71c5e?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" alt="User" class="user-avatar">
                                <div class="user-details">
                                    <h4>Maria Rodriguez</h4>
                                    <p>EdTech Founder</p>
                                </div>
                                <div class="status-online"></div>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="fact-check">
                        <h3><i class="fas fa-check-circle"></i> Fact-Check Feature</h3>
                        <p>Verify information before sharing. Our fact-check tool helps maintain accurate information on the platform.</p>
                        <button class="fact-check-btn">Check a Fact</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chat Modal -->
    <div class="modal" id="chatModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Message <span id="chatUserName">User</span></h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="chat-messages">
                    <div class="message received">
                        <p>Hi there! Thanks for your interest in my project.</p>
                        <div class="message-time">10:24 AM</div>
                    </div>
                    <div class="message sent">
                        <p>Hello! I really like your Urban Gardening App concept. Could you tell me more about the technical implementation?</p>
                        <div class="message-time">10:25 AM</div>
                    </div>
                    <div class="message received">
                        <p>Sure! It's built with React Native for cross-platform compatibility and uses a Node.js backend with MongoDB.</p>
                        <div class="message-time">10:26 AM</div>
                    </div>
                </div>
                <div class="chat-input">
                    <input type="text" placeholder="Type your message...">
                    <button class="send-btn"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>







<!-- Login/Register Modal -->
<div class="modal" id="authModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="authModalTitle">Login to ProjectConnect</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login">Login</button>
                <button class="auth-tab" data-tab="register">Register</button>
            </div>
            
            <form id="loginForm" class="auth-form active">
                <div class="form-group">
                    <input type="text" id="loginUsername" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="password" id="loginPassword" placeholder="Password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Login</button>
            </form>
            
            <form id="registerForm" class="auth-form">
                <div class="form-group">
                    <input type="text" id="regFullName" placeholder="Full Name" required>
                </div>
                <div class="form-group">
                    <input type="text" id="regUsername" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="email" id="regEmail" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <input type="password" id="regPassword" placeholder="Password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Create Account</button>
            </form>
        </div>
    </div>
</div>

<!-- Messages Interface (WhatsApp Style) -->
<div class="modal" id="messagesModal">
    <div class="modal-content messages-modal">
        <div class="modal-header">
            <h2>Messages</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="messages-container">
                <div class="conversations-list">
                    <div class="search-conversations">
                        <input type="text" placeholder="Search conversations...">
                    </div>
                    <div class="conversations">
                        <!-- Conversations will be loaded here -->
                    </div>
                </div>
                <div class="chat-area">
                    <div class="chat-header">
                        <div class="chat-user-info">
                            <img src="" alt="User" class="chat-user-avatar">
                            <div class="chat-user-details">
                                <h4 class="chat-user-name">Select a conversation</h4>
                                <p class="chat-user-status">Click on a user to start chatting</p>
                            </div>
                        </div>
                    </div>
                    <div class="chat-messages-container">
                        <div class="chat-messages">
                            <div class="no-chat-selected">
                                <i class="fas fa-comments"></i>
                                <p>Select a conversation to start messaging</p>
                            </div>
                        </div>
                    </div>
                    <div class="chat-input-container" style="display: none;">
                        <div class="chat-input">
                            <input type="text" placeholder="Type a message...">
                            <button class="send-btn"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal" id="settingsModal">
    <div class="modal-content settings-modal">
        <div class="modal-header">
            <h2>Settings</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="settings-container">
                <div class="settings-sidebar">
                    <ul class="settings-nav">
                        <li class="settings-nav-item active" data-section="account">Account</li>
                        <li class="settings-nav-item" data-section="privacy">Privacy</li>
                        <li class="settings-nav-item" data-section="notifications">Notifications</li>
                        <li class="settings-nav-item" data-section="theme">Theme</li>
                        <li class="settings-nav-item" data-section="kid-mode">Kid Mode</li>
                    </ul>
                </div>
            <div class="settings-content">
                <!-- Account Settings -->
                <div id="account-section" class="settings-section active">
                        <h3>Account Settings</h3>
                        <div class="setting-group">
                            <label>Username</label>
                            <input type="text" readonly>
                        </div>
                        <div class="setting-group">
                            <label>Email</label>
                            <input type="email">
                        </div>
                        <div class="setting-group">
                            <label>Full Name</label>
                            <input type="text">
                        </div>
                        <div class="setting-group">
                            <label>Bio</label>
                            <textarea placeholder="Tell us about yourself..."></textarea>
                        </div>
                        <button class="btn btn-primary">Save Changes</button>
                    </div>

                    <!-- Privacy Controls -->
                    <div id="privacy-section" class="settings-section">
                        <h3>Privacy Controls</h3>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Profile Visibility
                                <input type="checkbox" id="profileVisibility">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Make your profile visible to other users</small>
                        </div>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Show Online Status
                                <input type="checkbox" id="showOnlineStatus">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Let others see when you're online</small>
                        </div>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Allow Direct Messages
                                <input type="checkbox" id="allowDirectMessages">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Allow other users to send you direct messages</small>
                        </div>
                        <button class="btn btn-primary">Save Privacy Settings</button>
                    </div>

                    <!-- Notification Preferences -->
                    <div id="notifications-section" class="settings-section">
                        <h3>Notification Preferences</h3>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Email Notifications
                                <input type="checkbox" id="emailNotifications">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Receive notifications via email</small>
                        </div>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Push Notifications
                                <input type="checkbox" id="pushNotifications">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Receive push notifications in your browser</small>
                        </div>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Message Notifications
                                <input type="checkbox" id="messageNotifications">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Get notified when you receive new messages</small>
                        </div>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Project Updates
                                <input type="checkbox" id="projectUpdates">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Get notified about project updates and interactions</small>
                        </div>
                        <button class="btn btn-primary">Save Notification Settings</button>
                    </div>

                    <!-- Theme Customization -->
                    <div id="theme-section" class="settings-section">
                        <h3>Theme Customization</h3>
                        <div class="theme-options">
                            <label class="theme-option">
                                <input type="radio" name="theme" value="light">
                                <div class="theme-preview light-theme"></div>
                                <span>Light Mode</span>
                            </label>
                            <label class="theme-option">
                                <input type="radio" name="theme" value="dark">
                                <div class="theme-preview dark-theme"></div>
                                <span>Dark Mode</span>
                            </label>
                        </div>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Auto Theme
                                <input type="checkbox" id="autoTheme">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Automatically switch theme based on system preference</small>
                        </div>
                        <button id="applyTheme" class="btn btn-primary">Apply Theme Settings</button>
                    </div>

                    <!-- Kid Mode Settings -->
                    <div id="kid-mode-section" class="settings-section">
                        <h3>Kid Mode Settings</h3>
                        <div class="setting-item">
                            <label class="toggle-label">
                                Enable Kid Mode
                                <input type="checkbox" id="kidModeEnabled">
                                <span class="toggle-slider"></span>
                            </label>
                            <small>Limit usage time to protect children's eyes and encourage breaks</small>
                        </div>
                        <div class="setting-group">
                            <label for="kidModeTimeLimit">Time Limit (minutes)</label>
                            <input type="number" id="kidModeTimeLimit" min="1" max="480" value="60">
                            <small>Set the maximum time allowed per session (1-480 minutes)</small>
                        </div>
                        <div class="setting-group">
                            <label for="kidModePassword">Set Password</label>
                            <input type="password" id="kidModePassword" placeholder="Enter password">
                            <small>Password to unlock when time limit is reached</small>
                        </div>
                        <div class="setting-group">
                            <label for="kidModePasswordConfirm">Confirm Password</label>
                            <input type="password" id="kidModePasswordConfirm" placeholder="Confirm password">
                        </div>
                        <button id="saveKidMode" class="btn btn-primary">Save Kid Mode Settings</button>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Modal -->
<div class="modal" id="profileModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Profile Settings</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="profile-settings">
                <div class="current-avatar">
                    <img id="currentAvatar" src="test_image.jpg" alt="Current Avatar">
                    <h3>Change Profile Picture</h3>
                </div>
                <form id="profilePictureForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="profilePicture">Select new profile picture:</label>
                        <input type="file" id="profilePicture" name="profile_picture" accept="image/*">
                        <small>Supported formats: JPG, PNG, GIF. Max size: 5MB</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Upload Picture</button>
                    </div>
                    <div id="uploadStatus" class="upload-status"></div>
                </form>
            </div>
        </div>
    </div>
</div>



</body>
</html>
