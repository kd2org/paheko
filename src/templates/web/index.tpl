{include file="_head.tpl" title=$title current="web" hide_title=true}

<nav class="tabs">
	<aside>
		<form method="post" action="search.php" target="_dialog" data-disable-progress="1">
			{input type="text" name="q" size=25 placeholder="Rechercher dans le site" title="Rechercher dans le site"}
			{button shape="search" type="submit" title="Rechercher"}
		</form>
		{if !$config.site_disabled}
			{if $page && $page->isOnline()}
				{linkbutton shape="eye" label="Voir sur le site" target="_blank" href=$page->url()}
			{elseif !$page}
				{linkbutton shape="eye" label="Voir sur le site" target="_blank" href=$www_url}
			{/if}
		{/if}
	</aside>
</nav>

<nav class="web breadcrumbs no-clear">
	<ul>
		<li><a href="?p=">Site web</a></li>
		{foreach from=$breadcrumbs key="id" item="title"}
			<li><a href="?p={$id}">{$title|truncate:40}</a></li>
		{/foreach}
	</ul>
	{if $page}
		<small>{linkbutton href="?p=%s"|args:$page.parent shape="left" label="Retour à la catégorie parent"}</small>
	{/if}
</nav>


{if !$page && $config.site_disabled && $session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)}
	<p class="block alert">
		Le site public est désactivé.
		{linkbutton shape="settings" href="!config/" label="Activer le site dans la configuration"}
	</p>
{/if}

{if count($links_errors) && !$page}
	<div class="block alert">
		Des pages contiennent des liens qui mènent à des pages qui n'existent pas&nbsp;:
		<ul>
			{foreach from=$links_errors item="p"}
			<li>{link href="?p=%s"|args:$p.path label=$p.title}</li>
			{/foreach}
		</ul>
	</div>
{elseif count($links_errors)}
	<div class="block alert">
		Cette page contient des liens qui mènent à des pages qui n'existent pas ou ont été renommées&nbsp;:
		<ul>
			{foreach from=$links_errors item="link"}
			<li>{$link}</li>
			{/foreach}
		</ul>
		Il est conseillé de modifier la page pour corriger les liens.
	</div>
{/if}

{form_errors}

{if $page}
	{include file="./_page.tpl" excerpt=$page->isCategory()}
{/if}

{if !$page || $page->isCategory()}
	<div class="web header">
		{if $session->canAccess($session::SECTION_WEB, $session::ACCESS_WRITE)}
		<p class="actions">
			{if $page}
				{assign var="parent" value=$page.path}
				{assign var="label" value="Nouvelle sous-catégorie"}
			{else}
				{assign var="parent" value=""}
				{assign var="label" value="Nouvelle catégorie"}
			{/if}
			{linkbutton shape="plus" label=$label target="_dialog" href="new.php?type=%d&parent=%s"|args:$type_category,$page.path}
		</p>
		{/if}
		<h2 class="ruler">{if $page}Sous-catégories{else}Catégories{/if}</h2>
	</div>

	{if count($categories)}
		<nav class="web category-list">
			<ul>
			{foreach from=$categories item="p"}
				<li{if !$p->isOnline()} class="draft"{/if}><a href="?p={$p.path}">{icon shape="folder"}{$p.title}</a></li>
			{/foreach}
			</ul>
		</nav>
	{elseif $page}
		<p class="help">Il n'y a aucune sous-catégorie dans cette catégorie.</p>
	{else}
		<p class="help">Il n'y a aucune catégorie.</p>
	{/if}

	{if $drafts->count()}
		<h2 class="ruler">Brouillons</h2>
		{include file="./_list.tpl" list=$drafts}
	{/if}

	<div class="web header">
		{if $session->canAccess($session::SECTION_WEB, $session::ACCESS_WRITE)}
		<p class="actions">
			{linkbutton shape="plus" label="Nouvelle page" target="_dialog" href="new.php?type=%d&parent=%s"|args:$type_page,$page.path}
		</p>
		{/if}
		<h2 class="ruler">Pages</h2>
	</div>
	{if $pages->count()}
		{include file="./_list.tpl" list=$pages}
	{else}
		<p class="help">Il n'y a aucune page dans cette catégorie.</p>
	{/if}
{/if}


{include file="_foot.tpl"}