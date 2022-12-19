<?php
/****
你需要写一个site-status.php的脚本放到底包中，用于health check. 这个文件要包含wp的一些include文件，比如wp-load.php，及admin,主题相关的。
执行一些检查（需要想想，如暂时没有，后面再更新也行，包含引用文件本身也是一种检查）。
返回一个json对象，包含ok（true, false), blogname, version, hash（请求中计算）
ok表示检测结果。
version表示该文件的版本(v1,v2,...)，hash是该文件内容的hash（md5, sha1都行），这是为了确保该文件不被篡改。
每次更新该文件后，version和hash也要同步更新到检测系统，用于结果的校验。
 */
//  ini_set("display_errors", "On");
//  error_reporting(E_ALL ^ E_DEPRECATED);
header('Content-type:application/json');
try {
    require_once __DIR__ . '/wp-load.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
    require_once ABSPATH . 'wp-admin/includes/admin.php';

    $site_healh = new WP_Site_Health();
    //检测WP版本
    $wordpress_version = $site_healh->get_test_wordpress_version();
    $wordpress_status = ['good' => '', 'recommended' => 'This is a major version mismatch', 'critical' => 'This is a minor version, sometimes considered more critical'];
    $wordpress_version['label'] = $wordpress_status[$wordpress_version['status']];
    $wordpress_version['version'] = get_bloginfo('version');
    //检测插件
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
    //检测主题
    $theme_updates = get_theme_updates();
    $theme_version = $site_healh->get_test_theme_version();
    $default_theme = wp_get_theme(WP_DEFAULT_THEME);
    $all_themes = wp_get_themes();
    $active_theme = wp_get_theme();
    $theme_version['active_theme'] = $default_theme;
    $theme_version['themes'] = $all_themes;

    //检测PHP
    $php_version = $site_healh->get_test_php_version();

    //检测数据库
    $sql_v = $site_healh->get_test_sql_server();

    //检测https
    $https_status = $site_healh->get_test_https_status();

    //检测更新
    $background_updates = $site_healh->get_test_background_updates();
    $plugin_theme_auto = $site_healh->get_test_plugin_theme_auto_updates();
    $rest_availability = $site_healh->get_test_rest_availability();
    $version_check_exists = $site_healh->check_wp_version_check_exists();

    //页面状态
    // $page_status=$site_healh->get_tests();

    $site_status_filename = 'site-status.php';
    $index_filename = 'index.php';
    $site_status_md5file = md5_file($site_status_filename);
    $index_md5file = md5_file($index_filename);

    echo json_encode(
        [
            'ok' => true,
            'debug' => WP_DEBUG,
            'site_url' => esc_url(home_url('/')),
            'site_name' => get_bloginfo(),
            'version' => 'v1.0',
            'hash' => $site_status_md5file,
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
            // "version_check"=>$version_check_exists,      //Test if `wp_version_check` is blocked.
            // "site_status"=>$page_status["async"],
        ]
    );
} catch (\Exception $e) {
    echo json_encode(
        [
            'ok' => false,
            'code' => $e->getCode(),
            'msg' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    );
}
