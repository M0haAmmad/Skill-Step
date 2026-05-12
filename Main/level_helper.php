<?php
/**
 * Scaling Level Logic
 * Level 1 -> Level 2: 1000 XP
 * Level 2 -> Level 3: 1200 XP (+200 increment)
 * Max Level: 90
 */

function getLevelData($total_xp) {
    $level = 1;
    $xp_required = 1000;
    $current_xp = (int)$total_xp;
    $max_level = 90;
    
    while ($current_xp >= $xp_required && $level < $max_level) {
        $current_xp -= $xp_required;
        $level++;
        $xp_required += 200;
    }
    
    if ($level >= $max_level) {
        return [
            'level' => $max_level,
            'current_level_xp' => $current_xp,
            'next_level_required' => $xp_required,
            'progress_percent' => 100,
            'total_xp' => $total_xp
        ];
    }
    
    return [
        'level' => $level,
        'current_level_xp' => $current_xp,
        'next_level_required' => $xp_required,
        'progress_percent' => ($xp_required > 0) ? min(100, ($current_xp / $xp_required) * 100) : 100,
        'total_xp' => $total_xp
    ];
}
?>
