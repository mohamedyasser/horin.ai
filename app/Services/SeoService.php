<?php

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;

class SeoService
{
    protected string $title;

    protected string $description;

    protected ?string $image = null;

    protected string $type = 'website';

    protected array $breadcrumbs = [];

    public function __construct()
    {
        $this->title = config('app.name', 'Horin');
        $this->description = $this->getDefaultDescription();
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setBreadcrumbs(array $breadcrumbs): self
    {
        $this->breadcrumbs = $breadcrumbs;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getImage(): string
    {
        return $this->image ?? $this->getDefaultImage();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCanonicalUrl(): string
    {
        return URL::current();
    }

    public function getAlternateUrls(): array
    {
        $currentUrl = Request::url();
        $locale = App::getLocale();
        $alternateLocale = $locale === 'ar' ? 'en' : 'ar';

        // Replace locale in URL
        $alternateUrl = preg_replace(
            '#^('.preg_quote(config('app.url'), '#').')/'.$locale.'(.*)$#',
            '$1/'.$alternateLocale.'$2',
            $currentUrl
        );

        return [
            'ar' => $locale === 'ar' ? $currentUrl : $alternateUrl,
            'en' => $locale === 'en' ? $currentUrl : $alternateUrl,
            'x-default' => preg_replace(
                '#^('.preg_quote(config('app.url'), '#').')/'.$locale.'(.*)$#',
                '$1/ar$2',
                $currentUrl
            ),
        ];
    }

    public function getOpenGraphTags(): array
    {
        return [
            'og:title' => $this->title,
            'og:description' => $this->description,
            'og:image' => $this->getImage(),
            'og:url' => $this->getCanonicalUrl(),
            'og:type' => $this->type,
            'og:site_name' => config('app.name', 'Horin'),
            'og:locale' => App::getLocale() === 'ar' ? 'ar_SA' : 'en_US',
            'og:locale:alternate' => App::getLocale() === 'ar' ? 'en_US' : 'ar_SA',
        ];
    }

    public function getTwitterTags(): array
    {
        return [
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $this->title,
            'twitter:description' => $this->description,
            'twitter:image' => $this->getImage(),
        ];
    }

    public function getOrganizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name', 'Horin'),
            'url' => config('app.url'),
            'logo' => $this->getDefaultImage(),
            'description' => $this->getDefaultDescription(),
            'sameAs' => [],
        ];
    }

    public function getWebsiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name', 'Horin'),
            'url' => config('app.url'),
            'description' => $this->getDefaultDescription(),
            'inLanguage' => [App::getLocale() === 'ar' ? 'ar-SA' : 'en-US'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => config('app.url').'/'.App::getLocale().'/search?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function getBreadcrumbSchema(): array
    {
        if (empty($this->breadcrumbs)) {
            return [];
        }

        $items = [];
        foreach ($this->breadcrumbs as $index => $breadcrumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $breadcrumb['name'],
                'item' => $breadcrumb['url'] ?? null,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    public function getAllSchemas(): array
    {
        $schemas = [
            $this->getOrganizationSchema(),
            $this->getWebsiteSchema(),
        ];

        $breadcrumbSchema = $this->getBreadcrumbSchema();
        if (! empty($breadcrumbSchema)) {
            $schemas[] = $breadcrumbSchema;
        }

        return $schemas;
    }

    protected function getDefaultDescription(): string
    {
        if (App::getLocale() === 'ar') {
            return 'توقعات أسعار الأسهم بالذكاء الاصطناعي لأسواق الشرق الأوسط بما في ذلك مصر والسعودية والإمارات والكويت وقطر والبحرين.';
        }

        return 'AI-powered stock price predictions for Middle East markets including Egypt, Saudi Arabia, UAE, Kuwait, Qatar, and Bahrain.';
    }

    protected function getDefaultImage(): string
    {
        return config('app.url').'/og-image.png';
    }
}
