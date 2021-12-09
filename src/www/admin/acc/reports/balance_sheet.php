<?php

namespace Garradin;

use Garradin\Accounting\Reports;
use Garradin\Accounting\Years;
use Garradin\Entities\Accounting\Account;

require_once __DIR__ . '/_inc.php';

$tpl->assign('balance', Reports::getBalanceSheet($criterias));

if (!empty($criterias['year'])) {
	$tpl->assign('other_years', [null => '-- Ne pas comparer'] + Years::listClosedAssocExcept($criterias['year']));
}

$tpl->display('acc/reports/balance_sheet.tpl');
