<?php

namespace App\Enums;

/**
 * Priority Source Enum
 * 
 * Mendefinisikan sumber/alasan mengapa proyek diberi prioritas.
 */
enum ProjectPrioritySource: string
{
    case MANUAL = 'manual';           // Set manual oleh user (super_admin/admin)
    case AUTO_DEADLINE = 'auto_deadline';  // Auto karena mendekati deadline
    case AUTO_OVERDUE = 'auto_overdue';    // Auto karena sudah lewat deadline
    case AUTO_BLOCKED = 'auto_blocked';    // Auto karena ada blocker
    case SYSTEM = 'system';          // Set oleh sistem (rules engine)
    
    /**
     * Get display label
     */
    public function label(): string
    {
        return match($this) {
            self::MANUAL => 'Manual (User)',
            self::AUTO_DEADLINE => 'Otomatis (Mendekati Deadline)',
            self::AUTO_OVERDUE => 'Otomatis (Lewat Deadline)',
            self::AUTO_BLOCKED => 'Otomatis (Terhambat)',
            self::SYSTEM => 'Sistem',
        };
    }
    
    /**
     * Check if can be overridden manually
     */
    public function canOverride(): bool
    {
        return match($this) {
            self::MANUAL, self::SYSTEM => true,
            default => false, // Auto priorities tidak bisa di-override
        };
    }
}
