<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class RedirectService
{
    /**
     * Get redirect response based on configuration
     *
     * @param string $controller Controller name (e.g., 'lift_logs', 'body_logs')
     * @param string $action Action name (e.g., 'store', 'update', 'destroy')
     * @param Request $request The request object
     * @param array $context Additional context data for building route parameters
     * @param string|null $message Success message to flash
     * @param string $messageType Type of flash message (success, error, warning, info)
     * @return RedirectResponse
     */
    public function getRedirect(
        string $controller,
        string $action,
        Request $request,
        array $context = [],
        ?string $message = null,
        string $messageType = 'success'
    ): RedirectResponse {
        $config = config("redirects.{$controller}.{$action}");

        if (!$config) {
            throw new \InvalidArgumentException("No redirect configuration found for {$controller}.{$action}");
        }

        // Handle dynamic redirect case (e.g., food_logs.destroy)
        if (isset($config['dynamic']) && $config['dynamic'] === true) {
            $redirectTo = $request->input('redirect_to');
            if ($redirectTo) {
                $params = $this->buildParams($config['default']['params'] ?? [], $request, $context);
                return $this->buildRedirect($redirectTo, $params, $message, $messageType);
            }
        }

        // Get redirect target from request or use default
        $redirectTo = $request->input('redirect_to', 'default');
        $redirectConfig = $config[$redirectTo] ?? $config['default'];

        if (!$redirectConfig) {
            throw new \InvalidArgumentException("No redirect configuration found for {$controller}.{$action}.{$redirectTo}");
        }

        // Build route parameters from config
        $params = $this->buildParams($redirectConfig['params'], $request, $context);

        return $this->buildRedirect($redirectConfig['route'], $params, $message, $messageType);
    }

    /**
     * Build route parameters from configuration
     *
     * @param array $paramNames Parameter names to extract
     * @param Request $request The request object
     * @param array $context Additional context data
     * @return array
     */
    protected function buildParams(array $paramNames, Request $request, array $context): array
    {
        $params = [];

        foreach ($paramNames as $paramName) {
            // Special handling for exercise_id parameter
            // The route expects 'exercise' but config uses 'exercise_id'
            if ($paramName === 'exercise_id') {
                if (isset($context['exercise'])) {
                    $params['exercise'] = $context['exercise'];
                    continue;
                }
                if ($request->has('exercise_id')) {
                    $params['exercise'] = $request->input('exercise_id');
                    continue;
                }
                if (isset($context['exercise_id'])) {
                    $params['exercise'] = $context['exercise_id'];
                    continue;
                }
            }

            // Special handling for template_id parameter
            if ($paramName === 'template_id') {
                if (isset($context['template_id'])) {
                    $params['id'] = $context['template_id'];
                    continue;
                }
                if ($request->has('template_id')) {
                    $params['id'] = $request->input('template_id');
                    continue;
                }
            }

            // First check context (for computed values like submitted_lift_log_id)
            if (isset($context[$paramName])) {
                $params[$paramName] = $context[$paramName];
                continue;
            }

            // Then check request input
            if ($request->has($paramName)) {
                $params[$paramName] = $request->input($paramName);
                continue;
            }
        }

        return $params;
    }

    /**
     * Build redirect response
     *
     * @param string $route Route name
     * @param array $params Route parameters
     * @param string|null $message Flash message
     * @param string $messageType Message type
     * @return RedirectResponse
     */
    protected function buildRedirect(
        string $route,
        array $params,
        ?string $message,
        string $messageType
    ): RedirectResponse {
        $redirect = redirect()->route($route, $params);

        if ($message) {
            $redirect->with($messageType, $message);
        }

        return $redirect;
    }

    /**
     * Check if a redirect target is valid for a given controller action
     *
     * @param string $controller Controller name
     * @param string $action Action name
     * @param string $redirectTo Redirect target
     * @return bool
     */
    public function isValidRedirectTarget(string $controller, string $action, string $redirectTo): bool
    {
        $config = config("redirects.{$controller}.{$action}");

        if (!$config) {
            return false;
        }

        // Dynamic redirects accept any target
        if (isset($config['dynamic']) && $config['dynamic'] === true) {
            return true;
        }

        return isset($config[$redirectTo]) || $redirectTo === 'default';
    }
}
