<?php
namespace Garradin;

use Garradin\Entities\Files\File;
use Garradin\Files\Files;

require __DIR__ . '/../../_inc.php';

$file = Files::get(qg('p'));

if (!$file) {
	throw new UserException('Fichier inconnu');
}

if (!$file->checkDeleteAccess($session)) {
    throw new UserException('Vous n\'avez pas le droit de supprimer ce fichier.');
}

$csrf_key = 'file_delete_' . $file->pathHash();

$form->runIf('delete', function () use ($file, $tpl) {
	$file->delete();

	if (null !== qg('dialog')) {
		$tpl->display('common/_reload_parent.tpl');
		exit;
	}
}, $csrf_key, Utils::getSelfURI(['deleted' => 1]));

$tpl->assign(compact('file', 'csrf_key'));

$tpl->display('common/files/delete.tpl');