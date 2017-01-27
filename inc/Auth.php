<?php

class Auth {
	private $dbPath;
	private $usersTable;

	private function saveUsersTable() {
		// wait for writing availability
		while (file_exists($this->dbPath.'.lock'));

		touch($this->dbPath.'.lock');
		$f = fopen($this->dbPath, 'w');

		$contents = gzcompress(serialize($this->usersTable));

		fwrite($f, $contents);
		fclose($f);
		unlink($this->dbPath.'.lock');
	}

	public function __construct($dbFile) {
		if (file_exists($dbFile) && filesize($dbFile) > 0)
		{
			$f = fopen($dbFile, 'r');
			$conts = fread($f, filesize($dbFile));

			try
			{
				$this->usersTable = unserialize(trim(gzuncompress($conts)));
			}
			catch (Exception $e)
			{
				die('Failed parsing user table: ' . $e->getMessage());
			}
		}
		else
		{
			touch($dbFile);
			$this->usersTable = array();
		}

		$this->dbPath = $dbFile;
	}

	public function setUser($username, $password, $isAdmin=false) {
		$salt = uniqid(mt_rand(), true);
		$hashedPass = Auth::hashPassword($username, $password, $salt);
		$username = strtolower($username);

		$this->usersTable[$username] = array(
			'password' => $hashedPass,
			'salt' => $salt,
			'sessionkey' => '',
			'access' => array(),
			'admin' => $isAdmin
		);

		$this->saveUsersTable();
	}

	public function delUser($username) {
		$username = strtolower($username);
		if (array_key_exists($username, $this->usersTable))
		{
			unset($this->usersTable[$username]);
			$this->saveUsersTable();
		}
	}

	public function getUser($username) {
		$username = strtolower($username);
		return $this->usersTable[$username];
	}

	public function setAccess($username, $accessName) {
		$username = strtolower($username);
		if (array_key_exists($username, $this->usersTable))
		{
			$this->usersTable[$username][$accessName] = 1;
			$this->saveUsersTable();
		}
	}

	public function revokeAccess($username, $accessName) {
		$username = strtolower($username);
		if (array_key_exists($username, $this->usersTable))
		{
			unset($this->usersTable[$username][$accessName]);
			$this->saveUsersTable();
		}	
	}

	public function isAdmin($username) {
		$username = strtolower($username);
		if (array_key_exists($username, $this->usersTable))
		{
			return $this->usersTable[$username]['admin'];
		}

		return false;
	}

	public static function hashPassword($username, $password, $salt) {
		return sha1($username.sha1($password.$salt));
	}

	public function tryCreateSession($username, $password) {
		$username = strtolower($username);

		if (!array_key_exists($username, $this->usersTable))
		{
			return array(
				'success' => false,
				'sessionkey' => ''
			);
		}

		$salt = $this->usersTable[$username]['salt'];
		if ($this->hashPassword($username, $password, $salt) === $this->usersTable[$username]['password'])
		{ // login success
			$sessionkey = md5(microtime().rand());
			$this->usersTable[$username]['sessionkey'] = $sessionkey;
			$this->saveUsersTable();
			return array(
				'success' => true,
				'sessionkey' => $sessionkey
			);
		}

		// login failed
		return array(
			'success' => false,
			'sessionkey' => ''
		);
	}

	public function correctSession($username, $sessionkey) {
		$username = strtolower($username);
		if (array_key_exists($username, $this->usersTable))
		{
			return $this->usersTable[$username]['sessionkey'] === $sessionkey;
		}

		return false;
	}
}

class AuthFactory {
	public static function OpenAuth($universeName) {
		return new Auth(ABSPATH.'data/'.$universeName.'.authtable');
	}
};