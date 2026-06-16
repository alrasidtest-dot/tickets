<?php
/**
 * Component — ticket priority badge.
 *
 * Expects: $priorityLevel (int) 1=urgent, 2=medium, 3=low.
 * Colour + label mapping follows docs/FRONTEND_GUIDE.md; labels via lang/.
 */
// The colour is bound to the priority level (1/2/3), not the translated label.
$labelByLevel = [
    1 => 'priority_urgent',
    2 => 'priority_medium',
    3 => 'priority_low',
];

$level    = isset($priorityLevel) ? (int) $priorityLevel : 0;
$cls      = isset($labelByLevel[$level]) ? $level : 3;
$labelKey = $labelByLevel[$cls];
?>
<span class="badge badge-priority--<?php echo $cls; ?>"><?php echo e(t($labelKey)); ?></span>
