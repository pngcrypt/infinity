#!/usr/bin/php
<?php
function Usage($err) {
	if($err !== "")
		echo "$err\n\n";
	echo "Usage: emoji-create.php [-s N] [-l N] [-e]\n\n";
	echo "Where:\n";
	echo "    -s N - size of icon (default: 16; minimum: 4)\n";
	echo "    -l N - limit of unicode-words (default: 0 == unlimited)\n";
	echo "    -e - extract each icon to png file\n";
	die;
}

$opts = getopt('hs:l:e', ['help']);
if(isset($opts['h']) || isset($opts['help']))
	Usage();

if(!extension_loaded('imagick'))
	die('IMagick needed.');

$width = $height = 16;
if(isset($opts['s']))
	$height = $width = $opts['s']|0;
if($width < 4)
	Usage("Bad width or height\n");

$limit = isset($opts['l']) ? abs($opts['l']|0) : 0;
$extract = isset($opts['e']);

$svg_sprites = 'tools/emoji/svg/emojione.sprites.svg';
$dir_out = 'tools/emoji';
$fn_png = "emoji.{$width}x{$height}.png";
$fn_css = "emoji.{$width}x{$height}.css";
$dir_out_png = "png-{$width}x{$height}";

try {
	$sprites = simplexml_load_file($svg_sprites);
}
catch (Exception $e) {
	$sprites = false;
}
if($sprites === false)
	die("SVG load error: $svg_sprites");

$count = count($sprites->symbol);
if(!$count)
	die("SVG has not sprites: $svg_sprites");

if($limit) {
	// remove items wich code have more than $limit unicode-words
	for($i=0; $i<$count; $i++) {
		$sym = $sprites->symbol[$i];
		if(isset($sym['id']) && substr_count($sym['id'], '-') > $limit) {
			unset($sprites->symbol[$i]);
		}
	}
}
$count = $sprites->symbol->count();

$defs = $sprites->defs->asXML();
$icons_pre_line = round(sqrt($count));
$width_image = ($width) * $icons_pre_line;
$height_image = ceil($count / $icons_pre_line) * ($height);

if($extract)
	@mkdir("$dir_out/$dir_out_png", 0777, true);

echo "Icon size: $width x $height px\n";
if($limit)
	echo "Limit: $limit-word unicodes\n";
echo "Sprite-file: $svg_sprites\n";
echo "Found: $count items\n\n";
if($extract)
	echo "Exctrating to $dir_out/$dir_out_png...\n";
else
	echo "Creating PNG: $width_image x $height_image px ($icons_pre_line icons per line)...\n";

$transparent = new ImagickPixel('transparent');
$im_svg = new Imagick();
$im_svg->setBackgroundColor($transparent);	

if(!$extract) {
	$im_png = new Imagick();
	$im_png->newImage($width_image, $height_image, $transparent);
	$im_png->setImageFormat('png32'); 
}

$css = ".emoji {
	display:inline-block;
	width: {$width}px;
	height: {$height}px;
	font-size:inherit;
	font-style: normal;
	text-indent: -9999em;	
	line-height:normal;
	vertical-align:middle;
	border: 0;
	margin: 2px;
	padding: 0;
	background-repeat: no-repeat;"
	. (!$extract ? "background-image: url(\"$fn_png\");" : "")
. "\n}\n";

$html = "<html>\n<head><meta charset='utf-8'><link rel='stylesheet' href='$fn_css'></head>\n<body>\n";

$y = $x = 0;
$time = time();
for($i=1; $i <= $count; $i++) {
	$sym = $sprites->symbol[$i-1];
	$id = $sym["id"];
	$sym["width"] = $width;
	$sym["height"] = $height;
	$svg = $sym->asXML();
	$svg = preg_replace('#(</?s)ymbol#is', '$1vg', $sym->asXML()); // <symbol> --> <svg>
	$svg = preg_replace('#([^>]+>)(.+)#is', '$1'.$defs.'$2', $svg); // insert pre-defs
	$svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . $svg;

	$im_svg->readImageBlob($svg);  
	$im_svg->setImageFormat("png32");

	$h = $y * $height;
	$w = $x * $width;
	if($extract) {
		$css .= ".$id{background-image: url(\"$dir_out_png/$id.png\")}\n";
		$im_svg->writeImage("$dir_out/$dir_out_png/$id.png");
	}
	else {
		$css .= ".$id{background-position: " . ($x > 0 ? "-{$w}px" : '0') . ' ' . ($y > 0 ? "-{$h}px" : '0') . "}\n";
		$im_png->compositeImage($im_svg, Imagick::COMPOSITE_OVER, $w, $h);
	}

	// test html -->
	$uc = "";
	foreach(explode('-', substr($id, strpos($id, '-')+1)) as $v) {
		$uc .= '&#x'.$v.';'; // make unicode
	}
	$html .= "<i class=\"emoji $id\">$uc</i>";
	// <--

	$x++;
	if($x >= $icons_pre_line) {
		$y++;
	 	$x = 0;
	 	$html .= "<br>\n";
	}
	if($i == $count || time() - $time >= 3) {
		echo "$i of $count...\n";
		$time = time();
	}
}
$html .= "</body>\n</html>\n";
file_put_contents("$dir_out/$fn_css", $css);
file_put_contents("$dir_out/test.{$width}x{$height}.html", $html);

if(!$extract) {
	echo "Saving to $dir_out/$fn_png...\n";
	$im_png->writeImage("$dir_out/$fn_png");
	$im_png->clear();
	$im_png->destroy();
}
$im_svg->clear();
$im_svg->destroy();

echo "Done.\n";
