<?php

class RequestHandler {
	const SUCCESS = 0;
	const ERROR_INVALID_ACTION = 1;
	const ERROR_INVALID_ARGS = 2;
	const ERROR_UNKNOWN = 3;

	private $data;
	private $handlers;

	public function __construct($reqData) {
		$this->data = $reqData;
		$this->handlers = array();
	}

	public function addHandler($action, $callback) {
		if (!array_key_exists($action, $this->handlers))
		{
			$this->handlers[$action] = array();
		}

		array_push($this->handlers[$action], $callback);
	}

	public function run() {
		if (!array_key_exists('action', $this->data))
		{
			return json_encode(array(
				'code' => RequestHandler::ERROR_INVALID_ACTION
			));
		}

		$action = $this->data['action'];
		if (array_key_exists($action, $this->handlers)  && count($this->handlers[$action]) > 0)
		{
			$returnData = array();

			foreach ($this->handlers[$action] as $handler)
			{
				$ret = $handler($this->data);
				if ($ret === false)
				{
					$returnData['code'] = RequestHandler::ERROR_UNKNOWN;
					break;
				}

				if (is_array($ret))
				{
					foreach ($ret as $key => $value)
					{
						$returnData[$key] = $value;
					}
				}
				else
				{
					array_push($returnData, $ret);
				}
			}

			return json_encode($returnData);
		}

		return json_encode(array(
			'code' => RequestHandler::ERROR_INVALID_ACTION
		));
	}
};

class LoginRequest {
	const LOGIN_SUCCESS = 1000;
	const INVALID_USERPASS = 1001;
	const SESSIONKEY_CORRECT = 1002;
	const SESSIONKEY_INVALID = 1003;
};

class RequestHandlerFactory {
	public static function OpenRequest() {
		$requestData = array();

		foreach ($_GET as $key => $value) $requestData[$key] = $value;
		foreach ($_POST as $key => $value) $requestData[$key] = $value;

		return new RequestHandler($requestData);
	}

	public static function OpenDefaultFunctionalityRequest() {
		$handler = RequestHandlerFactory::OpenRequest();

		/////////////// LOGIN \\\\\\\\\\\\\\\\
		$handler->addHandler('login', function($data){
			if (!isset($data['username']) || !isset($data['password']) || !isset($data['universe']))
			{
				return array(
					'code' => RequestHandler::ERROR_INVALID_ARGS
				);
			}

			$auth = AuthFactory::OpenAuth($data['universe']);
			$res = $auth->tryCreateSession($data['username'], $data['password']);
			if (!$res['success'])
			{
				return array(
					'code' => LoginRequest::INVALID_USERPASS
				);
			}

			return array(
				'code' => LoginRequest::LOGIN_SUCCESS,
				'sessionkey' => $res['sessionkey']
			);
		});

		/////////////// MATCHSESSION \\\\\\\\\\\\\\\\
		$handler->addHandler('matchsession', function($data){
			if (!isset($data['universe']) || !isset($data['sessionkey']) || !isset($data['username']))
			{
				return array(
					'code' => RequestHandler::ERROR_INVALID_ARGS
				);
			}

			$auth = AuthFactory::OpenAuth($data['universe']);
			if (!$auth->correctSession($data['username'], $data['sessionkey']))
			{
				return array(
					'code' => LoginRequest::SESSIONKEY_INVALID
				);
			}
			return array(
				'code' => LoginRequest::SESSIONKEY_CORRECT
			);
		});

		/////////////// GETPOLLLIST \\\\\\\\\\\\\\\\
		$handler->addHandler('getpolllist', function($data){
			if (!isset($data['universe']) || !isset($data['username']) || !isset($data['sessionkey']))
			{
				return array(
					'code' => RequestHandler::ERROR_INVALID_ARGS
				);
			}

			$auth = AuthFactory::OpenAuth($data['universe']);

			if (!$auth->correctSession($data['username'], $data['sessionkey']))
			{
				return array(
					'code' => LoginRequest::SESSIONKEY_INVALID
				);
			}

			$user = $auth->getUser($data['username']);

			$polls = array();
			$dir = opendir(ABSPATH.'data/');
			$entry = readdir($dir);

			while ($entry = readdir($dir))
			{
				$pollname = substr($entry, 0, strlen($entry) - 5);

				if (!is_dir($entry) && (array_key_exists($pollname, $user['access']) || $auth->isAdmin($data['username'])) && preg_match('/.*\.poll/', $entry))
				{
					array_push($polls, array(
						'name' => $pollname,
						'text' => VotingSystemFactory::OpenPoll($pollname)->getText()
					));
				}
			}

			return array(
				'code' => RequestHandler::SUCCESS,
				'polls' => $polls
			);
		});

		/////////////// GETPOLL \\\\\\\\\\\\\\\\
		$handler->addHandler('getpoll', function($data){
			if (!isset($data['universe']) || !isset($data['username']) || !isset($data['sessionkey']) || !isset($data['pollname']))
			{
				return array(
					'code' => RequestHandler::ERROR_INVALID_ARGS
				);
			}

			$auth = AuthFactory::OpenAuth($data['universe']);

			if (!$auth->correctSession($data['username'], $data['sessionkey']))
			{
				return array(
					'code' => LoginRequest::SESSIONKEY_INVALID
				);
			}

			$poll = VotingSystemFactory::OpenPoll($data['pollname']);
			$elements = $poll->getElements();
			$elementRet = array();

			if (isset($data['results']) && strtolower($data['results']) == 'true')
			{
				foreach ($elements as $element)
				{
					array_push($elementRet, array(
						'text' => $element->getText(),
						'votecount' => $element->voteCount(),
						'hasvoted' => $element->hasVoted(strtolower($data['username'])),
						'id' => $element->getId()
					));
				}
			}
			else
			{
				foreach ($elements as $element)
				{
					array_push($elementRet, array(
						'text' => $element->getText(),
						'hasvoted' => $element->hasVoted(strtolower($data['username'])),
						'id' => $element->getId()
					));
				}
			}

			return array(
				'code' => RequestHandler::SUCCESS,
				'elements' => $elementRet
			);
		});

		/////////////// VOTE \\\\\\\\\\\\\\\\
		$handler->addHandler('vote', function($data){
			if (!isset($data['universe']) || !isset($data['username']) || !isset($data['sessionkey']) || !isset($data['pollname']) || !isset($data['elementid']))
			{
				return array(
					'code' => RequestHandler::ERROR_INVALID_ARGS
				);
			}

			$auth = AuthFactory::OpenAuth($data['universe']);

			if (!$auth->correctSession($data['username'], $data['sessionkey']))
			{
				return array(
					'code' => LoginRequest::SESSIONKEY_INVALID
				);
			}

			$poll = VotingSystemFactory::OpenPoll($data['pollname']);
			$poll->addVote($data['username'], intval($data['elementid']));

			return array(
				'code' => RequestHandler::SUCCESS
			);
		});

		return $handler;
	}
};
