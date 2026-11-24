<?php
/**
 * CR339: filing media into folders must respect per-item edit rights and the attachment post type.
 *
 * Usage: php tests/test-cr339-assign-permissions.php [path/to/plugin/root]
 *
 * Loads the real Taxonomy, FolderManager and BulkActions classes against WordPress stubs. The user
 * is an Author: upload_files, can edit attachment 10 (their own), cannot edit attachment 11
 * (another user's), and 12 is a page they can edit. Every assignment path is driven with all three
 * IDs; only 10 may be filed. FAILS on the pre-fix code (every path filed 11 and 12 too).
 */

define('ABSPATH', '/tmp/');
$root = $argv[1] ?? dirname(__DIR__);

$GLOBALS['terms'] = array(10 => array(3), 11 => array(3), 12 => array());
$GLOBALS['json'] = null;
$GLOBALS['post_types'] = array(10 => 'attachment', 11 => 'attachment', 12 => 'page');
$GLOBALS['editable'] = array(10 => true, 11 => false, 12 => true);

class WP_Error {}
function add_action() {}
function add_filter() {}
function __($s) { return $s; }
function _n($a, $b, $n) { return $n === 1 ? $a : $b; }
function absint($v) { return abs((int) $v); }
function sanitize_text_field($v) { return trim((string) $v); }
function check_ajax_referer() { return true; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function get_post_type($id) { return $GLOBALS['post_types'][$id] ?? false; }
function current_user_can($cap, $id = null) {
    if ($cap === 'upload_files') return true;
    if ($cap === 'edit_post') return !empty($GLOBALS['editable'][$id]);
    return false;
}
function wp_get_object_terms($id) { return $GLOBALS['terms'][$id] ?? array(); }
function wp_set_object_terms($id, $terms) { $GLOBALS['terms'][$id] = array_values($terms); return $terms; }
function wp_update_term_count_now() {}
function clean_term_cache() {}
function get_term() { return null; }
function add_query_arg($a, $url) { return $url . '?' . http_build_query($a); }
function wp_send_json_success($d = null) { $GLOBALS['json'] = array('success' => true, 'data' => $d); }
function wp_send_json_error($d = null) { $GLOBALS['json'] = array('success' => false, 'data' => $d); }

require $root . '/includes/Core/Taxonomy.php';
require $root . '/includes/Admin/FolderManager.php';
require $root . '/includes/Admin/BulkActions.php';

$fails = 0;
function check($name, $cond, $detail = '') {
    global $fails;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . (!$cond && $detail ? "  [$detail]" : '') . "\n";
    if (!$cond) $fails++;
}
function reset_terms() { $GLOBALS['terms'] = array(10 => array(3), 11 => array(3), 12 => array()); }
function only_own_filed($folder, $what) {
    $t = $GLOBALS['terms'];
    check("$what: the Author's own attachment is filed", in_array($folder, $t[10], true), json_encode($t));
    check("$what: another user's attachment is untouched", $t[11] === array(3), json_encode($t[11]));
    check("$what: a page gets no folder term", $t[12] === array(), json_encode($t[12]));
}

$fm = TPFMediaOrganizer\Admin\FolderManager::get_instance();
$ba = TPFMediaOrganizer\Admin\BulkActions::get_instance();

reset_terms();
$_POST = array('nonce' => 'n', 'attachment_ids' => array(10, 11, 12), 'folder_id' => 7);
$fm->ajax_assign_folder();
only_own_filed(7, 'ajax_assign_folder');

reset_terms();
$_POST = array('nonce' => 'n', 'attachment_ids' => array(10, 11, 12), 'folder_id' => 7, 'action_type' => 'move');
$ba->ajax_bulk_assign();
only_own_filed(7, 'ajax_bulk_assign move');

reset_terms();
$_POST = array('nonce' => 'n', 'attachment_ids' => array(10, 11, 12), 'folder_id' => 7, 'action_type' => 'add');
$ba->ajax_bulk_assign();
only_own_filed(7, 'ajax_bulk_assign add');

reset_terms();
$ba->handle_bulk_action('upload.php', 'tpf_mo_move_7', array(10, 11, 12));
only_own_filed(7, 'list-table bulk move');

reset_terms();
$ba->handle_bulk_action('upload.php', 'tpf_mo_remove_folder', array(10, 11, 12));
check('list-table bulk remove: own attachment cleared', $GLOBALS['terms'][10] === array());
check("list-table bulk remove: another user's attachment keeps its folder", $GLOBALS['terms'][11] === array(3),
      json_encode($GLOBALS['terms'][11]));

echo "\nFAILURES: $fails\n";
exit($fails ? 1 : 0);
