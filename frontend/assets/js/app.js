const APP_ROOT = new URL('../../../', document.currentScript.src);
const API_ROOT = new URL('api/', APP_ROOT);
let csrfToken = '';

class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

function appPath(path) {
    return new URL(path.replace(/^\/+/, ''), APP_ROOT).href;
}

function setTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    document.body.dataset.theme = isDark ? 'dark' : 'light';
    const toggle = document.querySelector('.theme-toggle');
    if (toggle) {
        toggle.setAttribute('aria-pressed', String(isDark));
        toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        toggle.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
        toggle.innerHTML = `<span aria-hidden="true">${isDark ? '☀' : '☾'}</span><span>${isDark ? 'Light mode' : 'Dark mode'}</span>`;
    }
}

function initializeTheme() {
    const savedTheme = localStorage.getItem('gpb-resolve-theme');
    const initialTheme = savedTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    setTheme(initialTheme);

    const toggle = document.createElement('button');
    toggle.className = 'theme-toggle';
    toggle.type = 'button';
    toggle.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('gpb-resolve-theme', nextTheme);
        setTheme(nextTheme);
    });

    const shell = document.querySelector('.topbar-inner');
    const navigation = shell?.querySelector('nav');
    if (shell && navigation) {
        shell.append(toggle);
    } else {
        toggle.classList.add('theme-toggle-floating');
        document.body.append(toggle);
    }
    setTheme(initialTheme);
}

function applyBrandLogos() {
    document.querySelectorAll('.brand-mark').forEach((mark) => {
        const logo = document.createElement('img');
        logo.className = 'brand-mark';
        logo.src = appPath('frontend/assets/images/gpb-resolve-mark.webp');
        logo.alt = '';
        mark.replaceWith(logo);
    });
}

function categoryIcon(name) {
    const value = String(name || '').toLowerCase();
    if (/classroom|infrastructure/.test(value)) return '⌂';
    if (/laboratory|equipment/.test(value)) return '⚗';
    if (/computer|internet/.test(value)) return '⌘';
    if (/library|academic/.test(value)) return '▤';
    if (/sanitation|clean/.test(value)) return '✦';
    if (/water|electric/.test(value)) return 'ϟ';
    if (/hostel|canteen/.test(value)) return '⌂';
    if (/administrative|student service/.test(value)) return '▣';
    if (/safety|maintenance/.test(value)) return '◇';
    return '⋯';
}

async function initHome() {
    const year = document.getElementById('home-copyright-year');
    if (year) year.textContent = String(new Date().getFullYear());

    const toggle = document.querySelector('.mobile-nav-toggle');
    const navigation = document.getElementById('home-navigation');
    toggle?.addEventListener('click', () => {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Open navigation' : 'Close navigation');
        navigation?.classList.toggle('is-open', !isOpen);
    });
    navigation?.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener('click', () => {
            navigation.classList.remove('is-open');
            toggle?.setAttribute('aria-expanded', 'false');
            toggle?.setAttribute('aria-label', 'Open navigation');
        });
    });

    try {
        const categories = await api('categories/list.php');
        const host = document.getElementById('home-categories');
        host.innerHTML = categories.map((category) => {
            const next = `student/create-complaint.html?category_id=${Number(category.id)}`;
            const login = new URL(appPath('frontend/login.html'));
            login.searchParams.set('role', 'student');
            login.searchParams.set('next', next);
            return `<a class="home-category-card" href="${escapeHtml(login.href)}"><span class="home-category-icon" aria-hidden="true">${categoryIcon(category.name)}</span><span class="home-category-copy"><strong>${escapeHtml(category.name)}</strong><small>${escapeHtml(category.description || 'Report an issue in this area of campus.')}</small><span class="home-category-action">Report an issue <span aria-hidden="true">→</span></span></span></a>`;
        }).join('');
    } catch (error) {
        const host = document.getElementById('home-categories');
        host.innerHTML = `<p class="home-data-error" role="status">${escapeHtml(error.message)} Complaint categories could not be loaded.</p>`;
    }

    try {
        const stats = await api('public/stats.php');
        const host = document.getElementById('home-statistics');
        host.innerHTML = [
            ['total', 'Total reports'],
            ['pending', 'Pending'],
            ['in_progress', 'In progress'],
            ['resolved', 'Resolved'],
        ].map(([key, label]) => `<div class="home-stat"><strong>${Number(stats[key])}</strong><span>${label}</span></div>`).join('');
    } catch (error) {
        const host = document.getElementById('home-statistics');
        host.innerHTML = `<p class="home-data-error" role="status">${escapeHtml(error.message)} Live statistics are temporarily unavailable.</p>`;
    }
}

function enhanceAppShell(user) {
    const shell = document.querySelector('.topbar-inner');
    if (!shell) return;

    const currentPath = window.location.pathname.toLowerCase();
    shell.querySelectorAll('nav [data-path]').forEach((link) => {
        if (new URL(link.href).pathname.toLowerCase() === currentPath) {
            link.setAttribute('aria-current', 'page');
        }
    });

    const logout = shell.querySelector('[data-logout]');
    const logoutItem = logout?.closest('li');
    if (!logout || !logoutItem) return;

    const account = document.createElement('div');
    account.className = 'sidebar-account';
    const avatar = document.createElement('span');
    avatar.className = 'sidebar-avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.textContent = String(user.full_name || 'U').trim().charAt(0).toUpperCase();

    const details = document.createElement('div');
    details.className = 'sidebar-account-details';
    const name = document.createElement('strong');
    name.textContent = user.full_name || 'Account';
    const role = document.createElement('small');
    role.textContent = user.role === 'admin' ? 'Administrator' : 'Student';
    details.append(name, role);

    logoutItem.remove();
    logout.className = 'sidebar-logout';
    account.append(avatar, details, logout);
    shell.append(account);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}

function showNotice(message, success = false, element = document.getElementById('form-notice')) {
    if (!element) {
        const main = document.querySelector('main');
        if (!main) return;
        element = document.createElement('div');
        main.prepend(element);
    }
    element.hidden = false;
    element.className = `notice ${success ? 'notice-success' : 'notice-error'}`;
    element.textContent = message;
}

async function api(path, options = {}) {
    const response = await fetch(new URL(path, API_ROOT), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...(options.headers || {}) },
        ...options,
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
        throw new ApiError(payload.message || 'The request could not be completed.', response.status);
    }
    return payload.data;
}

function formDataWithCsrf(form) {
    const data = new FormData(form);
    if (!data.has('csrf_token')) data.set('csrf_token', csrfToken);
    else if (!data.get('csrf_token')) data.set('csrf_token', csrfToken);
    return data;
}

function statusBadge(status) {
    const klass = String(status || '').toLowerCase().replace(/\s+/g, '-');
    return `<span class="badge badge-${escapeHtml(klass)}">${escapeHtml(status)}</span>`;
}

function priorityBadge(priority) {
    const klass = String(priority || '').toLowerCase();
    return `<span class="badge badge-${escapeHtml(klass)}">${escapeHtml(priority)}</span>`;
}

function complaintsTable(items, admin = false) {
    if (!items.length) return '<div class="form-card">No complaints to show.</div>';
    return `<div class="table-wrap"><table class="table"><thead><tr><th>Reference</th>${admin ? '<th>Student</th>' : ''}<th>Category</th><th>Status</th><th>Priority</th><th>Submitted</th><th></th></tr></thead><tbody>${items.map((item) => {
        const link = admin ? `frontend/admin/complaint-details.html?id=${encodeURIComponent(item.id)}` : `frontend/student/complaint-details.html?id=${encodeURIComponent(item.id)}`;
        return `<tr><td>${escapeHtml(item.reference_number)}</td>${admin ? `<td>${escapeHtml(item.student_name || 'Unknown')}</td>` : ''}<td>${escapeHtml(item.category_name || '—')}</td><td>${statusBadge(item.status)}</td><td>${priorityBadge(item.priority)}</td><td>${escapeHtml(item.created_at || '')}</td><td><a href="${appPath(link)}">View</a></td></tr>`;
    }).join('')}</tbody></table></div>`;
}

async function initAuth() {
    const csrf = await api('auth/csrf.php');
    csrfToken = csrf.csrf_token;
    document.querySelectorAll('input[name="csrf_token"]').forEach((input) => { input.value = csrfToken; });

    const params = new URLSearchParams(window.location.search);
    const role = params.get('role') === 'admin' ? 'admin' : 'student';
    const roleInput = document.querySelector('input[name="role"]');
    if (roleInput) roleInput.value = role;

    if (document.body.dataset.page === 'register' && role === 'admin') {
        document.getElementById('register-title').textContent = 'Request admin access';
        document.getElementById('register-description').textContent = 'A current administrator must approve your request before you can sign in.';
        document.querySelector('#register-form button[type="submit"]').textContent = 'Request approval';
        const studentIdentifierField = document.getElementById('student-identifier-field');
        studentIdentifierField.hidden = true;
    }

    const form = document.getElementById(`${document.body.dataset.page}-form`);
    if (!form) return;
    if (document.body.dataset.page === 'register') {
        const password = form.elements.password;
        const confirmation = form.elements.confirm_password;
        const validatePasswordConfirmation = () => {
            confirmation.setCustomValidity(password.value === confirmation.value
                ? ''
                : 'Passwords do not match.');
        };
        password.addEventListener('input', validatePasswordConfirmation);
        confirmation.addEventListener('input', validatePasswordConfirmation);
    }
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (document.body.dataset.page === 'register') {
            form.elements.confirm_password.setCustomValidity(
                form.elements.password.value === form.elements.confirm_password.value
                    ? ''
                    : 'Passwords do not match.',
            );
            if (!form.reportValidity()) return;
        }
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const endpoint = document.body.dataset.page === 'login' ? 'auth/login.php' : 'auth/register.php';
            const result = await api(endpoint, { method: 'POST', body: formDataWithCsrf(form) });
            if (result.requires_otp) {
                const otpField = document.getElementById('otp-field');
                otpField.hidden = false;
                otpField.querySelector('input').required = true;
                otpField.querySelector('input').focus();
                showNotice('A verification code was sent to your email. Enter it to continue.', true);
                return;
            }
            showNotice(document.body.dataset.page === 'register' && role === 'admin'
                ? 'Request submitted. An existing admin must approve it before you can sign in.'
                : 'Success. Redirecting...', true);
            if (document.body.dataset.page === 'login') {
                const next = new URLSearchParams(window.location.search).get('next') || '';
                const studentComplaintRoute = /^student\/create-complaint\.html(?:\?category_id=\d+)?$/.test(next);
                const destination = role === 'student' && studentComplaintRoute
                    ? `frontend/${next}`
                    : role === 'admin' ? 'frontend/admin/dashboard.html' : 'frontend/student/dashboard.html';
                window.location.assign(appPath(destination));
            } else if (role !== 'admin' && result.redirect) {
                window.location.assign(appPath('frontend/student/dashboard.html'));
            }
        } catch (error) {
            showNotice(error.message);
        } finally {
            button.disabled = false;
        }
    });
}

async function initStudentDashboard() {
    const [me, items] = await Promise.all([api('auth/me.php'), api('complaints/my-complaints.php')]);
    document.getElementById('welcome').textContent = `Welcome, ${me.user.full_name}`;
    const counts = { total: items.length, pending: 0, progress: 0, resolved: 0 };
    items.forEach((item) => {
        if (['Submitted', 'Under Review', 'Assigned'].includes(item.status)) counts.pending += 1;
        if (item.status === 'In Progress') counts.progress += 1;
        if (item.status === 'Resolved') counts.resolved += 1;
    });
    document.getElementById('student-stats').innerHTML = [
        ['Total complaints', counts.total], ['Pending', counts.pending],
        ['In progress', counts.progress], ['Resolved', counts.resolved],
    ].map(([label, count]) => `<div class="summary-box"><span>${label}</span><strong>${count}</strong></div>`).join('');
    document.getElementById('dashboard-content').innerHTML = complaintsTable(items.slice(0, 5));
}

async function initStudentComplaints() {
    const items = await api('complaints/my-complaints.php');
    document.getElementById('complaints-content').innerHTML = complaintsTable(items);
}

async function initCreateComplaint() {
    const [categories, csrf] = await Promise.all([api('categories/list.php'), api('auth/csrf.php')]);
    csrfToken = csrf.csrf_token;
    document.querySelector('[name="csrf_token"]').value = csrfToken;
    const select = document.getElementById('category_id');
    select.innerHTML = '<option value="">Select category</option>' + categories.map((category) =>
        `<option value="${Number(category.id)}">${escapeHtml(category.name)}</option>`).join('');
    const selectedCategoryId = new URLSearchParams(window.location.search).get('category_id');
    if (selectedCategoryId && categories.some((category) => Number(category.id) === Number(selectedCategoryId))) {
        select.value = selectedCategoryId;
    }

    const form = document.getElementById('complaint-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const data = formDataWithCsrf(form);
            const result = await api('complaints/create.php', { method: 'POST', body: data });
            showNotice(`Complaint submitted. Reference: ${result.reference_number}`, true);
            window.location.assign(appPath(`frontend/student/complaint-details.html?id=${encodeURIComponent(result.complaint_id)}`));
        } catch (error) {
            showNotice(error.message);
        } finally {
            button.disabled = false;
        }
    });
}

function renderComplaintDetail(complaint, admin) {
    const updates = complaint.updates || [];
    const attachments = complaint.attachments || [];
    const meta = `<div class="form-grid"><div><p><strong>Category:</strong> ${escapeHtml(complaint.category_name || '—')}</p><p><strong>Location:</strong> ${escapeHtml(complaint.location || '—')}</p><p><strong>Department:</strong> ${escapeHtml(complaint.department_name || 'Unassigned')}</p>${admin ? `<h2>Student details</h2><p><strong>Name:</strong> ${escapeHtml(complaint.student_name || '—')}</p><p><strong>Email:</strong> ${escapeHtml(complaint.student_email || '—')}</p><p><strong>Student ID / Roll number:</strong> ${escapeHtml(complaint.student_identifier || '—')}</p>` : ''}</div><div><p><strong>Submitted:</strong> ${escapeHtml(complaint.created_at)}</p><p><strong>Updated:</strong> ${escapeHtml(complaint.updated_at)}</p><p><strong>Resolution:</strong> ${escapeHtml(complaint.resolution_notes || 'None')}</p></div></div>`;
    const history = `<h2>Updates</h2>${updates.length ? `<ul>${updates.map((update) => `<li><strong>${escapeHtml(update.new_status || 'Update')}</strong> — ${escapeHtml(update.message)} <small>${escapeHtml(update.created_at)}</small></li>`).join('')}</ul>` : '<p>No updates yet.</p>'}`;
    const files = attachments.length ? `<h2>Supporting images</h2><div class="attachment-grid">${attachments.map((file) => `<a class="attachment-card" href="${escapeHtml(file.url || appPath(`api/attachments/view.php?id=${encodeURIComponent(file.id)}`))}" target="_blank" rel="noopener"><img src="${escapeHtml(file.url || appPath(`api/attachments/view.php?id=${encodeURIComponent(file.id)}`))}" alt="Complaint attachment: ${escapeHtml(file.original_filename)}" loading="lazy"><span>${escapeHtml(file.original_filename)}</span></a>`).join('')}</div>` : '<p>No supporting images were uploaded.</p>';
    const form = admin ? `<div id="form-notice" class="notice" hidden></div><form id="status-form"><input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="complaint_id" value="${Number(complaint.id)}"><div class="form-grid"><div><label>Status</label><select name="status">${['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Rejected'].map((s) => `<option ${s === complaint.status ? 'selected' : ''}>${s}</option>`).join('')}</select></div><div><label>Priority</label><select name="priority">${['Low', 'Medium', 'High', 'Urgent'].map((s) => `<option ${s === complaint.priority ? 'selected' : ''}>${s}</option>`).join('')}</select></div></div><label>Department</label><select name="department_id"><option value="">Unassigned</option>${(complaint.departments || []).map((d) => `<option value="${Number(d.id)}" ${Number(d.id) === Number(complaint.department_id) ? 'selected' : ''}>${escapeHtml(d.name)}</option>`).join('')}</select><label>Update note / resolution</label><textarea name="message" required></textarea><button class="btn btn-primary" type="submit">Update complaint</button></form>` : '';
    return `<article class="form-card"><div class="page-heading"><div><h1>${escapeHtml(complaint.title)}</h1><p>Reference: ${escapeHtml(complaint.reference_number)}</p></div><div>${statusBadge(complaint.status)} ${priorityBadge(complaint.priority)}</div></div>${meta}<h2>Description</h2><p>${escapeHtml(complaint.description).replace(/\n/g, '<br>')}</p>${files}${history}${form}</article>`;
}

async function initStudentComplaintDetail() {
    const id = new URLSearchParams(window.location.search).get('id');
    const complaint = await api(`complaints/details.php?id=${encodeURIComponent(id || '')}`);
    document.getElementById('complaint-detail').innerHTML = renderComplaintDetail(complaint, false);
}

async function initNotifications() {
    const items = await api('notifications/list.php');
    const host = document.getElementById('notifications-content');
    if (!items.length) {
        host.innerHTML = '<div class="form-card">You have no notifications.</div>';
        return;
    }
    host.innerHTML = items.map((item) => `<article class="form-card" style="margin:1rem 0"><h2>${escapeHtml(item.title)}</h2><p>${escapeHtml(item.message)}</p><small>${escapeHtml(item.created_at)}</small>${item.read_at ? '<p>Read</p>' : `<button class="btn btn-secondary" data-mark-read="${Number(item.id)}">Mark as read</button>`}</article>`).join('');
    host.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-mark-read]');
        if (!button) return;
        try {
            await api('notifications/mark-read.php', { method: 'POST', body: new URLSearchParams({ id: button.dataset.markRead, csrf_token: csrfToken }) });
            button.replaceWith(document.createTextNode('Read'));
        } catch (error) {
            showNotice(error.message, false, host);
        }
    });
}

async function initProfile() {
    const [me, csrf] = await Promise.all([api('auth/me.php'), api('auth/csrf.php')]);
    csrfToken = csrf.csrf_token;
    const form = document.getElementById('profile-form');
    form.elements.full_name.value = me.user.full_name || '';
    form.elements.email.value = me.user.email || '';
    form.elements.student_identifier.value = me.user.student_identifier || '';
    form.elements.csrf_token.value = csrfToken;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await api('auth/profile.php', { method: 'POST', body: formDataWithCsrf(form) });
            showNotice('Profile updated successfully.', true);
        } catch (error) {
            showNotice(error.message);
        }
    });
}

async function initAdminDashboard() {
    const data = await api('admin/dashboard.php');
    document.getElementById('admin-stats').innerHTML = [
        ['Total complaints', data.total], ['New / unreviewed', data.new],
        ['Pending', data.pending], ['In progress', data.in_progress], ['Resolved', data.resolved],
    ].map(([label, count]) => `<div class="summary-box"><span>${label}</span><strong>${Number(count)}</strong></div>`).join('');
    document.getElementById('recent-complaints').innerHTML = complaintsTable(data.recent || [], true);
    const requests = data.pending_admin_requests || [];
    const host = document.getElementById('pending-approvals');
    if (!requests.length) {
        host.innerHTML = '<h2>Pending admin approvals</h2><div class="form-card">No pending admin access requests.</div>';
        return;
    }
    host.innerHTML = `<h2>Pending admin approvals</h2><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Requested</th><th>Actions</th></tr></thead><tbody>${requests.map((r) => `<tr><td>${escapeHtml(r.full_name)}</td><td>${escapeHtml(r.email)}</td><td>${escapeHtml(r.phone_number || '—')}</td><td>${escapeHtml(r.created_at)}</td><td><button class="btn btn-primary" data-approval="${Number(r.id)}" data-action="approve">Approve</button> <button class="btn btn-secondary" data-approval="${Number(r.id)}" data-action="reject">Reject</button></td></tr>`).join('')}</tbody></table></div>`;
    host.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-approval]');
        if (!button) return;
        button.disabled = true;
        try {
            await api('admin/approve-admin.php', { method: 'POST', body: new URLSearchParams({ request_id: button.dataset.approval, action: button.dataset.action, csrf_token: csrfToken }) });
            window.location.reload();
        } catch (error) {
            showNotice(error.message, false, host);
            button.disabled = false;
        }
    });
}

async function initAdminComplaints() {
    const form = document.getElementById('complaint-filters');
    const load = async () => {
        const query = new URLSearchParams(new FormData(form));
        const items = await api(`admin/complaints.php?${query.toString()}`);
        document.getElementById('admin-complaints-content').innerHTML = complaintsTable(items, true);
    };
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        load().catch((error) => showNotice(error.message, false, document.getElementById('admin-complaints-content')));
    });
    await load();
}

async function initAdminComplaintDetail() {
    const id = new URLSearchParams(window.location.search).get('id');
    const data = await api(`admin/complaint-details.php?id=${encodeURIComponent(id || '')}`);
    document.getElementById('admin-complaint-detail').innerHTML = renderComplaintDetail(data, true);
    const form = document.getElementById('status-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await api('admin/update-status.php', { method: 'POST', body: formDataWithCsrf(form) });
            showNotice('Complaint updated successfully.');
            window.location.reload();
        } catch (error) {
            showNotice(error.message);
        }
    });
}

async function initCatalog(page) {
    const isCategories = page === 'admin-categories';
    const [items, csrf] = await Promise.all([api(`admin/${isCategories ? 'categories' : 'departments'}-list.php`), api('auth/csrf.php')]);
    csrfToken = csrf.csrf_token;
    const form = document.getElementById(isCategories ? 'category-form' : 'department-form');
    form.elements.csrf_token.value = csrfToken;
    const host = document.getElementById(isCategories ? 'categories-content' : 'departments-content');
    host.innerHTML = `<div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Description</th><th>Active</th></tr></thead><tbody>${items.map((item) => `<tr><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.description || '')}</td><td>${Number(item.is_active) ? 'Yes' : 'No'}</td></tr>`).join('')}</tbody></table></div>`;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await api(`admin/${isCategories ? 'categories' : 'departments'}.php`, { method: 'POST', body: formDataWithCsrf(form) });
            window.location.reload();
        } catch (error) {
            showNotice(error.message);
        }
    });
}

async function initUsers() {
    const users = await api('admin/users-list.php');
    document.getElementById('users-content').innerHTML = `<div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody>${users.map((user) => `<tr><td>${escapeHtml(user.full_name)}</td><td>${escapeHtml(user.email)}</td><td>${escapeHtml(user.role)}</td><td>${Number(user.is_active) ? 'Active' : 'Disabled'}</td></tr>`).join('')}</tbody></table></div>`;
}

async function initStatusForm() {
    const form = document.getElementById('status-form');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await api('admin/update-status.php', { method: 'POST', body: formDataWithCsrf(form) });
            window.location.reload();
        } catch (error) {
            showNotice(error.message);
        }
    });
}

async function logout() {
    try {
        await api('auth/logout.php', { method: 'POST', body: new URLSearchParams({ csrf_token: csrfToken }) });
    } finally {
        window.location.assign(appPath('frontend/login.html'));
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const year = document.getElementById('home-copyright-year');
    if (year) year.textContent = String(new Date().getFullYear());
    initializeTheme();
    document.querySelectorAll('[data-path]').forEach((link) => {
        link.href = appPath(link.dataset.path);
    });
    applyBrandLogos();
    document.querySelectorAll('[data-logout]').forEach((link) => {
        link.href = appPath('frontend/login.html');
        link.addEventListener('click', (event) => {
            event.preventDefault();
            logout();
        });
    });
    const page = document.body.dataset.page;
    try {
        if (page !== 'legal' && page !== 'not-found') {
            const csrf = await api('auth/csrf.php');
            csrfToken = csrf.csrf_token;
        }
        const requiredRole = document.body.dataset.requiredRole;
        if (requiredRole) {
            const profile = await api('auth/me.php');
            if (profile.user.role !== requiredRole) {
                window.location.assign(appPath('frontend/login.html' + (requiredRole === 'admin' ? '?role=admin' : '')));
                return;
            }
            enhanceAppShell(profile.user);
        }
        switch (page) {
            case 'home':
                await initHome();
                break;
            case 'login':
            case 'register':
                await initAuth();
                break;
            case 'student-dashboard':
                await initStudentDashboard();
                break;
            case 'student-complaints':
                await initStudentComplaints();
                break;
            case 'student-create-complaint':
                await initCreateComplaint();
                break;
            case 'student-complaint-details':
                await initStudentComplaintDetail();
                break;
            case 'student-notifications':
                await initNotifications();
                break;
            case 'student-profile':
                await initProfile();
                break;
            case 'admin-dashboard':
                await initAdminDashboard();
                break;
            case 'admin-complaints':
                await initAdminComplaints();
                break;
            case 'admin-complaint-details':
                await initAdminComplaintDetail();
                break;
            case 'admin-categories':
            case 'admin-departments':
                await initCatalog(page);
                break;
            case 'admin-users':
                await initUsers();
                break;
        }
    } catch (error) {
        showNotice(error.message);
        if (document.body.dataset.requiredRole && error.status === 401) {
            window.location.assign(appPath('frontend/login.html'));
        }
    }

    const reportLink = document.querySelector('[data-download-report]');
    if (reportLink) reportLink.href = new URL('admin/reports.php', API_ROOT).href;
});
