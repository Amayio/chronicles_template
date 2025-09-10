<?php
/**
 * Router z obsługą standalone pages
 *
 * @package   MyAAC
 */

use MyAAC\Models\Pages;
use MyAAC\Plugins;

defined('MYAAC') or die('Direct access not allowed!');

if (!isset($content[0])) {
    $content = '';
}

// check if site has been closed
$load_it = true;
$site_closed = false;
if (fetchDatabaseConfig('site_closed', $site_closed)) {
    $site_closed = ($site_closed == 1);
    if ($site_closed) {
        if (!admin()) {
            $title = getDatabaseConfig('site_closed_title');
            $content .= '<p class="note">' . getDatabaseConfig('site_closed_message') . '</p><br/>';
            $load_it = false;
        }

        if (!$logged) {
            ob_start();
            require SYSTEM . 'pages/account/manage.php';
            $content .= ob_get_contents();
            ob_end_clean();
            $load_it = false;
        }
    }
}
define('SITE_CLOSED', $site_closed);

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    if ($pos !== 1) {
        $uri = substr($uri, 0, $pos);
    } else {
        $uri = str_replace_first('?', '', $uri);
    }
}

$uri = rawurldecode($uri);
if (BASE_DIR !== '') {
    $tmp = str_replace_first('/', '', BASE_DIR);
    $uri = str_replace_first($tmp, '', $uri);
}

if (0 === strpos($uri, '/')) {
    $uri = str_replace_first('/', '', $uri);
}

define('URI', $uri);

if (!$load_it) {
    define('PAGE', '');
    return;
}

if (SITE_CLOSED && admin()) {
    $content .= '<p class="note">Site is under maintenance (closed mode). Only privileged users can see it.</p>';
}

$ignore = false;
$logged_access = 0;
if ($logged && $account_logged && $account_logged->isLoaded()) {
    $logged_access = $account_logged->getAccess();
}

/**
 * Routes loading
 */
$dispatcher = FastRoute\cachedDispatcher(function (FastRoute\RouteCollector $r) {
    $routesFinal = [];
    foreach (getDatabasePages() as $page) {
        $routesFinal[] = ['*', $page, '__database__/' . $page, 100];
    }

    $routes = require SYSTEM . 'routes.php';
    Plugins::clearWarnings();

    foreach (Plugins::getRoutes() as $pluginRoute) {
        $routesFinal[] = [$pluginRoute[0], $pluginRoute[1], $pluginRoute[2], $pluginRoute[3] ?? 1000];

        foreach ($routes as &$route) {
            if (str_contains($pluginRoute[2], 'pages/' . $route[2])) {
                $route[2] = $pluginRoute[2];
            }
        }
    }

    foreach ($routes as $route) {
        if (!str_contains($route[2], '__redirect__') && !str_contains($route[2], '__database__')
            && !str_contains($route[2], 'plugins/')
        ) {
            if (!is_file(BASE . 'system/pages/' . $route[2])) {
                continue;
            }
            $routesFinal[] = [$route[0], $route[1], 'system/pages/' . $route[2], $route[3] ?? 10000];
        } else {
            $routesFinal[] = [$route[0], $route[1], $route[2], $route[3] ?? 10000];
        }
    }

    usort($routesFinal, function ($a, $b) {
        return $a[3] <=> $b[3];
    });

    $aliases = [
        [':int', ':string', ':alphanum'],
        [':\d+', ':[A-Za-z0-9-_%+\' ]+', ':[A-Za-z0-9]+'],
    ];

    $routesFinal = array_filter($routesFinal, function ($a) use ($aliases) {
        $a[1] = str_replace($aliases[0], $aliases[1], $a[1]);
        static $duplicates = [];
        if (isset($duplicates[$a[1]])) {
            return false;
        }
        $duplicates[$a[1]] = true;
        return true;
    });

    foreach ($routesFinal as $route) {
        if ($route[0] === '*') {
            $route[0] = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD'];
        } else {
            if (is_string($route[0])) {
                $route[0] = explode(',', $route[0]);
            }
            $route[0] = array_map(fn($v) => trim(strtoupper($v)), $route[0]);
        }
        $route[1] = str_replace($aliases[0], $aliases[1], $route[1]);
        try {
            $r->addRoute($route[0], $route[1], $route[2]);
        } catch (\Exception $e) {
            // duplicated route, ignore
        }
    }
}, [
    'cacheFile' => CACHE . 'route.cache',
    'cacheDisabled' => config('env') === 'dev',
]);

$httpMethod = $_SERVER['REQUEST_METHOD'];
$found = true;

// --- BACKWARD SUPPORT ---
$page = $_REQUEST['p'] ?? ($_REQUEST['subtopic'] ?? '');
if (!empty($page) && preg_match('/^[A-z0-9\-]+$/', $page)) {
    if (isset($_REQUEST['p'])) {
        $_REQUEST['subtopic'] = $_REQUEST['p'];
    }
    if (setting('core.backward_support')) {
        require SYSTEM . 'compat/pages.php';
    }
    $file = loadPageFromFileSystem($page, $found);
    if (!$found) {
        $file = false;
    }
} else {
    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            $page = $uri;
            if (preg_match('/^[A-z0-9\/\-]+$/', $page)) {
                $file = loadPageFromFileSystem($page, $found);
            } else {
                $found = false;
            }
            break;

        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $page = '405';
            $file = SYSTEM . 'pages/405.php';
            break;

        case FastRoute\Dispatcher::FOUND:
            $path = $routeInfo[1];
            $vars = $routeInfo[2];
            $_REQUEST = array_merge($_REQUEST, $vars);
            $_GET = array_merge($_GET, $vars);
            extract($vars);

            if (str_contains($path, '__database__/')) {
                $pageName = str_replace('__database__/', '', $path);
                $success = false;
                $tmp_content = getCustomPage($pageName, $success);
                if ($success && $hooks->trigger(HOOK_BEFORE_PAGE_CUSTOM)) {
                    $content .= $tmp_content;
                    if (hasFlag(FLAG_CONTENT_PAGES) || superAdmin()) {
                        $pageInfo = getCustomPageInfo($pageName);
                        $content = $twig->render('admin.links.html.twig', [
                            'page' => 'pages',
                            'id' => $pageInfo['id'] ?? 0,
                            'hide' => $pageInfo['hide'] ?? '0'
                        ]) . $content;
                    }
                    $hooks->trigger(HOOK_AFTER_PAGE_CUSTOM);
                    $page = $pageName;
                    $file = false;
                }
            } elseif (str_contains($path, '__redirect__/')) {
                $path = str_replace('__redirect__/', '', $path);
                header('Location: ' . BASE_URL . $path);
                exit;
            } else {
                $tmp = BASE_DIR;
                $uri = $_SERVER['REQUEST_URI'];
                if (strlen($tmp) > 0) {
                    $uri = str_replace(BASE_DIR . '/', '', $uri);
                }
                if (false !== $pos = strpos($uri, '?')) {
                    $uri = substr($uri, 0, $pos);
                }
                if (str_starts_with($uri, '/')) {
                    $uri = str_replace_first('/', '', $uri);
                }
                $page = str_replace('index.php/', '', $uri);
                if (empty($page)) {
                    $page = 'news';
                }
                $file = BASE . $path;
            }
            unset($tmp, $uri);
            break;
    }
}

if (!$found) {
    $page = '404';
    $file = SYSTEM . 'pages/404.php';
}

define('PAGE', $page);

// FULL PAGE
$standalone_pages = [
    'account/create'            => 'pages/account/register.php',
    'account/manage'            => 'pages/account/manage.php',
    'account/change-email'      => 'pages/account/manageAcc.php',
    'account/register'          => 'pages/account/manageAcc.php',
    'account/change-info'       => 'pages/account/manageAcc.php',
    'account/characters/create' => 'pages/account/manageAcc.php',
    'account/characters/delete' => 'pages/account/manageAcc.php',
    'points'                    => 'pages/shop.php',
	// 'downloads'                 => 'pages/downloads.php',
];

if (isset($standalone_pages[$page])) {
    define('STANDALONE', true);
    require __DIR__ . '/' . $standalone_pages[$page];
    exit;
}

// --- Standardowy tryb MyAAC ---
ob_start();
if ($hooks->trigger(HOOK_BEFORE_PAGE)) {
    if (!$ignore && $file !== false) {
        require $file;
    }
}

if (setting('core.backward_support') && isset($main_content[0])) {
    $content .= $main_content;
}

$content .= ob_get_clean();
$hooks->trigger(HOOK_AFTER_PAGE);

if (isset($_REQUEST['_page_only'])) {
    echo $content;
    die;
}

if (!isset($title)) {
    $title = ucwords(str_replace(['_', '-', '/'], ' ', str_replace('index.php/', '', $page)));
}

if (setting('core.backward_support')) {
    $main_content = $content;
    $topic = $title;
}

unset($page);

function getDatabasePages($withHidden = false): array
{
    global $logged_access;
    $pages = Pages::where('access', '<=', $logged_access)->when(!$withHidden, function ($q) {
        $q->isPublic();
    })->get('name');

    return $pages->pluck('name')->toArray();
}

function loadPageFromFileSystem($page, &$found): string
{
    global $template_path;
    $file = $template_path . '/pages/' . $page . '.php';
    if (!is_file($file)) {
        $file = SYSTEM . 'pages/' . $page . '.php';
        if (!is_file($file)) {
            $file = SYSTEM . 'pages/' . camelCaseToUnderscore($page) . '.php';
            if (!is_file($file)) {
                $found = false;
            }
        }
    }
    return $file;
}
