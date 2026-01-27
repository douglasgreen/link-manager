<?php

namespace DouglasGreen\LinkManager\Controller;

use DouglasGreen\LinkManager\AppContainer;
use DouglasGreen\PageBuilder\PageBuilder;
use PDO;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Main link manager controller
 */
final class LinkController
{
    private AppContainer $app;
    private PDO $pdo;
    private Request $request;
    private Session $session;

    public function __construct(AppContainer $app)
    {
        $this->app = $app;
        $this->pdo = $app->getPdo();
        $this->request = $app->getRequest();
        $this->session = $app->getSession();
    }

    public function execute(): Response
    {
        // Handle POST requests
        if ($this->request->isMethod('POST')) {
            return $this->handlePostRequest();
        }

        // Handle GET requests (display page)
        return $this->displayPage();
    }

    private function handlePostRequest(): Response
    {
        $action = $this->request->request->get('action', '');

        try {
            switch ($action) {
                case 'add_bookmark':
                    return $this->addBookmark();
                case 'edit_bookmark':
                    return $this->editBookmark();
                case 'delete_bookmark':
                    return $this->deleteBookmark();
                case 'add_group':
                    return $this->addGroup();
                case 'edit_group':
                    return $this->editGroup();
                case 'delete_group':
                    return $this->deleteGroup();
                default:
                    throw new \Exception('Unknown action: ' . $action);
            }
        } catch (\Exception $e) {
            $this->session->getFlashBag()->add('error', $e->getMessage());
            return new RedirectResponse($this->request->getRequestUri());
        }
    }

    private function addBookmark(): RedirectResponse
    {
        $groupId = (int) $this->request->request->get('group_id', 0);
        $url = trim($this->request->request->get('url', ''));
        $title = trim($this->request->request->get('title', ''));
        $description = trim($this->request->request->get('description', ''));

        if (empty($url) || empty($title) || $groupId <= 0) {
            throw new \Exception('URL, title, and group are required');
        }

        // Check if group exists
        $stmt = $this->pdo->prepare("SELECT id FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);
        if (!$stmt->fetch()) {
            throw new \Exception('Selected group does not exist');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO bookmarks (group_id, url, title, description, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$groupId, $url, $title, $description]);

        $this->session->getFlashBag()->add('success', 'Bookmark added successfully');
        return new RedirectResponse('?group=' . $groupId);
    }

    private function editBookmark(): RedirectResponse
    {
        $bookmarkId = (int) $this->request->request->get('bookmark_id', 0);
        $groupId = (int) $this->request->request->get('group_id', 0);
        $url = trim($this->request->request->get('url', ''));
        $title = trim($this->request->request->get('title', ''));
        $description = trim($this->request->request->get('description', ''));

        if ($bookmarkId <= 0 || empty($url) || empty($title) || $groupId <= 0) {
            throw new \Exception('Invalid bookmark data');
        }

        // Check if bookmark exists
        $stmt = $this->pdo->prepare("SELECT id FROM bookmarks WHERE id = ?");
        $stmt->execute([$bookmarkId]);
        if (!$stmt->fetch()) {
            throw new \Exception('Bookmark not found');
        }

        // Check if group exists
        $stmt = $this->pdo->prepare("SELECT id FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);
        if (!$stmt->fetch()) {
            throw new \Exception('Selected group does not exist');
        }

        $stmt = $this->pdo->prepare("
            UPDATE bookmarks
            SET group_id = ?, url = ?, title = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$groupId, $url, $title, $description, $bookmarkId]);

        $this->session->getFlashBag()->add('success', 'Bookmark updated successfully');
        return new RedirectResponse('?group=' . $groupId);
    }

    private function deleteBookmark(): RedirectResponse
    {
        $bookmarkId = (int) $this->request->request->get('bookmark_id', 0);
        $groupId = (int) $this->request->request->get('group_id', 0);

        if ($bookmarkId <= 0) {
            throw new \Exception('Invalid bookmark ID');
        }

        $stmt = $this->pdo->prepare("SELECT group_id FROM bookmarks WHERE id = ?");
        $stmt->execute([$bookmarkId]);
        $bookmark = $stmt->fetch();
        if (!$bookmark) {
            throw new \Exception('Bookmark not found');
        }

        $actualGroupId = $bookmark['group_id'];

        $stmt = $this->pdo->prepare("DELETE FROM bookmarks WHERE id = ?");
        $stmt->execute([$bookmarkId]);

        // Auto-delete group if empty
        if ($this->isGroupEmpty($actualGroupId)) {
            $stmt = $this->pdo->prepare("DELETE FROM groups WHERE id = ?");
            $stmt->execute([$actualGroupId]);
            $this->session->getFlashBag()->add('success', 'Bookmark and empty group deleted successfully');
            return new RedirectResponse('/');
        }

        $this->session->getFlashBag()->add('success', 'Bookmark deleted successfully');
        return new RedirectResponse('?group=' . ($groupId > 0 ? $groupId : $actualGroupId));
    }

    private function addGroup(): RedirectResponse
    {
        $name = trim($this->request->request->get('group_name', ''));
        $description = trim($this->request->request->get('group_description', ''));

        if (empty($name)) {
            throw new \Exception('Group name is required');
        }

        // Check for duplicate
        $stmt = $this->pdo->prepare("SELECT id FROM groups WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new \Exception('A group with this name already exists');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO groups (name, description, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$name, $description]);
        $newGroupId = $this->pdo->lastInsertId();

        $this->session->getFlashBag()->add('success', 'Group added successfully');
        return new RedirectResponse('?group=' . $newGroupId);
    }

    private function editGroup(): RedirectResponse
    {
        $groupId = (int) $this->request->request->get('group_id', 0);
        $name = trim($this->request->request->get('group_name', ''));
        $description = trim($this->request->request->get('group_description', ''));

        if ($groupId <= 0 || empty($name)) {
            throw new \Exception('Invalid group data');
        }

        $stmt = $this->pdo->prepare("SELECT name FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        if (!$group) {
            throw new \Exception('Group not found');
        }

        // Check for duplicate (if changed)
        if ($name !== $group['name']) {
            $stmt = $this->pdo->prepare("SELECT id FROM groups WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                throw new \Exception('A group with this name already exists');
            }
        }

        $stmt = $this->pdo->prepare("
            UPDATE groups
            SET name = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $groupId]);

        $this->session->getFlashBag()->add('success', 'Group updated successfully');
        return new RedirectResponse('?group=' . $groupId);
    }

    private function deleteGroup(): RedirectResponse
    {
        $groupId = (int) $this->request->request->get('group_id', 0);

        if ($groupId <= 0) {
            throw new \Exception('Invalid group ID');
        }

        $stmt = $this->pdo->prepare("SELECT id FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);
        if (!$stmt->fetch()) {
            throw new \Exception('Group not found');
        }

        if (!$this->isGroupEmpty($groupId)) {
            throw new \Exception('Group is not empty. Delete all bookmarks in this group first.');
        }

        $stmt = $this->pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);

        $this->session->getFlashBag()->add('success', 'Group deleted successfully');
        return new RedirectResponse('/');
    }

    private function displayPage(): Response
    {
        $currentGroup = $this->request->query->get('group', '');
        $searchQuery = $this->request->query->get('search', '');

        // Load all groups with bookmark counts
        $groups = $this->loadAllGroups();

        // Load bookmarks based on search or group
        if (!empty($searchQuery)) {
            $results = $this->searchBookmarks(trim($searchQuery));
            $pageTitle = 'Search Results: ' . htmlspecialchars($searchQuery);
            $bookmarks = [];
            $selectedGroup = null;
            $isSearching = true;
        } elseif ($currentGroup !== '' && is_numeric($currentGroup)) {
            $groupId = (int) $currentGroup;
            $result = $this->loadBookmarksForGroup($groupId);
            $bookmarks = $result['bookmarks'];
            $selectedGroup = $result['group'];
            $pageTitle = $selectedGroup ? 'Group: ' . htmlspecialchars($selectedGroup['name']) : 'Group Not Found';
            $results = [];
            $isSearching = false;
        } else {
            $bookmarks = [];
            $selectedGroup = null;
            $pageTitle = 'Bookmark Manager';
            $results = [];
            $isSearching = false;
        }

        // Build page using PageBuilder and Twig
        $html = $this->buildPageWithBuilder([
            'pageTitle' => $pageTitle,
            'currentGroup' => $currentGroup,
            'searchQuery' => $searchQuery,
            'groups' => $groups,
            'bookmarks' => $bookmarks,
            'selectedGroup' => $selectedGroup,
            'searchResults' => $results,
            'isSearching' => $isSearching,
            'flashMessages' => $this->session->getFlashBag()->all(),
        ]);

        return new Response($html);
    }

    private function loadAllGroups(): array
    {
        $stmt = $this->pdo->query("
            SELECT g.id, g.name, g.description,
                   (SELECT COUNT(*) FROM bookmarks b WHERE b.group_id = g.id) as bookmark_count
            FROM groups g
            ORDER BY g.name
        ");
        return $stmt->fetchAll();
    }

    private function loadBookmarksForGroup(int $groupId): array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, description FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();

        if (!$group) {
            return ['group' => null, 'bookmarks' => []];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, url, title, description
            FROM bookmarks
            WHERE group_id = ?
            ORDER BY title
        ");
        $stmt->execute([$groupId]);
        $bookmarks = $stmt->fetchAll();

        return ['group' => $group, 'bookmarks' => $bookmarks];
    }

    private function searchBookmarks(string $query): array
    {
        $searchPattern = '%' . $query . '%';

        $stmt = $this->pdo->prepare("
            SELECT b.id, b.url, b.title, b.description,
                   g.id as group_id, g.name as group_name
            FROM bookmarks b
            JOIN groups g ON b.group_id = g.id
            WHERE b.title LIKE ? OR b.description LIKE ?
            ORDER BY g.name, b.title
        ");
        $stmt->execute([$searchPattern, $searchPattern]);
        $allResults = $stmt->fetchAll();

        // Group results by group_id
        $grouped = [];
        foreach ($allResults as $bookmark) {
            $gid = $bookmark['group_id'];
            if (!isset($grouped[$gid])) {
                $grouped[$gid] = [
                    'group_id' => $gid,
                    'group_name' => $bookmark['group_name'],
                    'bookmarks' => [],
                ];
            }
            $grouped[$gid]['bookmarks'][] = $bookmark;
        }

        return array_values($grouped);
    }

    private function isGroupEmpty(int $groupId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookmarks WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return (int) $stmt->fetchColumn() === 0;
    }

    private function buildPageWithBuilder(array $data): string
    {
        // Register inline templates in Twig
        $this->registerTemplates();

        $twig = $this->app->getTwig();

        // Render sections via Twig
        $header = $twig->render('header', $data);
        $sidebar = $twig->render('sidebar', $data);
        $mainContent = $twig->render('main_content', $data);
        $footer = $twig->render('footer', [
            'memory' => $this->app->getMemoryUsage(),
            'time' => number_format($this->app->getElapsedTime(), 3),
        ]);
        $modals = $twig->render('modals', $data);

        // Build final page with PageBuilder
        $builder = new PageBuilder();
        $builder->setTitle($data['pageTitle'] ?? 'Bookmark Manager')
            ->setContainerFluid()
            ->setLayoutColumns(3, 9, 0);

        // Integrated Bootstrap and assets
        $builder->addBootstrap('5.3.8');
        $builder->addStylesheet('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css');
        $builder->addScript('assets/app.js', 'body');

        // Inline CSS for hover effects
        $builder->addInlineStyle(<<<'CSS'
.list-group-item .btn-group-action {
    opacity: 0;
    transition: opacity 0.2s ease-in-out;
}
.list-group-item:hover .btn-group-action {
    opacity: 1;
}
.list-group-item.active {
    font-weight: bold;
}
CSS
            , 'head');

        // Configure layout sections
        $builder->setSection('header', $header)
            ->setSection('left', $sidebar)
            ->setSection('main', $mainContent)
            ->setSection('footer', $footer . $modals);

        return $builder->build();
    }

    private function registerTemplates(): void
    {
        $twig = $this->app->getTwig();
        $loader = $twig->getLoader();

        // Header with search bar
        $loader->setTemplate(
            'header',
            <<<'TWIG'
<header class="bg-white shadow-sm sticky-top py-3 mb-4">
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-secondary d-md-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNav" aria-controls="offcanvasNav" aria-label="Toggle navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <a href="/" class="text-decoration-none text-dark">
                    <h1 class="h4 mb-0">📚 Bookmark Manager</h1>
                </a>
            </div>
            <div class="col-12 col-md-auto mt-2 mt-md-0 ms-md-auto" style="max-width: 450px;">
                <form class="d-flex" method="GET" action="">
                    <input class="form-control me-2" type="search" name="search" placeholder="Search bookmarks..." value="{{ searchQuery }}" aria-label="Search">
                    <button class="btn btn-outline-primary" type="submit" aria-label="Search">
                        <i class="bi bi-search"></i>
                    </button>
                    {% if searchQuery %}
                        <a href="/" class="btn btn-outline-secondary ms-2" aria-label="Clear search">
                            <i class="bi bi-x"></i>
                        </a>
                    {% endif %}
                </form>
            </div>
        </div>
    </div>
</header>

<!-- Offcanvas for mobile navigation -->
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="offcanvasNav" aria-labelledby="offcanvasNavLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavLabel">Navigation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        {{ include('sidebar_content') }}
    </div>
</div>
TWIG
        );

        // Sidebar (used for both desktop and mobile offcanvas)
        $loader->setTemplate(
            'sidebar',
            <<<'TWIG'
<!-- Desktop Sidebar (hidden on mobile) -->
<div class="d-none d-md-block">
    {{ include('sidebar_content') }}
</div>
TWIG
        );

        // Sidebar content (shared between desktop and mobile)
        $loader->setTemplate(
            'sidebar_content',
            <<<'TWIG'
<div class="card shadow-sm border-0 h-100">
    <div class="card-header bg-body-tertiary text-secondary text-uppercase fw-semibold d-flex align-items-center">
        <i class="bi bi-book me-2"></i>Groups
    </div>
    <div class="card-body p-0">
        <div class="d-grid p-3 gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                <i class="bi bi-plus-lg me-2"></i>Add Group
            </button>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBookmarkModal">
                <i class="bi bi-bookmark-plus me-2"></i>Add Bookmark
            </button>
        </div>
        <ul class="list-group list-group-flush">
            {% for group in groups %}
                <li class="list-group-item d-flex justify-content-between align-items-center position-relative">
                    <a href="?group={{ group.id }}" class="text-decoration-none text-body flex-grow-1 pe-2 stretched-link {{ currentGroup == group.id ? 'fw-bold text-primary' : '' }}">
                        {{ group.name }}
                        {% if group.bookmark_count > 0 %}
                            <span class="badge bg-secondary rounded-pill ms-2">{{ group.bookmark_count }}</span>
                        {% endif %}
                    </a>
                    <div class="btn-group-action ms-2" style="position: relative; z-index: 2;">
                        {% if group.bookmark_count == 0 %}
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this empty group?');">
                                <input type="hidden" name="action" value="delete_group">
                                <input type="hidden" name="group_id" value="{{ group.id }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete group">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        {% endif %}
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal" data-bs-target="#editGroupModal"
                            data-group-id="{{ group.id }}"
                            data-group-name="{{ group.name }}"
                            data-group-description="{{ group.description|default('') }}"
                            aria-label="Edit group">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                </li>
            {% endfor %}
        </ul>
    </div>
</div>
TWIG
        );

        // Main content
        $loader->setTemplate(
            'main_content',
            <<<'TWIG'
<main class="p-4">
    {% for type, messages in flashMessages %}
        {% for message in messages %}
            <div class="alert alert-{{ type == 'error' ? 'danger' : 'success' }} alert-dismissible fade show" role="alert">
                {{ message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        {% endfor %}
    {% endfor %}

    {% if isSearching %}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-body-tertiary text-secondary text-uppercase fw-semibold d-flex align-items-center">
                <i class="bi bi-search me-2"></i>Search Results
            </div>
            <div class="card-body">
                <p class="mb-3">Search results for: <strong>{{ searchQuery }}</strong></p>

                {% if searchResults is not empty %}
                    {% for result in searchResults %}
                        <h5 class="fw-bold text-primary mb-2 mt-4">
                            {{ result.group_name }} ({{ result.bookmarks|length }})
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-body-tertiary text-secondary text-uppercase">
                                    <tr>
                                        <th class="fw-semibold" style="width: 80px;">Delete</th>
                                        <th class="fw-semibold" style="width: 80px;">Edit</th>
                                        <th class="fw-semibold">Title</th>
                                        <th class="fw-semibold">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {% for bookmark in result.bookmarks %}
                                        <tr>
                                            <td>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this bookmark?');">
                                                    <input type="hidden" name="action" value="delete_bookmark">
                                                    <input type="hidden" name="bookmark_id" value="{{ bookmark.id }}">
                                                    <input type="hidden" name="group_id" value="{{ result.group_id }}">
                                                    <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete bookmark">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#editBookmarkModal"
                                                    data-bookmark-id="{{ bookmark.id }}"
                                                    data-bookmark-title="{{ bookmark.title }}"
                                                    data-bookmark-url="{{ bookmark.url }}"
                                                    data-bookmark-description="{{ bookmark.description|default('') }}"
                                                    data-bookmark-group="{{ result.group_id }}"
                                                    aria-label="Edit bookmark">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <a href="{{ bookmark.url }}" target="_blank" class="text-decoration-none fw-medium">
                                                    {{ bookmark.title }}
                                                </a>
                                            </td>
                                            <td>{{ bookmark.description|default('')|nl2br }}</td>
                                        </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                        </div>
                    {% endfor %}
                {% else %}
                    <div class="text-center py-5">
                        <i class="bi bi-search fs-1 text-muted mb-3"></i>
                        <p class="text-muted">No bookmarks found matching your search.</p>
                    </div>
                {% endif %}
            </div>
        </div>
    {% elseif selectedGroup %}
        <div class="card shadow-sm border-0">
            <div class="card-header bg-body-tertiary text-secondary text-uppercase fw-semibold d-flex align-items-center">
                <i class="bi bi-folder me-2"></i>{{ selectedGroup.name }}
            </div>
            <div class="card-body">
                {% if selectedGroup.description %}
                    <p class="text-muted mb-4">{{ selectedGroup.description }}</p>
                {% endif %}

                {% if bookmarks is not empty %}
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-body-tertiary text-secondary text-uppercase">
                                <tr>
                                    <th class="fw-semibold" style="width: 80px;">Delete</th>
                                    <th class="fw-semibold" style="width: 80px;">Edit</th>
                                    <th class="fw-semibold">Title</th>
                                    <th class="fw-semibold">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                {% for bookmark in bookmarks %}
                                    <tr>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this bookmark?');">
                                                <input type="hidden" name="action" value="delete_bookmark">
                                                <input type="hidden" name="bookmark_id" value="{{ bookmark.id }}">
                                                <input type="hidden" name="group_id" value="{{ selectedGroup.id }}">
                                                <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete '{{ bookmark.title }}'">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#editBookmarkModal"
                                                data-bookmark-id="{{ bookmark.id }}"
                                                data-bookmark-title="{{ bookmark.title }}"
                                                data-bookmark-url="{{ bookmark.url }}"
                                                data-bookmark-description="{{ bookmark.description|default('') }}"
                                                data-bookmark-group="{{ selectedGroup.id }}"
                                                aria-label="Edit '{{ bookmark.title }}'">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <a href="{{ bookmark.url }}" target="_blank" class="text-decoration-none fw-medium">
                                                {{ bookmark.title }}
                                            </a>
                                        </td>
                                        <td>{{ bookmark.description|default('')|nl2br }}</td>
                                    </tr>
                                {% endfor %}
                            </tbody>
                        </table>
                    </div>
                {% else %}
                    <div class="text-center py-5">
                        <i class="bi bi-bookmark fs-1 text-muted mb-3"></i>
                        <p class="text-muted">No bookmarks in this group yet.</p>
                    </div>
                {% endif %}
            </div>
        </div>
    {% else %}
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">
                <i class="bi bi-arrow-left fs-1 text-muted mb-3 d-none d-md-inline-block"></i>
                <i class="bi bi-list fs-1 text-muted mb-3 d-md-none"></i>
                <p class="text-muted fs-5 d-none d-md-block">Select a group to get started.</p>
                <p class="text-muted fs-5 d-md-none">Select a group from the menu to get started.</p>
            </div>
        </div>
    {% endif %}
</main>
TWIG
        );

        // Footer
        $loader->setTemplate(
            'footer',
            <<<'TWIG'
<footer class="bg-light border-top py-3 mt-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col text-center text-muted small">
                Memory: {{ memory }} | Time: {{ time }}s
            </div>
        </div>
    </div>
</footer>
TWIG
        );

        // Modals
        $loader->setTemplate(
            'modals',
            <<<'TWIG'
<!-- Add Bookmark Modal -->
<div class="modal fade" id="addBookmarkModal" tabindex="-1" aria-labelledby="addBookmarkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addBookmarkModalLabel">Add Bookmark</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_bookmark">
                    <div class="mb-3">
                        <label for="addBookmarkGroup" class="form-label">Group *</label>
                        <select name="group_id" id="addBookmarkGroup" class="form-select" required>
                            <option value="">Select a group</option>
                            {% for group in groups %}
                                <option value="{{ group.id }}" {{ currentGroup == group.id ? 'selected' : '' }}>
                                    {{ group.name }}
                                </option>
                            {% endfor %}
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addBookmarkUrl" class="form-label">URL *</label>
                        <input type="url" name="url" id="addBookmarkUrl" class="form-control" placeholder="https://example.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="addBookmarkTitle" class="form-label">Title *</label>
                        <input type="text" name="title" id="addBookmarkTitle" class="form-control" placeholder="Bookmark title" required>
                    </div>
                    <div class="mb-3">
                        <label for="addBookmarkDescription" class="form-label">Description</label>
                        <textarea name="description" id="addBookmarkDescription" class="form-control" placeholder="Optional description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Bookmark</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bookmark Modal -->
<div class="modal fade" id="editBookmarkModal" tabindex="-1" aria-labelledby="editBookmarkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editBookmarkModalLabel">Edit Bookmark</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_bookmark">
                    <input type="hidden" id="editBookmarkId" name="bookmark_id">
                    <div class="mb-3">
                        <label for="editBookmarkGroup" class="form-label">Group *</label>
                        <select name="group_id" id="editBookmarkGroup" class="form-select" required>
                            {% for group in groups %}
                                <option value="{{ group.id }}">{{ group.name }}</option>
                            {% endfor %}
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editBookmarkUrl" class="form-label">URL *</label>
                        <input type="url" name="url" id="editBookmarkUrl" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="editBookmarkTitle" class="form-label">Title *</label>
                        <input type="text" name="title" id="editBookmarkTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="editBookmarkDescription" class="form-label">Description</label>
                        <textarea name="description" id="editBookmarkDescription" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-labelledby="addGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addGroupModalLabel">Add New Group</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_group">
                    <div class="mb-3">
                        <label for="addGroupName" class="form-label">Group Name *</label>
                        <input type="text" name="group_name" id="addGroupName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="addGroupDescription" class="form-label">Description</label>
                        <textarea name="group_description" id="addGroupDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editGroupModalLabel">Edit Group</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_group">
                    <input type="hidden" id="editGroupId" name="group_id">
                    <div class="mb-3">
                        <label for="editGroupName" class="form-label">Group Name *</label>
                        <input type="text" name="group_name" id="editGroupName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="editGroupDescription" class="form-label">Description</label>
                        <textarea name="group_description" id="editGroupDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
TWIG
        );
    }
}
