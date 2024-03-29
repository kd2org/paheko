{include file="_head.tpl" title="Renommer" current=null}

{form_errors}

<form method="post" action="{$self_url}">
	<fieldset>
		<legend>Renommer</legend>
		<dl>
			<dt>Nom actuel</dt>
			<dd><input type="text" disabled="disabled" value="{$file.name}" /></dd>
			{input type="text" name="new_name" required="required" label="Nouveau nom" default=$file.name}
		</dl>
		<p class="submit">
			{csrf_field key=$csrf_key}
			{button type="submit" name="rename" label="Renommer" shape="right" class="main"}
		</p>
	</fieldset>
</form>

<script type="text/javascript">
{literal}
var t = $('#f_new_name');
t.focus();
t.selectionStart = 0;
t.selectionEnd = t.value.lastIndexOf('.');
{/literal}
</script>

{include file="_foot.tpl"}