{* TestLink Open Source Project - http://testlink.sourceforge.net/ *}
{* $Id: attachmentupload.tpl,v 1.12 2009/12/28 08:46:59 franciscom Exp $ *}
{* Purpose: smarty template - template for attachment upload dialog 

   rev :
         20070310 - BUGID 732 

*}
{lang_get var='labels'
          s='title_upload_attachment,enter_attachment_title,btn_upload_file,
             local_file,attachment_upload_ok,title_choose_local_file,btn_cancel,max_size_file_upload'}

{include file="inc_head.tpl" jsValidate="yes" openHead="yes"}
{include file="inc_del_onclick.tpl"}


<script type="text/javascript">
var alert_box_title = "{lang_get s='warning'}";
var warning_empty_title = "{lang_get s='enter_attachment_title'}";
</script>
<body onunload="attachmentDlg_onUnload()" onload="attachmentDlg_onLoad()">
{config_load file="input_dimensions.conf" section="attachmentupload"}

<h1 class="title">{$labels.title_upload_attachment}</h1>
{if $gui->uploaded == 1}
  {include file="inc_update.tpl" user_feedback=$labels.attachment_upload_ok}
{/if}

<div class="workBack">
	<h2>{$labels.title_choose_local_file}</h2>
	
	<form action="lib/attachments/attachmentupload.php" method="post" enctype="multipart/form-data" id="aForm">
		<p>{$labels.local_file}
			<input type="hidden" name="MAX_FILE_SIZE" value="{$gui->import_limit}" /> {* restrict file size *}
			<input type="file" name="uploadedFile" size="{#UPLOAD_FILENAME_SIZE#}" />
		</p>
		<p>
			{$labels.enter_attachment_title}:
			<input type="text" id="title" name="title" maxlength="{#ATTACHMENT_TITLE_MAXLEN#}" 
			       size="{#ATTACHMENT_TITLE_SIZE#}" />
		</p>
		<div class="groupBtn">
			<input type="submit" value="{$labels.btn_upload_file}" onclick="return attachmentDlg_onSubmit({$gsmarty_attachments->allow_empty_title eq true})" />
			<input type="button" value="{$labels.btn_cancel}" onclick="window.close()" />
		</div>
	</form>
	<p>
		{$labels.max_size_file_upload'}: {$gui->import_limit} Bytes
	</p>
	{if $gui->msg neq ''}
		<p class="bold" style="color:red">{$gui->msg}</p>
	{/if}
</div>

</body>
</html>