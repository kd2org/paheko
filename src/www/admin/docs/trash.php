<?php

namespace Garradin;

use Garradin\Files\Files;
use Garradin\Files\Trash;
use Garradin\Users\Session;
use Garradin\Entities\Files\File;

require_once __DIR__ . '/../_inc.php';

$session = Session::getInstance();
$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);


$csrf_key = 'trash_action';
$check = f('check');
$extra = compact('check');
$count = $check ? count($check) : null;

$tpl->assign(compact('csrf_key', 'extra', 'count'));

$form->runIf('confirm_delete', function () use ($check, $session) {
	$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);
	foreach ($check as &$file) {
		$file = Files::get($file);

		if (!$file || !$file->canDelete()) {
			throw new UserException('Impossible de supprimer un fichier car vous n\'avez pas le droit de le supprimer');
		}
	}

	unset($file);

	foreach ($check as $file) {
		$file->delete();
	}

	Trash::pruneEmptyDirectories();
}, $csrf_key, '!docs/trash.php');

$form->runIf('restore', function() use ($check, $session) {
	foreach ($check as &$file) {
		$file = Files::get($file);

		if (!$file) {
			throw new UserException('Impossible de restaurer un fichier qui n\'existe plus');
		}
	}

	unset($file);

	foreach ($check as $file) {
		$file->restoreFromTrash();
	}

	Trash::pruneEmptyDirectories();
}, $csrf_key, '!docs/trash.php');

if (f('delete')) {
	$tpl->display('docs/trash_delete.tpl');
}
else {
	Trash::clean();

	$trash = Files::get(File::CONTEXT_TRASH);
	$tpl->assign('trash_size', $trash->getRecursiveSize());
	$list = Trash::list();

	$tpl->assign(compact('list'));

	$tpl->display('docs/trash.tpl');
}