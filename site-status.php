<?php
$_VERSION = 'v1.0';

try {
    $status_code = null;
    $resp = [
        'ok' => true,
        'version' => $_VERSION,
        'hash' => md5_file(__FILE__),
    ];

    $action = $_GET['action'] ?? 'check_status';
    switch ($action) {
        case 'check_status':
            $result = check_status();
            $resp = array_merge($resp, $result);
            break;
        case 'self_update':
            $result = self_update();
            $resp = array_merge($resp, $result);
            break;
        case 'version':
            break;
        default:
            $status_code = 400;
            throw new Exception('unsupported action', 400);
    }
} catch (Throwable $th) {
    $resp = array_merge($resp, [
        'ok' => false,
        'code' => $th->getCode(),
        'msg' => $th->getMessage(),
        'file' => $th->getFile(),
        'line' => $th->getLine()
    ]);
    if ($status_code === null) {
        $status_code = 500;
    }
} finally {
    if ($status_code === null) {
        $status_code = 200;
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status_code);
    echo json_encode($resp);
}

function check_status()
{
    require_once __DIR__ . '/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
    require_once ABSPATH . 'wp-admin/includes/admin.php';

    $site_healh = new WP_Site_Health();
    // 检测WP版本
    $wordpress_version = $site_healh->get_test_wordpress_version();
    $wordpress_status = ['good' => '', 'recommended' => 'This is a major version mismatch', 'critical' => 'This is a minor version, sometimes considered more critical'];
    $wordpress_version['label'] = $wordpress_status[$wordpress_version['status']];
    $wordpress_version['version'] = get_bloginfo('version');
    // 检测插件
    $plugin_version = $site_healh->get_test_plugin_version();
    $plugins = get_plugins();
    $plugin_updates = get_plugin_updates();
    $plugins_need_update = 0;
    $plugins_total = 0;
    $plugins_active = 0;
    // Loop over the available plugins and check their versions and active state.
    foreach ($plugins as $plugin_path => $plugin) {
        $plugins_total++;
        if (is_plugin_active($plugin_path)) {
            $plugins_active++;
        }
        // $plugin_version = $plugin['Version'];
        if (array_key_exists($plugin_path, $plugin_updates)) {
            $plugins_need_update++;
        }
    }
    $plugin_version['plugins_total'] = $plugins_total;
    $plugin_version['plugins_active'] = $plugins_active;
    $plugin_version['plugins_need_update'] = $plugins_need_update;
    // 检测主题
    // $theme_updates = get_theme_updates();
    $theme_version = $site_healh->get_test_theme_version();
    $active_theme = wp_get_theme();
    $theme_version['active_theme'] = $active_theme;

    // 检测PHP
    $php_version = $site_healh->get_test_php_version();

    // 检测数据库
    $sql_v = $site_healh->get_test_sql_server();

    // 检测https
    $https_status = $site_healh->get_test_https_status();

    // 检测更新
    $background_updates = $site_healh->get_test_background_updates();
    $plugin_theme_auto = $site_healh->get_test_plugin_theme_auto_updates();
    $rest_availability = $site_healh->get_test_rest_availability();
    // $version_check_exists = $site_healh->check_wp_version_check_exists();

    // 页面状态
    // $page_status = $site_healh->get_tests();

    $index_filename = 'index.php';
    $index_md5file = md5_file($index_filename);

    return [
        'debug' => WP_DEBUG,
        'site_url' => site_url(),
        'site_name' => get_bloginfo(),
        'index_hash' => $index_md5file,
        'wordpress' => $wordpress_version,                //Tests for WordPress version and outputs it
        'plugin' => $plugin_version,                      //Test if plugins are outdated, or unnecessary.
        'theme' => $theme_version,                        //Test if themes are outdated, or unnecessary.
        'php' => $php_version,                            //Test if the supplied PHP version is supported.
        'sql' => $sql_v,                                  //Test if the SQL server is up to date.
        'https' => $https_status,                         //Test if your site is serving content over HTTPS.
        'background_updates' => $background_updates,      //Test if WordPress can run automated background updates.
        'plugin_theme' => $plugin_theme_auto,             //Test if plugin and theme auto-updates appear to be configured correctly.
        'rest_availability' => $rest_availability,        //Test if the REST API is accessible.
        // 'version_check' => $version_check_exists,      //Test if `wp_version_check` is blocked.
        // 'site_status' => $page_status['async'],
    ];
}

function self_update()
{
    $url = 'https://raw.githubusercontent.com/kvdev01/wp-site-status/master/site-status.php';
    $tmp_file = __FILE__ . '.tmp';
    $content = curl_get_file_contents($url);
    if (empty($content)) {
        throw new Exception('failed to update', 1);
    }

    if (!file_put_contents($tmp_file, $content)) {
        throw new Exception('failed to update', 2);
    }

    if (!rename($tmp_file, __FILE__)) {
        throw new Exception('failed to update', 3);
    }

    return [];
}

function curl_get_file_contents($url)
{
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_URL, $url);
    $contents = curl_exec($c);
    curl_close($c);

    if ($contents) {
        return $contents;
    }
    return false;
}
