<?php
/**
 * Agent — ticket detail + processing: the ticket, its attachments, the comment
 * timeline, the claim / change-status controls and the add-comment form.
 *
 * Vars:
 *   $ticket             joined ticket row (Ticket::findDetailById)
 *   $comments           Comment::findByTicket rows
 *   $ticketAttachments  attachments with comment_id NULL (added at creation)
 *   $commentAttachments map: comment id => attachment rows
 *   $statuses           status lookup rows (id, code) for the change-status form
 *   $errors             field => lang key
 *   $old                sticky comment input
 *   $canModify          true when the ticket is assigned to this agent
 *   $assigned           true right after a successful claim (PRG)
 *   $statusChanged      true right after a successful status change (PRG)
 *   $commented          true right after a successful comment add (PRG)
 *
 * All visible text via t(); attachments download through ?page=download&id=.
 *
 * @var array $ticket
 * @var array $comments
 * @var array $ticketAttachments
 * @var array $commentAttachments
 * @var array $statuses
 * @var array $errors
 * @var array $old
 * @var bool  $canModify
 * @var bool  $assigned
 * @var bool  $statusChanged
 * @var bool  $commented
 */
$lang = Helpers::lang();

// Small local renderer for a list of attachment download links (reused for the
// ticket-level list and each comment's list). No business logic — view only.
$renderAttachments = function (array $items) {
    if (!$items) {
        return;
    }
    echo '<ul class="attachment-list">';
    foreach ($items as $att) {
        $href = e(BASE_URL) . '?page=download&amp;id=' . (int) $att['id'];
        echo '<li class="attachment-list__item">';
        echo '<svg class="attachment-list__icon" width="14" height="14" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
        echo '<a href="' . $href . '">' . e($att['file_name']) . '</a>';
        echo ' <span class="attachment-list__action">(' . e(t('action_download')) . ')</span>';
        echo '</li>';
    }
    echo '</ul>';
};

$ticketId    = (int) $ticket['id'];
$isUnassigned = $ticket['assigned_to'] === null;

// Signed-in agent's name — used to align their own comments as chat bubbles.
$currentUserName = Auth::fullName();

require VIEWS_PATH . '/layout/header.php';
require VIEWS_PATH . '/layout/sidebar.php';
?>
                <div class="page-head">
                    <h1 class="page-title page-title--ticket"><?php echo e($ticket['ticket_number']); ?></h1>
                    <a class="btn btn-outline btn-sm" href="<?php echo e(BASE_URL); ?>?page=agent_dashboard">
                        <?php echo e(t('action_back')); ?>
                    </a>
                </div>

                <?php if ($assigned): ?>
                    <div class="alert alert--success"><?php echo e(t('agent_assigned')); ?></div>
                <?php endif; ?>
                <?php if ($statusChanged): ?>
                    <div class="alert alert--success"><?php echo e(t('agent_status_changed')); ?></div>
                <?php endif; ?>
                <?php if ($commented): ?>
                    <div class="alert alert--success"><?php echo e(t('comment_added')); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert--danger"><?php echo e(t($errors['form'])); ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2 class="card__title"><?php echo e($ticket['title']); ?></h2>

                    <dl class="detail-grid">
                        <div class="detail-grid__row">
                            <dt><?php echo e(t('label_status')); ?></dt>
                            <dd><?php $statusCode = (string) $ticket['status_code']; require VIEWS_PATH . '/components/badge_status.php'; ?></dd>
                        </div>
                        <div class="detail-grid__row">
                            <dt><?php echo e(t('label_priority')); ?></dt>
                            <dd><?php $priorityLevel = (int) $ticket['priority_level']; require VIEWS_PATH . '/components/badge_priority.php'; ?></dd>
                        </div>
                        <div class="detail-grid__row">
                            <dt><?php echo e(t('label_category')); ?></dt>
                            <dd><?php echo e($lang === 'ar' ? $ticket['category_name_ar'] : $ticket['category_name_en']); ?></dd>
                        </div>
                        <div class="detail-grid__row">
                            <dt><?php echo e(t('label_created_by')); ?></dt>
                            <dd><?php echo e($ticket['creator_name']); ?></dd>
                        </div>
                        <div class="detail-grid__row">
                            <dt><?php echo e(t('label_assigned_to')); ?></dt>
                            <dd><?php echo e($ticket['assignee_name'] !== null ? $ticket['assignee_name'] : t('unassigned')); ?></dd>
                        </div>
                        <div class="detail-grid__row">
                            <dt><?php echo e(t('label_created_at')); ?></dt>
                            <dd><?php echo e(Helpers::formatDate($ticket['created_at'])); ?></dd>
                        </div>
                        <div class="detail-grid__row">
                            <dt><?php echo e(t('label_updated_at')); ?></dt>
                            <dd><?php echo e(Helpers::formatDate($ticket['updated_at'])); ?></dd>
                        </div>
                    </dl>

                    <div class="detail-description">
                        <h3 class="detail-subtitle"><?php echo e(t('label_description')); ?></h3>
                        <p class="detail-description__body"><?php echo nl2br(e($ticket['description'])); ?></p>
                    </div>

                    <div class="detail-attachments">
                        <h3 class="detail-subtitle"><?php echo e(t('label_attachments')); ?></h3>
                        <?php if ($ticketAttachments): ?>
                            <?php $renderAttachments($ticketAttachments); ?>
                        <?php else: ?>
                            <p class="empty-state"><?php echo e(t('no_attachments')); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card__title"><?php echo e(t('agent_actions')); ?></h2>

                    <?php if ($isUnassigned): ?>
                        <p><?php echo e(t('agent_claim_hint')); ?></p>
                        <form method="post"
                              action="<?php echo e(BASE_URL); ?>?page=agent_ticket_view&amp;id=<?php echo $ticketId; ?>">
                            <?php echo Auth::csrfField(); ?>
                            <input type="hidden" name="action" value="assign_me">
                            <div class="form-actions">
                                <button class="btn btn-primary" type="submit"><?php echo e(t('agent_claim')); ?></button>
                            </div>
                        </form>
                    <?php elseif ($canModify): ?>
                        <form method="post"
                              action="<?php echo e(BASE_URL); ?>?page=agent_ticket_view&amp;id=<?php echo $ticketId; ?>">
                            <?php echo Auth::csrfField(); ?>
                            <input type="hidden" name="action" value="change_status">

                            <div class="form-group">
                                <label class="form-label" for="status_id"><?php echo e(t('agent_change_status')); ?></label>
                                <select class="form-control" id="status_id" name="status_id" required>
                                    <?php foreach ($statuses as $s): ?>
                                        <option value="<?php echo (int) $s['id']; ?>"
                                            <?php echo (string) $ticket['status_code'] === (string) $s['code'] ? 'selected' : ''; ?>>
                                            <?php echo e(t('status_' . $s['code'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($errors['status'])): ?>
                                    <p class="form-error"><?php echo e(t($errors['status'])); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button class="btn btn-primary" type="submit"><?php echo e(t('agent_status_update')); ?></button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="empty-state"><?php echo e(t('agent_not_allowed')); ?></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2 class="card__title"><?php echo e(t('comments_title')); ?></h2>

                    <?php if (!$comments): ?>
                        <p class="empty-state"><?php echo e(t('no_comments')); ?></p>
                    <?php else: ?>
                        <ul class="comment-list">
                            <?php foreach ($comments as $comment): ?>
                                <?php $isMine = $comment['author_name'] === $currentUserName; ?>
                                <li class="comment<?php echo $isMine ? ' comment--mine' : ''; ?>">
                                    <span class="comment__avatar" aria-hidden="true"><?php echo e(mb_substr($comment['author_name'], 0, 1)); ?></span>
                                    <div class="comment__content">
                                        <div class="comment__head">
                                            <span class="comment__author"><?php echo e($comment['author_name']); ?></span>
                                            <span class="comment__date"><?php echo e(Helpers::formatDate($comment['created_at'])); ?></span>
                                        </div>
                                        <div class="comment__body"><?php echo nl2br(e($comment['comment'])); ?></div>
                                        <?php $renderAttachments($commentAttachments[(int) $comment['id']] ?? []); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2 class="card__title"><?php echo e(t('comment_add')); ?></h2>

                    <form method="post"
                          action="<?php echo e(BASE_URL); ?>?page=agent_ticket_view&amp;id=<?php echo $ticketId; ?>">
                        <?php echo Auth::csrfField(); ?>
                        <input type="hidden" name="action" value="add_comment">

                        <div class="form-group">
                            <label class="form-label" for="comment"><?php echo e(t('label_comment')); ?></label>
                            <textarea class="form-control" id="comment" name="comment" rows="3" required><?php echo e($old['comment']); ?></textarea>
                            <?php if (!empty($errors['comment'])): ?>
                                <p class="form-error"><?php echo e(t($errors['comment'])); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions form-actions--end">
                            <button class="btn btn-accent" type="submit"><?php echo e(t('comment_submit')); ?></button>
                        </div>
                    </form>
                </div>
<?php
require VIEWS_PATH . '/layout/footer.php';
