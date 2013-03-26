<?php
namespace SebRenauld;
use Exception;
use stdClass;

class SVN {
	private $username;
	private $password;
	private $old_username;
	private $old_password;
	private $path;
	private $uri;
	private $checked = false;
	public function __construct($path, $username=false, $password=false) {
		$this->username = $username;
		$this->password = $password;
		if (!is_dir($path)) {
			mkdir($path);
		}
		$this->path = $path.((substr($path,-1,1) == "/") ? "" : "/");
	}
	public function setRepository($URI) {
		if (!$this->_isRepository($this->path)) {
			$this->_checkout($URI);
		}
	}
	public function makePath($path) {
		if (substr($path,0,1) == "/") return $this->path.substr($path,1);
		else return $this->path.$path;
	}
	public function update($paths=array(),$revNo=SVN_REVISION_HEAD) {
		$this->_set_error_handler();
		$this->_set_auth();
		foreach ($paths as $v) {
			try {
				svn_update(realpath($this->makePath($v)),$revNo,true);
			} catch (SVNException $e) {
				die($e->getMessage());
			}
		}
		$this->_restore_error_handler();
		$this->_reset_auth();
	}
	public function log($path="",$rev=SVN_REVISION_HEAD,$to=SVN_REVISION_INITIAL) {
		$t = $this->info($path);
		$this->_set_error_handler();
		$this->_set_auth();
		$r = svn_log($t[0]['url'],$rev, $to);
		return $r;
		//die(print_r($r,true));
	}
	public function add_file($path) {
		$this->_set_error_handler();
		$this->_set_auth();
		try {
			$v = $this->makePath($path);
			if (file_exists($v)) {
				svn_add($v,false);
				return true;
			}
		} catch (SebRenauld\SVNException $e) {
		}
		$this->_restore_error_handler();
		$this->_reset_auth();
		return false;
	}
	public function delete_file($path) {
		$this->_set_error_handler();
		$this->_set_auth();
		try {
			$v = $this->makePath($path);
			if (file_exists($v)) {
				svn_delete($v);
				return true;
			}
		} catch (SebRenauld\SVNException $e) {
		}
		$this->_restore_error_handler();
		$this->_reset_auth();
		return false;
	}
	public function info($path) {
		$this->_set_error_handler();
		$this->_set_auth();
		$t = svn_status($this->makePath($path), SVN_NON_RECURSIVE|SVN_ALL);
		$this->_restore_error_handler();
		$this->_reset_auth();
		return $t;
	}
	public function update_all() {
		$this->update("");
	}
	public function commit($message,$paths) {
		$this->_set_error_handler();
		$this->_set_auth();
		if (!is_array($paths)) {
			throw new SVNException("No array was supplied","PathDoesNotExist",-3);
		}
		$r = array();
		foreach ($paths as $v) {
			$v = $this->makePath($v);
			if (!(is_dir($v) || is_file($v))) {
				// continue;
				// throw new SVNException($v." does not exist","PathDoesNotExist",-3);
			}
			$t = svn_status($v);
			if ($t[0]['text_status'] == 2) {
				svn_add($v);
			}
			$r[] = $v;
		}
		try {
			svn_commit($message,$r);
			die();
		} catch (SVNException $e) {
			if ($e->SVNErrorType() == "FileAlreadyExists") {
				$this->_restore_error_handler();
				$this->_reset_auth();
				throw $e;
			}
			throw $e;
		}
	}
	private function _set_auth() {
		// Save old stuff
		$this->old_username = svn_auth_get_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME);
		$this->old_password = svn_auth_get_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD);
		svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, $this->username);
        svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD,             $this->password);
        svn_auth_set_parameter(PHP_SVN_AUTH_PARAM_IGNORE_SSL_VERIFY_ERRORS, true);
        svn_auth_set_parameter(SVN_AUTH_PARAM_NON_INTERACTIVE,              true);
        svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE,                true);
	}
	private function _reset_auth() {
		if (isset($this->old_username)) {
			svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, $this->old_username);
			unset($this->old_username);
        }
		if (isset($this->old_password)) {
			svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, $this->old_password);
			unset($this->old_password);
		}
	}
	private function _checkout($r) {
		$this->_set_error_handler();
		try {
			$this->_set_auth();
			$t = svn_checkout($r, $this->path);
		} catch (SVNException $e) {
			$this->_reset_auth();
			$this->_restore_error_handler();
			switch ($e->SVNErrorType()) {
				case "OperationNotPermitted":
					throw $e;
					break;
				case "AuthenticationFailure":
					throw $e;
					break;
			}
		}
		$this->_reset_auth();
		$this->_restore_error_handler();
	}
	private function _isRepository($path) {
		$this->_set_error_handler();
		try {
			$this->_set_auth();
			$r = svn_status($path,  SVN_NON_RECURSIVE|SVN_ALL);
		}
		catch (SVNException $e) {
			if ($e->SVNErrorType() == "DirectoryNotWorkingCopy") {
				$r = false;
			}
		}
		$this->_reset_auth();
		$this->_restore_error_handler();
		
		if ($r === false) return false;
		else return true;
	}
	public function modifications($path) {
		$toSend = array(SVN_WC_STATUS_UNVERSIONED, SVN_WC_STATUS_ADDED, SVN_WC_STATUS_MISSING, SVN_WC_STATUS_DELETED, SVN_WC_STATUS_MODIFIED);
		$this->_set_error_handler();
		$r = array();
		try {
			$this->_set_auth();
			$rt = svn_status($this->makePath($path), SVN_ALL);
			foreach ($rt as $v) {
				if (in_array($v['text_status'],$toSend)) {
					$v['path'] = str_replace($this->path,"",$v['path']);
					$vt = new stdClass();
					$vt->path = $v['path'];
					$vt->modified = ($v['text_status'] == SVN_WC_STATUS_MODIFIED);
					$vt->added = ($v['text_status'] == SVN_WC_STATUS_UNVERSIONED || $v['text_status'] == SVN_WC_STATUS_ADDED);
					$vt->deleted = ($v['text_status'] == SVN_WC_STATUS_DELETED || $v['text_status'] == SVN_WC_STATUS_MISSING);
					$r[] = $vt;
				}
			}
		}
		catch (SVNException $e) {
			if ($e->SVNErrorType() == "DirectoryNotWorkingCopy") {
				$r = array();
			}
		}
		$this->_reset_auth();
		$this->_restore_error_handler();
		return $r;
	}
	private function _set_error_handler() {
		set_error_handler(array("SebRenauld\\SVNException","understand_error"), E_WARNING);
	}
	private function _restore_error_handler() {
		restore_error_handler();
	}
}
class SVNException extends Exception {
	private $failedFCT;
	private $errorType;
	private static $errorCodes = array(
		2 => array(
			"code" => "DirectoryDoesNotExist",
			"prio" => 4),
		175005 => array(
			"code" => "FileAlreadyExists",
			"prio" => 4),
		155007 => array(
			"code" => "DirectoryNotWorkingCopy",
			"prio" => 3),
		155002 => array(
			"code" => "DirectoryAlreadyVersioned",
			"prio" => 3),
		200009 => array(
			"code" => "OperationFailure",
			"prio" => 4),
		175002 => array(
			"code" => "OperationNotPermitted",
			"prio" => 4),
		170001 => array(
			"code" => "AuthenticationFailure",
			"prio" => 4));
	function __construct($message,$error,$errCode=-1) {
		$this->errorType = $error;
		return parent::__construct($message,$errCode);
	}
	public function SVNErrorType() { return $this->errorType; }
	public static function understand_error($errno, $errstr) {
		$errCode = "UnknownError";
		$errPrio = 0;
		$errStr = "";
		$errNum = -1;
		$r = explode("\n",$errstr);
		array_shift($r);
		foreach ($r as $v) {
			if (preg_match("#^([0-9]+) \([^\)]+\) (.+)\$#i",$v,$t)) {
				if (isset(self::$errorCodes[(int)$t[1]]) && self::$errorCodes[(int)$t[1]]['prio'] >= $errPrio) {
					$errCode = self::$errorCodes[(int)$t[1]]['code'];
					$errStr = $t[2];
					$errNum = (int)$t[1];
					$errPrio = self::$errorCodes[(int)$t[1]]['prio'];
				}
			}
		}
		// die($errstr);
		throw new static($errStr,$errCode,$errNum);
		//die($errCode." - ".$errStr);
		// die(print_r(explode("\n",$errstr)));
		// throw new static($errstr,$errno);
		return true;
	}
}
