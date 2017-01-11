<?php
include 'inc/functions.php';

if (!isset($_GET['board']) || !preg_match("/" . Vi::$config['board_regex'] . "/u", $_GET['board'])) {
	http_response_code(400);
	error(_('Bad board.'));
}
if (!openBoard($_GET['board'])) {
	http_response_code(404);
	error(_('No board.'));
}

header('Content-Type: application/json');
$safe_config['title']                         = Vi::$board['title'];
$safe_config['subtitle']                      = Vi::$board['subtitle'];
$safe_config['indexed']                       = (Vi::$board['indexed'] == "1");
$safe_config['country_flags']                 = Vi::$config['country_flags'];
$safe_config['field_disable_name']            = Vi::$config['field_disable_name'];
$safe_config['enable_embedding']              = Vi::$config['enable_embedding'];
$safe_config['force_image_op']                = Vi::$config['force_image_op'];
$safe_config['disable_images']                = Vi::$config['disable_images'];
$safe_config['poster_ids']                    = Vi::$config['poster_ids'];
$safe_config['show_sages']                    = Vi::$config['show_sages'];
$safe_config['auto_unicode']                  = Vi::$config['auto_unicode'];
$safe_config['strip_combining_chars']         = Vi::$config['strip_combining_chars'];
$safe_config['allow_roll']                    = Vi::$config['allow_roll'];
$safe_config['image_reject_repost']           = Vi::$config['image_reject_repost'];
$safe_config['image_reject_repost_in_thread'] = Vi::$config['image_reject_repost_in_thread'];
$safe_config['early_404']                     = Vi::$config['early_404'];
$safe_config['allow_delete']                  = Vi::$config['allow_delete'];
$safe_config['anonymous']                     = Vi::$config['anonymous'];
$safe_config['blotter']                       = Vi::$config['blotter'];
$safe_config['stylesheets']                   = Vi::$config['stylesheets'];
$safe_config['default_stylesheet']            = Vi::$config['default_stylesheet'];
$safe_config['captcha']                       = Vi::$config['captcha'];
$safe_config['force_subject_op']              = Vi::$config['force_subject_op'];
$safe_config['tor_posting']                   = Vi::$config['tor_posting'];
$safe_config['tor_image_posting']             = Vi::$config['tor_image_posting'];
$safe_config['new_thread_capt']               = Vi::$config['new_thread_capt'];
$safe_config['hour_max_threads']              = Vi::$config['hour_max_threads'];
$safe_config['disable_images']                = Vi::$config['disable_images'];
$safe_config['locale']                        = BoardLocale(Vi::$board['uri']);
$safe_config['allowed_ext_files']             = Vi::$config['allowed_ext_files'];
$safe_config['allowed_ext']                   = Vi::$config['allowed_ext'];
$safe_config['user_flags']                    = Vi::$config['user_flags'];
$safe_config['wordfilters']                   = Vi::$config['wordfilters'];
$safe_config['latex']                         = Vi::$config['katex'];
$safe_config['code_tags']                     = Vi::$config['code_tags'];
$safe_config['max_pages']                     = Vi::$config['max_pages'];
$safe_config['max_newlines']                  = Vi::$config['max_newlines'];
$safe_config['reply_limit']                   = Vi::$config['reply_limit'];
$safe_config['gif_preview_animate']           = Vi::$config['gif_preview_animate'];

echo json_encode($safe_config);
