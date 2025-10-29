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
        
        // Handle {% extends %} directive
        if (preg_match('/{%\s*extends\s+[\'"](.+?)[\'"]\s*%}/', $content, $matches)) {
            $parentTemplate = trim($matches[1]);
            $parentFile = $this->templateDir . '/' . $parentTemplate;
            
            if (file_exists($parentFile)) {
                $parentContent = file_get_contents($parentFile);
                
                // Extract blocks from child template
                preg_match_all('/{%\s*block\s+(\w+)\s*%}(.*?){%\s*endblock\s*%}/s', $content, $blockMatches);
                $blocks = [];
                foreach ($blockMatches[1] as $i => $blockName) {
                    $blocks[$blockName] = $blockMatches[2][$i];
                }
                
                // Replace blocks in parent template
                foreach ($blocks as $blockName => $blockContent) {
                    $parentContent = preg_replace(
                        '/{%\s*block\s+' . preg_quote($blockName) . '\s*%}.*?{%\s*endblock\s*%}/s',
                        $blockContent,
                        $parentContent
                    );
                }
                
                $content = $parentContent;
            }
        }
        
        // Merge with globals first so they're available for conditionals
        $mergedData = array_merge($this->globals, $data);
        
        // Handle {% if %} conditions with {% else %} and {% elseif %} support
        $content = preg_replace_callback('/{%\s*if\s+(.*?)\s*%}(.*?){%\s*endif\s*%}/s', function($matches) use ($mergedData) {
            $condition = trim($matches[1]);
            $blockContent = $matches[2];
            
            // Parse condition (e.g., "ticket.status == 'open'" or "ticket.priority")
            $conditionParts = explode('==', $condition);
            if (count($conditionParts) === 2) {
                $leftSide = trim($conditionParts[0]);
                $rightSide = trim($conditionParts[1], " \t\n\r\0\x0B'\"");
                
                // Handle nested access (e.g., "ticket.status")
                $keys = explode('.', $leftSide);
                $value = $mergedData;
                foreach ($keys as $key) {
                    if (is_array($value) && isset($value[$key])) {
                        $value = $value[$key];
                    } else {
                        $value = null;
                        break;
                    }
                }
                
                $isTruthy = (string)$value === $rightSide;
            } else {
                // Simple variable check
                $varName = trim($condition);
                $value = $mergedData[$varName] ?? null;
                
                // Check if value is truthy
                $isTruthy = false;
                if (is_string($value)) {
                    $isTruthy = $value !== '' && $value !== '0' && strtolower($value) !== 'false';
                } elseif (is_bool($value)) {
                    $isTruthy = $value === true;
                } elseif (is_array($value)) {
                    $isTruthy = count($value) > 0;
                } else {
                    $isTruthy = !empty($value);
                }
            }
            
            // Handle {% elseif %} clauses
            $elseifPattern = '/(.*?){%\s*elseif\s+(.*?)\s*%}(.*?)$/s';
            if (preg_match_all($elseifPattern, $blockContent, $elseifMatches, PREG_SET_ORDER)) {
                if ($isTruthy) {
                    return $elseifMatches[0][1]; // Return the if block
                }
                
                // Check elseif conditions
                foreach ($elseifMatches as $elseifMatch) {
                    $elseifCondition = trim($elseifMatch[2]);
                    $elseifConditionParts = explode('==', $elseifCondition);
                    
                    if (count($elseifConditionParts) === 2) {
                        $leftSide = trim($elseifConditionParts[0]);
                        $rightSide = trim($elseifConditionParts[1], " \t\n\r\0\x0B'\"");
                        
                        $keys = explode('.', $leftSide);
                        $value = $mergedData;
                        foreach ($keys as $key) {
                            if (is_array($value) && isset($value[$key])) {
                                $value = $value[$key];
                            } else {
                                $value = null;
                                break;
                            }
                        }
                        
                        if ((string)$value === $rightSide) {
                            return $elseifMatch[3]; // Return the elseif block
                        }
                    }
                }
                
                // Check for {% else %} after elseif
                $elsePattern = '/(.*?){%\s*else\s*%}(.*?)$/s';
                if (preg_match($elsePattern, $blockContent, $elseMatches)) {
                    return $elseMatches[2]; // Return the else block
                }
                
                return ''; // No match
            }
            
            // Handle simple {% else %} clause
            if (preg_match('/(.*?){%\s*else\s*%}(.*?)$/s', $blockContent, $elseMatches)) {
                return $isTruthy ? $elseMatches[1] : $elseMatches[2];
            }
            
            return $isTruthy ? $blockContent : '';
        }, $content);
        
        // Handle {% for %} loops BEFORE variable substitution
        $content = preg_replace_callback('/{%\s*for\s+(\w+)\s+in\s+(\w+)\s*%}(.*?){%\s*endfor\s*%}/s', function($matches) use ($mergedData) {
            $loopVar = trim($matches[1]); // e.g., "ticket"
            $arrayVar = trim($matches[2]); // e.g., "tickets"
            $loopContent = $matches[3];
            
            $items = $mergedData[$arrayVar] ?? [];
            $result = '';
            
            foreach ($items as $item) {
                $itemContent = $loopContent;
                
                // Replace variables like {{ ticket.title }}
                if (is_array($item)) {
                    foreach ($item as $key => $value) {
                        if (!is_array($value)) {
                            $itemContent = str_replace("{{ {$loopVar}.{$key} }}", htmlspecialchars((string)$value), $itemContent);
                            $itemContent = str_replace("{{ {$loopVar}.{$key}|raw }}", (string)$value, $itemContent);
                        }
                    }
                }
                
                // Process any filters within the loop
                $itemContent = preg_replace_callback('/{{\s*([^|}]+)\s*\|([^}]+)}}/', function($filterMatches) use ($loopVar, $item) {
                    $varName = trim($filterMatches[1]);
                    $filters = trim($filterMatches[2]);
                    
                    // Extract the property name after the loop variable
                    if (strpos($varName, $loopVar . '.') === 0) {
                        $propName = substr($varName, strlen($loopVar) + 1);
                        $value = $item[$propName] ?? '';
                    } else {
                        $value = '';
                    }
                    
                    // Apply filters
                    if (strpos($filters, 'lower') !== false) {
                        $value = strtolower((string)$value);
                    }
                    if (strpos($filters, 'upper') !== false) {
                        $value = strtoupper((string)$value);
                    }
                    if (strpos($filters, 'length') !== false) {
                        $value = is_array($value) ? count($value) : strlen((string)$value);
                    }
                    if (preg_match('/slice\((\d+),?\s*(\d+)?\)/', $filters, $sliceMatches)) {
                        $start = (int)$sliceMatches[1];
                        $length = isset($sliceMatches[2]) ? (int)$sliceMatches[2] : null;
                        $value = $length ? substr((string)$value, $start, $length) : substr((string)$value, $start);
                    }
                    
                    if (strpos($filters, 'raw') !== false) {
                        return (string)$value;
                    }
                    
                    return htmlspecialchars((string)$value);
                }, $itemContent);
                
                // Process if statements within the loop
                $itemContent = $this->processIfStatements($itemContent, [$loopVar => $item] + $mergedData);
                
                $result .= $itemContent;
            }
            
            return $result;
        }, $content);
        
        // Use merged data for variable substitution
        $data = $mergedData;
        
        // Handle nested array access first (e.g., stats.total, ticket.title)
        $content = preg_replace_callback('/{{\s*([\w\.]+)\s*}}/', function($matches) use ($data) {
            $path = trim($matches[1]);
            $keys = explode('.', $path);
            
            // Single key - handled by simple replacement below
            if (count($keys) === 1) {
                return $matches[0];
            }
            
            $value = $data;
            foreach ($keys as $key) {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return $matches[0]; // Return original if not found
                }
            }
            
            // Only convert to string if not array
            if (is_array($value)) {
                return $matches[0]; // Return original if still array
            }
            
            return htmlspecialchars((string)$value);
        }, $content);
        
        // Handle nested array access with raw filter
        $content = preg_replace_callback('/{{\s*([\w\.]+)\s*\|\s*raw\s*}}/', function($matches) use ($data) {
            $path = trim($matches[1]);
            $keys = explode('.', $path);
            
            // Single key - handled by simple replacement below
            if (count($keys) === 1) {
                return $matches[0];
            }
            
            $value = $data;
            foreach ($keys as $key) {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return $matches[0]; // Return original if not found
                }
            }
            
            // Only convert to string if not array
            if (is_array($value)) {
                return $matches[0]; // Return original if still array
            }
            
            return (string)$value;
        }, $content);
        
        // Handle filters (json_encode, raw, lower, upper, slice, length)
        $content = preg_replace_callback('/{{\s*([^|{}]+)\s*\|\s*json_encode\s*\|\s*raw\s*}}/', function($matches) use ($data) {
            $varName = trim($matches[1]);
            if (isset($data[$varName])) {
                return json_encode($data[$varName]);
            }
            return '[]';
        }, $content);
        
        // Handle simple variable substitution with filters
        $content = preg_replace_callback('/{{\s*([^|}]+)\s*\|([^}]+)}}/', function($matches) use ($data) {
            $varName = trim($matches[1]);
            $filters = trim($matches[2]);
            $value = $data[$varName] ?? '';
            
            // Apply filters
            if (strpos($filters, 'raw') !== false) {
                return (string)$value;
            }
            if (strpos($filters, 'lower') !== false) {
                $value = strtolower((string)$value);
            }
            if (strpos($filters, 'upper') !== false) {
                $value = strtoupper((string)$value);
            }
            if (strpos($filters, 'length') !== false) {
                $value = is_array($value) ? count($value) : strlen((string)$value);
            }
            if (preg_match('/slice\((\d+),?\s*(\d+)?\)/', $filters, $sliceMatches)) {
                $start = (int)$sliceMatches[1];
                $length = isset($sliceMatches[2]) ? (int)$sliceMatches[2] : null;
                $value = $length ? substr((string)$value, $start, $length) : substr((string)$value, $start);
            }
            
            // Check for raw at the end
            if (strpos($filters, 'raw') !== false) {
                return (string)$value;
            }
            
            return htmlspecialchars((string)$value);
        }, $content);
        
        // Simple template processing - skip arrays to avoid warnings
        foreach ($data as $key => $value) {
            // Skip arrays - they're handled above
            if (is_array($value)) {
                continue;
            }
            
            // Handle null values to prevent deprecation warnings
            $safeValue = $value !== null ? htmlspecialchars((string)$value) : '';
            $rawValue = $value !== null ? (string)$value : '';
            
            $content = str_replace('{{ ' . $key . ' }}', $safeValue, $content);
            $content = str_replace('{{ ' . $key . '|raw }}', $rawValue, $content);
        }
        
        // Process template variables within strings (like in data attributes or inline styles)
        $content = preg_replace_callback('/data-title="([^"]*)"/', function($matches) use ($data) {
            // Handle nested variable access in data attributes
            $content = $matches[1];
            // For now, just return as-is since the template engine should have already processed it
            return $matches[0];
        }, $content);
        
        // Remove any remaining Twig syntax that wasn't processed
        $content = preg_replace('/{%\s*.*?%\}/', '', $content);
        
        return $content;
    }

    public function addGlobal(string $key, $value): void
    {
        $this->globals[$key] = $value;
    }
    
    private function processIfStatements(string $content, array $data): string
    {
        return preg_replace_callback('/{%\s*if\s+(.*?)\s*%}(.*?){%\s*endif\s*%}/s', function($matches) use ($data) {
            $condition = trim($matches[1]);
            $blockContent = $matches[2];
            
            // Parse condition
            $conditionParts = explode('==', $condition);
            if (count($conditionParts) === 2) {
                $leftSide = trim($conditionParts[0]);
                $rightSide = trim($conditionParts[1], " \t\n\r\0\x0B'\"");
                
                $keys = explode('.', $leftSide);
                $value = $data;
                foreach ($keys as $key) {
                    if (is_array($value) && isset($value[$key])) {
                        $value = $value[$key];
                    } else {
                        $value = null;
                        break;
                    }
                }
                
                $isTruthy = (string)$value === $rightSide;
            } else {
                $varName = trim($condition);
                $value = $data[$varName] ?? null;
                $isTruthy = !empty($value);
            }
            
            // Handle {% elseif %}
            $elseifPattern = '/(.*?){%\s*elseif\s+(.*?)\s*%}(.*?)$/s';
            if (preg_match_all($elseifPattern, $blockContent, $elseifMatches, PREG_SET_ORDER)) {
                if ($isTruthy) {
                    return $elseifMatches[0][1];
                }
                
                foreach ($elseifMatches as $elseifMatch) {
                    $elseifCondition = trim($elseifMatch[2]);
                    $elseifConditionParts = explode('==', $elseifCondition);
                    
                    if (count($elseifConditionParts) === 2) {
                        $leftSide = trim($elseifConditionParts[0]);
                        $rightSide = trim($elseifConditionParts[1], " \t\n\r\0\x0B'\"");
                        
                        $keys = explode('.', $leftSide);
                        $value = $data;
                        foreach ($keys as $key) {
                            if (is_array($value) && isset($value[$key])) {
                                $value = $value[$key];
                            } else {
                                $value = null;
                                break;
                            }
                        }
                        
                        if ((string)$value === $rightSide) {
                            return $elseifMatch[3];
                        }
                    }
                }
                
                $elsePattern = '/(.*?){%\s*else\s*%}(.*?)$/s';
                if (preg_match($elsePattern, $blockContent, $elseMatches)) {
                    return $elseMatches[2];
                }
                
                return '';
            }
            
            if (preg_match('/(.*?){%\s*else\s*%}(.*?)$/s', $blockContent, $elseMatches)) {
                return $isTruthy ? $elseMatches[1] : $elseMatches[2];
            }
            
            return $isTruthy ? $blockContent : '';
        }, $content);
    }
}

// Initialize router
$router = new Router();
$templateEngine = new TemplateEngine(TEMPLATES_PATH);

// Add global variables
$templateEngine->addGlobal('app_name', 'TicketFlow');
$templateEngine->addGlobal('base_url', 'http://localhost:8000');
$templateEngine->addGlobal('current_year', date('Y'));

// Middleware for authentication check - add isAuthenticated to all templates
$router->middleware(function() use ($templateEngine) {
    $authService = new \App\Services\AuthService();
    
    // Add isAuthenticated as a global so it's available in all templates
    $templateEngine->addGlobal('isAuthenticated', $authService->isAuthenticated() ? 'true' : '');
    
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
        'current_page' => '/'
    ]);
});

$router->get('/dashboard', function() use ($templateEngine) {
    $authService = new \App\Services\AuthService();
    $ticketModel = new \App\Models\Ticket();
    
    $user = $authService->getCurrentUser();
    $stats = $ticketModel->getStats($user['id']);
    $recentTickets = $ticketModel->getRecent($user['id'], 5);
    
    // Process user name for display
    $userFirstName = '';
    if ($user && isset($user['name'])) {
        $nameParts = explode(' ', $user['name']);
        $userFirstName = $nameParts[0];
    }
    
    // Process recent tickets for display
    foreach ($recentTickets as &$ticket) {
        if (isset($ticket['description']) && strlen($ticket['description']) > 100) {
            $ticket['description_short'] = substr($ticket['description'], 0, 100) . '...';
        } else {
            $ticket['description_short'] = $ticket['description'] ?? '';
        }
        
        // Format date
        if (isset($ticket['created_at'])) {
            $ticket['created_at_formatted'] = date('M d, Y h:i A', strtotime($ticket['created_at']));
        } else {
            $ticket['created_at_formatted'] = '';
        }
    }
    unset($ticket);
    
    echo $templateEngine->render('pages/dashboard', [
        'user' => $user,
        'current_page' => '/dashboard',
        'userFirstName' => $userFirstName,
        'stats' => $stats,
        'recentTickets' => $recentTickets,
        'recentTicketsCount' => count($recentTickets)
    ]);
});

$router->get('/tickets', function() use ($templateEngine) {
    $authService = new \App\Services\AuthService();
    $ticketModel = new \App\Models\Ticket();
    
    $user = $authService->getCurrentUser();
    
    // Get all tickets for this user (filtering done client-side)
    $tickets = $ticketModel->findByUserId($user['id'], []);
    $stats = $ticketModel->getStats($user['id']);
    
    // Format dates for tickets
    foreach ($tickets as &$ticket) {
        if (isset($ticket['created_at'])) {
            $ticket['created_at_formatted'] = date('M d, Y h:i A', strtotime($ticket['created_at']));
        } else {
            $ticket['created_at_formatted'] = '';
        }
        if (isset($ticket['updated_at'])) {
            $ticket['updated_at_formatted'] = date('M d, Y h:i A', strtotime($ticket['updated_at']));
        } else {
            $ticket['updated_at_formatted'] = $ticket['created_at_formatted'];
        }
    }
    unset($ticket);
    
    echo $templateEngine->render('pages/tickets', [
        'user' => $user,
        'current_page' => '/tickets',
        'tickets' => $tickets,
        'stats' => $stats
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
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validate password confirmation
    if ($password !== $confirmPassword) {
        header('Location: /?error=' . urlencode('Passwords do not match'));
        exit;
    }
    
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

$router->post('/tickets', function() {
    $authService = new \App\Services\AuthService();
    if (!$authService->isAuthenticated()) {
        header('Location: /');
        exit;
    }
    
    $user = $authService->getCurrentUser();
    $ticketModel = new \App\Models\Ticket();
    
    // Validate input
    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        header('Location: /tickets?error=' . urlencode('Title is required'));
        exit;
    }
    
    $ticketData = [
        'title' => $title,
        'description' => trim($_POST['description'] ?? '') ?: null,
        'status' => $_POST['status'] ?? 'open',
        'priority' => !empty($_POST['priority']) ? $_POST['priority'] : null,
        'user_id' => $user['id']
    ];
    
    $ticketModel->create($ticketData);
    
    header('Location: /tickets?success=' . urlencode('Ticket created successfully'));
    exit;
});

$router->post('/tickets/update', function() {
    $authService = new \App\Services\AuthService();
    if (!$authService->isAuthenticated()) {
        header('Location: /');
        exit;
    }
    
    $user = $authService->getCurrentUser();
    $ticketModel = new \App\Models\Ticket();
    
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) {
        header('Location: /tickets?error=' . urlencode('Invalid ticket ID'));
        exit;
    }
    
    // Verify ticket belongs to user
    $ticket = $ticketModel->findById($ticketId);
    if (!$ticket || $ticket['user_id'] != $user['id']) {
        header('Location: /tickets?error=' . urlencode('Ticket not found'));
        exit;
    }
    
    // Validate input
    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        header('Location: /tickets?error=' . urlencode('Title is required'));
        exit;
    }
    
    $updateData = [
        'title' => $title,
        'description' => trim($_POST['description'] ?? '') ?: null,
        'status' => $_POST['status'] ?? $ticket['status'],
        'priority' => !empty($_POST['priority']) ? $_POST['priority'] : null
    ];
    
    $ticketModel->update($ticketId, $updateData);
    
    header('Location: /tickets?success=' . urlencode('Ticket updated successfully'));
    exit;
});

$router->post('/tickets/delete', function() {
    $authService = new \App\Services\AuthService();
    if (!$authService->isAuthenticated()) {
        header('Location: /');
        exit;
    }
    
    $user = $authService->getCurrentUser();
    $ticketModel = new \App\Models\Ticket();
    
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if (!$ticketId) {
        header('Location: /tickets?error=' . urlencode('Invalid ticket ID'));
        exit;
    }
    
    // Verify ticket belongs to user
    $ticket = $ticketModel->findById($ticketId);
    if (!$ticket || $ticket['user_id'] != $user['id']) {
        header('Location: /tickets?error=' . urlencode('Ticket not found'));
        exit;
    }
    
    $ticketModel->delete($ticketId);
    
    header('Location: /tickets?success=' . urlencode('Ticket deleted successfully'));
    exit;
});

// Run the router
$router->run();
