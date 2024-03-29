{include file="_head.tpl" title="%s — Modifier le membre"|args:$user->name() current="users"}

<nav class="tabs">
	{linkbutton href="details.php?id=%d"|args:$user.id label="Retour à la fiche membre" shape="left"}
</nav>

{form_errors}

<form method="post" action="{$self_url}" data-focus="fieldset.main input">
	<aside class="secondary">
		<fieldset>
			<dl>
			{if $user.id == $logged_user.id && $session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)}
				<p class="alert block">Pour éviter que votre association ne se retrouve sans administrateur, vous ne pouvez pas modifier votre catégorie de membre. Il faut qu'un autre administrateur le fasse pour vous.</p>
			{elseif !$can_change_category}
				<p class="alert block">Vous ne pouvez pas modifier la catégorie de ce membre, demandez à un administrateur.</p>
			{else}
				{input type="select" name="id_category" label="Catégorie du membre" required=true source=$user options=$categories}
			{/if}

			{if !$user->is_parent}
				{input type="list" name="id_parent" label="Rattacher à un membre" target="!users/selector.php?no_children=1" help="Permet de regrouper les personnes d'un même foyer par exemple. Sélectionner ici le membre responsable." default=$user->getParentSelector() can_delete=true}
			{/if}
			</dl>
		</fieldset>
	</aside>

	<fieldset class="main">
		<legend>Fiche du membre</legend>
		<dl>
			{foreach from=$fields item="field"}
				{edit_user_field field=$field user=$user context="admin_edit" session=$session}
			{/foreach}
		</dl>
	</fieldset>

	<p class="submit">
		{csrf_field key=$csrf_key}
		{button type="submit" name="save" label="Enregistrer" shape="right" class="main"}
	</p>

</form>

{include file="_foot.tpl"}