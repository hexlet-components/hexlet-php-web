<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/vendor/autoload.php';

use App\Application;
use function App\Renderer\render;

function loadGuestbook(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function saveGuestbook(string $path, array $messages): void
{
    $json = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json !== false) {
        file_put_contents($path, $json);
    }
}

$app = new Application();
$guestbookPath = __DIR__ . '/data/guestbook.json';

$articles = [
    ['slug' => 'php-basics', 'title' => 'PHP Basics', 'body' => 'Введение в язык и базовый синтаксис.'],
    ['slug' => 'routing-in-php', 'title' => 'Routing in PHP', 'body' => 'Как устроена маршрутизация в мини-фреймворке.'],
    ['slug' => 'templates', 'title' => 'Templates', 'body' => 'Как разделять логику и представление.'],
];

$apiUsers = [
    ['id' => 1, 'name' => 'Anna', 'email' => 'anna@example.com'],
    ['id' => 2, 'name' => 'Boris', 'email' => 'boris@example.com'],
    ['id' => 3, 'name' => 'Kate', 'email' => 'kate@example.com'],
    ['id' => 4, 'name' => 'Leo', 'email' => 'leo@example.com'],
];

$listUsers = [
    ['name' => 'Anna', 'status' => 'active'],
    ['name' => 'Boris', 'status' => 'banned'],
    ['name' => 'Kate', 'status' => 'active'],
    ['name' => 'Leo', 'status' => 'active'],
    ['name' => 'Mira', 'status' => 'banned'],
    ['name' => 'Ira', 'status' => 'active'],
];

$app->get('/', static function (): string {
    return render('home');
});

$app->get('/about', static function (): string {
    return render('about');
});

$app->get('/server', static function (): string {
    return render('server', ['server' => $_SERVER]);
});

$app->get('/users', static function ($meta, $params) use ($listUsers): string {
    $page = filter_var($params['page'] ?? 1, FILTER_VALIDATE_INT);
    if ($page === false || $page < 1) {
        $page = 1;
    }

    $per = filter_var($params['per'] ?? 2, FILTER_VALIDATE_INT);
    if ($per === false || $per < 1) {
        $per = 2;
    }

    $status = $params['status'] ?? null;
    $items = $listUsers;
    if (in_array($status, ['active', 'banned'], true)) {
        $items = array_values(array_filter(
            $listUsers,
            static fn(array $u): bool => $u['status'] === $status
        ));
    }

    $offset = ($page - 1) * $per;
    $items = array_slice($items, $offset, $per);

    return render('users/index', [
        'users' => $items,
        'page' => $page,
        'per' => $per,
        'status' => $status,
    ]);
});

$app->get('/articles', static function () use ($articles): string {
    return render('articles/index', ['articles' => $articles]);
});

$app->get('/articles/:slug', static function ($meta, $params, $arguments) use ($articles): string {
    $slug = $arguments['slug'] ?? '';
    foreach ($articles as $article) {
        if ($article['slug'] === $slug) {
            return render('articles/show', ['article' => $article]);
        }
    }

    return 'not found';
});

$app->get('/api/users', static function ($meta, $params) use ($apiUsers): string {
    header('Content-Type: application/json');

    $limit = $params['limit'] ?? null;
    if ($limit !== null) {
        $limitValue = filter_var($limit, FILTER_VALIDATE_INT);
        if ($limitValue === false || $limitValue < 1) {
            http_response_code(400);
            return json_encode(['error' => 'limit must be a positive integer']);
        }

        return json_encode(array_slice($apiUsers, 0, $limitValue));
    }

    return json_encode($apiUsers);
});

$app->get('/guestbook', static function () use ($guestbookPath): string {
    return render('guestbook/index', ['messages' => loadGuestbook($guestbookPath)]);
});

$app->get('/guestbook/new', static function (): string {
    return render('guestbook/new', [
        'form' => ['name' => '', 'message' => '', 'not_bot' => false],
        'errors' => [],
    ]);
});

$app->post('/guestbook', static function ($meta, $params) use ($guestbookPath): string {
    $form = $params['guestbook'] ?? [];
    $name = trim($form['name'] ?? '');
    $message = trim($form['message'] ?? '');
    $notBot = isset($form['not_bot']);

    $errors = [];
    if (mb_strlen($message) < 10) {
        $errors['message'] = 'Сообщение должно содержать минимум 10 символов.';
    }
    if (!$notBot) {
        $errors['not_bot'] = 'Подтвердите, что вы не бот.';
    }

    if (!empty($errors)) {
        return render('guestbook/new', [
            'form' => ['name' => $name, 'message' => $message, 'not_bot' => $notBot],
            'errors' => $errors,
        ]);
    }

    $messages = loadGuestbook($guestbookPath);
    $messages[] = [
        'name' => $name === '' ? 'Аноним' : $name,
        'message' => $message,
    ];
    saveGuestbook($guestbookPath, $messages);

    return render('guestbook/index', ['messages' => $messages]);
});

$app->get('/image-upload', static function (): string {
    return render('files/new', ['imageDataUrl' => null, 'error' => null]);
});

$app->post('/image-upload', static function (): string {
    $imageDataUrl = null;
    $error = null;

    if (!isset($_FILES['image'])) {
        return render('files/new', ['imageDataUrl' => null, 'error' => 'Файл не был отправлен.']);
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return render('files/new', ['imageDataUrl' => null, 'error' => 'Ошибка загрузки файла.']);
    }

    $tmpPath = $file['tmp_name'];
    $content = file_get_contents($tmpPath);
    if ($content === false) {
        return render('files/new', ['imageDataUrl' => null, 'error' => 'Не удалось прочитать временный файл.']);
    }

    $mime = $file['type'] ?: 'image/jpeg';
    $imageDataUrl = sprintf('data:%s;base64,%s', $mime, base64_encode($content));

    return render('files/new', ['imageDataUrl' => $imageDataUrl, 'error' => $error]);
});

$app->get('/welcome', static function (): string {
    $name = $_COOKIE['name'] ?? '';
    $theme = $_COOKIE['theme'] ?? 'light';
    if (!in_array($theme, ['light', 'dark'], true)) {
        $theme = 'light';
    }

    return render('cookies/welcome', ['name' => $name, 'theme' => $theme]);
});

$app->post('/welcome', static function ($meta, $params): string {
    $form = $params['settings'] ?? [];
    $name = trim($form['name'] ?? '');
    $theme = $form['theme'] ?? 'light';

    if (!in_array($theme, ['light', 'dark'], true)) {
        $theme = 'light';
    }

    setcookie('name', $name, time() + 7 * 24 * 60 * 60, '/');
    setcookie('theme', $theme, time() + 7 * 24 * 60 * 60, '/');

    header('Location: /welcome');
    http_response_code(302);
    return '';
});

$app->post('/welcome/reset', static function (): string {
    setcookie('name', '', time() - 3600, '/');
    setcookie('theme', '', time() - 3600, '/');

    header('Location: /welcome');
    http_response_code(302);
    return '';
});

$app->get('/login', static function (): string {
    return render('session/login', ['error' => null]);
});

$app->post('/login', static function ($meta, $params): string {
    $form = $params['auth'] ?? [];
    $login = trim($form['login'] ?? '');
    $password = trim($form['password'] ?? '');

    if ($login === 'admin' && $password === 'secret') {
        $_SESSION['user'] = ['login' => $login];
        header('Location: /profile');
        http_response_code(302);
        return '';
    }

    return render('session/login', ['error' => 'Неверный логин или пароль.']);
});

$app->get('/profile', static function (): string {
    $user = $_SESSION['user'] ?? null;
    if ($user === null) {
        header('Location: /login');
        http_response_code(302);
        return '';
    }

    return render('session/profile', ['user' => $user]);
});

$app->post('/logout', static function (): string {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: /login');
    http_response_code(302);
    return '';
});

$app->run();
