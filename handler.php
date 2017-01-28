<?php

/*
 * Absolute path to main directory.
 * */
if (!defined('ABSPATH'))
	define('ABSPATH', dirname(__FILE__) . '/');

require_once(ABSPATH.'inc/VotingSystem.php');
require_once(ABSPATH.'inc/Auth.php');
require_once(ABSPATH.'inc/RequestHandler.php');

$request = RequestHandlerFactory::OpenDefaultFunctionalityRequest();
header('Content-type: application/json');
echo $request->run();
/* try
{
	header('Content-type: application/json');
	echo $request->run();
}
catch (Exception $e)
{
	echo "unknown error";
}
catch (Error $e)
{
	echo "unknown error";
} */
