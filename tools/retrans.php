<?php

$rewrite = false;
$logfile = false;

function Usage() {
	echo "\nConvert {% trans %}...{% endtrans %} strings to {% trans('...') %}\n";
	echo "\nUSAGE: retrans [-r] [-w] file [file2 [...]]\n\n";
	echo "file - name or file mask\n";
	echo "-r - recursive search\n";
	echo "-w - overwrite files (if not defined only echo will be used)\n";
	die();
}

function writelog($str) {
	file_put_contents("retrans.log", $str."\n", FILE_APPEND);
}

function glob_recursive($pattern, $recursive = true, $flag = GLOB_NOSORT)
{
	$files = glob($pattern, $flag);

	if($recursive) {
		foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
			$files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $recursive, $flag));
	}
	return $files;
}


function make_string($m) {
	global $rep_count, $rewrite, $ext_vars, $logfile;

	$s = $m[1];

	if(strpos($s, '{%') !== FALSE) // проверка доп. тэгов в строке - если есть, то не меняем
		$s = $m[0];
	else {
		$ext_vars = [];
		if(strpos($s, '{{') !== FALSE) {
			// в строке есть переменные, извлекаем, заменяем их на %s
			$s = preg_replace_callback('/{{\s*([\S]+)\s*}}/U', function($m) {
				global $ext_vars;

				$ext_vars[] = $m[1];
				return "%s";
			}, $s);

		}

		// выбор типа кавычек
		if(strpos($s, '"') === FALSE)
			$s = '"'.$s.'"';
		else if(strpos($s, "'") === FALSE)
			$s = "'".$s."'";
		else
			$s = '"'.str_replace('"', '\\"', $s).'"'; // если в строке оба типа кавычек, эскейпим

		$rep_count++;
		if(count($ext_vars))
			$s = "{{ $s|trans|format(" . implode(", ", $ext_vars) . ") }}"; // есть переменные, преобразуем в {{ "text"|trans|format(vars) }}
		else
			$s = "{% trans($s) %}";
	}

	if(!$rewrite)
		echo $s . "\n";
	if($logfile)
		writelog($s);

	return $s;
}

function convertTrans($fin) {
	global $rep_count, $rewrite;

	$buf = @file_get_contents($fin);
	if($buf === FALSE) {
		echo "READ ERROR";
		return FASLE;
	}

	$rep_count = 0;
	$buf = preg_replace_callback('/{%\s*trans\s+[\'"](.+)[\'"] %}/U', 'make_string', $buf);
	$buf = preg_replace_callback('/{%\s*trans\s*%}(.+){%\s*endtrans\s*%}/U', 'make_string', $buf);

	if($rewrite)
		file_put_contents($fin, $buf);
	return $rep_count;
}

$oi = null;
$opts = getopt('rwl', [], $oi);
$args = array_slice($argv, $oi);
if(!count($args))
	Usage();

$recursive = isset($opts['r']);
$rewrite = isset($opts['w']);
$logfile = isset($opts['l']);

echo "Converting...\n";

foreach($args as $fm) {
	$files = glob_recursive($fm, $recursive);
	foreach($files as $f) {
		if(is_dir($f))
			continue;
		echo "$f... ".($rewrite ? "" : "\n");
		if($logfile)
			writelog($f."...");
		$cnt = convertTrans($f);
		if($cnt !== FALSE)
			echo "$cnt item(s)";
		echo "\n".($rewrite ? "" : "\n");
	}
}
echo "Done.\n";
?>