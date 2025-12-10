<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Market;
use App\Models\Sector;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $content = Cache::remember('sitemap-index', 3600, function () {
            $sitemaps = [
                url('/sitemap-static.xml'),
                url('/sitemap-markets.xml'),
                url('/sitemap-sectors.xml'),
                url('/sitemap-assets.xml'),
            ];

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($sitemaps as $sitemap) {
                $xml .= '<sitemap>';
                $xml .= '<loc>'.$sitemap.'</loc>';
                $xml .= '<lastmod>'.now()->toW3cString().'</lastmod>';
                $xml .= '</sitemap>';
            }

            $xml .= '</sitemapindex>';

            return $xml;
        });

        return response($content)->header('Content-Type', 'application/xml');
    }

    public function static(): Response
    {
        $content = Cache::remember('sitemap-static', 3600, function () {
            $pages = [
                ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
                ['url' => '/predictions', 'priority' => '0.9', 'changefreq' => 'hourly'],
                ['url' => '/markets', 'priority' => '0.8', 'changefreq' => 'daily'],
                ['url' => '/sectors', 'priority' => '0.8', 'changefreq' => 'daily'],
                ['url' => '/search', 'priority' => '0.7', 'changefreq' => 'weekly'],
                ['url' => '/about', 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['url' => '/faq', 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['url' => '/methodology', 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['url' => '/privacy', 'priority' => '0.3', 'changefreq' => 'yearly'],
                ['url' => '/terms', 'priority' => '0.3', 'changefreq' => 'yearly'],
                ['url' => '/contact', 'priority' => '0.4', 'changefreq' => 'monthly'],
            ];

            return $this->generateUrlSet($pages);
        });

        return response($content)->header('Content-Type', 'application/xml');
    }

    public function markets(): Response
    {
        $content = Cache::remember('sitemap-markets', 3600, function () {
            $markets = Market::select('code', 'updated_at')->get();

            $pages = $markets->map(fn ($market) => [
                'url' => '/markets/'.$market->code,
                'priority' => '0.8',
                'changefreq' => 'daily',
                'lastmod' => $market->updated_at?->toW3cString(),
            ])->toArray();

            return $this->generateUrlSet($pages);
        });

        return response($content)->header('Content-Type', 'application/xml');
    }

    public function sectors(): Response
    {
        $content = Cache::remember('sitemap-sectors', 3600, function () {
            $sectors = Sector::select('id', 'updated_at')->get();

            $pages = $sectors->map(fn ($sector) => [
                'url' => '/sectors/'.$sector->id,
                'priority' => '0.7',
                'changefreq' => 'daily',
                'lastmod' => $sector->updated_at?->toW3cString(),
            ])->toArray();

            return $this->generateUrlSet($pages);
        });

        return response($content)->header('Content-Type', 'application/xml');
    }

    public function assets(): Response
    {
        $content = Cache::remember('sitemap-assets', 3600, function () {
            $assets = Asset::select('symbol', 'updated_at')
                ->where('is_active', true)
                ->get();

            $pages = $assets->map(fn ($asset) => [
                'url' => '/assets/'.$asset->symbol,
                'priority' => '0.6',
                'changefreq' => 'daily',
                'lastmod' => $asset->updated_at?->toW3cString(),
            ])->toArray();

            return $this->generateUrlSet($pages);
        });

        return response($content)->header('Content-Type', 'application/xml');
    }

    protected function generateUrlSet(array $pages): string
    {
        $baseUrl = config('app.url');
        $locales = ['ar', 'en'];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        foreach ($pages as $page) {
            foreach ($locales as $locale) {
                $fullUrl = $baseUrl.'/'.$locale.$page['url'];

                $xml .= '<url>';
                $xml .= '<loc>'.htmlspecialchars($fullUrl).'</loc>';

                // Add hreflang alternates
                foreach ($locales as $altLocale) {
                    $altUrl = $baseUrl.'/'.$altLocale.$page['url'];
                    $xml .= '<xhtml:link rel="alternate" hreflang="'.$altLocale.'" href="'.htmlspecialchars($altUrl).'"/>';
                }
                // Add x-default (pointing to Arabic as default)
                $defaultUrl = $baseUrl.'/ar'.$page['url'];
                $xml .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.htmlspecialchars($defaultUrl).'"/>';

                if (isset($page['lastmod'])) {
                    $xml .= '<lastmod>'.$page['lastmod'].'</lastmod>';
                }

                $xml .= '<changefreq>'.$page['changefreq'].'</changefreq>';
                $xml .= '<priority>'.$page['priority'].'</priority>';
                $xml .= '</url>';
            }
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
