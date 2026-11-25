<?php
/**
 * CR340: the Uncategorized count must be one SQL COUNT, and only run where the sidebar shows it.
 *
 * Usage: php tests/test-cr340-uncategorized-count.php [path/to/plugin/root]
 *
 * Loads the real Taxonomy and MediaLibrary classes against WordPress stubs. $wpdb is backed by an
 * in-memory SQLite copy of the posts / term tables, and get_posts() is a spy. The library holds
 * uncategorized, foldered, tag-only (other taxonomy), trashed and non-attachment rows.
 * get_folder_count(0) must return the right number from a single query, without get_posts() or a
 * list of IDs. enqueue_assets() must only count on upload.php (the sidebar's only page) with
 * "Show Uncategorized" on.
 * FAILS on the pre-fix code: the count loaded every foldered ID and every uncategorized ID through
 * get_posts(posts_per_page=-1, post__not_in=...), on post.php / post-new.php too.
 */

define('ABSPATH', '/tmp/');
define('TPF_MEDIA_ORGANIZER_PLUGIN_URL', 'http://x/');
define('TPF_MEDIA_ORGANIZER_VERSION', '1');
$root = $argv[1] ?? dirname(__DIR__);

class FakeWpdb {
    public $posts = 'wp_posts';
    public $term_relationships = 'wp_term_relationships';
    public $term_taxonomy = 'wp_term_taxonomy';
    public $queries = array();
    public $pdo;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT)');
        $this->pdo->exec('CREATE TABLE wp_term_taxonomy (term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER, taxonomy TEXT)');
        $this->pdo->exec('CREATE TABLE wp_term_relationships (object_id INTEGER, term_taxonomy_id INTEGER)');
    }
    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        $pdo = $this->pdo;
        return preg_replace_callback('/%[sd]/', function ($m) use (&$args, $pdo) {
            $v = array_shift($args);
            return $m[0] === '%d' ? (string) (int) $v : $pdo->quote((string) $v);
        }, $sql);
    }
    private function run($sql) {
        $this->queries[] = $sql;
        $st = $this->pdo->query($sql);
        if (!$st) throw new RuntimeException('SQL error: ' . implode(' ', $this->pdo->errorInfo()) . "\n$sql");
        return $st;
    }
    public function get_col($sql) { return $this->run($sql)->fetchAll(PDO::FETCH_COLUMN); }
    public function get_var($sql) { $r = $this->run($sql)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }
}
$wpdb = new FakeWpdb();
$GLOBALS['wpdb'] = $wpdb;

// Library: 10-14 inherit attachments; 20 a trashed attachment; 30 a page.
foreach (array(10, 11, 12, 13, 14) as $id) $wpdb->pdo->exec("INSERT INTO wp_posts VALUES ($id, 'attachment', 'inherit')");
$wpdb->pdo->exec("INSERT INTO wp_posts VALUES (20, 'attachment', 'trash')");
$wpdb->pdo->exec("INSERT INTO wp_posts VALUES (30, 'page', 'publish')");
// Folders (tt 1, 2) and a tag in another taxonomy (tt 3).
$wpdb->pdo->exec("INSERT INTO wp_term_taxonomy VALUES (1, 101, 'tpf_media_folder'), (2, 102, 'tpf_media_folder'), (3, 103, 'post_tag')");
// 10 in one folder, 11 in two folders, 12 only has a tag (still uncategorized), 13/14 have nothing,
// 20 (trash) and 30 (page) are in a folder or not and must be ignored.
$wpdb->pdo->exec("INSERT INTO wp_term_relationships VALUES (10, 1), (11, 1), (11, 2), (12, 3), (20, 1), (30, 2)");
// Uncategorized inherit attachments: 12, 13, 14 => 3.

$GLOBALS['get_posts_calls'] = array();
$GLOBALS['options'] = array();
$GLOBALS['localized'] = null;

class WP_Error {}
function add_action() {}
function add_filter() {}
function __($s) { return $s; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_parse_args($a, $d) { return array_merge($d, $a); }
function get_terms() { return array(); }
function get_term() { return null; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['options']) ? $GLOBALS['options'][$k] : $d; }
function admin_url($p = '') { return 'http://x/wp-admin/' . $p; }
function wp_create_nonce() { return 'n'; }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script($h, $name, $data) { $GLOBALS['localized'] = $data; }
function get_posts($args) {
    // Spy, and answer like WordPress would so the old code yields a real number.
    $GLOBALS['get_posts_calls'][] = $args;
    $not_in = array_map('intval', $args['post__not_in'] ?? array());
    $ids = $GLOBALS['wpdb']->pdo->query("SELECT ID FROM wp_posts WHERE post_type = 'attachment' AND post_status = 'inherit'")
        ->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_filter(array_map('intval', $ids), function ($id) use ($not_in) { return !in_array($id, $not_in, true); }));
}

require $root . '/includes/Core/DefaultSettings.php';
require $root . '/includes/Core/Taxonomy.php';
require $root . '/includes/Admin/MediaLibrary.php';

use TPFMediaOrganizer\Core\Taxonomy;
use TPFMediaOrganizer\Admin\MediaLibrary;

$fails = 0;
function check($name, $cond, $detail = '') {
    global $fails;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . (!$cond && $detail !== '' ? "  [$detail]" : '') . "\n";
    if (!$cond) $fails++;
}
function reset_spies() { $GLOBALS['wpdb']->queries = array(); $GLOBALS['get_posts_calls'] = array(); $GLOBALS['localized'] = null; }

// 1. The count itself.
reset_spies();
$n = Taxonomy::get_folder_count(0);
check('get_folder_count(0) counts the 3 uncategorized inherit attachments', $n === 3, var_export($n, true));
check('get_folder_count(0) does not call get_posts()', count($GLOBALS['get_posts_calls']) === 0,
      json_encode($GLOBALS['get_posts_calls']));
check('get_folder_count(0) runs exactly one query', count($wpdb->queries) === 1, count($wpdb->queries) . ' queries');
check('that query is a COUNT, not an ID list', isset($wpdb->queries[0]) && stripos($wpdb->queries[0], 'COUNT(') !== false,
      $wpdb->queries[0] ?? '');

// A folder in another taxonomy assignment changes nothing; filing 13 drops the count to 2.
$wpdb->pdo->exec("INSERT INTO wp_term_relationships VALUES (13, 2)");
reset_spies();
$n = Taxonomy::get_folder_count(0);
check('filing one more item drops the count to 2', $n === 2, var_export($n, true));
$wpdb->pdo->exec("DELETE FROM wp_term_relationships WHERE object_id = 13");

// 2. Where it is computed.
$ml = MediaLibrary::get_instance();

reset_spies();
$ml->enqueue_assets('upload.php');
check('upload.php: uncategorizedCount is localized as 3', ($GLOBALS['localized']['uncategorizedCount'] ?? null) === 3,
      var_export($GLOBALS['localized']['uncategorizedCount'] ?? null, true));

foreach (array('post.php', 'post-new.php') as $hook) {
    reset_spies();
    $ml->enqueue_assets($hook);
    $counted = count($GLOBALS['get_posts_calls']) > 0;
    foreach ($wpdb->queries as $q) {
        if (stripos($q, 'tpf_media_folder') !== false && stripos($q, 'term_id =') === false) $counted = true;
    }
    check("$hook: no Uncategorized count is computed (the sidebar never shows there)", !$counted,
          json_encode(array('get_posts' => count($GLOBALS['get_posts_calls']), 'queries' => $wpdb->queries)));
}

$GLOBALS['options']['tpf_mo_show_uncategorized'] = false;
reset_spies();
$ml->enqueue_assets('upload.php');
check('upload.php with Show Uncategorized off: no count is computed',
      count($GLOBALS['get_posts_calls']) === 0 && count($wpdb->queries) === 0,
      json_encode(array('get_posts' => count($GLOBALS['get_posts_calls']), 'queries' => $wpdb->queries)));
unset($GLOBALS['options']['tpf_mo_show_uncategorized']);

echo "\nFAILURES: $fails\n";
exit($fails ? 1 : 0);
