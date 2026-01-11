<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertTemplate;
use App\Models\User;

class AlertTemplateService
{
    /**
     * Create an alert from a template
     */
    public function createFromTemplate(AlertTemplate $template, User $user, array $overrides = []): Alert
    {
        $data = [
            'user_id' => $user->id,
            'template_id' => $template->id,
            'type' => $template->type,
            'trigger_type' => $template->trigger_type,
            'parameters' => array_merge($template->default_parameters ?? [], $overrides['parameters'] ?? []),
            'delivery_config' => $overrides['delivery_config'] ?? $template->default_delivery_config,
            'scope' => $overrides['scope'] ?? 'single_asset',
            'asset_id' => $overrides['asset_id'] ?? null,
            'condition_logic' => 'single',
            'status' => 'active',
            'priority' => $overrides['priority'] ?? 'medium',
            'is_recurring' => $overrides['is_recurring'] ?? false,
            'cooldown_minutes' => $overrides['cooldown_minutes'] ?? 60,
            'market_hours_only' => $overrides['market_hours_only'] ?? true,
        ];

        $alert = Alert::create($data);

        // Increment usage count
        $template->increment('usage_count');

        return $alert;
    }

    /**
     * Create a template from an existing alert
     */
    public function createFromAlert(Alert $alert, string $name, bool $isPublic = false): AlertTemplate
    {
        return AlertTemplate::create([
            'user_id' => $alert->user_id,
            'name' => $name,
            'name_ar' => $name, // User can update later
            'description' => "Template created from alert for {$alert->asset?->symbol}",
            'description_ar' => "قالب تم إنشاؤه من تنبيه {$alert->asset?->symbol}",
            'type' => $alert->type,
            'trigger_type' => $alert->trigger_type,
            'default_parameters' => $this->sanitizeParameters($alert->parameters ?? []),
            'default_delivery_config' => $alert->delivery_config,
            'is_public' => $isPublic,
        ]);
    }

    /**
     * Get recommended templates for a user
     */
    public function getRecommendedTemplates(User $user): array
    {
        // System templates
        $systemTemplates = AlertTemplate::whereNull('user_id')
            ->orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();

        // User's most used templates
        $userTemplates = AlertTemplate::where('user_id', $user->id)
            ->orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();

        // Popular public templates
        $publicTemplates = AlertTemplate::where('is_public', true)
            ->where('user_id', '!=', $user->id)
            ->orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();

        return [
            'system' => $systemTemplates,
            'user' => $userTemplates,
            'popular' => $publicTemplates,
        ];
    }

    /**
     * Remove asset-specific values from parameters
     */
    private function sanitizeParameters(array $parameters): array
    {
        // Remove values that are asset-specific
        unset($parameters['target_price']);
        unset($parameters['entry_price']);
        unset($parameters['zone_low']);
        unset($parameters['zone_high']);
        unset($parameters['level']);

        return $parameters;
    }
}
