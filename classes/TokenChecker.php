<?php

require_once('./Services/Link/classes/class.ilLink.php');
require_once('./Services/Authentication/classes/class.ilSession.php');
require_once('./Services/User/classes/class.ilObjUser.php');
require_once('./Services/Utilities/classes/class.ilUtil.php');
require_once(__DIR__.'/entity/UserToken.php');

/**
 * Class TokenChecker handles a specific link to log in
 * the user with a token and redirect him to the wanted page.
 *
 * @author  Nicolas Märchy <nm@studer-raimann.ch>
 * @version 1.0.0
 *
 */
class TokenChecker {

	static $self_call;

	private $userId;
	private $refId;
	private $token;

	/**
	 * @return boolean true if this handler needs to handle the request, otherwise false
	 */
	public function isHandler() {

		if ($_SERVER['SCRIPT_NAME'] != "/goto.php") {
			return false;
		}

		$matches = array();

		if (preg_match("/^ilias_app_auth\|(\d+)\|(\d+)\|(.+)$/", $_GET['target'], $matches) === 0) {
			return false;
		}

		$this->userId = $matches[1];
		$this->refId = $matches[2];
		$this->token = $matches[3];

		return true;
	}


	/**
	 * Checks the request to an valid token,
	 * logs in and redirects the user to the wanted page.
	 *
	 * If the user is already logged in, the user will be redirected.
	 *
	 * The token will always be deleted.
	 */
	public function execute() {

		global $DIC;
		/**
		 * @var $ilAuthSession ilAuthSession
		 */
		$ilAuthSession = $DIC['ilAuthSession'];

		if ($ilAuthSession->isAuthenticated()) {
			$this->deleteToken();
			$this->redirect();
		} else {

			if ($this->isTokenValid()) {

				// log in user
				$ilAuthSession->regenerateId();
				$ilAuthSession->setUserId($this->userId);
				$ilAuthSession->setAuthenticated(true, $this->userId);
			}

			$this->deleteToken();
			$this->redirect();
		}
	}


	/**
	 * @return bool true if the token is valid, otherwise false
	 */
	private function isTokenValid() {

		/**
		 * @var $token UserToken
		 */
		$token = UserToken::find($this->userId);

		if ($token == NULL) {
			return false;
		}

		if ($token->getToken() !== $this->token) {
			return false;
		}

		$now = time();
		$expires = strtotime($token->getExpires());

		return $now < $expires;
	}


	/**
	 * Deletes the token if it exists.
	 */
	private function deleteToken() {

		$token = UserToken::find($this->userId);
		if ($token != NULL) {
			$token->delete();
		}
	}


	/**
	 * Redirects the user to the wanted page.
	 * The wanted page is determined by the ref_id.
	 *
	 * This methods redirects only once per request to avoid recursive calls.
	 */
	private function redirect() {

		if (!self::$self_call) {
			self::$self_call = true;
			$link = ilLink::_getLink($this->refId);
			ilUtil::redirect($link);
		}
	}
}