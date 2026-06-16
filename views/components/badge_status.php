<?php
/**
 * Component — ticket status badge.
 *
 * Expects: $statusCode (string) one of new/in_progress/on_hold/resolved/closed.
 * Colour mapping follows docs/FRONTEND_GUIDE.md; the label comes from lang/ via
 * the key status_{code} (no hardcoded text).
 */
// Known status codes carry a dedicated soft-pill class (style.css); the colour
// is bound to the status code, not the translated label.
$known = ['new', 'in_progress', 'on_hold', 'resolved', 'closed'];

$code = isset($statusCode) ? (string) $statusCode : '';
$key  = in_array($code, $known, true) ? $code : 'on_hold';
?>
<span class="badge badge-status--<?php echo $key; ?>"><?php echo e(t('status_' . $code)); ?></span>
