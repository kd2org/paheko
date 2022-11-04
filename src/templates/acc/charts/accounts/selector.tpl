{include file="admin/_head.tpl" title="Sélectionner un compte"}

<div class="selector">

{if empty($grouped_accounts) && empty($accounts)}
	<p class="block alert">Le plan comptable ne comporte aucun compte de ce type.<br />
		{linkbutton href="!acc/charts/accounts/new.php?id=%s&type=%s"|args:$chart.id,$targets[0] label="Créer un compte" shape="plus"}
	</p>

{else}

	<header>
		<h2>
			<input type="text" placeholder="Recherche rapide" id="lookup" />
		</h2>

		{if $session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_ADMIN)}
			<?php $page = isset($grouped_accounts) ? '' : 'all.php'; ?>
			<p class="edit">{linkbutton label="Modifier les comptes" href="!acc/charts/accounts/%s?id=%d&types=%s"|args:$page,$chart.id,$targets_str shape="edit"}</aside></p>
		{/if}

		<p>{input type="select" name="filter" options=$filter_options default=$filter}</p>
	</header>

	{if isset($grouped_accounts)}
		<?php $index = 1; ?>
		{foreach from=$grouped_accounts item="group"}
			<h2 class="ruler">{$group.label}</h2>

			<table class="list">
				<tbody>
				{foreach from=$group.accounts item="account"}
					<tr data-idx="{$index}" class="account">
						<td class="bookmark">{if $account.bookmark}{icon shape="star" title="Compte favori"}{/if}</td>
						<td class="num">{$account.code}</td>
						<th>{$account.label}</th>
						<td class="desc">{$account.description}</td>
						<td class="actions">
							<button class="icn-btn" value="{$account.id}" data-label="{$account.code} — {$account.label}" data-icon="&rarr;">Sélectionner</button>
						</td>
					</tr>
					<?php $index++; ?>
				{/foreach}
				</tbody>
			</table>
		{/foreach}

	{else}

		<table class="list">
			<tbody>
			{foreach from=$accounts item="account"}
				<tr data-idx="{$iteration}" class="account account-level-{$account->level()}">
					<td class="bookmark">{if $account.bookmark}{icon shape="star" title="Compte favori"}{/if}</td>
					<td class="num">{$account.code}</td>
					<th>{$account.label}</th>
					<td class="actions">
						<button class="icn-btn" value="{$account.id}" data-label="{$account.code} — {$account.label}" data-icon="&rarr;">Sélectionner</button>
					</td>
				</tr>
			{/foreach}
			</tbody>
		</table>

	{/if}
{/if}

</div>

<script type="text/javascript" src="{$admin_url}static/scripts/selector.js?{$version_hash}"></script>

{include file="admin/_foot.tpl"}