{include file="_head.tpl" title="Versionnement des fichiers" current="config"}

{include file="config/_menu.tpl" current="backup"}

{include file="config/backup/_menu.tpl" current="versions"}

{form_errors}


{if $_GET.msg === 'CONFIG_SAVED'}
	<p class="block confirm">
		La configuration du versionnement a bien été enregistrée.
	</p>
{elseif $_GET.msg === 'PRUNED'}
	<p class="confirm block">Les anciennes versions des fichiers qui étaient trop anciennes ont bien été supprimées.</p>
{/if}

{if FILE_VERSIONING_POLICY}
	<div class="info block">
		<?php $policy = $versioning_policies[FILE_VERSIONING_POLICY]; ?>
		<p>L'administrateur du serveur a défini cette règle de conservation des anciennes versions&nbsp;:</p>
		<h3>{$policy.label} — {$policy.help}</h3>
		<p>Note : les fichiers de plus de <?=FILE_VERSIONING_MAX_SIZE?> ne seront pas versionnés.</p>
	</div>
{else}
	<form method="post" action="{$self_url_no_qs}">

		<fieldset>
			<legend>Conservation des anciennes versions des fichiers</legend>
			<dl class="minor">
				<dt><strong>Règle de conservation des anciennes versions</strong></dt>
				{foreach from=$versioning_policies key="key" item="policy"}
					{input type="radio-btn" name="file_versioning_policy" value=$key default="" source=$config label=$policy.label help=$policy.help}
				{/foreach}
			</dl>
			<dl class="versions">
			{if FILE_VERSIONING_MAX_SIZE}
				<dd class="help">Note : les fichiers de plus de <?=FILE_VERSIONING_MAX_SIZE?> ne seront pas versionnés.</dd>
			{else}
				{input type="number" name="file_versioning_max_size" min=1 label="Taille maximale des fichiers à versionner" source=$config required=true help="Les fichiers qui sont plus gros que cette taille ne seront pas versionnés." suffix="Mo" max=100 size=3}
			{/if}
			</dl>
		</fieldset>

		<p class="submit">
			{csrf_field key=$csrf_key}
			{button type="submit" name="save" label="Enregistrer" shape="right" class="main"}
		</p>

	</form>

	<script type="text/javascript" async="async">
	{literal}
	function toggleVersions() {
		g.toggle('.versions', $('#f_file_versioning_policy_none').checked ? false : true);
	}
	toggleVersions();
	$('input[name=file_versioning_policy]').forEach((e) => e.onchange = toggleVersions);
	{/literal}
	</script>
{/if}

<details class="help block">
	<summary>
		<h3>Le versionnement des fichiers, c'est quoi&nbsp;?</h3>
	</summary>
	<p>Pour éviter de perdre un travail précieux en cas de maladresse, les anciennes versions des fichiers peuvent être conservées.</p>
	<p>Lorsqu'un fichier est modifié, l'ancienne version est sauvegardée.</p>
	<p>Seuls les fichiers suivants sont versionnés&nbsp;:</p>
	<ul>
		<li>documents de l'association (menu "Documents") ;</li>
		<li>fichiers joints aux fiches des membres ;</li>
		<li>fichiers joints aux écritures comptables.</li>
	</ul>
	<p>{linkbutton shape="help" href="%sversionnement"|args:$help_url label="Plus d'informations sur le versionnement"}</p>
</details>


<p class="help">
	Espace disque utilisé par les anciennes versions : {$disk_use|size_in_bytes}.
	{linkbutton shape="gallery" label="Voir le détail de l'utilisation de l'espace disque" href="!config/disk_usage.php"}
</p>

{if $disk_use}
	{if $config.file_versioning_policy === 'none'}
	<p class="help">
		{linkbutton href="?delete_versions=1" shape="delete" label="Supprimer les anciennes versions" target="_dialog"}
	</p>
	{else}
	<form method="post" action="">
		<p class="help">
			{csrf_field key=$csrf_key}
			{button type="submit" name="prune_versions" value=1 shape="reload" label="Nettoyer les anciennes versions"} (pour gagner un peu d'espace disque)
		</p>
	</form>
	{/if}
{/if}

{include file="_foot.tpl"}