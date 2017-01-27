<?php

/*
 * Absolute path to main directory.
 * */
if (!defined('ABSPATH'))
	define('ABSPATH', dirname(__FILE__) . '/');

require_once(ABSPATH.'inc/VotingSystem.php');
require_once(ABSPATH.'inc/Auth.php');

$auth = AuthFactory::OpenAuth('main');
