<nav class="tabs">
	<aside>
	{if $session->canAccess($session::SECTION_USERS, $session::ACCESS_WRITE) && $current == 'details'}
		{linkbutton href="edit.php?id=%d"|args:$id shape="edit" label="Modifier" accesskey="M"}
	{/if}
	{if $session->canAccess($session::SECTION_USERS, $session::ACCESS_ADMIN) && $logged_user.id != $id && $current == 'details'}
		{linkbutton href="delete.php?id=%d"|args:$id shape="delete" label="Supprimer" target="_dialog" accesskey="S"}
	{/if}
	{if $session->canAccess($session::SECTION_USERS, $session::ACCESS_WRITE) && $current == 'services'}
		{linkbutton href="!services/user/subscribe.php?user=%d"|args:$id label="Inscrire à une activité" shape="plus" target="_dialog" accesskey="K"}
	{/if}

	</aside>
	<ul>
		<li{if $current == 'details'} class="current"{/if}>{link href="!users/details.php?id=%d"|args:$id label="Fiche membre" accesskey="F"}</li>
		<li{if $current == 'services'} class="current"{/if}>{link href="!services/user/?id=%d"|args:$id label="Inscriptions aux activités" accesskey="I"}</li>
		<li{if $current == 'reminders'} class="current"{/if}>{link href="!services/reminders/user.php?id=%d"|args:$id label="Rappels envoyés" accesskey="R"}</li>
	</ul>
</nav>