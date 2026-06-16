<?php
/**
 * Component — header notifications bell + dropdown.
 *
 * Rendered inside the top bar (layout/header.php) on every authenticated page.
 * Shows the unread count as a badge and, on open, a list of the most recent
 * notifications for the current user. Each item is rendered via t(message_key)
 * in the user's current language with the ticket_number interpolated, and links
 * through ?page=notif_open (which marks it read, then forwards to the ticket).
 * A "mark all as read" form clears the lot.
 *
 * Uses a native <details> element so the dropdown needs no JavaScript.
 *
 * Relies on $currentPage (set by header.php) for the mark-all redirect target.
 *
 * @var string $currentPage
 */
require_once MODELS_PATH . '/Notification.php';

$notifUserId = (int) (Auth::id() ?? 0);
$notifUnread = $notifUserId > 0 ? Notification::countUnread($notifUserId) : 0;
$notifItems  = $notifUserId > 0 ? Notification::recent($notifUserId, 10) : [];
$notifBack   = isset($currentPage) ? (string) $currentPage : 'dashboard';
?>
<details class="notif">
    <summary class="notif__bell" aria-label="<?php echo e(t('notifications')); ?>" title="<?php echo e(t('notifications')); ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
        </svg>
        <?php if ($notifUnread > 0): ?>
            <span class="notif__count" aria-hidden="true"><?php echo (int) $notifUnread; ?></span>
            <span class="sr-only"><?php echo e(t('notif_unread', ['count' => $notifUnread])); ?></span>
        <?php endif; ?>
    </summary>

    <div class="notif__panel">
        <div class="notif__header">
            <span class="notif__title"><?php echo e(t('notifications')); ?></span>
            <?php if ($notifUnread > 0): ?>
                <form method="post" action="<?php echo e(BASE_URL); ?>?page=notif_read_all" class="notif__mark-form">
                    <?php echo Auth::csrfField(); ?>
                    <input type="hidden" name="redirect" value="<?php echo e($notifBack); ?>">
                    <button type="submit" class="notif__mark"><?php echo e(t('notif_mark_all_read')); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$notifItems): ?>
            <p class="notif__empty"><?php echo e(t('notif_empty')); ?></p>
        <?php else: ?>
            <ul class="notif__list">
                <?php foreach ($notifItems as $n): ?>
                    <li class="notif__item <?php echo (int) $n['is_read'] === 0 ? 'is-unread' : ''; ?>">
                        <a class="notif__link"
                           href="<?php echo e(BASE_URL); ?>?page=notif_open&amp;id=<?php echo (int) $n['id']; ?>">
                            <span class="notif__text">
                                <?php echo e(t((string) $n['message_key'], ['ticket_number' => (string) $n['ticket_number']])); ?>
                            </span>
                            <span class="notif__time"><?php echo e(Helpers::formatDate($n['created_at'])); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</details>
