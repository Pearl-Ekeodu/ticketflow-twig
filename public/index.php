<?php

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/../src');
define('CONFIG_PATH', BASE_PATH . '/../config');
define('TEMPLATES_PATH', BASE_PATH . '/../templates');

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Simple router
class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function middleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove trailing slash
        $path = rtrim($path, '/');
        if (empty($path)) {
            $path = '/';
        }
        
        // Run middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware();
            if ($result === false) {
                return;
            }
        }
        
        // Find matching route
        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            $handler();
        } else {
            // Try to match with parameters
            foreach ($this->routes[$method] ?? [] as $route => $handler) {
                if ($this->matchRoute($route, $path)) {
                    $handler();
                    return;
                }
            }
            
            // 404 Not Found
            http_response_code(404);
            echo "Page not found";
        }
    }

    private function matchRoute(string $route, string $path): bool
    {
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route);
        $routePattern = '#^' . $routePattern . '$#';
        
        return preg_match($routePattern, $path);
    }
}

// Simple template engine (basic Twig-like functionality)
class TemplateEngine
{
    private string $templateDir;
    private array $globals = [];

    public function __construct(string $templateDir)
    {
        $this->templateDir = $templateDir;
    }

    public function render(string $template, array $data = []): string
    {
        $templateFile = $this->templateDir . '/' . $template . '.twig';
        
        if (!file_exists($templateFile)) {
            throw new \Exception("Template not found: {$template}");
        }
        
        $content = file_get_contents($templateFile);
        
        // Merge with globals
        $data = array_merge($this->globals, $data);
        
        // Simple template processing
        foreach ($data as $key => $value) {
            $content = str_replace('{{ ' . $key . ' }}', htmlspecialchars($value), $content);
            $content = str_replace('{{ ' . $key . '|raw }}', $value, $content);
        }
        
        return $content;
    }

    public function addGlobal(string $key, $value): void
    {
        $this->globals[$key] = $value;
    }
}

// Initialize router
$router = new Router();
$templateEngine = new TemplateEngine(TEMPLATES_PATH);

// Add global variables
$templateEngine->addGlobal('app_name', 'TicketFlow');
$templateEngine->addGlobal('base_url', 'http://localhost:8000');

// Middleware for authentication check
$router->middleware(function() use ($templateEngine) {
    $authService = new \App\Services\AuthService();
    
    // Define protected routes
    $protectedRoutes = ['/dashboard', '/tickets'];
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    foreach ($protectedRoutes as $route) {
        if (strpos($currentPath, $route) === 0) {
            if (!$authService->isAuthenticated()) {
                header('Location: /');
                exit;
            }
            break;
        }
    }
    
    return true;
});

// Routes
$router->get('/', function() use ($templateEngine) {
    $authService = new \App\Services\AuthService();
    $user = $authService->getCurrentUser();
    
    echo $templateEngine->render('pages/landing', [
        'user' => $user,
        'isAuthenticated' => $authService->isAuthenticated()
    ]);
});

$router->get('/dashboard', function() use ($templateEngine) {
    $authService = new \App\Services\AuthService();
    $ticketModel = new \App\Models\Ticket();
    
    $user = $authService->getCurrentUser();
    $stats = $ticketModel->getStats($user['id']);
    $recentTickets = $ticketModel->getRecent($user['id'], 5);
    
    echo $templateEngine->render('pages/dashboard', [
        'user' => $user,
        'stats' => $stats,
        'recentTickets' => $recentTickets
    ]);
});

$router->get('/tickets', function() use ($templateEngine) {
    $authService = new \App\Services\AuthService();
    $ticketModel = new \App\Models\Ticket();
    
    $user = $authService->getCurrentUser();
    
    // Get filters from query parameters
    $filters = [
        'status' => $_GET['status'] ?? 'all',
        'search' => $_GET['search'] ?? ''
    ];
    
    $tickets = $ticketModel->findByUserId($user['id'], $filters);
    $stats = $ticketModel->getStats($user['id']);
    
    echo $templateEngine->render('pages/tickets', [
        'user' => $user,
        'tickets' => $tickets,
        'stats' => $stats,
        'filters' => $filters
    ]);
});

$router->post('/auth/login', function() {
    $authService = new \App\Services\AuthService();
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = $authService->login($email, $password);
    
    if ($result['success']) {
        header('Location: /dashboard');
    } else {
        header('Location: /?error=' . urlencode($result['message']));
    }
    exit;
});

$router->post('/auth/register', function() {
    $authService = new \App\Services\AuthService();
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = $authService->register($name, $email, $password);
    
    if ($result['success']) {
        header('Location: /dashboard');
    } else {
        header('Location: /?error=' . urlencode($result['message']));
    }
    exit;
});

$router->post('/auth/logout', function() {
    $authService = new \App\Services\AuthService();
    $authService->logout();
    header('Location: /');
    exit;
});

// Run the router
$router->run();
