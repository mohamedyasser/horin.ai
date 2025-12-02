<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CryptoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only run if environment is not production
        if (app()->isProduction()) {
            $this->command->info('CryptoSeeder is disabled in production environment.');

            return;
        }

        // Create or find country
        $country = Country::firstOrCreate(
            ['code' => 'GLOBAL'],
            [
                'name_en' => 'Global',
                'name_ar' => 'عالمي',
                'currency_en' => 'US Dollar',
                'currency_ar' => 'دولار أمريكي',
                'currency_code' => 'USD',
            ]
        );

        // Create or find market
        $market = Market::firstOrCreate(
            ['code' => 'CRYPTO'],
            [
                'country_id' => $country->id,
                'name_en' => 'Cryptocurrency Market',
                'name_ar' => 'سوق العملات المشفرة',
                'timezone' => 'UTC',
                'status' => 1,
                'is_open' => true,
            ]
        );

        // Create or find sector
        $sector = Sector::firstOrCreate(
            ['name_en' => 'Cryptocurrency'],
            [
                'name_ar' => 'العملات المشفرة',
                'description_en' => 'Digital or virtual currencies that use cryptography for security',
                'description_ar' => 'العملات الرقمية أو الافتراضية التي تستخدم التشفير للأمان',
            ]
        );

        $cryptoAssets = [
            [
                'symbol' => 'BTC',
                'inv_id' => '49799',
                'name_en' => 'Bitcoin',
                'name_ar' => 'بيتكوين',
                'description_en' => 'Bitcoin is a decentralized digital currency, without a central bank or single administrator.',
                'description_ar' => 'بيتكوين هي عملة رقمية لامركزية، بدون بنك مركزي أو مسؤول واحد.',
                'short_description_en' => 'Decentralized digital currency',
                'short_description_ar' => 'عملة رقمية لامركزية',
                'full_name' => 'Kraken:BTC/USD',
                'tv_id' => 'BTC/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'ETH',
                'inv_id' => '997650',
                'name_en' => 'Ethereum',
                'name_ar' => 'إيثيريوم',
                'description_en' => 'Ethereum is a decentralized, open-source blockchain with smart contract functionality.',
                'description_ar' => 'إيثيريوم هي بلوكشين لامركزية ومفتوحة المصدر مع وظائف العقود الذكية.',
                'short_description_en' => 'Blockchain platform with smart contracts',
                'short_description_ar' => 'منصة بلوكشين مع عقود ذكية',
                'full_name' => 'Bitfinex:ETH/USD',
                'tv_id' => 'ETH/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'XRP',
                'inv_id' => '1075586',
                'name_en' => 'XRP',
                'name_ar' => 'إكس آر بي',
                'description_en' => 'XRP is a digital asset built for payments. It is the native digital asset on the XRP Ledger.',
                'description_ar' => 'إكس آر بي هو أصل رقمي مبني للمدفوعات. إنه الأصل الرقمي الأصلي على دفتر XRP.',
                'short_description_en' => 'Digital asset for payments',
                'short_description_ar' => 'أصل رقمي للمدفوعات',
                'full_name' => 'Binance:XRP/USD',
                'tv_id' => 'XRP/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'SOL',
                'inv_id' => '1173409',
                'name_en' => 'Solana',
                'name_ar' => 'سولانا',
                'description_en' => 'Solana is a high-performance blockchain supporting builders around the world creating crypto apps.',
                'description_ar' => 'سولانا هي بلوكشين عالية الأداء تدعم المطورين حول العالم الذين ينشئون تطبيقات التشفير.',
                'short_description_en' => 'High-performance blockchain',
                'short_description_ar' => 'بلوكشين عالية الأداء',
                'full_name' => 'Binance:SOL/USD',
                'tv_id' => 'SOL/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'DOGE',
                'inv_id' => '1158819',
                'name_en' => 'Dogecoin',
                'name_ar' => 'دوجكوين',
                'description_en' => 'Dogecoin is a cryptocurrency created by software engineers as a joke, but has gained value as a digital currency.',
                'description_ar' => 'دوجكوين هي عملة مشفرة تم إنشاؤها بواسطة مهندسي البرمجيات كمزحة، لكنها اكتسبت قيمة كعملة رقمية.',
                'short_description_en' => 'Meme-based cryptocurrency',
                'short_description_ar' => 'عملة مشفرة قائمة على الميم',
                'full_name' => 'Binance:DOGE/USD',
                'tv_id' => 'DOGE/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'SHIB',
                'inv_id' => '1173198',
                'name_en' => 'Shiba Inu',
                'name_ar' => 'شيبا إينو',
                'description_en' => 'Shiba Inu is a decentralized meme token that evolved into a vibrant ecosystem.',
                'description_ar' => 'شيبا إينو هي رمز ميم لامركزي تطور إلى نظام بيئي نابض بالحياة.',
                'short_description_en' => 'Decentralized meme token',
                'short_description_ar' => 'رمز ميم لامركزي',
                'full_name' => 'Binance:SHIB/USD',
                'tv_id' => 'SHIB/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'ADA',
                'inv_id' => '1073899',
                'name_en' => 'Cardano',
                'name_ar' => 'كاردانو',
                'description_en' => 'Cardano is a proof-of-stake blockchain platform with a focus on security and sustainability.',
                'description_ar' => 'كاردانو هي منصة بلوكشين تعتمد على إثبات الحصة مع التركيز على الأمان والاستدامة.',
                'short_description_en' => 'Proof-of-stake blockchain platform',
                'short_description_ar' => 'منصة بلوكشين تعتمد على إثبات الحصة',
                'full_name' => 'Binance:ADA/USD',
                'tv_id' => 'ADA/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'USDT',
                'inv_id' => '1031397',
                'name_en' => 'Tether',
                'name_ar' => 'تيثر',
                'description_en' => 'Tether is a stablecoin pegged to the US dollar, providing stability in the volatile crypto market.',
                'description_ar' => 'تيثر هي عملة مستقرة مرتبطة بالدولار الأمريكي، توفر الاستقرار في سوق العملات المشفرة المتقلب.',
                'short_description_en' => 'USD-pegged stablecoin',
                'short_description_ar' => 'عملة مستقرة مرتبطة بالدولار الأمريكي',
                'full_name' => 'Kraken:USDT/USD',
                'tv_id' => 'USDT/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'PEPE',
                'inv_id' => '1202535',
                'name_en' => 'Pepe',
                'name_ar' => 'بيبي',
                'description_en' => 'Pepe is a meme cryptocurrency based on the popular Pepe the Frog internet meme.',
                'description_ar' => 'بيبي هي عملة مشفرة ميم تستند إلى ميم الإنترنت الشهير بيبي الضفدع.',
                'short_description_en' => 'Frog meme cryptocurrency',
                'short_description_ar' => 'عملة مشفرة ميم الضفدع',
                'full_name' => 'Binance:PEPE/USD',
                'tv_id' => 'PEPE/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'XMR',
                'inv_id' => '1024870',
                'name_en' => 'Monero',
                'name_ar' => 'مونيرو',
                'description_en' => 'Monero is a privacy-focused cryptocurrency that aims to be untraceable and private by default.',
                'description_ar' => 'مونيرو هي عملة مشفرة تركز على الخصوصية وتهدف إلى أن تكون غير قابلة للتتبع وخاصة بشكل افتراضي.',
                'short_description_en' => 'Privacy-focused cryptocurrency',
                'short_description_ar' => 'عملة مشفرة تركز على الخصوصية',
                'full_name' => 'Kraken:XMR/USD',
                'tv_id' => 'XMR/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'ETC',
                'inv_id' => '1129153',
                'name_en' => 'Ethereum Classic',
                'name_ar' => 'إيثيريوم كلاسيك',
                'description_en' => 'Ethereum Classic is a decentralized computing platform that executes smart contracts.',
                'description_ar' => 'إيثيريوم كلاسيك هي منصة حوسبة لامركزية تنفذ العقود الذكية.',
                'short_description_en' => 'Decentralized computing platform',
                'short_description_ar' => 'منصة حوسبة لامركزية',
                'full_name' => 'Binance:ETC/USD',
                'tv_id' => 'ETC/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'ELON',
                'inv_id' => '1178906',
                'name_en' => 'Dogelon Mars',
                'name_ar' => 'دوجيلون مارس',
                'description_en' => 'Dogelon Mars is a dog-themed meme coin named after Elon Musk and the planet Mars.',
                'description_ar' => 'دوجيلون مارس هي عملة ميم بثيمة الكلب سميت على اسم إيلون ماسك وكوكب المريخ.',
                'short_description_en' => 'Dog-themed meme coin',
                'short_description_ar' => 'عملة ميم بثيمة الكلب',
                'full_name' => 'Gate.io:ELON/USD',
                'tv_id' => 'ELON/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'BONK',
                'inv_id' => '1203480',
                'name_en' => 'Bonk',
                'name_ar' => 'بونك',
                'description_en' => 'Bonk is a Solana-based meme coin created by the Solana community.',
                'description_ar' => 'بونك هي عملة ميم تعتمد على سولانا تم إنشاؤها بواسطة مجتمع سولانا.',
                'short_description_en' => 'Solana-based meme coin',
                'short_description_ar' => 'عملة ميم تعتمد على سولانا',
                'full_name' => 'BitMart:BONK/USD',
                'tv_id' => 'BONK/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'OMG',
                'inv_id' => '1058183',
                'name_en' => 'OMG Network',
                'name_ar' => 'شبكة أو إم جي',
                'description_en' => 'OMG Network is a value transfer network for Ethereum and any ERC-20 token.',
                'description_ar' => 'شبكة أو إم جي هي شبكة نقل قيمة لإيثيريوم وأي رمز ERC-20.',
                'short_description_en' => 'Value transfer network',
                'short_description_ar' => 'شبكة نقل قيمة',
                'full_name' => 'HTX:OMG/USD',
                'tv_id' => 'OMG/USD',
                'currency' => 'USD',
            ],
            [
                'symbol' => 'FIRO',
                'inv_id' => '1218285',
                'name_en' => 'Firo',
                'name_ar' => 'فيرو',
                'description_en' => 'Firo is a privacy-focused cryptocurrency that implements the Lelantus protocol.',
                'description_ar' => 'فيرو هي عملة مشفرة تركز على الخصوصية وتنفذ بروتوكول ليلانتوس.',
                'short_description_en' => 'Privacy-focused cryptocurrency',
                'short_description_ar' => 'عملة مشفرة تركز على الخصوصية',
                'full_name' => 'Binance:FIRO/USD',
                'tv_id' => 'FIRO/USD',
                'currency' => 'USD',
            ],
        ];

        // Create crypto assets from local array
        foreach ($cryptoAssets as $data) {
            Asset::firstOrCreate(
                ['symbol' => $data['symbol']],
                [
                    'tv_id' => $data['tv_id'],
                    'isin' => 'CRYPTO:'.$data['symbol'],
                    'logo_id' => Str::uuid(),
                    'type' => 'crypto',
                    'currency' => $data['currency'],
                    'inv_symbol' => $data['symbol'],
                    'inv_id' => $data['inv_id'],
                    'name_en' => $data['name_en'],
                    'name_ar' => $data['name_ar'],
                    'description_en' => $data['description_en'],
                    'description_ar' => $data['description_ar'],
                    'short_description_en' => $data['short_description_en'],
                    'short_description_ar' => $data['short_description_ar'],
                    'full_name' => $data['full_name'],
                    'mb_url' => 'https://www.'.strtolower($data['symbol']).'.org',
                    'status' => 1,
                    'country_id' => $country->id,
                    'market_id' => $market->id,
                    'sector_id' => $sector->id,
                ]
            );
        }

    }
}
