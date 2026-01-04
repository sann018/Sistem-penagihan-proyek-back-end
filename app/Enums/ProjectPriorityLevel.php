<?php

namespace App\Enums;

/**
 * Priority Level Enum
 * 
 * Mendefinisikan tingkat prioritas proyek dengan sistem scoring yang jelas.
 * Level yang lebih rendah = prioritas lebih tinggi (urgent).
 */
enum ProjectPriorityLevel: string
{
    case CRITICAL = 'critical';   // P0: Darurat/blocking - Harus diselesaikan segera
    case HIGH = 'high';           // P1: Tinggi - Prioritas tinggi manual
    case MEDIUM = 'medium';       // P2: Menengah - Mendekati deadline
    case LOW = 'low';             // P3: Rendah - Normal
    case NONE = 'none';           // No priority set
    
    /**
     * Get numeric score for sorting (lower = higher priority)
     */
    public function score(): int
    {
        return match($this) {
            self::CRITICAL => 0,
            self::HIGH => 1,
            self::MEDIUM => 2,
            self::LOW => 3,
            self::NONE => 4,
        };
    }
    
    /**
     * Get display label in Indonesian
     */
    public function label(): string
    {
        return match($this) {
            self::CRITICAL => 'Kritis',
            self::HIGH => 'Tinggi',
            self::MEDIUM => 'Menengah',
            self::LOW => 'Rendah',
            self::NONE => 'Tidak Ada',
        };
    }
    
    /**
     * Get color class for UI (Tailwind CSS)
     */
    public function colorClass(): string
    {
        return match($this) {
            self::CRITICAL => 'text-red-700 bg-red-100 border-red-300',
            self::HIGH => 'text-orange-700 bg-orange-100 border-orange-300',
            self::MEDIUM => 'text-yellow-700 bg-yellow-100 border-yellow-300',
            self::LOW => 'text-blue-700 bg-blue-100 border-blue-300',
            self::NONE => 'text-gray-700 bg-gray-100 border-gray-300',
        };
    }
    
    /**
     * Get icon for UI
     */
    public function icon(): string
    {
        return match($this) {
            self::CRITICAL => '🚨',
            self::HIGH => '🔴',
            self::MEDIUM => '🟡',
            self::LOW => '🔵',
            self::NONE => '⚪',
        };
    }
    
    /**
     * Create from legacy integer value
     */
    public static function fromLegacy(?int $value): self
    {
        return match($value) {
            1 => self::HIGH,
            2 => self::MEDIUM,
            default => self::NONE,
        };
    }
}
