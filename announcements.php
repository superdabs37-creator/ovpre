<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Admin', ROLE_RESEARCH_DIRECTOR]);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isArchivedRedirect = (($_GET['archived'] ?? '') === '1') || (($_POST['archived_view'] ?? '') === '1');

    if ($action === 'create') {
        $audience = (string) ($_POST['audience'] ?? '');
        $audienceRoles = announcement_audience_roles($audience);
        if ($audienceRoles === null) {
            flash('error', 'Choose a valid announcement audience.');
            redirect_to('announcements.php');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($title === '' || $content === '') {
            flash('error', 'Enter an announcement title and content.');
            redirect_to('announcements.php');
        }
        db()->prepare('INSERT INTO announcements (title, content, audience, status, posted_by, created_at) VALUES (?, ?, ?, "Published", ?, NOW())')
            ->execute([$title, $content, $audience, $user['id']]);
        $announcementId = (int) db()->lastInsertId();
        if ($audienceRoles === []) {
            $recipients = db()->query("SELECT id FROM users WHERE status = 'Active' AND deleted_at IS NULL");
        } else {
            $rolePlaceholders = implode(', ', array_fill(0, count($audienceRoles), '?'));
            $recipientStmt = db()->prepare("SELECT id FROM users WHERE status = 'Active' AND deleted_at IS NULL AND role IN (" . $rolePlaceholders . ")");
            $recipientStmt->execute($audienceRoles);
            $recipients = $recipientStmt;
        }
        foreach ($recipients as $recipient) {
            notify_user((int) $recipient['id'], 'New announcement: ' . $title, 'announcements.php#announcement-' . $announcementId);
        }
        log_action((int) $user['id'], "Published announcement #{$announcementId}");
        flash('success', 'Announcement published and notifications sent to the selected audience.');
        redirect_to('announcements.php');
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $audience = (string) ($_POST['audience'] ?? 'All');
        if ($id > 0 && $title !== '' && $content !== '') {
            $stmt = db()->prepare('UPDATE announcements SET title = ?, content = ?, audience = ? WHERE id = ?');
            $stmt->execute([$title, $content, $audience, $id]);
            log_action((int) $user['id'], "Updated announcement #{$id}");
            flash('success', 'Announcement updated successfully.');
        } else {
            flash('error', 'Please fill in all required fields.');
        }
        redirect_to('announcements.php' . ($isArchivedRedirect ? '?archived=1' : ''));
    } elseif ($action === 'archive') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE announcements SET status = "Archived" WHERE id = ? AND status <> "Archived"');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_action((int) $user['id'], 'Archived announcement #' . $id);
            flash('success', 'Announcement archived.');
        } else {
            flash('error', 'Announcement could not be found or is already archived.');
        }
    } elseif ($action === 'restore') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE announcements SET status = "Published" WHERE id = ? AND status = "Archived"');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_action((int) $user['id'], 'Restored announcement #' . $id);
            flash('success', 'Announcement restored to published.');
        } else {
            flash('error', 'Announcement could not be found or is already published.');
        }
    } elseif ($action === 'delete') {
        flash('error', 'Deleting announcements is disabled. Use archive instead.');
    } elseif ($action === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), static fn($id) => $id > 0)));
        $bulkAction = (string) ($_POST['bulk_action'] ?? '');

        if (!$ids) {
            flash('error', 'No announcements selected.');
            redirect_to('announcements.php' . ($isArchivedRedirect ? '?archived=1' : ''));
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));

        if ($bulkAction === 'archive') {
            db()->prepare("UPDATE announcements SET status = 'Archived' WHERE status <> 'Archived' AND id IN ($marks)")->execute($ids);
            foreach ($ids as $annId) {
                log_action((int) $user['id'], 'Bulk archived announcement #' . $annId);
            }
            flash('success', count($ids) . ' announcement(s) archived.');
            redirect_to('announcements.php');
        } elseif ($bulkAction === 'restore') {
            db()->prepare("UPDATE announcements SET status = 'Published' WHERE status = 'Archived' AND id IN ($marks)")->execute($ids);
            foreach ($ids as $annId) {
                log_action((int) $user['id'], 'Bulk restored announcement #' . $annId);
            }
            flash('success', count($ids) . ' announcement(s) restored.');
            redirect_to('announcements.php?archived=1');
        } elseif ($bulkAction === 'delete') {
            flash('error', 'Deleting announcements is disabled. Use archive instead.');
            redirect_to('announcements.php' . ($isArchivedRedirect ? '?archived=1' : ''));
        }
    }

    redirect_to('announcements.php' . ($isArchivedRedirect ? '?archived=1' : ''));
}

$notice = flash();
$archived = ($_GET['archived'] ?? '') === '1';
$announcementStatus = $archived ? 'Archived' : 'Published';

$publishedCount = (int) db()->query('SELECT count(*) FROM announcements WHERE status = "Published"')->fetchColumn();
$archivedCount = (int) db()->query('SELECT count(*) FROM announcements WHERE status = "Archived"')->fetchColumn();

$announcementStmt = db()->prepare('SELECT a.*, u.fullname FROM announcements a JOIN users u ON u.id = a.posted_by WHERE a.status = ? ORDER BY a.created_at DESC');
$announcementStmt->execute([$announcementStatus]);
$announcements = $announcementStmt->fetchAll();

function get_announcement_meta(array $item): array {
    $category = $item['category'] ?? '';
    if (!$category) {
        $t = strtolower($item['title'] . ' ' . ($item['content'] ?? ''));
        if (str_contains($t, 'research') && (str_contains($t, 'proposal') || str_contains($t, 'seed'))) $category = 'Research Call';
        elseif (str_contains($t, 'policy') || str_contains($t, 'guideline') || str_contains($t, 'liquidation')) $category = 'Policy';
        elseif (str_contains($t, 'colloquium') || str_contains($t, 'event') || str_contains($t, 'award')) $category = 'Event';
        elseif (str_contains($t, 'extension') && str_contains($t, 'proposal')) $category = 'Extension Call';
        elseif (str_contains($t, 'compendium') || str_contains($t, 'publication')) $category = 'Publication';
        else $category = 'Institutional';
    }

    $priority = $item['priority'] ?? '';
    if (!$priority) {
        $t = strtolower($item['title'] . ' ' . ($item['content'] ?? ''));
        if (str_contains($t, 'urgent') || str_contains($t, 'colloquium') || str_contains($t, 'award')) $priority = 'Urgent';
        elseif (str_contains($t, 'seed funding') || str_contains($t, 'call') || str_contains($t, 'window')) $priority = 'High';
        elseif (str_contains($t, 'guidelines') || str_contains($t, 'policy') || str_contains($t, 'liquidation')) $priority = 'Medium';
        else $priority = 'Normal';
    }

    $expiry = !empty($item['expiry_date']) ? date('M d, Y', strtotime($item['expiry_date'])) : date('M d, Y', strtotime($item['created_at'] . ' +60 days'));

    return [
        'category' => $category,
        'priority' => $priority,
        'expiry' => $expiry,
    ];
}

function render_priority_badge(string $priority): string {
    return match ($priority) {
        'High' => '<strong style="color: #ea580c; font-size: 0.85rem;">High</strong>',
        'Urgent' => '<strong style="color: #dc2626; font-size: 0.85rem;">Urgent</strong>',
        'Medium' => '<strong style="color: #1e3a8a; font-size: 0.85rem;">Medium</strong>',
        default => '<strong style="color: #15803d; font-size: 0.85rem;">Normal</strong>',
    };
}

renderHeader($archived ? 'Archived Announcements' : 'Announcements Management', 'announcements');
?>

<?php if ($notice): ?>
    <div class="notice <?php echo h($notice['type']); ?>" style="margin-bottom: 18px;">
        <i class="ri-<?php echo $notice['type'] === 'success' ? 'checkbox-circle' : 'error-warning'; ?>-line"></i>
        <?php echo h($notice['message']); ?>
    </div>
<?php endif; ?>

<!-- MODAL: CREATE ANNOUNCEMENT -->
<div class="modal" id="createAnnouncementModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="createAnnouncementTitle">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card simple-submit-modal" style="max-width: 620px;">
        <button class="modal-close" type="button" data-close-modal aria-label="Close form"><i class="ri-close-line"></i></button>
        <div class="activity-modal-head" style="margin-bottom: 16px;">
            <span class="category-badge" style="background: #ffedd5; color: #ea580c; font-weight: 700; padding: 3px 10px; border-radius: 6px; font-size: 0.78rem;">Announcement</span>
            <h2 id="createAnnouncementTitle" style="margin: 8px 0 4px; font-size: 1.25rem;">Publish Announcement</h2>
            <p style="margin: 0; color: #64748b; font-size: 0.88rem;">Disseminate critical notices across university portal recipients.</p>
        </div>
        <form class="announcement-form" method="post" style="display: flex; flex-direction: column; gap: 14px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            
            <div>
                <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Announcement Title</label>
                <input name="title" required placeholder="e.g. Call for Research Proposals: FY 2026 Institutional Seed Funding" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Audience</label>
                    <select name="audience" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: #fff;">
                        <option value="" selected disabled>Choose audience</option>
                        <?php foreach (['All', 'Directors', 'Coordinators', 'Faculty Extensionists', 'OVPRE Staff', 'Faculty Researchers'] as $audienceOption): ?>
                            <option value="<?php echo h($audienceOption); ?>"><?php echo h($audienceOption); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Category</label>
                    <select name="category" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: #fff;">
                        <option value="Research Call" selected>Research Call</option>
                        <option value="Policy">Policy</option>
                        <option value="Event">Event</option>
                        <option value="Extension Call">Extension Call</option>
                        <option value="Publication">Publication</option>
                        <option value="General Notice">General Notice</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Priority Level</label>
                    <select name="priority" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: #fff;">
                        <option value="High" selected>High</option>
                        <option value="Medium">Medium</option>
                        <option value="Urgent">Urgent</option>
                        <option value="Normal">Normal</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Expiry Date</label>
                    <input type="text" name="expiry_date" placeholder="e.g. Dec 15, 2026" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem;">
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Announcement Message / Body</label>
                <textarea name="content" rows="5" required placeholder="Write the announcement body here..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; line-height: 1.5;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                <button type="button" class="ghost-btn mini-btn" data-close-modal>Cancel</button>
                <button class="primary-btn post-btn" type="submit" style="background: #ea580c; color: #ffffff; padding: 10px 22px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
                    <i class="ri-send-plane-line"></i> Publish Announcement
                </button>
            </div>
        </form>
    </article>
</div>

<!-- ANNOUNCEMENTS TABLE PANEL -->
<section class="panel" style="border: 1px solid #dbe7df; border-radius: 16px; background: #ffffff; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    <!-- Segmented Control Tabs & Action Button -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px;">
        <div class="portal-table-tabs" style="display: flex; gap: 8px;">
            <a class="portal-table-tab <?php echo !$archived ? 'active' : ''; ?>" href="announcements.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-weight: 700; font-size: 0.88rem; text-decoration: none; <?php echo !$archived ? 'background: #ea580c; color: #ffffff; border: none;' : 'border: 1px solid #cbd5e1; background: #ffffff; color: #475569;'; ?>">
                <i class="ri-checkbox-circle-line"></i>
                <span>Published</span>
                <span class="tab-count" style="<?php echo !$archived ? 'background: rgba(255,255,255,0.25); color: #ffffff;' : 'background: #f1f5f9; color: #475569;'; ?> padding: 2px 8px; border-radius: 999px; font-size: 0.78rem;"><?php echo $publishedCount; ?></span>
            </a>
            <a class="portal-table-tab <?php echo $archived ? 'active' : ''; ?>" href="announcements.php?archived=1" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-weight: 700; font-size: 0.88rem; text-decoration: none; <?php echo $archived ? 'background: #ea580c; color: #ffffff; border: none;' : 'border: 1px solid #cbd5e1; background: #ffffff; color: #475569;'; ?>">
                <i class="ri-archive-line"></i>
                <span>Archived</span>
                <span class="tab-count" style="<?php echo $archived ? 'background: rgba(255,255,255,0.25); color: #ffffff;' : 'background: #f1f5f9; color: #475569;'; ?> padding: 2px 8px; border-radius: 999px; font-size: 0.78rem;"><?php echo $archivedCount; ?></span>
            </a>
        </div>
        <?php if (!$archived): ?>
        <button class="primary-btn" type="button" data-open-modal="createAnnouncementModal" style="display: inline-flex; align-items: center; gap: 8px; padding: 9px 20px; background: #ea580c; color: #ffffff; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; font-size: 0.88rem; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25);">
            <i class="ri-megaphone-line"></i> Publish Announcement
        </button>
        <?php endif; ?>
    </div>

    <!-- Table Toolbar (Search, Show Entries, Export CSV) -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 8px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 8px 14px; background: #ffffff; width: 280px;">
            <i class="ri-search-line" style="color: #94a3b8; font-size: 1rem;"></i>
            <input type="text" id="annSearchInput" oninput="filterPhpAnnTable(this.value)" placeholder="Search this table" style="border: none; outline: none; width: 100%; font-size: 0.88rem; background: transparent;">
        </div>
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.88rem; color: #475569;">
                <span>Show</span>
                <select id="pageSizeSelect" onchange="changePhpPageSize(this.value)" style="border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px 10px; font-size: 0.88rem; background: #fff; cursor: pointer;">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span>entries</span>
            </div>
            <button type="button" class="ghost-btn action-btn" onclick="exportAnnouncementsCsvPhp()" style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: 0.88rem; cursor: pointer;">
                <i class="ri-download-2-line"></i> <span>Export CSV</span>
            </button>
        </div>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="announcementsTable" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #ecfdf5; border-bottom: 2px solid #bbf7d0;">
                <tr style="text-align: left;">
                    <th style="padding: 12px 14px; color: #065f46; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase;">ANNOUNCEMENT TITLE <i class="ri-arrow-up-line" style="font-size: 0.8rem; opacity: 0.7;"></i></th>
                    <th style="padding: 12px 14px; color: #065f46; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase;">CATEGORY <i class="ri-arrow-up-line" style="font-size: 0.8rem; opacity: 0.7;"></i></th>
                    <th style="padding: 12px 14px; color: #065f46; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase;">AUDIENCE <i class="ri-arrow-up-line" style="font-size: 0.8rem; opacity: 0.7;"></i></th>
                    <th style="padding: 12px 14px; color: #065f46; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase;">PRIORITY <i class="ri-arrow-up-line" style="font-size: 0.8rem; opacity: 0.7;"></i></th>
                    <th style="padding: 12px 14px; color: #065f46; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase;">PUBLISHED DATE <i class="ri-arrow-up-line" style="font-size: 0.8rem; opacity: 0.7;"></i></th>
                    <th style="padding: 12px 14px; color: #065f46; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase;">EXPIRY DATE <i class="ri-arrow-up-line" style="font-size: 0.8rem; opacity: 0.7;"></i></th>
                    <th style="padding: 12px 14px; color: #065f46; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.5px; text-transform: uppercase; text-align: center;">MANAGE / ACTION <i class="ri-arrow-up-line" style="font-size: 0.8rem; opacity: 0.7;"></i></th>
                </tr>
            </thead>
            <tbody id="announcementsTableBody" style="font-size: 0.88rem;">
                <?php foreach ($announcements as $item): 
                    $meta = get_announcement_meta($item);
                    $audienceDisplay = $item['audience'] === 'Other Authorized Users' ? 'Faculty Researchers' : $item['audience'];
                ?>
                    <tr data-table-row data-priority="<?php echo h($meta['priority']); ?>" data-category="<?php echo h($meta['category']); ?>" id="announcement-row-<?php echo (int) $item['id']; ?>" style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 14px 14px; max-width: 320px;">
                            <strong style="color: #0f172a; font-size: 0.92rem; display: block; line-height: 1.35; cursor: pointer;" data-open-modal="viewAnnouncementModal<?php echo (int) $item['id']; ?>"><?php echo h($item['title']); ?></strong>
                            <p style="margin: 4px 0 0; font-size: 0.8rem; color: #64748b; line-height: 1.4;">
                                <?php echo h(mb_strimwidth($item['content'] ?? '', 0, 95, '...')); ?>
                            </p>
                        </td>
                        <td style="padding: 14px 14px; color: #334155; font-size: 0.88rem; font-weight: 500;">
                            <?php echo h($meta['category']); ?>
                        </td>
                        <td style="padding: 14px 14px; color: #475569; font-size: 0.88rem; font-weight: 500;">
                            <?php echo h($audienceDisplay); ?>
                        </td>
                        <td style="padding: 14px 14px;">
                            <?php echo render_priority_badge($meta['priority']); ?>
                        </td>
                        <td style="padding: 14px 14px; color: #475569; font-size: 0.88rem; white-space: nowrap;">
                            <?php echo h(date('M d, Y', strtotime($item['created_at']))); ?>
                        </td>
                        <td style="padding: 14px 14px; color: #475569; font-size: 0.88rem; white-space: nowrap;">
                            <?php echo h($meta['expiry']); ?>
                        </td>
                        <td style="padding: 14px 14px; text-align: center; white-space: nowrap;">
                            <div style="display: flex; gap: 6px; align-items: center; justify-content: center;">
                                <button type="button" class="action-btn" data-open-modal="editAnnouncementModal<?php echo (int) $item['id']; ?>" style="background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c; font-weight: 700; font-size: 0.82rem; padding: 4px 14px; border-radius: 8px; cursor: pointer; min-height: 30px;">Edit</button>
                                <?php if ($archived): ?>
                                    <form method="post" style="margin: 0; display: inline;" onsubmit="return confirm('Restore this announcement to published?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                        <button class="action-btn" type="submit" style="background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c; font-weight: 700; font-size: 0.82rem; padding: 4px 14px; border-radius: 8px; cursor: pointer; min-height: 30px;">Restore</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="margin: 0; display: inline;" onsubmit="return confirm('Archive this announcement?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                        <button class="action-btn" type="submit" style="background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c; font-weight: 700; font-size: 0.82rem; padding: 4px 14px; border-radius: 8px; cursor: pointer; min-height: 30px;">Archive</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$announcements): ?>
                    <tr id="emptyRow">
                        <td colspan="7" style="text-align: center; padding: 36px 16px; color: #64748b;">
                            <i class="ri-inbox-line" style="font-size: 2.2rem; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
                            <?php echo $archived ? 'No archived announcements found.' : 'No announcements published yet.'; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Table Footer / Pagination Info -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; padding-top: 14px; border-top: 1px solid #e2e8f0; font-size: 0.85rem; color: #64748b;">
        <div>
            Showing <span id="showingCount"><?php echo count($announcements); ?></span> entries
        </div>
        <div>
            <span style="font-size: 0.85rem; color: #94a3b8;">I-Record Announcements Module</span>
        </div>
    </div>
</section>

<!-- MODALS FOR EACH ANNOUNCEMENT: VIEW & EDIT -->
<?php foreach ($announcements as $item): 
    $meta = get_announcement_meta($item);
    $audienceDisplay = $item['audience'] === 'Other Authorized Users' ? 'Faculty Researchers' : $item['audience'];
?>
    <!-- VIEW MODAL -->
    <div class="modal" id="viewAnnouncementModal<?php echo (int) $item['id']; ?>" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="viewAnnouncementTitle<?php echo (int) $item['id']; ?>">
        <div class="modal-backdrop" data-close-modal></div>
        <article class="modal-card simple-submit-modal" style="max-width: 620px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close modal"><i class="ri-close-line"></i></button>
            <div class="activity-modal-head" style="margin-bottom: 14px;">
                <span class="category-badge" style="background: #ffedd5; color: #ea580c; font-weight: 700; padding: 3px 10px; border-radius: 6px; font-size: 0.78rem;"><?php echo h($meta['category']); ?></span>
                <h2 id="viewAnnouncementTitle<?php echo (int) $item['id']; ?>" style="margin: 8px 0 4px; font-size: 1.25rem; line-height: 1.35;"><?php echo h($item['title']); ?></h2>
                <div style="margin-top: 6px; font-size: 0.84rem; color: #64748b; display: flex; gap: 8px; flex-wrap: wrap;">
                    <span><i class="ri-user-line"></i> <?php echo h($item['fullname'] ?? 'System Administrator'); ?></span> &bull;
                    <span><i class="ri-group-line"></i> <?php echo h($audienceDisplay); ?></span> &bull;
                    <span><i class="ri-calendar-line"></i> <?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></span> &bull;
                    <span>Priority: <?php echo render_priority_badge($meta['priority']); ?></span>
                </div>
            </div>
            <div style="margin-top: 14px; padding: 16px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.92rem; line-height: 1.6; color: #334155; white-space: pre-wrap;">
                <?php echo nl2br(h($item['content'])); ?>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-top: 16px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
                <span style="font-size: 0.82rem; color: #64748b;">Expires: <?php echo h($meta['expiry']); ?></span>
                <button class="ghost-btn mini-btn" type="button" data-close-modal>Close</button>
            </div>
        </article>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal" id="editAnnouncementModal<?php echo (int) $item['id']; ?>" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="editAnnouncementTitle<?php echo (int) $item['id']; ?>">
        <div class="modal-backdrop" data-close-modal></div>
        <article class="modal-card simple-submit-modal" style="max-width: 620px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close modal"><i class="ri-close-line"></i></button>
            <div class="activity-modal-head" style="margin-bottom: 14px;">
                <span class="category-badge" style="background: #ffedd5; color: #ea580c; font-weight: 700; padding: 3px 10px; border-radius: 6px; font-size: 0.78rem;">Edit Announcement</span>
                <h2 id="editAnnouncementTitle<?php echo (int) $item['id']; ?>" style="margin: 8px 0 4px; font-size: 1.25rem;">Edit Announcement #<?php echo (int) $item['id']; ?></h2>
                <p style="margin: 0; color: #64748b; font-size: 0.88rem;">Update announcement headline, audience, or message content.</p>
            </div>
            <form method="post" style="display: flex; flex-direction: column; gap: 14px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Announcement Title</label>
                    <input name="title" required value="<?php echo h($item['title']); ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem;">
                </div>

                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Audience</label>
                    <select name="audience" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: #fff;">
                        <?php foreach (['All', 'Directors', 'Coordinators', 'Faculty Extensionists', 'OVPRE Staff', 'Faculty Researchers'] as $audienceOption): ?>
                            <option value="<?php echo h($audienceOption); ?>" <?php echo $item['audience'] === $audienceOption ? 'selected' : ''; ?>><?php echo h($audienceOption); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 5px;">Announcement Message / Body</label>
                    <textarea name="content" rows="6" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; line-height: 1.5;"><?php echo h($item['content']); ?></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                    <button type="button" class="ghost-btn mini-btn" data-close-modal>Cancel</button>
                    <button class="primary-btn post-btn" type="submit" style="background: #ea580c; color: #ffffff; padding: 10px 22px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;">
                        <i class="ri-save-line"></i> Save Changes
                    </button>
                </div>
            </form>
        </article>
    </div>
<?php endforeach; ?>

<script>
function filterPhpAnnTable(query) {
    query = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#announcementsTableBody tr[data-table-row]');
    let count = 0;
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        if (!query || text.includes(query)) {
            r.style.display = '';
            count++;
        } else {
            r.style.display = 'none';
        }
    });
    const showingCount = document.getElementById('showingCount');
    if (showingCount) showingCount.textContent = count;
}

function exportAnnouncementsCsvPhp() {
    const rows = Array.from(document.querySelectorAll('#announcementsTableBody tr[data-table-row]'));
    if (!rows.length) {
        alert('No announcements to export.');
        return;
    }
    const headers = ['Announcement Title', 'Category', 'Audience', 'Priority', 'Published Date', 'Expiry Date'];
    const lines = [headers.join(',')];

    rows.forEach(r => {
        const cols = r.querySelectorAll('td');
        if (cols.length >= 6) {
            const title = '"' + (cols[0].querySelector('strong')?.textContent.trim() || '').replace(/"/g, '""') + '"';
            const cat = '"' + (cols[1]?.textContent.trim() || '').replace(/"/g, '""') + '"';
            const aud = '"' + (cols[2]?.textContent.trim() || '').replace(/"/g, '""') + '"';
            const prio = '"' + (cols[3]?.textContent.trim() || '').replace(/"/g, '""') + '"';
            const pub = '"' + (cols[4]?.textContent.trim() || '').replace(/"/g, '""') + '"';
            const exp = '"' + (cols[5]?.textContent.trim() || '').replace(/"/g, '""') + '"';
            lines.push([title, cat, aud, prio, pub, exp].join(','));
        }
    });

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'announcements_export.csv';
    link.click();
}
</script>

<?php renderFooter(); ?>
