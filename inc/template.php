<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

function load_twig() {
	require 'lib/Twig/Autoloader.php';
	Twig_Autoloader::register();

	Twig_Autoloader::autoload('Twig_Extensions_Node_Trans');
	Twig_Autoloader::autoload('Twig_Extensions_TokenParser_Trans');
	Twig_Autoloader::autoload('Twig_Extensions_Extension_I18n');
	Twig_Autoloader::autoload('Twig_Extensions_Extension_Tinyboard');

	$loader = new Twig_Loader_Filesystem(Vi::$config['dir']['template']);
	$loader->setPaths(Vi::$config['dir']['template']);
	Vi::$twig = new Twig_Environment($loader, array(
		'autoescape' => false,
		'cache'      => (is_writable('templates') || (is_dir('templates/cache') && is_writable('templates/cache'))) && Vi::$config['twig_cache'] ?
		Vi::$config['dir']['template'] . "/cache" : false,
		'debug'      => Vi::$config['debug'],
	));
	Vi::$twig->addExtension(new Twig_Extensions_Extension_Tinyboard());
	Vi::$twig->addExtension(new Twig_Extensions_Extension_I18n());

	Vi::$twig->addFilter(new Twig_SimpleFilter('hex2bin', 'hex2bin'));
	Vi::$twig->addFilter(new Twig_SimpleFilter('base64_encode', 'base64_encode'));
}

function Element($templateFile, array $options) {
	if (!Vi::$twig) {
		load_twig();
	}

	if (function_exists('create_pm_header') && ((isset($options['mod']) && $options['mod']) || isset($options['__mod'])) && !preg_match('!^mod/!', $templateFile)) {
		$options['pm'] = create_pm_header();
	}

	$options['default_locale'] = Vi::$default_locale;
	$options['current_locale'] = Vi::$current_locale;

	if (isset($options['body']) && Vi::$config['debug']) {
		$_debug = Vi::$debug;

		if (isset(Vi::$debug['start'])) {
			$_debug['time']['total'] = '~' . round((microtime(true) - $_debug['start']) * 1000, 2) . 'ms';
			$_debug['time']['init']  = '~' . round(($_debug['start_debug'] - $_debug['start']) * 1000, 2) . 'ms';
			unset($_debug['start']);
			unset($_debug['start_debug']);
		}
		if (Vi::$config['try_smarter'] && Vi::$build_pages && !empty(Vi::$build_pages)) {
			$_debug['build_pages'] = Vi::$build_pages;
		}

		$_debug['included']           = get_included_files();
		$_debug['memory']             = round(memory_get_usage(true) / (1024 * 1024), 2) . ' MiB';
		$_debug['time']['db_queries'] = '~' . round($_debug['time']['db_queries'] * 1000, 2) . 'ms';
		$_debug['time']['exec']       = '~' . round($_debug['time']['exec'] * 1000, 2) . 'ms';
		$options['body'] .=
		'<h3>Debug</h3><pre style="white-space: pre-wrap;font-size: 10px;">' .
		str_replace("\n", '<br/>', utf8tohtml(print_r($_debug, true))) .
			'</pre>';
	}

	// Read the template file
	if (@file_get_contents(Vi::$config['dir']['template'] . "/${templateFile}")) {
		$body = Vi::$twig->render($templateFile, $options);

		if (Vi::$config['minify_html'] && preg_match('/\.html$/', $templateFile)) {
			$body = trim(preg_replace("/[\t\r\n]/", '', $body));
		}

		return $body;
	} else {
		throw new Exception("Template file '${templateFile}' does not exist or is empty in '" . Vi::$config['dir']['template'] . "'!");
	}
}
