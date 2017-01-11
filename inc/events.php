<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

function event() {
	$args = func_get_args();

	$event = $args[0];

	$args = array_splice($args, 1);

	if (!isset(Vi::$events[$event])) {
		return false;
	}

	foreach (Vi::$events[$event] as $callback) {
		if (!is_callable($callback)) {
			error('Event handler for ' . $event . ' is not callable!');
		}

		if ($error = call_user_func_array($callback, $args)) {
			return $error;
		}

	}

	return false;
}

function event_handler($event, $callback) {
	if (!isset(Vi::$events[$event])) {
		Vi::$events[$event] = array();
	}

	Vi::$events[$event][] = $callback;
}

function reset_events() {
	Vi::$events = array();
}
