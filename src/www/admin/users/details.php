<?php
namespace Garradin;

use Garradin\Accounting\Transactions;
use Garradin\Services\Services_User;
use Garradin\Users\Categories;
use Garradin\Users\Users;

use Garradin\UserTemplate\UserForms;
use Garradin\Entities\UserForm;

require_once __DIR__ . '/_inc.php';

$user = Users::get((int) qg('id'));

if (!$user) {
	throw new UserException("Ce membre n'existe pas.");
}

$category = $user->category();
$csrf_key = 'user_' . $user->id();

$form->runIf('login_as', function () use ($user, $category, $session) {
	if (!$session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)) {
		throw new \RuntimeException('Security alert: attempt to login as a different user, but does not hold the right to do so.');
	}

	$session->logout();
	$session->forceLogin($user->id);
}, $csrf_key, '!?login_as=1');

$services = Services_User::listDistinctForUser($user->id);

$variables = [];

if ($session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_READ)) {
	$variables['transactions_linked'] = Transactions::countForUser($user->id);
	$variables['transactions_created'] = Transactions::countForCreator($user->id);
}

$parent_name = $user->getParentName();
$children = $user->listChildren();
$siblings = $user->listSiblings();

$variables += compact('services', 'user', 'category', 'children', 'siblings', 'parent_name', 'csrf_key');

$tpl->assign($variables);
$tpl->assign('snippets', UserForms::getSnippets(UserForm::SNIPPET_USER, $variables));

$tpl->display('users/details.tpl');
