<?php

namespace Database\Seeders;

use App\Domain\Supplier\Models\Supplier;
use App\Enums\SupplierAddressType;
use App\Enums\SupplierStatus;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'code' => 'SUP-001',
                'name' => 'Nigerchin Cables Ltd',
                'legal_name' => 'Nigerchin Cables Industries Limited',
                'tax_identification_number' => 'TIN-1002003001',
                'registration_number' => 'RC-120001',
                'email' => 'sales@nigerchin.test',
                'phone' => '+234 803 100 2001',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'address' => 'Km 18 Lagos-Ibadan Expressway, Lagos',
                'contact_person' => 'Adebayo Ogunleye',
                'status' => SupplierStatus::Active,
                'is_approved' => true,
                'rating' => '4.50',
            ],
            [
                'code' => 'SUP-002',
                'name' => 'ABB Power Products',
                'legal_name' => 'ABB Nigeria Limited',
                'tax_identification_number' => 'TIN-1002003002',
                'registration_number' => 'RC-120002',
                'email' => 'power@abb.test',
                'phone' => '+234 803 100 2002',
                'city' => 'Abuja',
                'state' => 'FCT',
                'country' => 'Nigeria',
                'address' => 'Plot 1234 Central Business District, Abuja',
                'contact_person' => 'Chinwe Eze',
                'status' => SupplierStatus::Active,
                'is_approved' => true,
                'rating' => '4.80',
            ],
            [
                'code' => 'SUP-003',
                'name' => 'Momas Metering Systems',
                'legal_name' => 'Momas Electricity Meters Manufacturing Company Ltd',
                'tax_identification_number' => 'TIN-1002003003',
                'registration_number' => 'RC-120003',
                'email' => 'meters@momas.test',
                'phone' => '+234 803 100 2003',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'address' => 'Momas Avenue, Mowe, Ogun State',
                'contact_person' => 'Funke Adeyemi',
                'status' => SupplierStatus::Active,
                'is_approved' => true,
                'rating' => '4.20',
            ],
            [
                'code' => 'SUP-004',
                'name' => '3M Safety Nigeria',
                'legal_name' => '3M Nigeria Limited',
                'tax_identification_number' => 'TIN-1002003004',
                'registration_number' => 'RC-120004',
                'email' => 'safety@3m.test',
                'phone' => '+234 803 100 2004',
                'city' => 'Port Harcourt',
                'state' => 'Rivers',
                'country' => 'Nigeria',
                'address' => 'Trans Amadi Industrial Layout, Port Harcourt',
                'contact_person' => 'Ibrahim Musa',
                'status' => SupplierStatus::Active,
                'is_approved' => true,
                'rating' => '4.10',
            ],
            [
                'code' => 'SUP-005',
                'name' => 'Delta Tools & Equipment',
                'legal_name' => 'Delta Tools & Equipment Nigeria Ltd',
                'tax_identification_number' => 'TIN-1002003005',
                'registration_number' => 'RC-120005',
                'email' => 'info@deltatools.test',
                'phone' => '+234 803 100 2005',
                'city' => 'Ikeja',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'address' => '22 Allen Avenue, Ikeja, Lagos',
                'contact_person' => 'Samuel Okoro',
                'status' => SupplierStatus::Pending,
                'is_approved' => false,
                'rating' => null,
            ],
            [
                'code' => 'SUP-006',
                'name' => 'Sahara Electricals',
                'legal_name' => 'Sahara Electricals & Supplies Ltd',
                'tax_identification_number' => 'TIN-1002003006',
                'registration_number' => 'RC-120006',
                'email' => 'sales@saharaelectricals.test',
                'phone' => '+234 803 100 2006',
                'city' => 'Kano',
                'state' => 'Kano',
                'country' => 'Nigeria',
                'address' => '15 Murtala Muhammed Way, Kano',
                'contact_person' => 'Aisha Bello',
                'status' => SupplierStatus::Suspended,
                'is_approved' => false,
                'rating' => '2.50',
            ],
        ];

        foreach ($suppliers as $data) {
            $supplier = Supplier::updateOrCreate(
                ['code' => $data['code']],
                [
                    ...$data,
                    'status' => $data['status']->value,
                    'approved_at' => $data['is_approved'] ? now()->subMonths(6) : null,
                ],
            );

            $supplier->contacts()->updateOrCreate(
                ['email' => strtolower(str_replace(' ', '.', $data['contact_person'])).'@'.$supplier->code.'.test'],
                [
                    'name' => $data['contact_person'],
                    'position' => 'Sales Manager',
                    'phone' => $data['phone'],
                    'is_primary' => true,
                ],
            );

            $supplier->addresses()->updateOrCreate(
                ['type' => SupplierAddressType::Registered->value, 'line1' => $data['address']],
                [
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'country' => $data['country'],
                    'is_primary' => true,
                ],
            );
        }
    }
}
