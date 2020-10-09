{include file="admin/_head.tpl" title="Journal : %s - %s"|args:$account.code:$account.label current="acc/accounts" body_id="rapport"}

{if empty($year)}
	{include file="acc/_year_select.tpl"}
{else}
	<nav class="acc-year">
		<h4>Exercice sélectionné&nbsp;:</h4>
		<h3>{$current_year.label} — {$current_year.start_date|date_fr:'d/m/Y'} au {$current_year.end_date|date_fr:'d/m/Y'}</h3>
	</nav>
{/if}


{if $account.type}
<nav class="tabs">
	<ul>
		<li{if $simple_view} class="current"{/if}><a href="{$admin_url}acc/accounts/journal.php?id={$account.id}&amp;simple=1&amp;year={$year_id}">Vue simplifiée</a></li>
		<li{if !$simple_view} class="current"{/if}><a href="{$admin_url}acc/accounts/journal.php?id={$account.id}&amp;simple=0&amp;year={$year_id}">Vue normale</a></li>
	</ul>
</nav>
{/if}

<table class="list">
	<colgroup>
		<col width="3%" />
		<col width="12%" />
		<col width="10%" />
		{if !$simple_view}<col width="10%" />{/if}
		<col width="12%" />
		<col />
		<col width="6%" />
	</colgroup>
	<thead>
		<tr>
			<td>Réf.</td>
			<td>Date</td>
			{if $simple_view}
			<td>Mouvement</td>
			{else}
			<td class="money">Débit</td>
			<td class="money">Crédit</td>
			{/if}
			<td>Solde cumulé</td>
			<th>Libellé</th>
			<td></td>
		</tr>
	</thead>
	<tbody>
	{foreach from=$journal item="line"}
		<tr>
			<td class="num"><a href="{$admin_url}acc/transactions/details.php?id={$line.id}">{if $line.line_reference}{$line.line_reference}{elseif $line.reference}{$line.reference}{else}#{$line.id}{/if}</a></td>
			<td>{$line.date|date_fr:'d/m/Y'}</td>
			{if $simple_view}
			<td class="money">{if $line.debit}-{$line.debit|escape|html_money}{else}+{$line.credit|escape|html_money}{/if}</td>
			{else}
			<td class="money">{if $line.debit}{$line.debit|escape|html_money}{/if}</td>
			<td class="money">{if $line.credit}{$line.credit|escape|html_money}{/if}</td>
			{/if}
			<td class="money">{$line.running_sum|escape|html_money}</td>
			<th>{$line.label}</th>
			<td class="actions">
				{linkbutton href="acc/transactions/details.php?id=%d"|args:$line.id label="Détails" shape="search"}
			</td>
		</tr>
	{/foreach}
	</tbody>
	<tfoot>
		<tr>
			<td colspan="{if $simple_view}3{else}4{/if}">Solde</td>
			<td class="money">{$sum|escape|html_money}</td>
			<td colspan="2"></td>
		</tr>
	</tfoot>
</table>

{include file="admin/_foot.tpl"}