<?php
/**
 * CR341: the private folder taxonomy must not be listable over the public REST API.
 *
 * Usage: php tests/test-cr341-folder-rest.php [path/to/plugin/root]
 *
 * Loads the real Taxonomy class against WordPress stubs and captures the register_taxonomy() args.
 * WordPress core only registers /wp/v2/<taxonomy> when show_in_rest is true, and
 * WP_REST_Terms_Controller::get_items_permissions_check() lets anyone read in view context once it
 * is registered (public=false is ignored). So show_in_rest must be false. The plugin's own UI uses
 * admin-ajax, so the admin screens (show_ui, capabilities) must be unchanged.
 * FAILS on the pre-fix code (show_in_rest was true, so GET /wp-json/wp/v2/tpf_media_folder listed
 * every folder name, slug and count to logged-out visitors).
 */

define('ABSPATH', '/tmp/');
$root = $argv[1] ?? dirname(__DIR__);

$GLOBALS['registered'] = array();
function did_action() { return true; }
function add_action() {}
function __($s) { return $s; }
function _x($s) { return $s; }
function register_taxonomy($name, $type, $args) { $GLOBALS['registered'][$name] = array('type' => $type, 'args' => $args); }

require $root . '/includes/Core/Taxonomy.php';
\TPFMediaOrganizer\Core\Taxonomy::get_instance();

$fails = 0;
function check($name, $cond, $detail = '') {
    global $fails;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . (!$cond && $detail !== '' ? "  [$detail]" : '') . "\n";
    if (!$cond) $fails++;
}

$reg = $GLOBALS['registered']['tpf_media_folder'] ?? null;
check('tpf_media_folder is registered on attachment', $reg && $reg['type'] === 'attachment');
$args = $reg['args'] ?? array();
check('show_in_rest is off, so no anonymous /wp/v2/tpf_media_folder route exists',
      empty($args['show_in_rest']), var_export($args['show_in_rest'] ?? null, true));
check('taxonomy stays non-public', ($args['public'] ?? null) === false);
check('admin UI is still on', ($args['show_ui'] ?? null) === true);
check('folder management still needs upload_files', ($args['capabilities']['manage_terms'] ?? null) === 'upload_files');

echo "\nFAILURES: $fails\n";
exit($fails ? 1 : 0);
