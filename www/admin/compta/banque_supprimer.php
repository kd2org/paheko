<?php

require_once __DIR__ . '/_inc.php';

if ($user['droits']['compta'] < Garradin_Membres::DROIT_ADMIN)
{
    throw new UserException("Vous n'avez pas le droit d'accéder à cette page.");
}

require_once GARRADIN_ROOT . '/include/class.compta_comptes_bancaires.php';
$banque = new Garradin_Compta_Comptes_Bancaires;

$compte = $banque->get(utils::get('id'));

if (!$compte)
{
    throw new UserException('Le compte demandé n\'existe pas.');
}

$error = false;

if (!empty($_POST['delete']))
{
    if (!utils::CSRF_check('compta_delete_banque_'.$compte['id']))
    {
        $error = 'Une erreur est survenue, merci de renvoyer le formulaire.';
    }
    else
    {
        try
        {
            $banque->delete($compte['id']);
            utils::redirect('/admin/compta/banques.php');
        }
        catch (UserException $e)
        {
            $error = $e->getMessage();
        }
    }
}

$tpl->assign('error', $error);

$tpl->assign('compte', $compte);

$tpl->display('admin/compta/banque_supprimer.tpl');

?>