<?php

require_once __DIR__ . '/../bootstrap/init.php';

$routes = require __DIR__ . '/../config/routes.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Normalize path relative to public directory / base path
$basePath = '/employee-assessment-system/public';
if (str_starts_with($rawUri, $basePath)) {
    $rawUri = substr($rawUri, strlen($basePath));
}
$uri = rtrim($rawUri, '/');
if (empty($uri)) {
    $uri = '/employee/login';
}

if ($uri === '/' || $uri === '/employee') {
    header('Location: /employee/login', true, 303);
    exit(0);
}

// 1. Direct Static Route Matching
if (isset($routes[$method][$uri])) {
    [$controllerClass, $action] = $routes[$method][$uri];
    $controller = new $controllerClass();
    $controller->$action();
    exit(0);
}

// 2. Dynamic Regex Route Matching
if ($method === 'GET' && isset($routes['DYNAMIC_GET'])) {
    foreach ($routes['DYNAMIC_GET'] as $pattern => $routeInfo) {
        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches); // Remove full match
            [$controllerClass, $action, $paramNames] = $routeInfo;
            $params = [];
            foreach ($paramNames as $idx => $name) {
                $params[$name] = $matches[$idx] ?? null;
            }
            $controller = new $controllerClass();
            $controller->$action($params);
            exit(0);
        }
    }
}

// 404 Route Not Found
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>404 Not Found</title></head><body><h1>404 Page Not Found</h1><p>The requested page does not exist.</p></body></html>';
