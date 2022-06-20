<?php

if ( ! $module->canConfigure() )
{
	exit;
}

$listRecordNameTypes = [ 'R' => 'Record number',
                         'G' => 'DAG',
                         'U' => 'User supplied',
                         'T' => 'Timestamp',
                         'F' => 'Field value lookup' ];

$listArms = $module->getArms();

$projectSettingsConfig = $module->getConfig()['project-settings'];
$listProjectSettings = [];
$listArmSettings = [];
foreach ( $projectSettingsConfig as $settingConfig )
{
	if ( $settingConfig['type'] == 'descriptive' ||
	     $settingConfig['key'] == 'project-record-counter' ||
	     $settingConfig['key'] == 'project-last-record' )
	{
		continue;
	}
	if ( $settingConfig['key'] == 'scheme-settings' )
	{
		foreach ( $settingConfig['sub_settings'] as $subSettingConfig )
		{
			if ( $subSettingConfig['type'] == 'descriptive' )
			{
				continue;
			}
			$listArmSettings[ $subSettingConfig['key'] ] =
				[ 'name' => $subSettingConfig['name'],
				  'type' => $subSettingConfig['type'],
				  'choices' => array_column( $subSettingConfig['choices'] ?? [], 'name', 'value' ),
				  'value' => $module->getProjectSetting( $subSettingConfig['key'] ) ];
		}
		continue;
	}
	$listProjectSettings[ $settingConfig['key'] ] =
		[ 'name' => $settingConfig['name'],
		  'type' => $settingConfig['type'],
		  'choices' => array_column( $settingConfig['choices'] ?? [], 'name', 'value' ),
		  'value' => $module->getProjectSetting( $settingConfig['key'] ) ];
}

// Handle submission.
if ( ! empty( $_POST ) )
{
	$checkOnly = isset( $_POST['check_only'] );
	unset( $_POST['check_only'] );
	// If the option has been selected, the settings from the first arm can be applied to all arms.
	if ( isset( $_POST['apply_all_arms'] ) )
	{
		foreach ( $_POST as $field => $value )
		{
			if ( $field != 'scheme-arm' && is_array( $value ) )
			{
				$count = count( $value );
				for ( $i = 1; $i < $count; $i++ )
				{
					$_POST[ $field ][ $i ] = $_POST[ $field ][ 0 ];
				}
			}
		}
		unset( $_POST['apply_all_arms'] );
	}
	// Validate the settings, and if only a validation check is being run, return the validation
	// results as JSON.
	$validationErrors = $module->validateSettings( $_POST );
	if ( $checkOnly )
	{
		header( 'Content-Type: application/json' );
		if ( $validationErrors !== null )
		{
			$i = 0;
			foreach ( $listArms as $armName )
			{
				$i++;
				$validationErrors = str_replace( 'Naming scheme ' . $i, $armName,
				                                 $validationErrors );
			}
		}
		echo json_encode( $validationErrors );
		exit;
	}
	// Apply the settings if no validation errors, then redirect.
	if ( $validationErrors === null )
	{
		foreach ( $_POST as $field => $value )
		{
			if ( isset( $listProjectSettings[ $field ] ) || isset( $listArmSettings[ $field ] ) )
			{
				$module->setProjectSetting( $field, $value );
			}
		}
	}
	header( 'Location: ' . $_SERVER['REQUEST_URI'] );
	exit;
}

function makeSettingRow( $field, $name, $type, $choices, $value )
{
	$row = '<tr';
	if ( strpos( $field, 'scheme-number' ) !== false )
	{
		$row .= ' data-type="R"';
	}
	elseif ( strpos( $field, 'scheme-dag' ) !== false )
	{
		$row .= ' data-type="G"';
	}
	elseif ( strpos( $field, 'user-supplied' ) !== false )
	{
		$row .= ' data-type="U"';
	}
	elseif ( strpos( $field, 'timestamp' ) !== false )
	{
		$row .= ' data-type="T"';
	}
	elseif ( strpos( $field, 'field-lookup' ) !== false )
	{
		$row .= ' data-type="F"';
	}
	$row .= '><td style="width:0px;white-space:nowrap;padding:10px 15px 10px 0px">' .
	       ( strlen( $name ) > 60 ? str_replace( '(', '<br>(', $name ) : $name ) .
	       '</td><td style="padding:10px 0px 10px 0px">';
	if ( $type == 'text' )
	{
		$row .= '<input type="text" name="' . $field . '" value="' .
		        htmlspecialchars( $value ) . '" style="width:100%">';
	}
	elseif ( $type == 'number' )
	{
		$row .= '<input type="number" name="' . $field . '" value="' .
		        htmlspecialchars( $value ) . '" min="0">';
	}
	elseif ( $type == 'textarea' )
	{
		$row .= '<textarea name="' . $field . '" style="width:100%;height:80px">' .
		        htmlspecialchars( $value ) . '</textarea>';
	}
	elseif ( $type == 'dropdown' || $type == 'radio' )
	{
		$row .= '<select name="' . $field . '">';
		if ( $type == 'dropdown' || $value == '' )
		{
			$row .= '<option value=""></option>';
		}
		foreach ( $choices as $choiceVal => $choiceLabel )
		{
			$row .= '<option value="' . $choiceVal . '"' .
			        ( $value == $choiceVal ? ' selected' : '' ) . '> ' .
			        htmlspecialchars( $choiceLabel ) . '</option>';
		}
		$row .= '</select>';
	}
	elseif ( $type == 'multiselect' )
	{
		$row .= '<ul class="multiselect">';
		$selectedOptions = array_intersect_key( $choices, array_flip( str_split( $value, 1 ) ) );
		$selectedOptions =
		  array_reduce( $value == '' ? [] : str_split( $value, 1 ),
		                function ( $c, $i ) use ( $choices ) { $c[$i] = $choices[$i]; return $c; },
		                [] );
		$remainingOptions = array_diff_key( $choices, $selectedOptions );
		foreach ( [ $selectedOptions, $remainingOptions ] as $choiceList )
		{
			foreach ( $choiceList as $choiceVal => $choiceLabel )
			{
				$row .= '<li data-value="' . $choiceVal . '">' .
				        '<input type="checkbox"' .
				        ( isset( $selectedOptions[ $choiceVal ] ) ? ' checked' : '' ) . '> ' .
				        htmlspecialchars( $choiceLabel ) . '</li>';
			}
		}
		$row .= '</ul><input type="hidden" name="' . $field . '" value="' . $value . '">' .
		        '(drag items to change order)';
	}
	$row .= '</td></tr>';
	return $row;
}

// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';


?>
<div class="projhdr"><i class="fas fa-list-ul"></i> Custom Record Naming</div>
<form method="post" id="customrecordnaming_form">
 <div id="modsettings" style="width:97%">
  <ul>
   <li><a href="#modsettings_general">General</a></li>
<?php
foreach ( $listArms as $armID => $armName )
{
?>
   <li>
    <a href="#modsettings_arm<?php echo $armID; ?>"><?php echo htmlspecialchars( $armName ); ?></a>
   </li>
<?php
}
?>
  </ul>
  <div id="modsettings_general">
   <table style="width:100%">
<?php
foreach ( $listProjectSettings as $fieldName => $setting )
{
	if ( $fieldName == 'dag-format' )
	{
		echo '<tr><td style="padding:10px 0px 10px 0px">Restrict DAG name format</td>' .
		     '<td style="padding:10px 0px 10px 0px"><select class="choose-general-dag-format">' .
		     '<option value=""></option><option value="^[0-9]+[^0-9]">Numeric prefix</option>' .
		     '<option value="^[0-9]+[ ]">Numeric prefix (space separator)</option><option value=' .
		     '"^[0-9]{2}[^0-9]">Numeric prefix (2 digit)</option><option value="^[0-9]{2}[ ]">' .
		     'Numeric prefix (2 digit, space separator)</option><option value="^[0-9]{3}[^0-9]">' .
		     'Numeric prefix (3 digit)</option><option value="^[0-9]{3}[ ]">Numeric prefix (3 ' .
		     'digit, space separator)</option><option value="^[0-9]{4}[^0-9]">Numeric prefix ' .
		     '(4 digit)</option><option value="^[0-9]{4}[ ]">Numeric prefix (4 digit, space ' .
		     'separator)</option><option value="^[0-9]{5}[^0-9]">Numeric prefix (5 digit)' .
		     '</option><option value="^[0-9]{5}[ ]">Numeric prefix (5 digit, space separator)' .
		     '</option><option value="^[A-Za-z0-9]{2}[ ]">2 character prefix (space separator)' .
		     '</option><option value="^[A-Z]{2}[ ]">2 character prefix (uppercase A-Z only, ' .
		     'space separator)</option><option value="^[A-Za-z0-9]{3}[ ]">3 character prefix ' .
		     '(space separator)</option><option value="^[A-Z]{3}[ ]">3 character prefix ' .
		     '(uppercase A-Z only, space separator)</option><option value=":">Custom (regular ' .
		     'expression)</option></select></td></tr>';
	}
	echo makeSettingRow( $fieldName, $setting['name'], $setting['type'],
	                     $setting['choices'], $setting['value'] );
}
?>
   </table>
  </div>
<?php
$firstArm = true;
foreach ( $listArms as $armID => $armName )
{
	$valueIndex = false;
	if ( is_array( $listArmSettings['scheme-arm']['value'] ) )
	{
		$valueIndex = array_search( $armID, $listArmSettings['scheme-arm']['value'] );
	}
?>
  <div id="modsettings_arm<?php echo $armID; ?>">
   <input type="hidden" name="scheme-settings[]" value="true">
   <input type="hidden" name="scheme-arm[]" value="<?php echo $armID; ?>">
   <table style="width:100%">
<?php
	foreach ( $listArmSettings as $fieldName => $setting )
	{
		if ( $fieldName == 'scheme-arm' )
		{
			continue;
		}
		$fieldType = $setting['type'];
		$fieldChoices = $setting['choices'];
		if ( $fieldName == 'scheme-name-type' )
		{
			$fieldType = 'multiselect';
			$fieldChoices = $listRecordNameTypes;
		}
		elseif ( $fieldName == 'scheme-number-start' || $fieldName == 'scheme-dag-section' )
		{
			$fieldType = 'number';
		}
		elseif ( $fieldName == 'scheme-name-prefix' )
		{
			echo '<tr><td></td><td style="font-size:x-small">If using per-arm numbering, it is ',
			     'highly recommended that you make use of at least one of prefix, separator and ',
			     'suffix, with different values for each arm, in order to avoid a naming clash.',
			     '</td></tr>';
		}
		elseif ( $fieldName == 'scheme-dag-format' )
		{
			echo '<tr data-type="G"><td style="padding:10px 0px 10px 0px">Accept DAG name format' .
			     '</td><td style="padding:10px 0px 10px 0px"><select class="choose-dag-format">' .
			     '<option value=""></option><option value="^([^ ]+)[ ]">Use all up to first space' .
			     '</option><option value="^([0-9]+)[^0-9]">Use numeric prefix (all up to first ' .
			     'non-number)</option><option value=":">Custom (regular expression)</option>' .
			     '</select></td></tr>';
		}
		elseif ( $fieldName == 'scheme-timestamp-tz' )
		{
			$fieldChoices['S'] .= ' (' . date('e') . ')';
		}
		$value = ( $valueIndex === false ? '' : $setting['value'][ $valueIndex ] );
		echo makeSettingRow( $fieldName.'[]', $setting['name'], $fieldType, $fieldChoices, $value );
	}
	if ( $firstArm )
	{
		echo '<tr><td style="padding:10px 0px 10px 0px">Apply to all arms</td><td style="padding:' .
		     '10px 0px 10px 0px"><input type="checkbox" name="apply_all_arms" value="1"></td></tr>';
		$firstArm = false;
	}
?>
   </table>
  </div>
<?php
}
?>
 </div>
 <p><input type="submit" value="Submit"></p>
</form>
<?php
if ( $module->getUser()->isSuperUser() )
{
?>
<p>&nbsp;</p>
<hr style="max-width:300px;margin-left:0px">
<p><b>Administrative Options</b></p>
<ul>
 <li>
  <a href="<?php echo $module->getUrl( 'counter_overview.php' ) ?>">Open counter overview</a>
 </li>
 <li>
  <a href="<?php echo $module->getUrl( 'import_export.php' ) ?>">Import/export settings</a>
 </li>
</ul>
<?php
}
?>
<script type="text/javascript">
$(function()
{
  var vFuncUpdateNameType = function( vList )
  {
    var vValue = $.map($(vList).find('li:has(input:checked)'),
                       function(v){return $(v).attr('data-value')}).join('')
    vList.siblings('input').val(vValue)
    var vBranchedFields = $(vList).closest('table').find('[data-type]')
    vBranchedFields.each( function()
    {
      var vRow = $(this)
      if ( vValue.includes( vRow.attr('data-type') ) )
      {
        vRow.css('display','')
      }
      else
      {
        vRow.css('display','none')
      }
    })
    if ( vValue.includes('G') )
    {
      vBranchedFields.find('.choose-dag-format').change()
    }
  }
  $('head').append('<style type="text/css">.multiselect{margin-bottom:3px;padding:0px}' +
                   '.multiselect li{display:inline-block;cursor:grab;border:solid 1px #000;' +
                   'background:#eee;margin-right:5px;padding:4px;font-size:small}</style>')
  $('#modsettings').tabs()
  $('.multiselect').sortable({"update":function(){ vFuncUpdateNameType($(this)) }})
  $('.multiselect :checkbox').click(function(){ vFuncUpdateNameType($(this).closest('ul')) })
  $('.choose-dag-format').each(function()
  {
    var vSelect = $(this)
    var vTr = vSelect.closest('tr')
    var vTrRegex = vTr.next()
    var vTxtRegex = vTrRegex.find('input')
    var vTrSubp = vTrRegex.next()
    var vTxtSubp = vTrSubp.find('input')
    var vValue = vTxtRegex.val()
    if ( vValue != '' )
    {
      vSelect.val( vValue )
      if ( vSelect.val() == null || vTxtSubp.val() != '1' )
      {
        vSelect.val( ':' )
      }
    }
  })
  $('.choose-dag-format').change(function()
  {
    var vSelect = $(this)
    var vValue = vSelect.val()
    var vTr = vSelect.closest('tr')
    var vTrRegex = vTr.next()
    var vTxtRegex = vTrRegex.find('input')
    var vTrSubp = vTrRegex.next()
    var vTxtSubp = vTrSubp.find('input')
    if ( vValue == ':' )
    {
      vTrRegex.css('display','')
      vTrSubp.css('display','')
    }
    else
    {
      vTrRegex.css('display','none')
      vTrSubp.css('display','none')
    }
    if ( vValue == '' )
    {
      vTxtRegex.val('')
      vTxtSubp.val('')
    }
    else if ( vValue != ':' )
    {
      vTxtRegex.val( vValue )
      vTxtSubp.val( '1' )
    }
  })
  $('.choose-dag-format').change()
  $('.choose-general-dag-format').each(function()
  {
    var vSelect = $(this)
    var vTr = vSelect.closest('tr')
    var vTrRegex = vTr.next()
    var vTxtRegex = vTrRegex.find('input')
    var vValue = vTxtRegex.val()
    if ( vValue != '' )
    {
      vSelect.val( vValue )
      if ( vSelect.val() == null )
      {
        vSelect.val( ':' )
      }
    }
  })
  $('.choose-general-dag-format').change(function()
  {
    var vSelect = $(this)
    var vValue = vSelect.val()
    var vTr = vSelect.closest('tr')
    var vTrRegex = vTr.next()
    var vTxtRegex = vTrRegex.find('input')
    if ( vValue == ':' )
    {
      vTrRegex.css('display','')
    }
    else
    {
      vTrRegex.css('display','none')
    }
    if ( vValue == '' )
    {
      vTxtRegex.val('')
    }
    else if ( vValue != ':' )
    {
      vTxtRegex.val( vValue )
    }
  })
  $('.choose-general-dag-format').change()
  $('.multiselect').each(function(){ vFuncUpdateNameType($(this)) })
  var vDoFormSubmit = false
  $('#customrecordnaming_form').submit( function( vEvent )
  {
    if ( ! vDoFormSubmit )
    {
      vEvent.preventDefault()
      var vForm = $(this)
      vForm.find('input[type="submit"]').prop('disabled', true)
      var vData = vForm.serialize() + '&check_only=1'
      $.post( '', vData,
              function( vResponse )
              {
                if ( vResponse === null )
                {
                  vDoFormSubmit = true
                  vForm.submit()
                  return
                }
                vForm.find('input[type="submit"]').prop('disabled', false)
                $('<div><pre>' + vResponse + '</pre></div>').dialog({width:"65%",modal:true})
              },
              'json' )
    }
  })
})
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
