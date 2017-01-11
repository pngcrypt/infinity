<?php
/*
 * Secrets for configuration
 *
 * Included from instance-config.php.
 *
 * Copy this file to secrets.php and edit.
 */

Vi::$config['db']['server'] = 'localhost';
Vi::$config['db']['database'] = '8chan';
//Vi::$config['db']['prefix'] = '';
Vi::$config['db']['user'] = 'eightchan-user';
Vi::$config['db']['password'] = 'mysecretpassword';

// Consider generating these from the following command.
// $ cat /proc/sys/kernel/random/uuid
Vi::$config['secure_trip_salt'] = 'generate-a-uuid';
Vi::$config['cookies']['salt'] = 'generate-a-uuid';
