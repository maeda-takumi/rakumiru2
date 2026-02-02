<?php
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'ss911157_rakumiru2');
    define('DB_USER', 'ss911157_sedo');
    define('DB_PASS', 'sedorisedori');
    define('DB_CHARSET', 'utf8mb4');
    define('LINE_CHANNEL_ID', '2009012040');
    define('LINE_CHANNEL_SECRET', 'f7984afbe1a8f69b59610142c0d0f729');
    define('LINE_REDIRECT_URI', 'https://totalappworks.com/rakumiru/test/line_callback.php');
    define('GEMINI_API_KEY','AIzaSyBlYZPgDDjTHmHdMDN6Yk_xwDIgnXqVbBQ');

    function configureSessionCookie(): void {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => $isHttps ? 'None' : 'Lax',
        ]);
    }
?>  