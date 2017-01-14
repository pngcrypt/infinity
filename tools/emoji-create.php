#!/usr/bin/php
<?php

$width = 16;
$height = 16;

$dir_in = 'js/twemoji/16x16';
$dir_out = 'tools/emoji';

@mkdir($dir_out, 0777, true);

$twemojis = glob("$dir_in/*.png");

$count = count($twemojis);
$twemoji_per_line = round(sqrt($count));
$width_image = ($width+1) * $twemoji_per_line;
$height_image = ceil($count / $twemoji_per_line) * ($height+1);

echo "Emoji: $width x $height: $dir_in", PHP_EOL;
echo "Found: $count files", PHP_EOL;
echo "Creating: $width_image x $height_image px ($twemoji_per_line icons per line)...", PHP_EOL;

$png = imagecreatetruecolor($width_image, $height_image);
$transparent = imagecolorallocatealpha($png, 0, 0, 0, 127);
imagefill($png, 0, 0, $transparent);
imagesavealpha($png, TRUE);
imagealphablending($png, FALSE); 

$str = ".twemoji {
	width: {$width}px;
	height: {$height}px;
	background-image: url(\"twemoji.png\");
	background-repeat: no-repeat;
	background-position: 0 0;
	box-sizing: border-box;
	display: inline-block;
}
";

$test = "<html>\n<head><link rel='stylesheet' href='./twemoji.css'></head>\n<body>\n";

$twemojis = array_chunk($twemojis, $twemoji_per_line);
foreach($twemojis as $i=>$twemoji) {
	foreach($twemoji as $j=>$item) {
		$h = $height * $i;
		$w = $width * $j;
		
		if($i > 0) {
			$h += 1 * $i;
		}

		if($j > 0) {
			$w += 1 * $j;
		}

		$twemoji_image = imagecreatefrompng($item);
		imagecopy($png, $twemoji_image, $w, $h, 0, 0, $width, $height); 
		imagedestroy($twemoji_image);
		$uclass = 'U-' . basename(current(explode('.', $item)));
		$str .= '.twemoji.'.$uclass 
			.' {background-position: ' . ($j > 0 ? '-' . $w . 'px' : '0') . ' ' . ($i > 0 ? '-' . $h . 'px' : '0') . ';}' 
			. PHP_EOL;

		$test .= "<span class='twemoji $uclass'></span>";
	}
	$test .= "<br>\n";
}
$test .= "</body>\n</html>\n";

file_put_contents("$dir_out/twemoji.css", $str);
file_put_contents("$dir_out/test.html", $test);
imagepng($png, "$dir_out/twemoji.png", 9);
imagedestroy($png);

echo 'Ok', PHP_EOL;
