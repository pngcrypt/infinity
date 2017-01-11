<?php
include 'inc/functions.php';
$global = isset($_GET['global']);
$post   = (isset($_GET['post']) ? $_GET['post'] : false);
$board  = (isset($_GET['board']) ? $_GET['board'] : false);

if (!$post || !preg_match('/^delete_\d+$/', $post) || !$board || !openBoard($board)) {
	header('HTTP/1.1 400 Bad Request');
	error(_('Bad request.'));
}

$body = Element('report.html', ['global' => $global, 'post' => $post, 'board' => Vi::$board, 'config' => Vi::$config]);
echo Element('page.html', ['no_logo' => true, 'config' => Vi::$config, 'body' => $body]);
