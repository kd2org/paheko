<?php

namespace Garradin;

use Garradin\Files\Files;
use Garradin\Entities\Files\File;
use Garradin\Membres\Champs;

use KD2\SMTP;

class Config extends Entity
{
	protected $nom_asso;
	protected $adresse_asso;
	protected $email_asso;
	protected $telephone_asso;
	protected $site_asso;

	protected $monnaie;
	protected $pays;

	protected $champs_membres;
	protected $categorie_membres;

	protected $admin_homepage;

	protected $frequence_sauvegardes;
	protected $nombre_sauvegardes;

	protected $champ_identifiant;
	protected $champ_identite;

	protected $last_chart_change;
	protected $last_version_check;

	protected $couleur1;
	protected $couleur2;

	protected $admin_background;

	protected $desactiver_site;

	protected $_types = [
		'nom_asso'              => 'string',
		'adresse_asso'          => '?string',
		'email_asso'            => 'string',
		'telephone_asso'        => '?string',
		'site_asso'             => '?string',

		'monnaie'               => 'string',
		'pays'                  => 'string',

		'champs_membres'        => Champs::class,

		'categorie_membres'     => 'int',

		'admin_homepage'        => '?Garradin\Entities\Files\File',

		'frequence_sauvegardes' => 'int',
		'nombre_sauvegardes'    => 'int',

		'champ_identifiant'     => 'string',
		'champ_identite'        => 'string',

		'last_chart_change'     => '?int',
		'last_version_check'    => '?string',

		'couleur1'              => '?string',
		'couleur2'              => '?string',
		'admin_background'      => '?Garradin\Entities\Files\File',

		'desactiver_site'       => 'bool',
	];

	protected $_deleted_files = [];

	static protected $_instance = null;

	static public function getInstance()
	{
		return self::$_instance ?: self::$_instance = new self;
	}

	static public function deleteInstance()
	{
		self::$_instance = null;
	}

	public function __clone()
	{
		throw new \LogicException('Cannot clone config');
	}

	protected function __construct()
	{
		parent::__construct();

		$db = DB::getInstance();

		$config = $db->getAssoc('SELECT key, value FROM config ORDER BY key;');

		$default = array_fill_keys(array_keys($this->_types), null);
		$config = array_merge($default, $config);

		$config['champs_membres'] = new Champs($config['champs_membres']);

		foreach ($this->_types as $key => $type) {
			$value = $config[$key];

			if ($type[0] == '?' && $value === null) {
				continue;
			}

			if ($type == File::class || substr($type, 1) == File::class) {
				$config[$key] = Files::get($value);
			}
		}

		$this->load($config);

		$this->champs_membres = new Membres\Champs((string)$this->champs_membres);
	}

	public function set(string $key, $value, bool $loose = false, bool $check_for_changes = true)
	{
		// Append to deleted files queue if it's set to null
		if (isset($this->_types[$key])
			&& substr($this->_types[$key], -strlen(File::class)) == File::class
			&& $value === null
			&& $this->$key !== null) {
			$this->_deleted_files[] = $this->$key;
		}

		parent::set($key, $value, $loose, $check_for_changes);
	}

	public function save(): bool
	{
		if (!count($this->_modified)) {
			return true;
		}

		$this->selfCheck();

		$values = [];
		$db = DB::getInstance();

		foreach ($this->_modified as $key => $modified) {
			$value = $this->$key;

			if ($this->_types[$key] == File::class && null !== $value) {
				$value = $value->id();
			}
			else if ($this->_types[$key] == Champs::class) {
				$value = $value->toString();
			}

			$values[$key] = $value;
		}

		unset($value, $key, $modified);

		$db->begin();

		foreach ($this->_modified as $key=>$modified)
		{
			$value = $this->get($key);

			if (is_array($value)) {
				$value = implode(',', $value);
			}
			elseif (is_object($value)) {
				if ($value instanceof File) {
					$value = $value->id();
				}
				else {
					$value = (string) $value;
				}
			}

			$db->preparedQuery('INSERT OR REPLACE INTO config (key, value) VALUES (?, ?);',
				[$key, $value]);
		}

		if (!empty($this->_modified['champ_identifiant']))
		{
			// Mettre les champs identifiant vides à NULL pour pouvoir créer un index unique
			$db->exec('UPDATE membres SET '.$this->get('champ_identifiant').' = NULL
				WHERE '.$this->get('champ_identifiant').' = "";');

			// Création de l'index unique
			$db->exec('DROP INDEX IF EXISTS membres_identifiant;');
			$db->exec('CREATE UNIQUE INDEX membres_identifiant ON membres ('.$this->get('champ_identifiant').');');
		}

		foreach ($this->_deleted_files as $file) {
			$file->delete();
		}

		$db->commit();

		$this->_modified = [];

		return true;
	}

	public function delete(): bool
	{
		throw new \LogicException('Cannot delete config');
	}

	public function importForm($source = null): void
	{
		if (null === $source) {
			$source = $_POST;
		}

		// N'enregistrer les couleurs que si ce ne sont pas les couleurs par défaut
		if (!isset($source['couleur1'], $source['couleur2'])
			|| ($source['couleur1'] == ADMIN_COLOR1 && $source['couleur2'] == ADMIN_COLOR2))
		{
			$source['couleur1'] = null;
			$source['couleur2'] = null;
		}

		if (isset($source['admin_background']) && trim($source['admin_background']) == 'RESET') {
			$source['admin_background'] = null;
		}
		elseif (isset($source['admin_background']) && strlen($source['admin_background'])) {
			if ($this->admin_background) {
				$this->admin_background->storeFromBase64($source['admin_background']);
				$this->admin_background->save();
			}
			else {
				$this->set('admin_background', File::createFromBase64(File::CONTEXT_CONFIG, 'admin_background.png', $source['admin_background']));
			}

			unset($source['admin_background']);
		}

		parent::importForm($source);
	}

	protected function _filterType(string $key, $value)
	{
		switch ($this->_types[$key]) {
			case 'int':
				return (int) $value;
			case 'bool':
				return (bool) $value;
			case 'string':
				return (string) $value;
			case File::class:
			case Champs::class:
				if (!is_object($value) || !($value instanceof $this->_types[$key])) {
					throw new \InvalidArgumentException(sprintf('"%s" is not of type "%s"', $key, $this->_types[$key]));
				}
				return $value;
			default:
				throw new \InvalidArgumentException(sprintf('"%s" has unknown type "%s"', $key, $this->_types[$key]));
		}
	}

	public function selfCheck(): void
	{
		$this->assert(trim($this->nom_asso) != '', 'Le nom de l\'association ne peut rester vide.');
		$this->assert(trim($this->monnaie) != '', 'La monnaie ne peut rester vide.');
		$this->assert(trim($this->pays) != '' && Utils::getCountryName($this->pays), 'Le pays ne peut rester vide.');
		$this->assert(null === $this->site_asso || filter_var($this->site_asso, FILTER_VALIDATE_URL), 'L\'adresse URL du site web est invalide.');
		$this->assert(trim($this->email_asso) != '' && SMTP::checkEmailIsValid($this->email_asso, false), 'L\'adresse e-mail de l\'association est  invalide.');
		$this->assert(null === $this->admin_homepage || $this->admin_homepage instanceof File, 'Page d\'accueil invalide');
		$this->assert($this->champs_membres instanceof Champs, 'Objet champs membres invalide');

		$champs = $this->champs_membres;

		$this->assert(!empty($champs->get($this->champ_identite)), sprintf('Le champ spécifié pour identité, "%s" n\'existe pas', $this->champ_identite));
		$this->assert(!empty($champs->get($this->champ_identifiant)), sprintf('Le champ spécifié pour identifiant, "%s" n\'existe pas', $this->champ_identifiant));

		$db = DB::getInstance();
		$sql = sprintf('SELECT (COUNT(DISTINCT %s) = COUNT(*)) FROM membres WHERE %1$s IS NOT NULL AND %1$s != \'\';', $this->champ_identifiant);
		$is_unique = $db->firstColumn($sql);

		$this->assert($is_unique, sprintf('Le champ "%s" comporte des doublons et ne peut donc pas servir comme identifiant unique de connexion.', $this->champ_identifiant));

		$this->assert($db->test('users_categories', 'id = ?', $this->categorie_membres), 'Catégorie de membres inconnue');
	}

	public function getConfig()
	{
		return $this->asArray();
	}
}
