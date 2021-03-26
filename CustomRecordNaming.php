<?php

namespace Nottingham\CustomRecordNaming;


class CustomRecordNaming extends \ExternalModules\AbstractExternalModule
{



	function redcap_every_page_before_render( $project_id )
	{
		if ( !$project_id )
		{
			return;
		}

		$this->canAddRecord = true;
		$this->hasSettingsForArm = true;
		$this->userGroup = null;
		$this->groupCode = null;

		// Perform a redirect when a new record is created to use the appropriate participant ID.
		if ( defined( 'PROJECT_ID' ) && defined( 'USERID' ) &&
			 substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 10 ) == 'DataEntry/' )
		{
			// Determine the current DAG and arm.
			$userRights = \REDCap::getUserRights( USERID );
			$userGroup = $userRights[ USERID ][ 'group_id' ]; // group ID or NULL
			$this->userGroup = $userGroup;

			$armNum = isset( $_GET['arm'] ) ? $_GET['arm'] : 1;
			$armID = $this->getArmIdFromNum( $armNum ); // arm ID or NULL

			// If record numbering is based on Data Access Groups (DAGs), then the user must be in
			// a DAG in order to create a record.
			if ( $armID === null ||
			     ( $userGroup === null &&
			       in_array( $this->getProjectSetting( 'numbering' ), [ 'G', 'AG' ] ) ) )
			{
				$this->canAddRecord = false;
			}
			// Check that the settings have been completed for the chosen arm. If there is no
			// naming scheme for the arm, then a record cannot be created.
			else
			{
				$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
				if ( in_array( $armID, $listSettingArmIDs ) )
				{
					$armSettingID = array_search( $armID, $listSettingArmIDs );
				}
				else
				{
					$this->canAddRecord = false;
					$this->hasSettingsForArm = false;
				}
			}

			// Get the scheme DAG format and check the current DAG matches.
			if ( $this->canAddRecord )
			{
				$listGroups = \REDCap::getGroupNames( false );
				$groupName = $listGroups[ $userGroup ];
				$dagFormat = $this->getProjectSetting( 'scheme-dag-format' )[ $armSettingID ];
				$dagFormat = $this->makePcreString( $dagFormat );
				if ( preg_match( $dagFormat, $groupName, $dagMatches ) )
				{
					$dagSection = $this->getProjectSetting( 'scheme-dag-section' )[ $armSettingID ];
					if ( ! isset( $dagMatches[ $dagSection ] ) )
					{
						$dagSection = 0;
					}
					$groupCode = $dagMatches[ $dagSection ];
					$this->groupCode = $groupCode;
				}
				else
				{
					$this->canAddRecord = false;
				}
			}

			// When an ID is assigned to the record (whether by this module or REDCap), tell REDCap
			// that auto incrementing record IDs are not being used. This ensures that the 'auto'
			// query string parameter is not re-inserted during a redirect.
			if ( $this->canAddRecord && isset( $_GET[ 'id' ] ) )
			{
				$GLOBALS[ 'auto_inc_set' ] = 0;
			}

			// The presence of the 'auto' query string parameter indicates that the REDCap assigned
			// record ID is in use. This will need to be replaced by the module generated record ID.
			if ( isset( $_GET[ 'auto' ] ) )
			{
				// If the record cannot be created, redirect back to the add/edit records page.
				// This shouldn't usually be invoked, as the add new record button will be replaced
				// with explanatory text.
				if ( ! $this->canAddRecord )
				{
					$this->redirect( PAGE_FULL . '?pid=' . PROJECT_ID );
					return;
				}

				// Determine whether the project currently has records.
				$hasRecords = count( \REDCap::getData( 'array' ) ) > 0;

				// If the project does not currently have any records, the module project settings
				// which keep track of the record number(s) are reset to blank values. This ensures
				// that numbering always starts from the beginning even if the project previously
				// contained records (e.g. development records which were cleared when placing the
				// project into production status).
				if ( ! $hasRecords )
				{
					// Clear the record counter.
					$this->setProjectSetting( 'project-record-counter', '{}' );
					// Clear the last created record.
					$this->setProjectSetting( 'project-last-record', '{}' );
				}

				// Generate the new record name.
				$recordName = $this->generateRecordName( $armID, $armSettingID, $groupCode );

				// Regenerate the URL query string using the new record name and removing the 'auto'
				// parameter, and perform a redirect to the new URL.
				$queryString = '';
				foreach ( $_GET as $name => $val )
				{
					if ( $name == 'auto' )
					{
						continue;
					}
					$queryString .= ( $queryString == '' ? '?' : '&' );
					$queryString .= rawurlencode( $name ) . '=';
					if ( $name == 'id' )
					{
						$queryString .= rawurlencode( $recordName );
					}
					else
					{
						$queryString .= rawurlencode( $val );
					}
				}
				$this->redirect( PAGE_FULL . $queryString );
			}
		}

	}



	function redcap_every_page_top( $project_id )
	{
		if ( !$project_id )
		{
			return;
		}


		// On the DAGs page, use the DAG format restriction to constrain how DAGs can be named,
		// and/or display the defined notice explaining how to name DAGs.
		$dagFormat = $this->getProjectSetting( 'dag-format' );
		$dagFormatNotice = $this->getProjectSetting( 'dag-format-notice' );
		if ( ( $dagFormat != '' || $dagFormatNotice != '' ) &&
		     substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 17 ) == 'DataAccessGroups/' )
		{
			$dagFormatErrorText = 'The DAG name you entered does not conform to the allowed' .
			                      ' DAG name format.';
			if ( $dagFormatNotice != '' )
			{
				$dagFormatErrorText .= '\n\nPlease use a DAG name which conforms to the format' .
				                       ' described on the DAGs page.';
			}

?>
<script type="text/javascript">
  $(function() {
<?php

			// Prevent creating/renaming DAGs where the DAG name does not conform to the format.
			if ( $dagFormat != '' )
			{
				$dagFormatJS = addslashes( $dagFormat );

?>
    var vDAGRegex = new RegExp( '<?php echo $dagFormatJS; ?>' )
    var vFuncAddGroup = add_group
    var vDoneEnter = false
    add_group = function()
    {
      if ( $( '#new_group' ).val() != 'Enter new group name' )
      {
        if ( vDAGRegex.test( $( '#new_group' ).val() ) )
        {
          vFuncAddGroup()
        }
        else
        {
          alert( '<?php echo $dagFormatErrorText; ?>' )
        }
      }
    }
    var vFuncFieldEnter = fieldEnter
    fieldEnter = function ( field, evt, idfld )
    {
      evt = (evt) ? evt : window.event
      if ( evt.keyCode == 13 )
      {
        vDoneEnter = true
        if ( field.value != '' && ! vDAGRegex.test( field.value ) )
        {
          alert( '<?php echo $dagFormatErrorText; ?>' )
          field.focus()
          field = document.createElement( 'input' )
        }
      }
      else
      {
        vDoneEnter = false
      }
      return vFuncFieldEnter( field, evt, idfld )
    }
    var vFuncFieldBlur = fieldBlur
    fieldBlur = function ( field, idfld )
    {
      if ( field.value != '' && ! vDAGRegex.test( field.value ) )
      {
        if ( ! vDoneEnter )
        {
          alert( '<?php echo $dagFormatErrorText; ?>' )
        }
        vDoneEnter = true
        var vFocusField = field
        setTimeout( function() { vFocusField.focus() }, 300 )
        field = document.createElement( 'input' )
      }
      return vFuncFieldBlur( field, idfld )
    }
<?php

			}

			// Add a notice to the DAGs page. This can be used to explain how to format DAG names.
			if ( $dagFormatNotice != '' )
			{
				$dagFormatNotice =
					preg_replace( '/&lt;b&gt;(.*?)&lt;\/b&gt;/', '<b style="font-size:14px">$1</b>',
						          preg_replace( '/&lt;a href="([^"]*)"( target="_blank")?' .
						                        '&gt;(.*?)&lt;\/a&gt;/',
						                        '<a href="$1"$2>$3</a>',
						                        htmlspecialchars( $dagFormatNotice,
						                                          ENT_NOQUOTES ) ) );
				$dagFormatNotice = str_replace( "\n", '<br>', $dagFormatNotice );
				$dagFormatNotice = '<img src="' . APP_PATH_WEBROOT .
					               '/Resources/images/exclamation_orange.png"> ' . $dagFormatNotice;
				$dagFormatNotice = addslashes( $dagFormatNotice );

?>
    var vListTable = $( '#dags_table tr' )
    for ( var i = 0; i < vListTable.length; i++ )
    {
      vListTable[ i ].children[ 4 ].style.display = 'none'
    }
    $( '#dags_table' )[ 0 ].style.width = '638px'
    $( '<div class="yellow"><?php echo $dagFormatNotice; ?></div>' ).insertBefore( '#group_table' )
<?php

			}

?>
  })
</script>
<?php

		} // End DAGs page content.



		// Remove the 'add new record' button from the Add/Edit Records page, where the user is not
		// currently in a valid DAG, or where the selected arm does not have a naming scheme.
		// A brief explanation of why a new record cannot be added is displayed instead.
		if ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 25 ) == 'DataEntry/record_home.php' &&
			 ! $this->canAddRecord )
		{
?>
<script type="text/javascript">
  $(function() {
    var vListButton = $( 'button' )
    for ( var i = 0; i < vListButton.length; i++ )
    {
      if ( vListButton[ i ].innerText.trim() == 'Add new record' ||
           vListButton[ i ].innerText.trim() == 'Add new record for the arm selected above' )
      {
        vListButton[ i ].style.display = 'none'
<?php
			if ( $this->hasSettingsForArm )
			{
?>
        $( '<i>(You must be in a valid Data Access Group to add records)</i>'
               ).insertBefore( vListButton[ i ] )
<?php
			}
			else
			{
?>
        $( '<i>(Record numbering has not been set up for the current arm)</i>'
               ).insertBefore( vListButton[ i ] )
<?php
			}
?>
        break
      }
    }
  })
</script>
<?php
		} // End Add/Edit Records page content.



		// On the data entry form, if the record is new (no data saved yet), if the user is in a
		// DAG and the 'assign record to DAG' drop down is present (this likely only applies to
		// administrators) then ensure the drop down is set to the user's DAG.
		// Send an ajax request to the ajax_keepalive.php file every 30 seconds, so that the
		// record ID is reserved while the form is being completed. If the user navigates away from
		// the form without saving, the record ID can be reused after 90 seconds (unless the next
		// record ID has already been used or reserved).
		if ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 19 ) == 'DataEntry/index.php' &&
			 isset( $_GET[ 'id' ] ) && count( \REDCap::getData( 'array', $_GET[ 'id' ] ) ) == 0 )
		{

?>
<script type="text/javascript">
  $(function() {
<?php

			if ( $this->userGroup !== null )
			{

?>
    var vDAGSelect = $('select[name=__GROUPID__]')
    if ( vDAGSelect.length == 1 && vDAGSelect[0].value == '' )
    {
      vDAGSelect[0].value = '<?php echo $this->userGroup; ?>'
    }
<?php

			}

?>
    var vFuncKeepAlive = function ()
    {
      $.ajax( { url : '<?php echo $this->getUrl( 'ajax_keepalive.php' ); ?>',
                method : 'POST',
                data : { record : '<?php echo addslashes( $_GET['id'] ); ?>',
                         arm : '<?php echo $this->getArmIdFromNum( $_GET['arm'] ?? 1 ); ?>',
                         dag : '<?php echo $this->groupCode ?? ''; ?>' },
                headers : { 'X-RC-CRN-Req' : '1' },
                dataType : 'json'
              } )
    }
    vFuncKeepAlive()
    setInterval( vFuncKeepAlive, 30000 )
  })
</script>
<?php

		} // End data entry form content.
	}



	// Validation for the module settings.
	public function validateSettings( $settings )
	{
		if ( $this->getProjectID() === null )
		{
			return null;
		}

		$errMsg = '';

		// If the DAG name restriction is specified, check it is a valid regular expression.
		if ( $settings['dag-format'] != '' &&
		     preg_match( $this->makePcreString( $settings['dag-format'] ), '' ) === false )
		{
			$errMsg .= "\n- Invalid regular expression for restrict DAG name format";
		}

		// Ensure that a setting is present for record numbering. This setting cannot be changed
		// once records exist, as the record counter(s) would then be invalid.
		if ( ! isset( $settings['numbering'] ) || $settings['numbering'] == '' )
		{
			$errMsg .= "\n- Value required for record numbering";
		}
		elseif ( $settings['numbering'] != $this->getProjectSetting( 'numbering' ) &&
		         count( \REDCap::getData( 'array' ) ) > 0 )
		{
			$errMsg .= "\n- Record numbering cannot be changed once records exist";
		}

		// Validate the settings for each custom naming scheme.
		// There should be a 1-1 mapping  between naming schemes and arms.
		$definedArms = [];
		for ( $i = 0; $i < count( $settings['scheme-settings'] ); $i++ )
		{
			// Check the arm is specified and has not already had a scheme defined for it.
			if ( $settings['scheme-arm'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) . ": Value required for target arm";
			}
			elseif ( in_array( $settings['scheme-arm'][$i], $definedArms ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) . ": Scheme already defined for arm";
			}
			$definedArms[] = $settings['scheme-arm'][$i];

			// Ensure that the record name type has been set, and that it includes the DAG if per
			// DAG numbering is being used.
			if ( ! isset( $settings['scheme-name-type'][$i] ) ||
			     $settings['scheme-name-type'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Value required for record name type";
			}
			elseif ( $settings['scheme-name-type'][$i] == 'R' &&
			         in_array( $settings['numbering'], [ 'G', 'AG' ] ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) . ": Record name type" .
				           " cannot be record number only if per DAG numbering used";
			}

			// Ensure that the starting number, if set, is a positive integer.
			if ( $settings['scheme-number-start'][$i] != '' &&
			     ! preg_match( '/^[1-9][0-9]*$/', $settings['scheme-number-start'][$i] ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Starting number must be a positive integer";
			}

			// Validate the DAG name format for the naming scheme. This is required if the record
			// name includes the DAG. If the name format is specified then the subpattern must also
			// be specified, otherwise neither can be specified. The subpattern must point to a
			// valid subpattern within the name format regular expression.
			if ( $settings['scheme-dag-format'][$i] == '' &&
			     in_array( $settings['scheme-name-type'][$i], [ 'GR', 'RG' ] ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Value required for DAG name format";
			}
			elseif ( $settings['scheme-dag-format'][$i] != '' &&
			         preg_match( $this->makePcreString( $settings['scheme-dag-format'][$i] ),
			                     '' ) === false )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Invalid regular expression for DAG name format";
			}
			elseif ( $settings['scheme-dag-format'][$i] == '' &&
			         $settings['scheme-dag-section'][$i] != '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": DAG format subpattern specified but DAG name format not specified";
			}
			elseif ( $settings['scheme-dag-format'][$i] != '' &&
			         $settings['scheme-dag-section'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": DAG name format specified but DAG format subpattern not specified";
			}
			elseif ( $settings['scheme-dag-section'][$i] != '' &&
			         ! preg_match( '/^0|[1-9][0-9]*$/', $settings['scheme-dag-section'][$i] ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": DAG format subpattern must be an integer";
			}
			elseif ( $settings['scheme-dag-section'][$i] >
			         substr_count( str_replace( '\(', '',
			                                    $settings['scheme-dag-format'][$i] ), '(' ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Specified DAG format subpattern greater than number of subpatterns";
			}

			// If per arm numbering is used, then the naming scheme must have at least one of
			// prefix, separator or suffix. This is to help ensure that record names for each arm
			// are different and thus won't clash.
			if ( $settings['scheme-name-prefix'][$i] . $settings['scheme-name-separator'][$i] .
			     $settings['scheme-name-suffix'][$i] == '' &&
			     in_array( $settings['numbering'], [ 'A', 'AG' ] ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) . ": Value required for at least" .
				           " one of prefix, separator or suffix if per arm numbering used";
			}

		}

		if ( $errMsg != '' )
		{
			return "Your record naming configuration contains errors:$errMsg";
		}

		return null;
	}



	// Generate a new record name.
	protected function generateRecordName( $armID, $armSettingID, $groupCode )
	{
		// TODO: consider computing armSettingID from armID within this function

		// Get the scheme settings for the arm.
		$numbering = $this->getProjectSetting( 'numbering' );
		$nameType = $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ];
		$startNum = $this->getProjectSetting( 'scheme-number-start' )[ $armSettingID ];
		$zeroPad = $this->getProjectSetting( 'scheme-number-pad' )[ $armSettingID ];
		$namePrefix = $this->getProjectSetting( 'scheme-name-prefix' )[ $armSettingID ];
		$nameSeparator = $this->getProjectSetting( 'scheme-name-separator' )[ $armSettingID ];
		$nameSuffix = $this->getProjectSetting( 'scheme-name-suffix' )[ $armSettingID ];

		// Determine the record number using the record counter.
		$counterID = 'project';
		if ( $numbering == 'A' )
		{
			$counterID = "$armID";
		}
		elseif ( $numbering == 'G' )
		{
			$counterID = "$groupCode";
		}
		elseif ( $numbering == 'AG' )
		{
			$counterID = "$armID/$groupCode";
		}
		$recordCounter = json_decode( $this->getProjectSetting( 'project-record-counter' ), true );
		$lastRecord = json_decode( $this->getProjectSetting( 'project-last-record' ), true );

		// If the record counter has not been started yet, set to the starting number.
		if ( ! isset( $recordCounter[ $counterID ] ) )
		{
			if ( $startNum == '' )
			{
				$recordCounter[ $counterID ] = 1;
			}
			else
			{
				$recordCounter[ $counterID ] = intval( $startNum );
			}
			$lastRecord[ $counterID ] = [ 'name' => '', 'timestamp' => 0, 'user' => '' ];
			$this->setProjectSetting( 'project-record-counter', json_encode( $recordCounter ) );
			$this->setProjectSetting( 'project-last-record', json_encode( $lastRecord ) );
		}

		// Check if the last record was successfully created, and increment the record counter if
		// so. Treat the last record as created if the timestamp value is within the last 90
		// seconds. This ensures that if 2 users add records at roughly the same time, that
		// different record names will be assigned. A 'keepalive' script will update the timestamp
		// while data for the named record is being entered until the record has been saved.
		$currentUser = ( USERID == '[survey respondent]' ? '' : USERID );
		if ( $lastRecord[ $counterID ][ 'timestamp' ] > 0 &&
		     ( ( $lastRecord[ $counterID ][ 'timestamp' ] > time() - 90 &&
		         ( $currentUser == '' || $currentUser != $lastRecord[ $counterID ][ 'user' ] ) ) ||
		       count( \REDCap::getData( 'array', $lastRecord[ $counterID ][ 'name' ] ) ) > 0 ) )
		{
			$recordCounter[ $counterID ]++;
		}

		// Create the record name.
		// - Start by taking the value from the record counter.
		$recordName = $recordCounter[ $counterID ];
		// - If applicable, left pad the record number with zeros to make it the set number
		//   of digits.
		if ( $zeroPad != '' )
		{
			$recordName = str_pad( $recordName, $zeroPad, '0', STR_PAD_LEFT );
		}
		// - If (part of) the DAG name is to be included, prepend or append it to the
		//   record number along with the separator.
		if ( $nameType == 'GR' )
		{
			$recordName = $groupCode . $nameSeparator . $recordName;
		}
		elseif ( $nameType == 'RG' )
		{
			$recordName .= $nameSeparator . $groupCode;
		}
		// - Prepend the prefix and append the suffix to the record name.
		$recordName = $namePrefix . $recordName . $nameSuffix;

		// TODO: add check that recordName does not already exist

		// Set the new record counter and last record values.
		$lastRecord[ $counterID ] = [ 'name' => $recordName, 'timestamp' => time(),
		                              'user' => $currentUser ];
		$this->setProjectSetting( 'project-record-counter', json_encode( $recordCounter ) );
		$this->setProjectSetting( 'project-last-record', json_encode( $lastRecord ) );

		return $recordName;

	}



	// Perform a redirect within a hook.
	// Note that it may be necessary to return from the hook after calling this function.
	protected function redirect( $url )
	{
		header( 'Location: ' . $url );
		$this->exitAfterHook();
	}



	// Convert a regular expression without delimiters to one with delimiters.
	private function makePcreString( $str )
	{
		foreach ( str_split( '/!#$%&,-.:;@|~' ) as $chr )
		{
			if ( strpos( $str, $chr ) === false )
			{
				return $chr . $str . $chr;
			}
		}
	}



	// Given an arm number for the project, return the (server-wide) arm ID number.
	function getArmIdFromNum( $num )
	{
		if ( !is_array( $this->listArmIdNum ) )
		{
			$this->listArmIdNum = [];
			if ( defined( 'PROJECT_ID' ) )
			{
				$res = $this->query( 'SELECT arm_id, arm_num FROM redcap_events_arms' .
					                 " WHERE project_id = '" . PROJECT_ID . "'" );
				foreach ( $res as $item )
				{
					$this->listArmIdNum[ $item['arm_num'] ] = $item['arm_id'];
				}
			}
		}
		if ( ! isset( $this->listArmIdNum[ $num ] ) )
		{
			return null;
		}
		return $this->listArmIdNum[ $num ];
	}



	private $canAddParticipant;
	private $hasSettingsForArm;
	private $listArmIdNum;
	private $userGroup;
	private $groupCode;

}

