{include file="admin/_head.tpl" title="Mes informations de connexion et sécurité" current="mes_infos" js=1}

<ul class="actions">
    <li><a href="{$admin_url}mes_infos.php">Mes informations personnelles</a></li>
    <li class="current"><a href="{$admin_url}mes_infos_securite.php">Mot de passe et options de sécurité</a></li>
</ul>

{form_errors}

{if $confirm}
    <form method="post" action="{$self_url}">

    {if !empty($otp)}
        <p class="alert">
            Confirmez l'activation de l'authentification à double facteur TOTP en l'utilisant une première fois.
        </p>

        <fieldset>
            <legend>Confirmer l'activation de l'authentification à double facteur (2FA)</legend>
            <img class="qrcode" src="{$otp.qrcode}" alt="" />
            <dl>
                <dt>Ma clé secrète est&nbsp;:</dt>
                <dd><code>{$otp.secret_display}</code></dd>
                <dd class="help">Recopiez la clé secrète ou scannez le QR code pour configurer votre application TOTP (par exemple <a href="https://freeotp.github.io/">FreeOTP</a>), puis utilisez celle-ci pour générer un code d'accès et confirmer l'activation.</dd>
                <dt><label for="f_code">Code TOTP</label></dt>
                <dd class="help">Entrez ici le code donné par l'application d'authentification double facteur.</dd>
                <dd><input type="text" name="code" id="f_code" value="{form_field name=code}" /></dd>
            </dl>
        </fieldset>
    {/if}

    <fieldset>
        <legend>Confirmer les changements</legend>
        <dl>
            <dt><label for="f_passe_confirm">Mot de passe actuel</label></dt>
            <dd class="help">Entrez votre mot de passe actuel pour confirmer les changements demandés.</dd>
            <dd><input type="password" name="passe_confirm" /></dd>
        </dl>
    </fieldset>

    <p class="submit">
        {csrf_field key="edit_me_security"}
        <input type="hidden" name="passe" value="{form_field name="passe"}" />
        <input type="hidden" name="passe_confirmed" value="{form_field name="passe_confirmed"}" />
        <input type="hidden" name="clef_pgp" value="{form_field name="clef_pgp"}" />
        <input type="hidden" name="otp_secret" value="{$otp.secret}" />
        <input type="hidden" name="otp" value="generate" />
        <input type="submit" name="confirm" value="Confirmer &rarr;" />
    </p>

    </form>
{else}

    <form method="post" action="{$self_url}">

        <fieldset>
            <legend>Changer mon mot de passe</legend>
            {if $user.droits.membres < Garradin\Membres::DROIT_ADMIN && (!empty($champs.passe.private) || empty($champs.passe.editable))}
                <p class="help">Vous devez contacter un administrateur pour changer votre mot de passe.</p>
            {else}
                <dl>
                    <dd>Vous avez déjà un mot de passe, ne remplissez les champs suivants que si vous souhaitez en changer.</dd>
                    <dt><label for="f_passe">Nouveau mot de passe</label></dt>
                    <dd class="help">
                        Astuce : un mot de passe de quatre mots choisis au hasard dans le dictionnaire est plus sûr 
                        et plus simple à retenir qu'un mot de passe composé de 10 lettres et chiffres.
                    </dd>
                    <dd class="help">
                        Pas d'idée&nbsp;? Voici une suggestion choisie au hasard :
                        <input type="text" readonly="readonly" title="Cliquer pour utiliser cette suggestion comme mot de passe" id="pw_suggest" value="{$passphrase}" autocomplete="off" />
                    </dd>
                    <dd><input type="password" name="passe" id="f_passe" value="{form_field name=passe}" pattern=".{ldelim}5,{rdelim}" /></dd>
                    <dt><label for="f_repasse">Encore le mot de passe</label> (vérification)</dt>
                    <dd><input type="password" name="passe_confirmed" id="f_passe_confirmed" value="{form_field name=passe_confirmed}" pattern=".{ldelim}5,{rdelim}" /></dd>
                </dl>
            {/if}
        </fieldset>

        <fieldset>
            <legend>Authentification à double facteur (2FA)</legend>
            <p class="help">Pour renforcer la sécurité de votre connexion en cas de vol de votre mot de passe, vous pouvez activer
                l'authentification à double facteur. Cela nécessite d'installer une application comme <a href="https://freeotp.github.io/">FreeOTP</a>
                sur votre téléphone.</p>
            <dl>
                <dt>Authentification à double facteur (TOTP)</dt>
            {if $membre.secret_otp}
                <dd><label><input type="radio" name="otp" value="" checked="checked" /> <strong>Activée</strong></label></dd>
                <dd><label><input type="radio" name="otp" value="generate" /> Régénérer une nouvelle clé secrète</label></dd>
                <dd><label><input type="radio" name="otp" value="disable" /> Désactiver l'authentification à double facteur</label></dd>
            {else}
                <dd><em>Désactivée</em></dd>
                <dd><label><input type="checkbox" name="otp" value="generate" /> Activer</label></dd>
            {/if}
            </dl>
        </fieldset>

        {if $pgp_disponible}
        <fieldset>
            <legend>Protéger mes mails personnels par chiffrement PGP/GnuPG</legend>
            <dl>
                <dt><label for="f_clef_pgp">Ma clé publique PGP</label></dt>
                <dd class="help">En inscrivant ici votre clé publique, tous les emails personnels (non collectifs) qui vous
                    sont envoyés seront chiffrés (cryptés) avec cette clé&nbsp;: messages envoyés par les membres, rappels de cotisation,
                    procédure de récupération de mot de passe, etc.</dd>
                <dd><textarea name="clef_pgp" id="f_clef_pgp" cols="90" rows="5">{form_field name="clef_pgp" data=$user}</textarea></dd>
                {if $clef_pgp_fingerprint}<dd class="help">L'empreinte de la clé est&nbsp;: <code>{$clef_pgp_fingerprint}</code></dd>{/if}
            </dl>
            <p class="alert">
                Attention&nbsp;: en inscrivant ici votre clé PGP, les emails de récupération de mot de passe perdu vous seront envoyés chiffrés
                et ne pourront être lus sans utiliser le mot de passe protégeant votre clé privée correspondante.
            </p>
        </fieldset>
        {/if}

        <p class="submit">
            {csrf_field key="edit_me_security"}
            <input type="submit" name="save" value="Enregistrer &rarr;" />
        </p>

    </form>

    <script type="text/javascript">
    {literal}
    g.script('scripts/password.js').onload = function () {
        initPasswordField('pw_suggest', 'f_passe', 'f_passe_confirmed');
    };
    {/literal}
    </script>
{/if}

{include file="admin/_foot.tpl"}