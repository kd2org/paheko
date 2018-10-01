<?php

namespace Garradin\Membres;

use Garradin\Membres;
use Garradin\Config;
use Garradin\DB;
use Garradin\Utils;
use Garradin\UserException;

use KD2\ODSWriter;

class Import
{
	public function getCSVAsArray($path)
	{
		if (!file_exists($path) || !is_readable($path))
		{
			throw new \RuntimeException('Fichier inconnu : '.$path);
		}

		$fp = fopen($path, 'r');

		if (!$fp)
		{
			return false;
		}

		$delim = Utils::find_csv_delim($fp);
		Utils::skip_bom($fp);

		$line = 0;
		$out = [];
		$nb_columns = null;

		while (!feof($fp))
		{
			$row = fgetcsv($fp, 4096, $delim);
			$line++;

			if (empty($row))
			{
				continue;
			}

			if (null === $nb_columns)
			{
				$nb_columns = count($row);
			}

			if (count($row) != $nb_columns)
			{
				throw new UserException('Erreur sur la ligne ' . $line . ' : incohérence dans le nombre de colonnes avec la première ligne.');
			}

			$out[$line] = $row;
		}

		fclose($fp);

		return $out;
	}

	/**
	 * Importer un CSV générique
	 * @param  string $path              Chemin vers le CSV
	 * @param  array  $translation_table Tableau indiquant la correspondance à effectuer entre les colonnes
	 * du CSV et les champs de Garradin. Par exemple : ['Date création fiche' => 'date_inscription']
	 * @return boolean                   TRUE en cas de succès
	 */
	public function fromArray(array $table, $translation_table, $skip_lines = 0)
	{
		$db = DB::getInstance();
		$db->begin();
		$membres = new Membres;
		$champs = Config::getInstance()->get('champs_membres');

		$nb_columns = count($translation_table);

		if ($skip_lines)
		{
			$table = array_slice($table, $skip_lines, null, true);
		}

		foreach ($table as $line => $row)
		{
			if (empty($row))
			{
				continue;
			}

			if (count($row) != $nb_columns)
			{
				$db->rollback();
				throw new UserException('Erreur sur la ligne ' . $line . ' : le nombre de colonnes est incorrect.');
			}

			$data = [];

			foreach ($translation_table as $column_index => $garradin_field)
			{
				// Champs qu'on ne veut pas importer
				if (empty($garradin_field))
				{
					continue;
				}

				// Concaténer plusieurs champs, si on choisit d'indiquer plusieurs fois
				// le même champ pour plusieurs colonnes (par exemple pour mettre nom et prénom
				// dans un seul champ)
				if (isset($data[$garradin_field]))
				{
					$champ = $champs->get($garradin_field);

					if ($champ->type == 'text')
					{
						$data[$garradin_field] .= ' ' . $row[$column_index];
					}
					elseif ($champ->type == 'textarea')
					{
						$data[$garradin_field] .= "\n" . $row[$column_index];
					}
					else
					{
						throw new UserException(sprintf('Erreur sur la ligne %d : impossible de concaténer des colonnes avec le champ %s : n\'est pas un champ de type texte', $line, $champ->title));
					}
				}
				else
				{
					$data[$garradin_field] = $row[$column_index];
				}
			}

			try {
				$membres->add($data, false);
			}
			catch (UserException $e)
			{
				$db->rollback();
				throw new UserException('Erreur sur la ligne ' . $line . ' : ' . $e->getMessage());
			}
		}

		$db->commit();
		return true;
	}

	/**
	 * Importer un CSV de la liste des membres depuis un export Garradin
	 * @param  string $path     Chemin vers le CSV
	 * @param  int    $current_user_id
	 * @return boolean          TRUE en cas de succès
	 */
	public function fromGarradinCSV($path, $current_user_id)
	{
		if (!file_exists($path) || !is_readable($path))
		{
			throw new \RuntimeException('Fichier inconnu : '.$path);
		}

		$fp = fopen($path, 'r');

		if (!$fp)
		{
			return false;
		}

		$db = DB::getInstance();
		$db->begin();
		$membres = new Membres;

		// On récupère les champs qu'on peut importer
		$champs = Config::getInstance()->get('champs_membres')->getAll();
		$champs = array_keys((array)$champs);
		$champs[] = 'date_inscription';
		//$champs[] = 'date_connexion';
		//$champs[] = 'id';
		//$champs[] = 'id_categorie';

		$line = 0;
		$delim = Utils::find_csv_delim($fp);
		Utils::skip_bom($fp);

		while (!feof($fp))
		{
			$row = fgetcsv($fp, 4096, $delim);

			$line++;

			if (empty($row))
			{
				continue;
			}

			if ($line == 1)
			{
				if (is_numeric($row[0]))
				{
					$db->rollback();
					throw new UserException('Erreur sur la ligne 1 : devrait contenir l\'en-tête des colonnes.');
				}

				$columns = array_flip($row);
				continue;
			}

			if (count($row) != count($columns))
			{
				$db->rollback();
				throw new UserException('Erreur sur la ligne ' . $line . ' : le nombre de colonnes est incorrect.');
			}

			$data = [];

			foreach ($columns as $name=>$id)
			{
				$name = trim($name);
				
				// Champs qui n'existent pas dans le schéma actuel
				if (!in_array($name, $champs))
					continue;

				if (trim($row[$id]) !== '')
					$data[$name] = $row[$id];
			}

			if (!empty($data['numero']) && $data['numero'] > 0)
			{
				$numero = (int)$data['numero'];
			}
			else
			{
				unset($data['numero']);
				$numero = false;
			}

			try {
				if ($numero && ($id = $membres->getIDWithNumero($numero)))
				{
					if ($id === $current_user_id)
					{
						// Ne pas modifier le membre courant, on risque de se tirer une balle dans le pied
						continue;
					}

					$membres->edit($id, $data);
				}
				else
				{
					$membres->add($data, false);
				}
			}
			catch (UserException $e)
			{
				$db->rollback();
				throw new UserException('Erreur sur la ligne ' . $line . ' : ' . $e->getMessage());
			}
		}

		$db->commit();

		fclose($fp);
		return true;
	}

	protected function export()
	{
		$db = DB::getInstance();

		$champs = Config::getInstance()->get('champs_membres')->getKeys();
		$champs_sql = 'm.' . implode(', m.', $champs);

		$res = $db->iterate('SELECT ' . $champs_sql . ', c.nom AS categorie FROM membres AS m 
			LEFT JOIN membres_categories AS c ON m.id_categorie = c.id ORDER BY c.id;');

		return [
			array_merge($champs, ['categorie']),
			$res,
			sprintf('Export membres - %s - %s', Config::getInstance()->get('nom_asso'), date('Y-m-d')),
		];
	}

	public function toCSV()
	{
		list($champs, $result, $name) = $this->export();
		return Utils::toCSV($name, $result, $champs);
	}

	public function toODS()
	{
		list($champs, $result, $name) = $this->export();
		return Utils::toODS($name, $result, $champs);
	}
}