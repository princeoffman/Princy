// Shared helpers for API calls, stored state and project rendering.

async function apiGet(url) {
    const response = await fetch(url);
    return response.json();
}

async function apiPost(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body)
    });
    return response.json();
}

async function apiPostForm(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        body: formData
    });
    return response.json();
}

function getStoredUser() {
    const savedUser = localStorage.getItem('currentUser');
    return savedUser ? JSON.parse(savedUser) : null;
}

function storeCurrentUser(user) {
    localStorage.setItem('currentUser', JSON.stringify(user));
}

function getUserSettings() {
    try {
        return JSON.parse(localStorage.getItem('userSettings') || '{}') || {};
    } catch (error) {
        console.error('Invalid stored settings:', error);
        return {};
    }
}

/** Merge the given values into the stored settings and return the result. */
function updateUserSettings(changes) {
    const settings = Object.assign(getUserSettings(), changes);
    localStorage.setItem('userSettings', JSON.stringify(settings));
    return settings;
}

/**
 * Fetch projects from the backend.
 * Returns null when the request fails or yields no projects, so callers can fall back.
 */
async function fetchProjects(queryString = '') {
    try {
        const result = await apiGet('get_projects.php' + queryString);
        if (result.success && result.projects.length > 0) {
            return result.projects;
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
    return null;
}

function projectCardMarkup(project, likesMarkup) {
    return `
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
                        ${likesMarkup}
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
}

/**
 * Render project cards into a grid element.
 * With interactiveLikes the heart is a clickable like button showing the stored count,
 * otherwise it is a static heart with a placeholder count.
 */
function renderProjectsInGrid(projects, grid, options = {}) {
    const interactiveLikes = options.interactiveLikes !== false;

    grid.innerHTML = '';

    projects.forEach(project => {
        const likesMarkup = interactiveLikes
            ? `<i class="far fa-heart like-btn" data-project-id="${project.id}"></i> ${project.like_count || 0}`
            : `<i class="far fa-heart"></i> ${Math.floor(Math.random() * 200) + 50}`;

        const card = document.createElement('div');
        card.className = 'project-card';
        card.innerHTML = projectCardMarkup(project, likesMarkup);
        grid.appendChild(card);
    });
}
