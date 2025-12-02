<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed sectors
        $sectors = [
            [
                'id' => '0d3a2667-74db-441a-ae73-03b9414736bb',
                'name_en' => 'Real Estate',
                'name_ar' => 'العقارات',
                'description_en' => 'Sector related to real estate development, management, and investment.',
                'description_ar' => 'قطاع يتعلق بتطوير العقارات وإدارتها واستثمارها.',
            ],
            [
                'id' => '0fd1bbaa-8060-4165-8084-3caf0ad624e6',
                'name_en' => 'Retail',
                'name_ar' => 'التجزئة',
                'description_en' => 'Sector related to retail businesses and consumer sales.',
                'description_ar' => 'قطاع يتعلق بالأعمال التجارية للمستهلكين.',
            ],
            [
                'id' => '105189b4-0415-4156-a1a3-09266cf8f741',
                'name_en' => 'Materials',
                'name_ar' => 'المواد',
                'description_en' => 'Sector producing and distributing raw materials and commodities.',
                'description_ar' => 'قطاع ينتج ويوزع المواد الخام والسلع.',
            ],
            [
                'id' => '12755d84-04b8-4c5d-844b-4de9a1c763c7',
                'name_en' => 'Energy',
                'name_ar' => 'الطاقة',
                'description_en' => 'Sector focusing on oil, gas, and renewable energy.',
                'description_ar' => 'قطاع يركز على النفط والغاز والطاقة المتجددة.',
            ],
            [
                'id' => '47ebe274-48d3-433d-b4ab-32fd748bd8ad',
                'name_en' => 'Technology',
                'name_ar' => 'التكنولوجيا',
                'description_en' => 'Sector focusing on IT services, software, and hardware.',
                'description_ar' => 'قطاع يركز على خدمات تكنولوجيا المعلومات والبرمجيات والأجهزة.',
            ],
            [
                'id' => '59049759-5928-463c-a47c-76f9aa2cf6d9',
                'name_en' => 'Telecommunications',
                'name_ar' => 'الاتصالات',
                'description_en' => 'Sector focusing on telecom services, products, and infrastructure.',
                'description_ar' => 'قطاع يركز على خدمات ومنتجات البنية التحتية للاتصالات.',
            ],
            [
                'id' => '5ac5f8ac-198b-4655-88e9-0e08c75ad038',
                'name_en' => 'Industrials',
                'name_ar' => 'الصناعية',
                'description_en' => 'Sector encompassing manufacturing and industrial services.',
                'description_ar' => 'قطاع يشمل التصنيع والخدمات الصناعية.',
            ],
            [
                'id' => '5cba38be-1950-4fcd-8f78-57911ef2b71e',
                'name_en' => 'Consumer Staples',
                'name_ar' => 'السلع الاستهلاكية الأساسية',
                'description_en' => 'Sector providing essential goods like food and beverages.',
                'description_ar' => 'قطاع يوفر السلع الأساسية مثل الأغذية والمشروبات.',
            ],
            [
                'id' => '6c73e0f1-1781-4df1-a34e-e974a88b1ed3',
                'name_en' => 'General',
                'name_ar' => 'عام',
                'description_en' => 'Sector for general and unspecified categories.',
                'description_ar' => 'قطاع للفئات العامة وغير المحددة.',
            ],
            [
                'id' => '8bd6ab2c-b1cf-4258-8b1c-82f9445d5c04',
                'name_en' => 'Transport',
                'name_ar' => 'النقل',
                'description_en' => 'Sector focusing on logistics, shipping, and transportation services.',
                'description_ar' => 'قطاع يركز على الخدمات اللوجستية والشحن والنقل.',
            ],
            [
                'id' => '8d509fc2-bfab-4da4-973c-81e35e0a7aa2',
                'name_en' => 'Healthcare',
                'name_ar' => 'الرعاية الصحية',
                'description_en' => 'Sector providing medical services, pharmaceuticals, and equipment.',
                'description_ar' => 'قطاع يقدم خدمات طبية وأدوية ومعدات.',
            ],
            [
                'id' => '92b0594f-d1f5-43b5-95d9-6dc32122d4d3',
                'name_en' => 'Consumer Discretionary',
                'name_ar' => 'السلع الاستهلاكية الكمالية',
                'description_en' => 'Sector providing luxury and non-essential goods.',
                'description_ar' => 'قطاع يوفر السلع الكمالية وغير الأساسية.',
            ],
            [
                'id' => '995d8a79-659b-4672-92c2-1743f546cd9c',
                'name_en' => 'Miscellaneous',
                'name_ar' => 'متنوع',
                'description_en' => 'Sector including various unrelated activities.',
                'description_ar' => 'قطاع يشمل أنشطة متنوعة وغير ذات صلة.',
            ],
            [
                'id' => '9bc791a7-f1f9-471e-a3be-4256e9aeea8a',
                'name_en' => 'Construction',
                'name_ar' => 'البناء',
                'description_en' => 'Sector encompassing construction, engineering, and infrastructure.',
                'description_ar' => 'قطاع يشمل البناء والهندسة والبنية التحتية.',
            ],
            [
                'id' => 'add59cd8-3881-44fa-a901-461281c0e628',
                'name_en' => 'Financials',
                'name_ar' => 'المالية',
                'description_en' => 'Sector focused on financial services such as banking, insurance, and investment.',
                'description_ar' => 'قطاع يركز على الخدمات المالية مثل البنوك والتأمين والاستثمار.',
            ],
            [
                'id' => 'c3343659-04c3-4193-a1a5-3ad88ce00dd6',
                'name_en' => 'Tourism',
                'name_ar' => 'السياحة',
                'description_en' => 'Sector focusing on travel, hospitality, and tourism services.',
                'description_ar' => 'قطاع يركز على السفر والضيافة وخدمات السياحة.',
            ],
            [
                'id' => 'e7fa345f-7eea-4617-b205-0e821a1dc7f5',
                'name_en' => 'Utilities',
                'name_ar' => 'الخدمات العامة',
                'description_en' => 'Sector providing essential public services such as electricity and water.',
                'description_ar' => 'قطاع يوفر خدمات عامة أساسية مثل الكهرباء والمياه.',
            ],
        ];

        // Create sectors from array
        foreach ($sectors as $data) {
            Sector::firstOrCreate(
                ['id' => $data['id']],
                [
                    'name_en' => $data['name_en'],
                    'name_ar' => $data['name_ar'],
                    'description_en' => $data['description_en'],
                    'description_ar' => $data['description_ar'],
                ]
            );
        }
    }
}
