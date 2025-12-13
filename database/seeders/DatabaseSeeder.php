<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Penagihan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create sample invoices
        Penagihan::create([
            'project_name' => 'E-Commerce Platform',
            'client_name' => 'Tech Corp Inc',
            'invoice_number' => 'INV-2024-001',
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'amount' => 25000000,
            'status' => 'paid',
            'notes' => 'Full payment received',
            'payment_date' => '2024-02-10',
        ]);

        Penagihan::create([
            'project_name' => 'Mobile App Development',
            'client_name' => 'Digital Solutions Ltd',
            'invoice_number' => 'INV-2024-002',
            'invoice_date' => '2024-02-01',
            'due_date' => '2024-03-01',
            'amount' => 35000000,
            'status' => 'pending',
            'notes' => 'Awaiting payment',
        ]);

        Penagihan::create([
            'project_name' => 'System Integration',
            'client_name' => 'Future Tech Co',
            'invoice_number' => 'INV-2024-003',
            'invoice_date' => '2024-01-20',
            'due_date' => '2024-02-20',
            'amount' => 50000000,
            'status' => 'overdue',
            'notes' => 'Payment overdue',
        ]);

        Penagihan::create([
            'project_name' => 'Cloud Migration',
            'client_name' => 'Global Systems',
            'invoice_number' => 'INV-2024-004',
            'invoice_date' => '2024-03-01',
            'due_date' => '2024-04-01',
            'amount' => 45000000,
            'status' => 'pending',
            'notes' => 'Cloud infrastructure project',
        ]);

        Penagihan::create([
            'project_name' => 'Website Redesign',
            'client_name' => 'Creative Agency',
            'invoice_number' => 'INV-2024-005',
            'invoice_date' => '2024-02-15',
            'due_date' => '2024-03-15',
            'amount' => 20000000,
            'status' => 'cancelled',
            'notes' => 'Project cancelled by client',
        ]);
    }
}

