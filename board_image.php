<?php
// board_image.php - a banner displaying script
// ---------------
//
// I would name it "banner.php", but most adblocks have that blocked,
// degrading the site quality for certain users.

$dir = "static/banners/";
$domain = "/";
$banner = [
	'board' => NULL,
	'image' => $dir . '../8chan banner.png', // заглушка
];

$banners = glob('{' . $dir . '*.*,' . $dir . '*/*.*}', GLOB_BRACE);
if($banners) {
	$banner['image'] = $banners[array_rand($banners)];
}

if(preg_match('#'.$dir.'([^/]+)#', $banner['image'], $board))
	$banner['board'] = $board[1];

if(isset($_GET['json']))
	echo json_encode($banner);
else
	header("Location: " . $domain . $banner['image']);
