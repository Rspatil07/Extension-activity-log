<?php
/*
Plugin Name: Extension Activity Log
Description: Track and manage plugin usage with visualization, sorting, cleanup tools, and admin notifications.
Version: 1.0.0
Author: Rohit Patil
Author URI: https://rohitpatil.unaux.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.2
Text Domain: extension-activity-log
*/

if (!defined('ABSPATH')) exit;

class Exteaclo_Plugin_Tracker {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'exteaclo_plugin_usage';

        register_activation_hook(__FILE__, [$this, 'create_usage_table']);
        add_action('activated_plugin', [$this, 'log_activation'], 10, 2);
        add_action('deactivated_plugin', [$this, 'log_deactivation'], 10, 2);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_exteaclo_export_csv', [$this, 'export_csv']);
        add_action('exteaclo_daily_cleanup', [$this, 'auto_cleanup']);

        if (!wp_next_scheduled('exteaclo_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'exteaclo_daily_cleanup');
        }
    }

    public function create_usage_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id INT NOT NULL AUTO_INCREMENT,
            plugin_name VARCHAR(255) NOT NULL,
            usage_count INT DEFAULT 0,
            last_used DATETIME,
            is_active TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function log_activation($plugin, $network_wide) {
        global $wpdb;
        $plugin_name = plugin_basename($plugin);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE plugin_name = %s", $plugin_name));

        if ($row) {
            $wpdb->update($this->table_name, [
                'usage_count' => $row->usage_count + 1,
                'last_used' => current_time('mysql'),
                'is_active' => 1
            ], ['plugin_name' => $plugin_name]);
        } else {
            $wpdb->insert($this->table_name, [
                'plugin_name' => $plugin_name,
                'usage_count' => 1,
                'last_used' => current_time('mysql'),
                'is_active' => 1
            ]);
        }
    }

    public function log_deactivation($plugin, $network_wide) {
        global $wpdb;
        $plugin_name = plugin_basename($plugin);
        $wpdb->update($this->table_name, ['is_active' => 0], ['plugin_name' => $plugin_name]);
    }

    public function add_menu() {
        add_menu_page('Extension Activity Log', 'Plugin Tracker', 'manage_options', 'exteaclo-usage-tracker', [$this, 'render_admin_page']);
    }

    public function render_admin_page() {
        global $wpdb;
        $data = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY usage_count DESC");

        $labels = array();
        $values = array();
        foreach ($data as $item) {
            $labels[] = $item->plugin_name;
            $values[] = $item->usage_count;
        }

        echo '<div class="wrap"><h1>Extension Activity Log</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">
            <input type="hidden" name="action" value="exteaclo_export_csv">
            <input type="submit" class="button button-primary" value="Export to CSV">
        </form><br>';
        echo '<form method="post"><input type="submit" name="exteaclo_cleanup" class="button" value="Manual Cleanup"></form><br>';

        echo '<table class="widefat"><thead><tr>
            <th>Plugin Name</th><th>Usage Count</th><th>Last Used</th><th>Status</th>
        </tr></thead><tbody>';

        foreach ($data as $item) {
            echo '<tr><td>' . esc_html($item->plugin_name) . '</td>
                      <td>' . intval($item->usage_count) . '</td>
                      <td>' . esc_html($item->last_used) . '</td>
                      <td>' . ($item->is_active ? 'Active' : 'Inactive') . '</td></tr>';
        }
        echo '</tbody></table></div>';

        if (isset($_POST['exteaclo_cleanup'])) {
            $this->manual_cleanup();
        }
    }

    public function manual_cleanup() {
        global $wpdb;
        $threshold = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        $unused = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE last_used < %s", $threshold));

        foreach ($unused as $plugin) {
            $wpdb->delete($this->table_name, ['id' => $plugin->id]);
        }

        wp_mail(get_option('admin_email'), 'Plugin Usage Cleanup Report', count($unused) . ' unused plugin records deleted.');
    }

    public function auto_cleanup() {
        $this->manual_cleanup();
    }

    public function export_csv() {
    global $wpdb;
    $data = $wpdb->get_results("SELECT * FROM {$this->table_name}", ARRAY_A);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="plugin-usage.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output) {
        fputcsv($output, ['Plugin Name', 'Usage Count', 'Last Used', 'Status']);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['plugin_name'],
                $row['usage_count'],
                $row['last_used'],
                $row['is_active'] ? 'Active' : 'Inactive'
            ]);
        }
    }

    exit;
}

}

function exteaclo_enqueue_assets($hook) {
    wp_enqueue_script(
        'exteaclo-chart',
        plugin_dir_url(__FILE__) . 'assets/js/chart.umd.min.js',
        array(),
        '4.4.9',
        true
    );
}
add_action('admin_enqueue_scripts', 'exteaclo_enqueue_assets');

new Exteaclo_Plugin_Tracker();
