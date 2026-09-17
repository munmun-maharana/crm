<?php

namespace Database\Seeders;

use App\Models\CrmField;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_table' => true, 'is_system' => true, 'sort_order' => 1],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'required' => false, 'in_table' => true, 'is_system' => true, 'sort_order' => 2],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'in_table' => true, 'is_system' => true, 'sort_order' => 3],
            ['key' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false, 'in_table' => false, 'is_system' => true, 'sort_order' => 4],
            ['key' => 'city', 'label' => 'City', 'type' => 'text', 'required' => false, 'in_table' => true, 'is_system' => true, 'sort_order' => 5],
            ['key' => 'source', 'label' => 'Source', 'type' => 'text', 'required' => false, 'in_table' => true, 'is_system' => true, 'sort_order' => 6],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'in_table' => true, 'is_system' => true, 'sort_order' => 7, 'options' => ['new', 'interested', 'contacted', 'converted', 'lost', 'request']],
            ['key' => 'assigned_to', 'label' => 'Assign agent', 'type' => 'select', 'required' => false, 'in_table' => false, 'in_excel' => false, 'is_system' => true, 'sort_order' => 8],
            ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'in_table' => false, 'is_system' => true, 'sort_order' => 9],
        ];

        foreach ($defaults as $field) {
            CrmField::create([
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => $field['options'] ?? null,
                'required' => $field['required'],
                'active' => true,
                'in_form' => true,
                'in_table' => $field['in_table'] ?? false,
                'in_excel' => $field['in_excel'] ?? true,
                'is_system' => $field['is_system'] ?? false,
                'sort_order' => $field['sort_order'],
            ]);
        }

        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@crm.local',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        $agent = User::create([
            'name' => 'Agent One',
            'username' => 'agent',
            'email' => 'agent@crm.local',
            'password' => Hash::make('agent123'),
            'role' => 'agent',
        ]);

        $samples = [
            ['name' => 'Riya Sharma', 'email' => 'riya@example.com', 'phone' => '9876500011', 'company' => 'Nova Tech', 'city' => 'Delhi', 'status' => 'interested', 'source' => 'Website'],
            ['name' => 'Aman Verma', 'email' => 'aman@example.com', 'phone' => '9876500022', 'company' => 'Pixel Soft', 'city' => 'Mumbai', 'status' => 'request', 'source' => 'WhatsApp'],
            ['name' => 'Neha Gupta', 'email' => 'neha@example.com', 'phone' => '9876500033', 'company' => 'Orbit Labs', 'city' => 'Pune', 'status' => 'contacted', 'source' => 'Call'],
            ['name' => 'Kunal Mehta', 'email' => 'kunal@example.com', 'phone' => '9876500044', 'company' => 'Bright Co', 'city' => 'Jaipur', 'status' => 'converted', 'source' => 'Referral'],
            ['name' => 'Sana Khan', 'email' => 'sana@example.com', 'phone' => '9876500055', 'company' => 'Leaf Media', 'city' => 'Hyderabad', 'status' => 'lost', 'source' => 'Excel'],
            ['name' => 'Vikram Singh', 'email' => 'vikram@example.com', 'phone' => '9876500066', 'company' => 'Core Systems', 'city' => 'Noida', 'status' => 'new', 'source' => 'Form'],
            ['name' => 'Pooja Nair', 'email' => 'pooja@example.com', 'phone' => '9876500077', 'company' => 'Zen Retail', 'city' => 'Kochi', 'status' => 'interested', 'source' => 'Instagram'],
            ['name' => 'Arjun Patel', 'email' => 'arjun@example.com', 'phone' => '9876500088', 'company' => 'Delta Foods', 'city' => 'Ahmedabad', 'status' => 'request', 'source' => 'Email'],
        ];

        foreach ($samples as $i => $row) {
            $customer = Customer::create([
                ...$row,
                'notes' => null,
                'assigned_to' => $i % 2 === 0 ? $admin->id : $agent->id,
                'last_contact_at' => now()->subDays($i),
                'created_at' => now()->subDays($i * 3),
                'updated_at' => now()->subDays($i),
            ]);

            CustomerActivity::create([
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'type' => 'note',
                'title' => 'Initial note',
                'body' => 'Customer showed interest in the product demo.',
                'created_at' => now()->subDays($i + 1),
            ]);

            CustomerActivity::create([
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'type' => 'whatsapp',
                'title' => 'WhatsApp follow-up',
                'body' => 'Sent brochure and pricing sheet on WhatsApp.',
                'created_at' => now()->subHours($i + 2),
            ]);
        }
    }
}
