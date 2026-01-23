<?php

namespace App\Services;

use LightnCandy\LightnCandy;

/**
 * Template rendering service using Handlebars syntax.
 *
 * Uses LightnCandy library to compile and render Handlebars templates
 * with variable interpolation.
 */
class TemplateRenderer
{
    /**
     * Render a template with given variables.
     *
     * @param  string  $template  Template string with {{variable}} syntax
     * @param  array  $variables  Key-value pairs of variables
     * @return string Rendered template
     */
    public function render(string $template, array $variables): string
    {
        try {
            // Compile the template
            $phpStr = LightnCandy::compile($template, [
                'flags' => LightnCandy::FLAG_HANDLEBARS
                    | LightnCandy::FLAG_ERROR_EXCEPTION
                    | LightnCandy::FLAG_RUNTIMEPARTIAL,
                'helpers' => $this->getHelpers(),
            ]);

            // Create renderer function
            $renderer = LightnCandy::prepare($phpStr);

            // Render with variables
            return $renderer($this->flattenVariables($variables));
        } catch (\Exception $e) {
            \Log::error('Template rendering failed', [
                'template' => $template,
                'error' => $e->getMessage(),
            ]);

            // Return template as-is if rendering fails
            return $template;
        }
    }

    /**
     * Validate template syntax.
     *
     * @param  string  $template  Template to validate
     * @return array ['valid' => bool, 'error' => ?string]
     */
    public function validate(string $template): array
    {
        try {
            LightnCandy::compile($template, [
                'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION,
            ]);

            return ['valid' => true, 'error' => null];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Flatten nested variables for template rendering.
     *
     * Converts ['user' => ['name' => 'John']] to ['user.name' => 'John']
     * and also keeps the nested structure.
     *
     * @param  array  $variables  Nested variables
     * @param  string  $prefix  Current prefix for recursion
     * @return array Flattened variables
     */
    protected function flattenVariables(array $variables, string $prefix = ''): array
    {
        $result = [];

        foreach ($variables as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && ! empty($value)) {
                // Keep nested structure for Handlebars helpers
                $result[$key] = $value;

                // Also add flattened keys
                $result = array_merge($result, $this->flattenVariables($value, $fullKey));
            } else {
                $result[$key] = $value;
                if ($prefix) {
                    $result[$fullKey] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get Handlebars helpers.
     *
     * @return array Helper functions
     */
    protected function getHelpers(): array
    {
        return [
            // Format currency: {{currency amount}}
            'currency' => function ($value) {
                return '$'.number_format((float) $value, 2, '.', ',');
            },

            // Format date: {{date timestamp "d/m/Y"}}
            'date' => function ($value, $format = 'd/m/Y') {
                if (! $value) {
                    return '';
                }
                try {
                    $date = new \DateTime($value);

                    return $date->format($format);
                } catch (\Exception) {
                    return $value;
                }
            },

            // Uppercase: {{upper text}}
            'upper' => function ($value) {
                return mb_strtoupper($value, 'UTF-8');
            },

            // Lowercase: {{lower text}}
            'lower' => function ($value) {
                return mb_strtolower($value, 'UTF-8');
            },

            // Conditional: {{#if variable}}...{{/if}}
            'if' => function ($value, $options) {
                return $value ? $options['fn']() : $options['inverse']();
            },
        ];
    }

    /**
     * Extract variables used in a template.
     *
     * @param  string  $template  Template to analyze
     * @return array List of variable names (e.g., ['user.name', 'amount'])
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $variables = [];
        foreach ($matches[1] as $match) {
            // Remove helpers and modifiers
            $match = trim($match);
            $match = preg_replace('/^(#|\/|\^)/', '', $match); // Remove block helpers
            $match = explode(' ', $match)[0]; // Get first word (variable name)

            // Skip helper names
            if (in_array($match, ['if', 'unless', 'each', 'with', 'currency', 'date', 'upper', 'lower'])) {
                continue;
            }

            $variables[] = $match;
        }

        return array_unique($variables);
    }
}
