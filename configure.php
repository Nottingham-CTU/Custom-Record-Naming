<?php

namespace Nottingham\CustomRecordNaming;


if ( ! $module->canConfigure() )
{
	exit;
}

$listRecordNameTypes = $module->getListRecordNameTypes();

$listRecordNumberingTypes = [ 'A' => $module->tt('setting_scheme_numbering_arm') ] +
                            array_diff_key( $module->getListRecordNameTypes(),
                                            [ 'R' => true, 'C' => true, '1' => true ] );

$listArms = $module->getArms();
$listEvents = \REDCap::getEventNames( false, false );
$listEventsUN = \REDCap::getEventNames( true, false );
$listInstruments = \REDCap::getInstrumentNames();

$listEventInstruments = [];
foreach ( $listArms as $armID => $armName )
{
	$listArmEventInstruments = [];
	foreach ( $module->getInstrumentEventMapping( $armID ) as $eventInstrument )
	{
		$listArmEventInstruments[ $listEventsUN[ $eventInstrument['event_id'] ] . ':' .
		                                                    $eventInstrument['instrument'] ] =
				$listEvents[ $eventInstrument['event_id'] ] . ' : ' .
				$listInstruments[ $eventInstrument['instrument'] ];
	}
	$listEventInstruments[ $armID ] = $listArmEventInstruments;
}

// Extract the module settings from the config.json file.
$projectSettingsConfig = $module->getConfig()['project-settings'];
$listProjectSettings = [];
$listArmSettings = [];
foreach ( $projectSettingsConfig as $settingConfig )
{
	if ( $settingConfig['type'] == 'descriptive' ||
	     $settingConfig['key'] == 'project-record-counter' )
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
		$module->echoText( json_encode( $validationErrors ) );
		exit;
	}
	// Apply the settings if no validation errors, then redirect.
	if ( $validationErrors === null )
	{
		foreach ( $_POST as $field => $value )
		{
			if ( isset( $listProjectSettings[ $field ] ) || isset( $listArmSettings[ $field ] ) ||
			     $field == 'scheme-settings' )
			{
				$module->setProjectSetting( $field, $value );
			}
		}
	}
	header( 'Location: ' . $_SERVER['REQUEST_URI'] );
	exit;
}

// Function to make a setting field for the configuration page.
function makeSettingRow( $field, $name, $type, $choices, $value )
{
	global $module;
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
	elseif ( strpos( $field, 'public-survey-logic' ) !== false )
	{
		$row .= ' data-type="S"';
	}
	elseif ( strpos( $field, 'field-lookup' ) !== false )
	{
		$row .= ' data-type="F"';
	}
	elseif ( strpos( $field, 'check-digit' ) !== false )
	{
		$row .= ' data-type="C"';
	}
	elseif ( $field == 'scheme-const1[]' )
	{
		$row .= ' data-type="1"';
	}
	elseif ( $field == 'scheme-name-separator[]' )
	{
		$row .= ' data-separator="1"';
	}
	$row .= '><td style="width:0px;min-width:25%;white-space:nowrap;padding:10px 15px 10px 0px">' .
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
		if ( $type == 'dropdown' || ( $value == '' && ! array_key_exists( '', $choices ) ) )
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
	elseif ( $type == 'checkboxes' )
	{
		$row .= '<span class="checkboxes" data-field="' . $field . '">';
		foreach ( $choices as $choiceVal => $choiceLabel )
		{
			$row .= '<label><input type="checkbox" data-value="' . $choiceVal . '"' .
			        ( strpos( $value, $choiceVal) === false ? '' : ' checked' ) . '> ' .
			        htmlspecialchars( $choiceLabel ) . '</label> ';
		}
		$row .= '</span><input type="hidden" name="' . $field . '" value="' . $value . '">';
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
		        $module->tt('multi_select_drag');
	}
	$row .= '</td></tr>';
	return $row;
}

// Get the arms with existing records.
$queryNonEmptyArms = $module->query( 'SELECT arm_id, count(*) AS num ' .
                                     'FROM redcap_record_list AS rl ' .
                                     'JOIN redcap_events_arms AS ea ' .
                                     'ON rl.project_id = ea.project_id AND rl.arm = ea.arm_num ' .
                                     'WHERE rl.project_id = ? GROUP BY arm_id',
                                     [ $module->getProjectId() ] );
$listNonEmptyArms = [];
while ( $res = $queryNonEmptyArms->fetch_assoc() )
{
	if ( $res['num'] > 0 )
	{
		$listNonEmptyArms[ $res['arm_id'] ] = intval( $res['num'] );
	}
}

// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';


?>
<div class="projhdr"><i class="fas fa-list-ul"></i> <?php echo $module->tt('module_name'); ?></div>
<form method="post" id="customrecordnaming_form">
 <div id="modsettings" style="width:97%">
  <ul>
   <li><a href="#modsettings_general"><?php echo $module->tt('general'); ?></a></li>
<?php

foreach ( $listArms as $armID => $armName )
{

?>
   <li>
    <a href="#modsettings_arm<?php
	echo intval( $armID ); ?>"><?php echo $module->escape( $armName ); ?></a>
   </li>
<?php

}

?>
  </ul>
  <div id="modsettings_general">
   <table style="width:100%">
<?php

// Output general settings.
foreach ( $listProjectSettings as $fieldName => $setting )
{
	if ( $fieldName == 'reserved-hide-from-non-admins-in-project-list' )
	{
		continue;
	}
	if ( $fieldName == 'dag-format' )
	{
		// Provide a dropdown for DAG format restriction to auto-fill regex field.
		echo '<tr><td style="padding:10px 0px 10px 0px">', $module->tt('setting_dag_format_sel'),
		     '</td><td style="padding:10px 0px 10px 0px"><select class="choose-general-dag-format">',
		     '<option value=""></option>',
		     '<option value="^[0-9]+[^0-9]">', $module->tt('dag_format_numprefixr'), '</option>',
		     '<option value="^[0-9]+[ ]">', $module->tt('dag_format_numprefixs'), '</option>',
		     '<option value="^[0-9]{2}[^0-9]">', $module->tt('dag_format_numprefixf', '2'), '</option>',
		     '<option value="^[0-9]{2}[ ]">', $module->tt('dag_format_numprefixfs', '2'), '</option>',
		     '<option value="^[0-9]{3}[^0-9]">', $module->tt('dag_format_numprefixf', '3'), '</option>',
		     '<option value="^[0-9]{3}[ ]">', $module->tt('dag_format_numprefixfs', '3'), '</option>',
		     '<option value="^[0-9]{4}[^0-9]">', $module->tt('dag_format_numprefixf', '4'), '</option>',
		     '<option value="^[0-9]{4}[ ]">', $module->tt('dag_format_numprefixfs', '4'), '</option>',
		     '<option value="^[0-9]{5}[^0-9]">', $module->tt('dag_format_numprefixf', '5'), '</option>',
		     '<option value="^[0-9]{5}[ ]">', $module->tt('dag_format_numprefixfs', '5'), '</option>',
		     '<option value="^[A-Za-z0-9]{2}[ ]">',
		     $module->tt('dag_format_charprefixs', '2'), '</option>',
		     '<option value="^[A-Z]{2}[ ]">', $module->tt('dag_format_charprefixus', '2'), '</option>',
		     '<option value="^[A-Za-z0-9]{3}[ ]">',
		     $module->tt('dag_format_charprefixs', '3'), '</option>',
		     '<option value="^[A-Z]{3}[ ]">', $module->tt('dag_format_charprefixus', '3'), '</option>',
		     '<option value=":">', $module->tt('dag_format_custom'), '</option></select></td></tr>';
	}
	if ( $fieldName == 'reserved-language-project' )
	{
		$setting['name'] = preg_replace( '|<b>.*?</b>.*?<br>|', '', $setting['name'] );
	}
	echo makeSettingRow( $fieldName, $setting['name'], $setting['type'],
	                     $setting['choices'], $setting['value'] );
}

?>
   </table>
  </div>
<?php

// Output arm settings.
$firstArm = true;
foreach ( $listArms as $armID => $armName )
{
	$valueIndex = false;
	if ( is_array( $listArmSettings['scheme-arm']['value'] ) )
	{
		$valueIndex = array_search( $armID, $listArmSettings['scheme-arm']['value'] );
	}

?>
  <div id="modsettings_arm<?php echo intval( $armID ); ?>">
   <input type="hidden" name="scheme-settings[]" value="true">
   <input type="hidden" name="scheme-arm[]" value="<?php echo intval( $armID ); ?>">
<?php

	// Display a warning if the arm already contains records.
	if ( isset( $listNonEmptyArms[ $armID ] ) )
	{

?>
   <div class="yellow" style="max-width:100%">
    <img src="<?php echo APP_PATH_WEBROOT; ?>/Resources/images/exclamation_orange.png">
    <?php echo $module->tt( 'arm_contains_record' . ( $listNonEmptyArms[ $armID ] == 1 ? '' : 's' ),
                            $listNonEmptyArms[ $armID ] ), "\n"; ?>
   </div>
<?php

	}

?>
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
			// The scheme name type is a drag-and-drop 'multiselect' field.
			$fieldType = 'multiselect';
			$fieldChoices = $listRecordNameTypes;
		}
		elseif ( $fieldName == 'scheme-numbering' )
		{
			// The scheme numbering is a checkboxes field.
			$fieldType = 'checkboxes';
			$fieldChoices = $listRecordNumberingTypes;
		}
		elseif ( $fieldName == 'scheme-number-start' || $fieldName == 'scheme-dag-section' )
		{
			// Numeric fields have a 'number' type.
			$fieldType = 'number';
		}
		elseif ( $fieldName == 'scheme-number-pad' )
		{
			foreach ( $fieldChoices as $k => $v )
			{
				if ( preg_match( '/^[0-9]+ digits$/', $v ) )
				{
					$fieldChoices[ $k ] = $module->tt( 'setting_scheme_number_pad_digits',
					                                   explode( ' ', $v )[0] );
				}
			}
		}
		elseif ( $fieldName == 'scheme-name-prefix' )
		{
			// Add a note before the prefix, separator and suffix fields.
			echo '<tr><td></td><td style="font-size:x-small">',
			     $module->tt('setting_scheme_name_type_note'), '</td></tr>';
		}
		elseif ( $fieldName == 'scheme-dag-format' )
		{
			// Provide a dropdown for DAG format to auto-fill regex field.
			echo '<tr data-type="G"><td style="padding:10px 0px 10px 0px">',
			     $module->tt('setting_scheme_dag_format_sel'),
			     '</td><td style="padding:10px 0px 10px 0px"><select class="choose-dag-format">',
			     '<option value=""></option><option value="^([^ ]+)[ ]">',
			     $module->tt('dag_format_space'), '</option><option value="^([0-9]+)[^0-9]">',
			     $module->tt('dag_format_numprefix'), '</option><option value=":">',
			     $module->tt('dag_format_custom'), '</option></select></td></tr>';
		}
		elseif ( $fieldName == 'scheme-timestamp-tz' )
		{
			// For the server timestamp option, show the current server timestamp.
			$fieldChoices['S'] .= ' (' . date('e') . ')';
		}
		elseif ( $fieldName == 'scheme-instrument' )
		{
			// Provide the events/instruments dropdown for the data entry form to load after naming.
			$fieldType = 'dropdown';
			$fieldChoices = $listEventInstruments[ $armID ];
		}
		$value = ( $valueIndex === false ? '' : $setting['value'][ $valueIndex ] );
		echo makeSettingRow( $fieldName.'[]', $setting['name'], $fieldType, $fieldChoices, $value );
		if ( $fieldName == 'scheme-name-trigger' )
		{
			// Add a note after the trigger field.
			echo '<tr><td></td><td style="font-size:x-small">',
			     $module->tt('setting_scheme_name_trigger_note'), '</td></tr>';
		}
		elseif ( strpos( $fieldName, 'scheme-const' ) === 0 )
		{
			// Add a note after the constant field.
			echo '<tr data-type="', substr( $fieldName, 12 ), '"><td></td><td style="',
			     'font-size:x-small">', $module->tt('setting_scheme_const_note'), '</td></tr>';
		}
	}
	if ( $firstArm )
	{
		echo '<tr><td style="padding:10px 0px 10px 0px">', $module->tt('setting_apply_all_arms'),
		     '</td><td style="padding:10px 0px 10px 0px">',
		     '<input type="checkbox" name="apply_all_arms" value="1">';
		if ( ! empty( $listNonEmptyArms ) )
		{
			echo ' &nbsp;&nbsp;<span class="yellow"><img src="', APP_PATH_WEBROOT, '/Resources/',
			     'images/exclamation_orange.png"> ', $module->tt('arms_contain_records'), '</span>';
		}
		echo '</td></tr>';
		$firstArm = false;
	}
?>
   </table>
  </div>
<?php
}
?>
 </div>
 <p style="margin:18px 0">
  <button type="submit" class="btn btn-sm btn-primaryrc">
   <i class="fas fa-save fs14"></i> &nbsp;<?php echo $module->tt('save'), "\n"; ?>
  </button>
 </p>
</form>
<?php
if ( $module->getUser()->isSuperUser() )
{
?>
<p>&nbsp;</p>
<hr style="max-width:300px;margin-left:0px">
<p><b><?php echo $module->tt('admin_opt'); ?></b></p>
<ul>
 <li>
  <?php echo '<a href="', $module->getUrl( 'counter_overview.php' ), '">',
             $module->tt('admin_opt_counter'), "</a>\n"; ?>
 </li>
 <li>
  <?php echo '<a href="', $module->getUrl( 'import_export.php' ), '">',
             $module->tt('admin_opt_imp_exp'), "</a>\n"; ?>
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
    if ( ! vValue.includes( 'C' ) )
    {
      $(vList).closest('table').find('select[name="scheme-check-digit-algorithm[]"]').val('')
    }
    $(vList).closest('table').find('[data-separator]').each( function()
    {
      if ( vValue.length > 1 )
      {
        $(this).css('display','')
      }
      else
      {
        $(this).css('display','none')
      }
    })
    var vNumberingCheckboxSet =
        $(vList).closest('table').find('span[data-field="scheme-numbering[]"]')
    var vNumberingCheckboxes = vNumberingCheckboxSet.find('input[data-value]')
    vNumberingCheckboxes.each( function()
    {
      var vChkbx = $(this)
      if ( vChkbx.attr('data-value') == 'A' || vValue.includes( vChkbx.attr('data-value') ) )
      {
        vChkbx.parent().css('display','')
      }
      else
      {
        vChkbx.prop('checked',false)
        vChkbx.parent().css('display','none')
      }
    })
    vFuncUpdateCheckboxes( vNumberingCheckboxSet )
  }
  var vFuncUpdateCheckboxes = function ( vCheckboxSet )
  {
    var vValue = $.map($(vCheckboxSet).find('input:checked'),
                       function(v){return $(v).attr('data-value')}).join('')
    vCheckboxSet.siblings('input').val(vValue)
  }
  $('head').append('<style type="text/css">.multiselect{margin-bottom:3px;padding:0px}' +
                   '.multiselect li{display:inline-block;cursor:grab;border:solid 1px #000;' +
                   'background:#eee;margin:0px 5px 7px 0px;padding:4px;font-size:small}' +
                   '.ui-tabs .ui-tabs-nav li.ui-tabs-active .ui-tabs-anchor' +
                   '{cursor:default;font-weight:bold;color:#000}' +
                   '.checkboxes label{margin-right:10px}</style>')
  $('#modsettings').tabs()
  $('.multiselect').sortable({"update":function(){ vFuncUpdateNameType($(this)) }})
  $('.multiselect :checkbox').click(function(){ vFuncUpdateNameType($(this).closest('ul')) })
  $('.checkboxes :checkbox').click(function(){ vFuncUpdateCheckboxes($(this).closest('span')) })
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
                vResponse = vResponse.split('\n')
                var vTitle = vResponse[0]
                vResponse = vResponse.slice(1).join('\n')
                vForm.find('input[type="submit"]').prop('disabled', false)
                simpleDialog($('<pre style="background:unset;border:unset"></pre>').text(vResponse),
                             vTitle, null, window.innerWidth*0.65)
              },
              'json' )
    }
  })
<?php
if ( $module->getProjectStatus() != 'DEV' ) // Project placed into production status.
{
?>
  if ( document.referrer.indexOf('custom_record_naming') == -1 )
  {
    $("<div><?php echo $module->tt('prod_warning'); ?></div>").dialog(
    {
      buttons:
      {
        "Go Back" : function() { window.history.back() },
        "Continue" : function() { $(this).dialog('close') }
      },
      modal: true,
      open: function() { $('.ui-dialog-titlebar-close',$(this).closest('.ui-dialog')).hide() },
      title: 'Warning',
      width: 400
    })
  }
<?php
}
?>
})
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
