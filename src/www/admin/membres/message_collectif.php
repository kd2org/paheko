<?php
namespace Garradin;

use Garradin\Users\Categories;

require_once __DIR__ . '/_inc.php';

$recherche = new Recherche;

if (f('send'))
{
    $form->check('send_message_co', [
        'sujet'      => 'required|string',
        'message'    => 'required|string',
        'recipients' => 'required|string',
    ]);

    if (f('recipients') == 'all_but_hidden') {
        $recipients = $membres->listAllEmailsButHidden();
    }
    elseif (preg_match('/^(categorie|recherche)_(\d+)$/', f('recipients'), $match))
    {
        if ($match[1] == 'categorie')
        {
            $recipients = $membres->listAllByCategory($match[2], true);
        }
        else
        {
            try {
                $recipients = $recherche->search($match[2], ['membres.id AS "#IDENTITE"', 'membres.email AS "#EMAIL"'], true);
            }
            catch (UserException $e) {
                $form->addError($e->getMessage());
            }
        }
    }
    else
    {
        $form->addErrror('Destinataires invalides : ' . f('recipients'));
    }

    if (empty($recipients) || !count($recipients))
    {
        $form->addError('La liste de destinataires sélectionnée ne comporte aucun membre, ou aucun avec une adresse e-mail renseignée.');
    }

    if (!$form->hasErrors())
    {
        try {
            $membres->sendMessage($recipients, f('sujet'),
                f('message'), (bool) f('copie'));

            Utils::redirect(ADMIN_URL . 'membres/?sent');
        }
        catch (UserException $e)
        {
            $form->addError($e->getMessage());
        }
    }
}

$tpl->assign('categories', Categories::listNotHidden());
$tpl->assign('recherches', $recherche->getList($user->id, 'membres'));

$tpl->display('admin/membres/message_collectif.tpl');
