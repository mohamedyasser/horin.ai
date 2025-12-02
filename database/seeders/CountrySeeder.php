<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed countries
        $countries = [
            [
                'id' => '05291500-f7c4-4a23-b8a6-27ee63b10770',
                'name_en' => 'United Arab Emirates',
                'name_ar' => 'الإمارات العربية المتحدة',
                'code' => 'AE',
                'currency_en' => 'UAE Dirham',
                'currency_ar' => 'درهم إماراتي',
                'currency_code' => 'AED',
            ],
            [
                'id' => '4e43d19b-e3af-4aac-8f25-d74b4e3bc21e',
                'name_en' => 'Qatar',
                'name_ar' => 'قطر',
                'code' => 'QA',
                'currency_en' => 'Qatari Riyal',
                'currency_ar' => 'ريال قطري',
                'currency_code' => 'QAR',
            ],
            [
                'id' => '6895eb95-0ca9-4c84-ae88-a65f8689463f',
                'name_en' => 'Bahrain',
                'name_ar' => 'البحرين',
                'code' => 'BH',
                'currency_en' => 'Bahraini Dinar',
                'currency_ar' => 'دينار بحريني',
                'currency_code' => 'BHD',
            ],
            [
                'id' => '82991941-c2cb-448f-b445-5c4294a5a43e',
                'name_en' => 'Kuwait',
                'name_ar' => 'الكويت',
                'code' => 'KW',
                'currency_en' => 'Kuwaiti Dinar',
                'currency_ar' => 'دينار كويتي',
                'currency_code' => 'KWD',
            ],
            [
                'id' => 'cf0b6f43-babb-4364-b0e6-b2b35c403225',
                'name_en' => 'Egypt',
                'name_ar' => 'مصر',
                'code' => 'EG',
                'currency_en' => 'Egyptian Pound',
                'currency_ar' => 'جنيه مصري',
                'currency_code' => 'EGP',
            ],
            [
                'id' => 'e292b43a-e8dc-484e-b20e-ec45ad6407a6',
                'name_en' => 'Saudi Arabia',
                'name_ar' => 'السعودية',
                'code' => 'SA',
                'currency_en' => 'Saudi Riyal',
                'currency_ar' => 'ريال سعودي',
                'currency_code' => 'SAR',
            ],
        ];

        // Create countries from array
        foreach ($countries as $data) {
            Country::firstOrCreate(
                ['id' => $data['id']],
                [
                    'name_en' => $data['name_en'],
                    'name_ar' => $data['name_ar'],
                    'code' => $data['code'],
                    'currency_en' => $data['currency_en'],
                    'currency_ar' => $data['currency_ar'],
                    'currency_code' => $data['currency_code'],
                ]
            );
        }
    }
}
