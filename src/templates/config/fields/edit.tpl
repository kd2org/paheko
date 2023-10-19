<?php
$title = $field->exists() ? 'Modifier un champ' : 'Ajouter un champ';
?>
{include file="_head.tpl" current="config" title=$title}

{include file="config/_menu.tpl" current="fields"}

{form_errors}

<form method="post" action="{$self_url}">
<fieldset>
	<legend>{$title}</legend>
	<dl>
	{if !$field->isPreset() && !$field->exists()}
		{input type="select" name="type" options=$field::TYPES source=$field label="Type" default="text" help="Il ne sera plus possible de modifier le type une fois le champ créé." required=true}
		{input type="text" name="name" pattern="[a-z](_?[a-z0-9]+)*" label="Nom unique" required=true source=$field help="Ne peut comporter que des lettres minuscules et des tirets bas. Par exemple pour un champ demandant l'adresse, on peut utiliser 'adresse_postale'. Ce nom ne peut plus être modifié ensuite."}
	{else}
		{if $field->isPreset()}
			<dd class="help">Le type, nom unique et libellé ne sont pas modifiables pour ce champ.</dd>
		{else}
			<dd class="help">Le type et le nom unique ne sont pas modifiables.</dd>
		{/if}
		{input type="select" name="type" options=$field::TYPES source=$field label="Type" disabled=true}
		{input type="text" name="name" disabled=true label="Nom unique" source=$field}
	{/if}
		{input type="text" name="label" label="Libellé" required=true source=$field}
	</dl>
	<dl class="type-not-virtual">
		{input type="text" name="help" label="Texte d'aide" help="Apparaîtra dans les formulaires de manière identique à ce texte." source=$field}

	</dl>
	<dl class="type-not-virtual type-not-password">
		{input type="checkbox" name="required" value=1 label="Champ obligatoire" help="Si coché, une fiche membre ne pourra pas être enregistrée si ce champ n'est pas renseigné." source=$field}
		{input type="text" name="default" source=$field label="Valeur par défaut" help="Si renseigné, le champ aura cette valeur par défaut lors de l'ajout d'un nouveau membre"}
	</dl>
	<dl class="type-not-password">
		{input type="checkbox" name="list_table" value=1 label="Afficher dans la liste des membres" source=$field}
	</dl>
	<dl class="type-virtual">
		{input type="textarea" required=true name="sql" class="full-width" rows=3 source=$field label="Code SQL utilisée pour calculer ce champ" disabled=$field->isPreset()}
		<dd class="help">
			Les champs calculés utilisent du code SQL correspondant à une <a href="https://www.sqlite.org/gencol.html" target="_blank">colonne générée dans SQLite</a>.<br />
			Le code ne peut faire référence qu'à des colonnes de la même ligne et n'utiliser que des <a href="https://www.sqlite.org/deterministic.html" target="_blank">fonctions déterministes</a>. Il n'est pas possible d'utiliser des sous-requêtes, des fonctions d'aggrégation, etc.<br />
			Exemple : il est possible de mettre <code>SUBSTR(nom, 1, 1) || SUBSTR(prenom, 1, 1)</code> pour que le champ renvoie les initiales du nom et du prénom. Mais il n'est pas possible de mettre <code>SELECT nom FROM users WHERE …</code> pour utiliser le nom d'un autre membre.
		</dd>
	</dl>
</fieldset>

<fieldset class="type-select type-multiple type-datalist">
	<legend>Options possibles</legend>

	<p class="alert block type-select type-datalist">Attention renommer ou supprimer une option n'affecte pas ce qui a déjà été enregistré dans les fiches des membres.</p>
	<p class="alert block type-multiple">Attention changer l'ordre des options peut avoir des effets indésirables.</p>

	<dl class="type-multiple type-select type-datalist options">
		{if $field.options}
			{foreach from=$field.options item="option"}
			<dd>{input type="text" name="options[]" default=$option}</dd>
			{/foreach}
		{/if}
		<dd>{input type="text" name="options[]"}</dd>
	</dl>
</fieldset>

<fieldset>
	<legend>Accès</legend>
	<dl>
		<dt><label for="f_user_access_level_0">Le membre lui-même peut…</label></dt>
		<dd class="help">Indiquer ici si le membre pourra voir ou modifier cette information dans sa section <em>"Mes infos personnelles"</em>.</dd>
		{input type="radio" name="user_access_level" value=$session::ACCESS_WRITE label="Voir et modifier ce champ" source=$field}
		{input type="radio" name="user_access_level" value=$session::ACCESS_READ label="Seulement voir ce champ" source=$field default=$session::ACCESS_READ}
		{input type="radio" name="user_access_level" value=$session::ACCESS_NONE label="Rien, cette information ne doit pas être visible par le membre" source=$field}
		<dd class="help">Attention&nbsp;: conformément à la réglementation (RGPD), quel que soit votre choix, le membre pourra voir le contenu de ce champ en effectuant un export de ses données personnelles (s'il a le droit de se connecter).</dd>
		<dt><label for="f_management_access_level_1">Un autre membre peut voir ce champ…</label></dt>
		{input type="radio" name="management_access_level" value=$session::ACCESS_READ label="S'il a accès à la gestion des membres (en lecture, écriture, ou administration)" source=$field default=$session::ACCESS_READ}
		{input type="radio" name="management_access_level" value=$session::ACCESS_WRITE label="Seulement s'il a accès en écriture à la gestion des membres" source=$field}
		{input type="radio" name="management_access_level" value=$session::ACCESS_ADMIN label="Seulement s'il a accès en administration à la gestion des membres" source=$field}
	</dl>
</fieldset>

<p class="submit">
	{csrf_field key=$csrf_key}
	{linkbutton label="Annuler" shape="left" href="./" target="_parent"}
	{button type="submit" name="save" label="Enregistrer" shape="right" class="main"}
</p>

</form>

<script type="text/javascript" src="{$admin_url}static/scripts/config_fields.js"></script>

{include file="_foot.tpl"}