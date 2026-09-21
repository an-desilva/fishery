<?php
/**
 * System Constants & UI Formatting Utilities
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics System
 */

define('APP_NAME', 'SeaLogix - Catch & Logistics System');
define('APP_VERSION', '2.0.0');

define('CURRENCY_SYMBOL', 'Rs.');
define('CURRENCY_CODE', 'LKR');

// Fish Species List
define('FISH_SPECIES', [
    'Yellowfin Tuna (Kelawalla)' => 'Yellowfin Tuna (Kelawalla / කෙලවල්ලා)',
    'Skipjack (Balaya)'           => 'Skipjack (Balaya / බලයා)',
    'Sailfish (Thalapatha)'      => 'Sailfish (Thalapatha / තලපතා)',
    'Marlin (Koppara)'           => 'Marlin (Koppara / කොප්පරා)',
    'Alagoduwa'                  => 'Alagoduwa (අලගොඩුවා)',
    'Hurulla'                    => 'Hurulla (හුරුල්ලා)',
    'Mixed / Small Fish'         => 'Mixed / Small Fish (මිශ්‍ර / කුඩා මාළු)'
]);

// Quality Grades
define('QUALITY_GRADES', [
    'Grade 1 (①)' => 'Grade 1 (① - High Export Grade)',
    'Grade 2 (②)' => 'Grade 2 (② - Local Wholesale)',
    'Grade 3 (③)' => 'Grade 3 (③ - Retail / Canning)',
    'Reject'       => 'Reject (ප්‍රතික්ෂේපිත / C-Grade)'
]);

// Size Categories
define('SIZE_CATEGORIES', [
    'L' => 'L (Large / ලොකු)',
    'P' => 'P (Small / පොඩි)'
]);

// Payment Types for Direct Buyers
define('PAYMENT_TYPES', [
    'CASH'          => 'Cash (තනි මුදලින්)',
    'BANK_TRANSFER' => 'Bank Transfer (බැංකු හුවමාරු)',
    'CREDIT'        => 'Credit / Outstanding (ණය)'
]);


function formatCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2, '.', ',');
}

function formatWeight($weightKg) {
    return number_format((float)$weightKg, 2, '.', '') . ' Kg';
}

function formatDate($dateStr, $format = 'd M Y') {
    if (empty($dateStr)) return '-';
    return date($format, strtotime($dateStr));
}

function getStatusBadge($status) {
    switch (strtoupper($status)) {
        case 'DEPARTED':
            return '<span class="badge bg-primary text-wrap px-3 py-2"><i class="fa-solid fa-anchor me-1"></i> At Sea (Departed)</span>';
        case 'LANDED':
            return '<span class="badge bg-warning text-dark text-wrap px-3 py-2"><i class="fa-solid fa-water-lower me-1"></i> Landed at Harbour</span>';
        case 'DISPATCHED':
            return '<span class="badge bg-info text-dark text-wrap px-3 py-2"><i class="fa-solid fa-truck-fast me-1"></i> Catch Dispatched</span>';
        case 'COMPLETED':
            return '<span class="badge bg-success text-wrap px-3 py-2"><i class="fa-solid fa-lock me-1"></i> Completed & Locked</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

function getGradeBadge($grade) {
    if (strpos($grade, '1') !== false || strpos($grade, '①') !== false) {
        return '<span class="badge bg-success"><i class="fa-solid fa-award me-1"></i>Grade 1 ①</span>';
    } elseif (strpos($grade, '2') !== false || strpos($grade, '②') !== false) {
        return '<span class="badge bg-primary">Grade 2 ②</span>';
    } elseif (strpos($grade, '3') !== false || strpos($grade, '③') !== false) {
        return '<span class="badge bg-warning text-dark">Grade 3 ③</span>';
    } else {
        return '<span class="badge bg-danger">Reject</span>';
    }
}

function getRoleBadge($role) {
    if (strtoupper($role) === 'ADMIN') {
        return '<span class="badge bg-danger"><i class="fa-solid fa-user-shield me-1"></i>Admin</span>';
    } else {
        return '<span class="badge bg-secondary"><i class="fa-solid fa-user me-1"></i>Staff</span>';
    }
}
