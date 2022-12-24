<?php

namespace Garradin\Users;

use Garradin\Config;
use Garradin\DB;
use Garradin\Log;
use Garradin\Utils;
use Garradin\Plugin;
use Garradin\UserException;
use Garradin\ValidationException;

use Garradin\Users\Users;
use Garradin\Email\Templates as EmailsTemplates;
use Garradin\Files\Files;

use Garradin\Entities\Users\Category;
use Garradin\Entities\Users\User;

use const Garradin\{
	SECRET_KEY,
	WWW_URL,
	ADMIN_URL,
	LOCAL_LOGIN
};

use KD2\Security;
use KD2\Security_OTP;
use KD2\Graphics\QRCode;
use KD2\HTTP;

class Session extends \KD2\UserSession
{
	const SECTION_WEB = 'web';
	const SECTION_DOCUMENTS = 'documents';
	const SECTION_USERS = 'users';
	const SECTION_ACCOUNTING = 'accounting';
	const SECTION_CONNECT = 'connect';
	const SECTION_CONFIG = 'config';
	const SECTION_SUBSCRIBE = 'subscribe';

	const ACCESS_NONE = 0;
	const ACCESS_READ = 1;
	const ACCESS_WRITE = 2;
	const ACCESS_ADMIN = 9;

	// Personalisation de la config de UserSession
	protected $cookie_name = 'pko';
	protected $remember_me_cookie_name = 'pkop';
	protected $remember_me_expiry = '+3 months';

	protected ?User $_user;
	protected ?array $_permissions;

	static protected $_instance = null;

	static public function getInstance()
	{
		// Use of static is important for Files\WebDAV\Session
		return static::$_instance ?: static::$_instance = new static;
	}

	public function __clone()
	{
		throw new \LogicException('Cannot clone');
	}

	public function __construct()
	{
		if (static::$_instance !== null) {
			throw new \LogicException('Wrong call, use getInstance');
		}

		$url = parse_url(ADMIN_URL);

		parent::__construct(DB::getInstance(), [
			'cookie_domain' => $url['host'],
			'cookie_path'   => preg_replace('!/admin/$!', '/', $url['path']),
			'cookie_secure' => HTTP::getScheme() == 'https' ? true : false,
		]);
	}

	public function isPasswordCompromised($password)
	{
		if (!isset($this->http)) {
			$this->http = new \KD2\HTTP;
		}

		// Vérifier s'il n'y a pas un plugin qui gère déjà cet aspect
		// notamment en installation mutualisée c'est plus efficace
		$return = ['is_compromised' => null];

		if (Plugin::fireSignal('password.check', ['password' => $password], $return) && isset($return['is_compromised'])) {
			return (bool) $return['is_compromised'];
		}

		return parent::isPasswordCompromised($password);
	}

	protected function getUserForLogin($login)
	{
		$id_field = DynamicFields::getLoginField();

		// Ne renvoie un membre que si celui-ci a le droit de se connecter
		$query = 'SELECT u.id, u.%1$s AS login, u.password, u.otp_secret
			FROM users AS u
			INNER JOIN users_categories AS c ON c.id = u.id_category
			WHERE u.%1$s = ? COLLATE NOCASE AND c.perm_connect >= %2$d
			LIMIT 1;';

		$query = sprintf($query, $id_field, self::ACCESS_READ);

		return $this->db->first($query, $login);
	}

	protected function getUserDataForSession($id)
	{
		$this->_user = Users::get($id);

		if (!$this->_user) {
			return null;
		}

		return $id;
	}

	protected function storeRememberMeSelector($selector, $hash, $expiry, $user_id)
	{
		$selector = $this->cookie_name . '_' . $selector;
		return $this->db->insert('users_sessions', [
			'selector'  => $selector,
			'hash'      => $hash,
			'expiry'    => $expiry,
			'id_user'   => $user_id,
		]);
	}

	protected function expireRememberMeSelectors()
	{
		return $this->db->delete('users_sessions', $this->db->where('expiry', '<', time()));
	}

	protected function getRememberMeSelector($selector)
	{
		$selector = $this->cookie_name . '_' . $selector;
		return $this->db->first('SELECT REPLACE(selector, ?, \'\') AS selector, hash,
			s.id_user AS user_id, u.password AS user_password, expiry
			FROM users_sessions AS s
			LEFT JOIN users AS u ON u.id = s.id_user
			WHERE s.selector = ? LIMIT 1;', $this->cookie_name . '_', $selector);
	}

	protected function deleteRememberMeSelector($selector)
	{
		$selector = $this->cookie_name . '_' . $selector;
		return $this->db->delete('users_sessions', $this->db->where('selector', $selector));
	}

	protected function deleteAllRememberMeSelectors($user_id)
	{
		return $this->db->delete('users_sessions', $this->db->where('id_user', $user_id));
	}

	public function isLogged(bool $disable_local_login = false)
	{
		$logged = parent::isLogged();

		// Ajout de la gestion de LOCAL_LOGIN
		if (!$disable_local_login && LOCAL_LOGIN) {
			$logged = $this->forceLogin(LOCAL_LOGIN);
		}

		return $logged;
	}

	public function forceLogin($login)
	{
		// Force login with a static user, that is not in the local database
		// this is useful for using a SSO like LDAP for example
		if (is_array($login)) {
			$this->_user = (new User)->import($login['user'] ?? []);

			if (isset($login['user']['_name'])) {
				$name = DynamicFields::getFirstNameField();
				$this->_user->$name = $login['user']['_name'];
			}

			$this->_permissions = [];

			foreach (Category::PERMISSIONS as $perm => $data) {
				$this->_permissions[$perm] = $login['permissions'][$perm] ?? self::ACCESS_NONE;
			}

			return true;
		}

		// On va chercher le premier membre avec le droit de gérer la config
		if (-1 === $login) {
			$login = $this->db->firstColumn('SELECT id FROM users
				WHERE id_category IN (SELECT id FROM users_categories WHERE perm_config = ?)
				LIMIT 1', self::ACCESS_ADMIN);
		}

		$logged = parent::isLogged();

		// Only login if required
		if ($login > 0 && (!$logged || (self::getUserId() != $login))) {
			return $this->create($login);
		}

		return $logged;
	}

	public function login($login, $password, $remember_me = false)
	{
		$success = parent::login($login, $password, $remember_me);
		$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 150) ?: null;

		if (true === $success) {
			Log::add(Log::LOGIN_SUCCESS, compact('user_agent'));

			// Mettre à jour la date de connexion
			$this->db->preparedQuery('UPDATE users SET date_login = datetime() WHERE id = ?;', [$this->getUser()->id]);
		}
		elseif (!$success && ($user = $this->getUserForLogin($login))) {
			Log::add(Log::LOGIN_FAIL, compact('user_agent'), $user->id);
		}
		elseif (!$success) {
			Log::add(Log::LOGIN_FAIL, compact('user_agent'));
		}
		// Last case: $success === self::REQUIRE_OTP

		Plugin::fireSignal('user.login', compact('login', 'password', 'remember_me', 'success'));

		// Clean up logs
		Log::clean();

		// Populate user object, even for OTP, as we can only create private key using password here
		if ($success === self::REQUIRE_OTP) {
			$this->_user = Users::get($_SESSION['userSessionRequireOTP']->user->id);
		}

		// Generate user public/private key pair using password if required
		if ($success && ($user = $this->user())) {
			if (!$user->encryption) {
				$user->generateKeyPair($password, true);
			}
		}

		return $success;
	}

	public function loginOTP(string $code): bool
	{
		$this->start();
		$user_id = $_SESSION['userSessionRequireOTP']->user->id ?? null;
		$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 150) ?: null;
		$details = compact('user_agent') + ['otp' => true];

		$success = parent::loginOTP($code);

		if ($success) {
			Log::add(Log::LOGIN_SUCCESS, $details, $user_id);

			// Mettre à jour la date de connexion
			$this->db->preparedQuery('UPDATE users SET date_login = datetime() WHERE id = ?;', [$user_id]);
		}
		else {
			Log::add(Log::LOGIN_FAIL, $details, $user_id);
		}

		Plugin::fireSignal('user.login.otp', compact('success', 'user_id'));

		return $success;
	}

	public function logout(bool $all = false)
	{
		$this->_user = null;
		$this->_permissions = null;
		$this->_files_permissions = null;

		return parent::logout();
	}

	public function recoverPasswordSend(string $id): void
	{
		$user = $this->fetchUserForPasswordRecovery($id);

		if (!$user) {
			throw new UserException('Aucun membre trouvé avec cette adresse e-mail, ou le membre trouvé n\'a pas le droit de se connecter.');
		}

		if ($user->perm_connect == self::ACCESS_NONE) {
			throw new UserException('Ce membre n\'a pas le droit de se connecter.');
		}

		$email = DynamicFields::getFirstEmailField();

		if (!trim($user->$email)) {
			throw new UserException('Ce membre n\'a pas d\'adresse e-mail renseignée dans son profil.');
		}

		$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 150) ?: null;
		Log::add(Log::LOGIN_RECOVER, compact('user_agent'), $user->id);

		$query = $this->makePasswordRecoveryQuery($user);

		$url = ADMIN_URL . 'password.php?c=' . $query;

		EmailsTemplates::passwordRecovery($user->$email, $url, $user->pgp_key);
	}

	protected function fetchUserForPasswordRecovery(string $id): ?\stdClass
	{
		$db = DB::getInstance();

		$id_field = DynamicFields::getLoginField();
		$email_field = DynamicFields::getFirstEmailField();

		// Fetch user, must have an email
		$sql = sprintf('SELECT u.id, u.%s AS email, u.password, u.pgp_key, c.perm_connect
			FROM users u
			INNER JOIN users_categories c ON c.id = u.id_category
			WHERE u.%s = ? COLLATE NOCASE
				AND u.%1$s IS NOT NULL
			LIMIT 1;',
			$db->quoteIdentifier($email_field),
			$db->quoteIdentifier($id_field));

		return $db->first($sql, trim($id)) ?: null;
	}

	protected function makePasswordRecoveryHash(\stdClass $user, ?int $expire = null): string
	{
		// valide pour 1 heure minimum
		$expire = $expire ?? ceil((time() - strtotime('2017-01-01')) / 3600) + 1;

		$hash = hash_hmac('sha256', $user->email . $user->id . $user->password . $expire, SECRET_KEY, true);
		$hash = substr(Security::base64_encode_url_safe($hash), 0, 16);
		return $hash;
	}

	protected function makePasswordRecoveryQuery(\stdClass $user): string
	{
		$expire = ceil((time() - strtotime('2017-01-01')) / 3600) + 1;
		$hash = $this->makePasswordRecoveryHash($user, $expire);
		$id = base_convert($user->id, 10, 36);
		$expire = base_convert($expire, 10, 36);
		return sprintf('%s.%s.%s', $id, $expire, $hash);
	}

	/**
	 * Check that the supplied query is valid, if so, return the user information
	 * @param  string $query User-supplied query
	 */
	public function checkRecoveryPasswordQuery(string $query): ?\stdClass
	{
		if (substr_count($query, '.') !== 2) {
			return null;
		}

		list($id, $expire, $email_hash) = explode('.', $query);

		$id = base_convert($id, 36, 10);
		$expire = base_convert($expire, 36, 10);

		$expire_timestamp = ($expire * 3600) + strtotime('2017-01-01');

		// Check that the query has not expired yet
		if (time() / 3600 > $expire_timestamp) {
			return null;
		}

		// Fetch user info
		$user = $this->fetchUserForPasswordRecovery($id);

		if (!$user) {
			return null;
		}

		// Check hash using secret data from the user
		$hash = $this->makePasswordRecoveryHash($user, $expire);

		if (!hash_equals($hash, $email_hash)) {
			return null;
		}

		return $user;
	}

	public function recoverPasswordChange(string $query, string $password, string $password_confirmed)
	{
		$user = $this->checkRecoveryPasswordQuery($query);

		if (null === $user) {
			throw new UserException('Le code permettant de changer le mot de passe a expiré. Merci de bien vouloir recommencer la procédure.');
		}

		$ue = Users::get($user->id);
		$ue->importSecurityForm(false, compact('password', 'password_confirmed'));
		$ue->save();
		EmailsTemplates::passwordChanged($ue);
	}

	public function user(): ?User
	{
		return $this->getUser();
	}

	static public function getPreference(string $key)
	{
		return self::getLoggedUser()->getPreference($key);
	}

	static public function getLoggedUser(): ?User
	{
		$s = self::getInstance();

		if (!$s->isLogged()) {
			return null;
		}

		return $s->getUser();
	}

	public function getUser()
	{
		if (isset($this->_user)) {
			return $this->_user;
		}

		if (!$this->isLogged())
		{
			throw new \LogicException('User is not logged in.');
		}

		$this->_user = Users::get($this->user);
		$this->_permissions = null;
		$this->_files_permissions = null;
		return $this->_user;
	}

	static public function getUserId(): ?int
	{
		$i = self::getInstance();

		if (!$i->isLogged()) {
			return null;
		}

		return $i->user;
	}

	public function canAccess(string $section, int $permission): bool
	{
		if (!$this->isLogged()) {
			return false;
		}

		if (!isset($this->_permissions)) {
			$this->_permissions = $this->user()->category()->getPermissions();
		}

		$perm = $this->_permissions[$section];

		return ($perm >= $permission);
	}

	public function requireAccess(string $section, int $permission): void
	{
		if (!$this->canAccess($section, $permission)) {
			throw new UserException('Vous n\'avez pas le droit d\'accéder à cette page.');
		}
	}

	public function checkFilePermission(string $path, string $permission): bool
	{
		$path_level = preg_replace('!/[^/]+!', '/', $path);
		$path = trim($path, '/');

		if (!isset($this->_files_permissions)) {
			$this->_files_permissions = Files::buildUserPermissions($this);
		}

		foreach ($this->_files_permissions as $context => $permissions) {
			if (!array_key_exists($permission, $permissions)) {
				throw new \InvalidArgumentException(sprintf('Unknown permission "%s" in context "%s"', $permission, $context));
			}

			if ($context == $path
				|| 0 === strpos($path, $context)
				|| 0 === strpos($path_level, $context)) {
				return $permissions[$permission];
			}
		}

		throw new \InvalidArgumentException(sprintf('Unknown context: %s', $path));
	}

	public function getFilePermissions(string $path): ?array
	{
		if (!isset($this->_files_permissions)) {
			$this->_files_permissions = Files::buildUserPermissions($this);
		}

		return $this->_files_permissions[$path] ?? null;
	}

	public function requireFilePermission(string $context, string $permission)
	{
		if (!$this->checkFilePermission($context, $permission)) {
			throw new UserException('Vous n\'avez pas le droit d\'effectuer cette action.');
		}
	}

	// Ici checkOTP utilise NTP en second recours
	public function checkOTP($secret, $code)
	{
		if (Security_OTP::TOTP($secret, $code))
		{
			return true;
		}

		// Vérifier encore, mais avec le temps NTP
		// au cas où l'horloge du serveur n'est pas à l'heure
		if (\Garradin\NTP_SERVER
			&& ($time = Security_OTP::getTimeFromNTP(\Garradin\NTP_SERVER))
			&& Security_OTP::TOTP($secret, $code, $time))
		{
			return true;
		}

		return false;
	}

	public function getNewOTPSecret()
	{
		$config = Config::getInstance();
		$out = [];
		$out['secret'] = Security_OTP::getRandomSecret();
		$out['secret_display'] = implode(' ', str_split($out['secret'], 4));

		$icon = $config->fileURL('icon');
		$out['url'] = Security_OTP::getOTPAuthURL($config->org_name, $out['secret'], 'totp', $icon);

		$qrcode = new QRCode($out['url']);
		$out['qrcode'] = 'data:image/svg+xml;base64,' . base64_encode($qrcode->toSVG());

		return $out;
	}

	public function countActiveSessions(): int
	{
		$selector = $this->getRememberMeCookie()->selector ?? null;
		$user = $this->getUser();
		return DB::getInstance()->count('users_sessions', 'id_user = ? AND selector != ?', $user->id(), $this->cookie_name . '_' . $selector) + 1;
	}

	public function isAdmin(): bool
	{
		return $this->canAccess(self::SECTION_CONNECT, self::ACCESS_READ)
			&& $this->canAccess(self::SECTION_CONFIG, self::ACCESS_ADMIN);
	}
}
