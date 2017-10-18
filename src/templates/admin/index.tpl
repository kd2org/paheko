{include file="admin/_head.tpl" title="Bonjour %s !"|args:$user.identite current="home"}

<div class="infos_asso">
    <h3>{$config.nom_asso}</h3>
    {if !empty($config.adresse_asso)}
    <p>
        {$config.adresse_asso|escape|nl2br}
    </p>
    {/if}
    {if !empty($config.email_asso)}
    <p>
        E-Mail : <a href="{$config.email_asso}">{$config.email_asso}</a>
    </p>
    {/if}
    {if !empty($config.site_asso)}
    <p>
        Web : <a href="{$config.site_asso}">{$config.site_asso}</a>
    </p>
    {/if}
</div>

<ul class="actions">
    <li><a href="{$admin_url}mes_infos.php">Modifier mes informations personnelles</a></li>
    {if $cotisation}
    <li>
        {if !$cotisation.a_jour}
            <b class="error">Cotisation en retard&nbsp;!</b>
        {else}
            <b class="confirm">Cotisation à jour</b>
            {if $cotisation.expiration}
                (expire le {$cotisation.expiration|format_sqlite_date_to_french})
            {/if}
        {/if}
    </li>
    {/if}
    <li><a href="{$admin_url}mes_cotisations.php">Suivi de mes cotisations</a></li>
</ul>

<div class="wikiContent">
    {$page.contenu.contenu|raw|format_wiki|liens_wiki:'wiki/?'}
</div>

{include file="admin/_foot.tpl"}