<?php

namespace Nottingham\CustomRecordNaming;


class CustomRecordNaming extends \ExternalModules\AbstractExternalModule
{


	// Upgrade settings from older module version if required.

	function redcap_module_system_enable()
	{
		$moduleDirPrefix = preg_replace( '/_v[^_]*$/', '', $this->getModuleDirectoryName() );
		// Convert pre v1.4.0 numbering setting to new per-arm format.
		$queryProjects = $this->query( 'SELECT project_id FROM redcap_external_module_settings ' .
		                               'WHERE external_module_id = (SELECT external_module_id ' .
		                               'FROM redcap_external_modules WHERE directory_prefix = ?) ' .
		                               'AND `key` = ?', [ $moduleDirPrefix, 'numbering' ] );
		$listProjects = [];
		while ( $infoProject = $queryProjects->fetch_assoc() )
		{
			$listProjects[] = $infoProject['project_id'];
		}
		foreach ( $listProjects as $projectID )
		{
			$numbering = $this->getProjectSetting( 'numbering', $projectID );
			$schemeNameTypes = $this->getProjectSetting( 'scheme-name-type', $projectID );
			$schemeNumbering = $this->getProjectSetting( 'scheme-arm', $projectID );
			$schemeSettings = [];
			for ( $i = 0; $i < count( $schemeNumbering ); $i++ )
			{
				$schemeRemove = ['P','?'];
				if ( strpos( $schemeNameTypes[$i], 'G' ) === false )
				{
					$schemeRemove[] = 'G';
				}
				if ( strpos( $schemeNameTypes[$i], 'F' ) === false )
				{
					$schemeRemove[] = 'F';
				}
				$schemeNumbering[$i] = str_replace( $schemeRemove, '', $numbering );
				$schemeSettings[] = 'true';
			}
			$this->setProjectSetting( 'scheme-numbering', $schemeNumbering, $projectID );
			$this->setProjectSetting( 'scheme-settings', $schemeSettings, $projectID );
			$this->removeProjectSetting( 'numbering', $projectID );
			$this->removeProjectSetting( 'project-last-record', $projectID );
		}
	}


	// Determine whether link to module configuration is shown.
	function redcap_module_link_check_display( $project_id, $link )
	{
		if ( $this->canConfigure() )
		{
			return $link;
		}
		return null;
	}


	// Always hide the button for the default REDCap module configuration interface.
	function redcap_module_configure_button_display()
	{
		return ( $this->getProjectId() === null ) ? true : null;
	}



	function redcap_every_page_before_render( $project_id )
	{
		if ( !$project_id )
		{
			return;
		}

		// If the REDCap UI Tweaker module is enabled, instruct the external modules simplified view
		// to exclude state tracking settings.
		if ( $this->isModuleEnabled('redcap_ui_tweaker') )
		{
			$moduleDirPrefix = preg_replace( '/_v[^_]*$/', '', $this->getModuleDirectoryName() );
			$UITweaker = \ExternalModules\ExternalModules::getModuleInstance('redcap_ui_tweaker');
			if ( method_exists( $UITweaker, 'areExtModFuncExpected' ) &&
			     $UITweaker->areExtModFuncExpected() )
			{
				$UITweaker->addExtModFunc( $moduleDirPrefix, function( $data )
				{
					if ( in_array( $data['setting'],
					               [ 'scheme-settings', 'scheme-arm', 'project-last-record',
					                 'project-record-counter' ] ) || $data['value'] == '' ||
					     preg_match( '/^\[""(,"")*\]$/', $data['value'] ) )
					{
						return false;
					}
					return true;
				});
			}
		}

		// For survey pages, check if a 'dag' query string parameter is specified and if so set a
		// cookie to match (in case the parameter is dropped during the submission process).
		if ( $this->isSurveyPage() && isset( $_GET['_dag'] ) || isset( $_GET['dag'] ) )
		{
			preg_match( '/.*/', ( $_GET['_dag'] ?? $_GET['dag'] ), $dagVal );
			setcookie( 'custom-record-naming-survey-dag',
			           $dagVal[0], time() + 60, '', '', true, true );
		}

		$this->canAddRecord = true;
		$this->hasSettingsForArm = true;
		$this->blockedBySettings = false;
		$this->userSuppliedComponentPrompt = null;
		$this->userSuppliedComponentRegex = null;
		$this->fieldLookupPrompt = null;
		$this->fieldLookupList = null;
		$this->userGroup = null;
		$this->groupCode = null;
		$this->allowNew = '';

		$pagePath = substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ) );

		// Perform a redirect when a new record is created to use the appropriate participant ID.
		if ( defined( 'PROJECT_ID' ) && defined( 'USERID' ) &&
			 ( substr( $pagePath, 0, 11 ) == 'DataEntry/?' ||
			   substr( $pagePath, 0, 19 ) == 'DataEntry/index.php' ||
			   substr( $pagePath, 0, 25 ) == 'DataEntry/record_home.php' ||
			   substr( $pagePath, 0, 37 ) == 'DataEntry/record_status_dashboard.php' ) )
		{
			// Determine the current DAG and arm.
			$userRights = \REDCap::getUserRights( USERID );
			$userGroup = $userRights[ USERID ][ 'group_id' ]; // group ID or NULL
			$this->userGroup = $userGroup;

			$armNum = 1;
			$armID = null;
			if ( isset( $_GET['arm'] ) && is_numeric( $_GET['arm'] ) )
			{
				$armNum = $_GET['arm'];
			}
			elseif ( substr( $pagePath, 0, 37 ) == 'DataEntry/record_status_dashboard.php' )
			{
				$savedArmNum =
					\UIState::getUIStateValue( PROJECT_ID, 'record_status_dashboard', 'arm' );
				if ( $savedArmNum != '' )
				{
					$armNum = $savedArmNum;
				}
			}
			elseif ( isset( $_GET['event_id'] ) && is_numeric( $_GET['event_id'] ) )
			{
				$this->getArmIdFromNum( null );
				$armID = $this->getArmIdFromEventId( $_GET['event_id'] );
			}

			if ( $armID === null )
			{
				$armID = $this->getArmIdFromNum( $armNum ); // arm ID or NULL
			}
			if ( isset( $GLOBALS['multiple_arms'] ) && ! $GLOBALS['multiple_arms'] &&
			     count( $this->listArmIdNum ) == 1 )
			{
				$armID = array_values( $this->listArmIdNum )[0];
			}
			$armSettingID = null;
			$schemePrefix = '';
			$schemeSuffix = '';

			// If the arm ID cannot be determined, a record cannot be created.
			if ( $armID === null )
			{
				$this->canAddRecord = false;
				$this->hasSettingsForArm = false;
			}

			// Check that the settings have been completed for the chosen arm. If there is no
			// naming scheme for the arm, then a record cannot be created.
			if ( $this->canAddRecord )
			{
				$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
				if ( is_array( $listSettingArmIDs ) && in_array( $armID, $listSettingArmIDs ) )
				{
					$armSettingID = array_search( $armID, $listSettingArmIDs );
					if ( strpos( $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ],
					             'U' ) !== false )
					{
						$this->userSuppliedComponentPrompt =
							$this->getProjectSetting( 'scheme-prompt-user-supplied' )[ $armSettingID ];
						$this->userSuppliedComponentRegex =
							$this->getProjectSetting( 'scheme-user-supplied-format' )[ $armSettingID ];
					}
					if ( strpos( $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ],
					             'F' ) !== false )
					{
						$this->fieldLookupPrompt =
							$this->getProjectSetting( 'scheme-prompt-field-lookup' )[ $armSettingID ];
						$this->fieldLookupList =
							$this->getFieldLookupList(
								$this->getProjectSetting( 'scheme-field-lookup-value' )[ $armSettingID ],
								$this->getProjectSetting( 'scheme-field-lookup-desc' )[ $armSettingID ],
								$this->getProjectSetting( 'scheme-field-lookup-filter' )[ $armSettingID ] );
					}
					$schemePrefix = $this->getProjectSetting( 'scheme-name-prefix' )[ $armSettingID ];
					$schemeSuffix = $this->getProjectSetting( 'scheme-name-suffix' )[ $armSettingID ];
					$schemeTriggerOn = $this->getProjectSetting( 'scheme-name-trigger' );
					$triggerOnRCName = ( is_array( $schemeTriggerOn ) &&
					                     isset( $schemeTriggerOn[ $armSettingID ] ) )
					                   ? ( $schemeTriggerOn[ $armSettingID ] == 'R' ) : false;
					$triggerOnMismatch = ( is_array( $schemeTriggerOn ) &&
					                       isset( $schemeTriggerOn[ $armSettingID ] ) )
					                     ? ( $schemeTriggerOn[ $armSettingID ] == 'M' ) : false;
					$this->allowNew = $this->getProjectSetting( 'scheme-allow-new' );
					$this->allowNew = ( is_array( $this->allowNew ) &&
					                    isset( $this->allowNew[ $armSettingID ] ) )
					                  ? $this->allowNew[ $armSettingID ] : '';
					$schemeAllowNew = ( $this->allowNew != 'S' );
					if ( ! $schemeAllowNew )
					{
						$this->canAddRecord = false;
						$this->blockedBySettings = true;
					}
				}
				else
				{
					$this->canAddRecord = false;
					$this->hasSettingsForArm = false;
				}
			}

			// Check that a record name can be generated given the name type.
			if ( $this->canAddRecord )
			{
				// Get the numbering type, and check the chosen arm to see if DAG based numbering
				// is in use or the naming scheme for the arm contains the DAG.
				$numberingType = $this->getProjectSetting( 'scheme-numbering' )[ $armSettingID ];
				$armNeedsDAG = false;
				if ( strpos( $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ],
				             'G' ) !== false )
				{
					$armNeedsDAG = true;
				}

				// If record numbering is based on Data Access Groups (DAGs) or the naming scheme
				// contains the DAG, then the user must be in a DAG in order to create a record.
				// Get the scheme DAG format and check the current DAG matches.
				if ( strpos( $numberingType, 'G' ) !== false || $armNeedsDAG )
				{
					if ( $userGroup === null )
					{
						$this->canAddRecord = false;
					}
					else
					{
						$groupCode = $this->getGroupCode( $userGroup, $armSettingID );
						if ( $groupCode === false )
						{
							$groupCode = '';
							$this->canAddRecord = false;
						}
						$this->groupCode = $groupCode;
					}
				}
			}

			// If a new record is being submitted, check that the record name is still unused. If
			// it is not, then generate a new one.
			if ( substr( $pagePath, 0, 19 ) == 'DataEntry/index.php' &&
				 isset( $_POST[ 'module-custom-record-naming-new-record' ] ) )
			{
				unset( $_POST[ 'module-custom-record-naming-new-record' ] );
				$submittedRecordName = $_POST[ \REDCap::getRecordIdField() ];
				$newRecordName =
						$this->generateRecordName( $armID, $armSettingID, $groupCode, null, true );
				if ( $submittedRecordName != $newRecordName )
				{
					$_SESSION['module_customrecordnaming_amended'] =
									[ $submittedRecordName, $newRecordName ];
					$_POST[ \REDCap::getRecordIdField() ] = $newRecordName;
				}
				setcookie( 'redcap_custom_record_name', '', 1, '', '', true );
				setcookie( 'redcap_custom_record_name_fieldval', '', 1, '', '', true );
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
			if ( isset( $_GET[ 'auto' ] ) ||
			     ( $armSettingID !== null && $triggerOnRCName && isset( $_GET[ 'id' ] ) &&
			       preg_match( '/^([1-9][0-9]*-)?[1-9][0-9]*$/', $_GET[ 'id' ] ) ) ||
			     ( $armSettingID !== null && $triggerOnMismatch && isset( $_GET[ 'id' ] ) &&
			       ( strpos( $_GET[ 'id' ], $schemePrefix ) !== 0 ||
			         strpos( strrev( $_GET[ 'id' ] ), strrev( $schemeSuffix ) ) !== 0 ) ) )
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
				$hasRecords = $this->countRecords() > 0;

				// If the project does not currently have any records, the module project settings
				// which keep track of the record number(s) are reset to blank values. This ensures
				// that numbering always starts from the beginning even if the project previously
				// contained records (e.g. development records which were cleared when placing the
				// project into production status).
				if ( ! $hasRecords )
				{
					// Clear the record counter.
					$this->setProjectSetting( 'project-record-counter', '{}' );
				}

				// Generate the new record name.
				$recordName = $this->generateRecordName( $armID, $armSettingID, $groupCode );

				// Get the data entry form to load, if applicable.
				$loadInstrument = $this->getProjectSetting( 'scheme-instrument' );
				$loadInstrument = ( is_array( $loadInstrument ) &&
				                     isset( $loadInstrument[ $armSettingID ] ) )
				                   ? $loadInstrument[ $armSettingID ] : '';
				$loadInstrument = explode( ':', $loadInstrument );
				if ( count( $loadInstrument ) == 2 )
				{
					$loadInstrument[0] =
						array_search( $loadInstrument[0], \REDCap::getEventNames( true, false ) );
					$loadInstrument =
						'&event_id=' . $loadInstrument[0] . '&page=' . $loadInstrument[1];
				}
				else
				{
					$loadInstrument = '';
				}

				// Regenerate the URL query string using the new record name and removing the 'auto'
				// parameter, and perform a redirect to the new URL.
				$queryString = '';
				foreach ( $_GET as $name => $val )
				{
					if ( $name == 'auto' ||
					     ( $loadInstrument != '' && in_array( $name, [ 'arm', 'pnid' ] ) ) )
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
				if ( $loadInstrument == '' )
				{
					$this->redirect( PAGE_FULL . $queryString );
				}
				else
				{
					$this->redirect( str_replace( 'record_home.php', 'index.php', PAGE_FULL ) .
					                 $queryString . $loadInstrument );
				}
			}
		}

	}



	function redcap_every_page_top( $project_id )
	{
		if ( !$project_id )
		{
			return;
		}


		$pagePath = substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ) );


		// On the DAGs page, use the DAG format restriction to constrain how DAGs can be named,
		// and/or display the defined notice explaining how to name DAGs.
		$dagFormat = $this->getProjectSetting( 'dag-format' );
		$dagFormatNotice = $this->getProjectSetting( 'dag-format-notice' );
		if ( ( $dagFormat != '' || $dagFormatNotice != '' ) &&
		     ( substr( $pagePath, 0, 17 ) == 'DataAccessGroups/' ||
		       ( substr( $pagePath, 0, 9 ) == 'index.php' &&
		         $_GET['route'] == 'DataAccessGroupsController:index' ) ) )
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
    if ( typeof( fieldBlur ) != 'undefined' )
    {
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
				$dagFormatNotice = str_replace( [ "\r\n", "\n" ], '<br>', $dagFormatNotice );
				$dagFormatNotice = '<img src="' . APP_PATH_WEBROOT .
					               '/Resources/images/exclamation_orange.png"> ' . $dagFormatNotice;
				$dagFormatNotice = addslashes( $dagFormatNotice );

?>
    $( '<div class="yellow" style="max-width:900px"><?php echo $dagFormatNotice; ?></div>'
                                                                    ).insertBefore( '#group_table' )
<?php

			}

?>
  })
</script>
<?php

		} // End DAGs page content.



		// On the Add/Edit Records and Record Status Dashboard pages, amend the 'add new record'
		// button if required.
		if ( ( substr( $pagePath, 0, 25 ) == 'DataEntry/record_home.php' ||
		       substr( $pagePath, 0, 37 ) == 'DataEntry/record_status_dashboard.php' ) )
		{
			$addText1 = $GLOBALS['lang']['data_entry_46'];
			$addText2 = $GLOBALS['lang']['data_entry_46'] . ' ' . $GLOBALS['lang']['data_entry_442'];
			$addText3 = $GLOBALS['lang']['data_entry_46'] . $GLOBALS['lang']['data_entry_99'];
			$addText4 = $GLOBALS['lang']['data_entry_533'];

			// If a new record cannot be added (either because the user is not currently in a valid
			// DAG, or because the selected arm does not have a naming scheme), then remove the
			// 'add new record' button and replace it with a brief explanation.
			if( ! $this->canAddRecord )
			{
?>
<script type="text/javascript">
  $(function() {
    var vListButton = $( 'button' )
    for ( var i = 0; i < vListButton.length; i++ )
    {
      if ( vListButton[ i ].innerText.trim() == '<?php echo $addText1; ?>' ||
           vListButton[ i ].innerText.trim() == '<?php echo $addText2; ?>' ||
           vListButton[ i ].innerText.trim() == '<?php echo $addText3; ?>' ||
           vListButton[ i ].innerText.trim() == '<?php echo $addText4; ?>' )
      {
        vListButton[ i ].style.display = 'none'
<?php
				if ( ! $this->hasSettingsForArm )
				{
?>
        $( '<i>(Record numbering has not been set up for the current arm)</i>'
               ).insertBefore( vListButton[ i ] )
<?php
				}
				else if ( $this->blockedBySettings )
				{
?>
        $( '<i>(New records cannot be added for this arm)</i>'
               ).insertBefore( vListButton[ i ] )
<?php
				}
				else
				{
?>
        $( '<i>(You must be in a valid Data Access Group to add records)</i>'
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
			}
			// If the record name contains a user supplied component, then ensure that the user is
			// prompted for it when they click the 'add new record' button.
			elseif ( $this->userSuppliedComponentPrompt !== null ||
			         $this->fieldLookupPrompt !== null )
			{
?>
<script type="text/javascript">
  $(function() {
    var vListButton = $( 'button' )
    for ( var i = 0; i < vListButton.length; i++ )
    {
      if ( vListButton[ i ].innerText.trim() == '<?php echo $addText1; ?>' ||
           vListButton[ i ].innerText.trim() == '<?php echo $addText2; ?>' ||
           vListButton[ i ].innerText.trim() == '<?php echo $addText3; ?>' ||
           vListButton[ i ].innerText.trim() == '<?php echo $addText4; ?>' )
      {
        var vOldOnclick = vListButton[ i ].onclick
        vListButton[ i ].onclick = <?php
				echo $this->makeUserPromptJS( '', 'vOldOnclick()', '',
				                              $this->userSuppliedComponentPrompt,
				                              $this->userSuppliedComponentRegex,
				                              $this->fieldLookupPrompt,
				                              $this->fieldLookupList, false ); ?>

        break
      }
    }
  })
</script>
<?php
			}

		} // End Add/Edit Records and Record Status Dashboard page content.



		// On the data entry form, if the record is new (no data saved yet), if the user is in a
		// DAG and the 'assign record to DAG' drop down is present (this likely only applies to
		// administrators) then ensure the drop down is set to the user's DAG.
		// Denote the record as new so the module can check the record name is still unused upon
		// submission.
		if ( substr( $pagePath, 0, 19 ) == 'DataEntry/index.php' &&
			 isset( $_GET[ 'id' ] ) && $this->countRecords( $_GET[ 'id' ] ) == 0 )
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
    $('input[name="<?php echo \REDCap::getRecordIdField(); ?>"]').after(
                    '<input type="hidden" name="module-custom-record-naming-new-record" value="1">')
<?php

			if ( $this->allowNew == 'C' )
			{

?>
    $('select[name="<?php echo $this->escapeHTML( $_GET['page'] );
?>_complete"] option:not([value="2"])').remove()
<?php

			}

?>
  })
</script>
<?php

		} // End data entry form content.



		// Add public survey links for DAGs.
		if ( ( substr( $pagePath, 0, 31 ) == 'Surveys/invite_participants.php' ) )
		{
			$listDAGs = \REDCap::getGroupNames( false );
			if ( ! empty( $listDAGs ) )
			{
				$qrDescText = str_replace( ["\r", "\n"], ' ',
				                           addslashes( $GLOBALS['lang']['survey_632'] ) );
?>
<script type="text/javascript">
  $(function()
  {
    if ( $('#longurl').length )
    {
      var vBaseURL = $('#longurl').val()
      var vURLCode = vBaseURL.replace('<?php echo addslashes( APP_PATH_SURVEY_FULL ); ?>?s=','')
      var vInsertAfter = $('#longurl').parent()
      var vQRDialog = $('<div><p><?php echo $qrDescText; ?></p>' +
                        '<p style="text-align:center"></p></div>')
      var vFuncSelect = function(elem)
      {
        var vSel = window.getSelection()
        var vRange = document.createRange()
        vRange.selectNodeContents(elem)
        vSel.removeAllRanges()
        vSel.addRange(vRange)
      }
      var vFuncQRClick = function(elem)
      {
        var vQR = vQRDialog.find('p').eq(1)
        vQR.html('')
        vQR.append($('<img>').attr('src','<?php echo addslashes( APP_PATH_WEBROOT ); ?>Surveys/' +
                                         'survey_link_qrcode.php?pid=' +
                                         '<?php echo intval( $_GET['pid'] ); ?>&hash=' +
                                         elem.dataset.qr))
        vQRDialog.dialog(
        {
          autoOpen:true,
          height:380,
          modal:true,
          resizable:false,
          title:'<?php echo addslashes( $GLOBALS['lang']['survey_620'] ); ?>',
          width:420
        })
      }
      var vURLTable = $('<table><tr><th style="border:solid #000 1px;padding:3px">DAG</th>' +
                        '<th style="border:solid #000 1px;padding:3px">Public Survey URL</th>' +
                        '<th style="border:solid #000 1px;padding:3px"><img ' +
                        'src="<?php echo APP_PATH_WEBROOT; ?>Resources/images/qrcode.png" ' +
                        'style="vertical-align:middle"> QR Code</th></tr></table>')
<?php
				$dagURL = $this->dagQueryID( '' );
?>
      var vURLTR = $('<tr><td style="border:solid #000 1px;padding:3px"><i>none</i></td>' +
                     '<td style="border:solid #000 1px;padding:3px">' + vBaseURL + '&amp;_dag=' +
                     '<?php echo $this->escapeHTML( $dagURL ); ?></td>' +
                     '<td style="border:solid #000 1px;padding:3px;text-align:center">' +
                     '<a href="#" data-qr="' + vURLCode + '%26_dag%3D' +
                     '<?php echo $this->escapeHTML( $dagURL ); ?>">View</a></td></tr>')
      vURLTR.find('td').eq(1).on('click',function(){vFuncSelect(this)})
      vURLTR.find('a[data-qr]').eq(0).on('click',function(e){vFuncQRClick(this);e.preventDefault()})
      vURLTable.append(vURLTR)
<?php
				foreach ( $listDAGs as $dagID => $dagName )
				{
					$dagURL = $this->dagQueryID( $dagID );
?>
      var vURLTR = $('<tr><td style="border:solid #000 1px;padding:3px">' +
                     '<?php echo $this->escapeHTML( $dagName ); ?></td>' +
                     '<td style="border:solid #000 1px;padding:3px">' + vBaseURL + '&amp;_dag=' +
                     '<?php echo $this->escapeHTML( $dagURL ); ?></td>' +
                     '<td style="border:solid #000 1px;padding:3px;text-align:center">' +
                     '<a href="#" data-qr="' + vURLCode + '%26_dag%3D' +
                     '<?php echo $this->escapeHTML( $dagURL ); ?>">View</a></td></tr>')
      vURLTR.find('td').eq(1).on('click',function(){vFuncSelect(this)})
      vURLTR.find('a[data-qr]').eq(0).on('click',function(e){vFuncQRClick(this);e.preventDefault()})
      vURLTable.append(vURLTR)
<?php
				}
?>
      vURLTable.insertAfter( vInsertAfter )
      vInsertAfter.css('display','none')
      vURLTable.before('<p>Please note that these URLs will only work if the DAG is valid for ' +
                       'the custom naming scheme invoked by the public survey.</p>')
      $('.link-actions-container, .url-actions-container').css('display', 'none')
    }
  })
</script>
<?php

			}
		} // End public survey links content.



		// If the module had to amend a record name because the name already exists, notify the
		// user of the updated record name.
		if ( isset( $_SESSION['module_customrecordnaming_amended'] ) )
		{

?>
<script type="text/javascript">
  $(function()
  {
    var vDialog = $('<div><p>The record name/number <?php
			echo $this->escapeHTML( $_SESSION['module_customrecordnaming_amended'][0] );
?> already exists in the project.<br>This record has been created as <b><?php
			echo $this->escapeHTML( $_SESSION['module_customrecordnaming_amended'][1] );
?></b>.</p></div>')
    vDialog.dialog(
    {
      autoOpen:true,
      modal:true,
      resizable:false,
      title:'Record name updated',
      width:450
    })
  })
</script>
<?php

			unset( $_SESSION['module_customrecordnaming_amended'] );
		}

	}



	public function redcap_save_record( $project_id, $record, $instrument, $event_id, $group_id,
	                                    $survey_hash, $response_id, $repeat_instance )
	{
		// Check that the survey is the public survey and that the submission is incomplete,
		// and exit this function if not.
		if ( ! in_array( $survey_hash, $this->getPublicSurveyHashes( $project_id ) ) ||
		     json_decode( \REDCap::getData( 'json', $record, $instrument . '_complete' ),
		                  true )[0][$instrument . '_complete'] == '2' )
		{
			return;
		}
		// Perform the record rename and DAG assignment.
		$newRecordID = $this->performSurveyRename( $record, $event_id );
		setcookie( 'redcap_custom_record_name', '', 1, '', '', true );
		setcookie( 'redcap_custom_record_name_fieldval', '', 1, '', '', true );
		// Remove the survey record's first submit timestamp, so that the user is able to load the
		// survey again after rename in order to complete it.
		$this->query( 'UPDATE redcap_surveys_response SET first_submit_time = NULL ' .
		              'WHERE completion_time IS NULL ' .
		              'AND record = ? AND instance = ? AND participant_id IN ' .
		              '(SELECT participant_id FROM redcap_surveys_participants p ' .
		              'JOIN redcap_surveys s ON p.survey_id = s.survey_id ' .
		              'WHERE form_name = ? AND event_id = ? AND project_id = ?) LIMIT 1',
		              [ $newRecordID, ( is_numeric( $repeat_instance ) ? $repeat_instance : 1 ),
		                $instrument, $event_id, $project_id ] );
		// Redirect to the survey link for the now established record.
		$_SESSION['module_customrecordnaming_resubmit'] = [ 't' => time(), 'f' => $instrument ];
		$this->redirect( \REDCap::getSurveyLink( $newRecordID, $instrument, $event_id ) );
	}



	public function redcap_survey_complete( $project_id, $record, $instrument, $event_id, $group_id,
	                                        $survey_hash, $response_id, $repeat_instance )
	{
		// Check that the survey is the public survey and exit this function if not.
		if ( ! in_array( $survey_hash, $this->getPublicSurveyHashes( $project_id ) ) )
		{
			return;
		}
		// Perform the record rename and DAG assignment.
		$this->performSurveyRename( $record, $event_id );
		setcookie( 'redcap_custom_record_name', '', 1, '', '', true );
		setcookie( 'redcap_custom_record_name_fieldval', '', 1, '', '', true );
		$_SESSION['module_customrecordnaming_resubmit'] = [ 't' => time(), 'f' => $instrument ];
	}



	public function redcap_survey_page_top( $project_id, $record, $instrument, $event_id, $group_id,
	                                        $survey_hash, $response_id, $repeat_instance )
	{
		// If a survey resubmit is required, perform this once the page has loaded.
		if ( isset( $_SESSION['module_customrecordnaming_resubmit'] ) &&
		     $_SESSION['module_customrecordnaming_resubmit']['t'] > time() - 40 )
		{
			if ( $_SESSION['module_customrecordnaming_resubmit']['f'] == $instrument )
			{

?>
<script type="text/javascript">
  $(function(){
    $('body').css('display','none')
    $('[name="submit-btn-saverecord"]').click()
  })
</script>
<?php

			}
			unset( $_SESSION['module_customrecordnaming_resubmit'] );
			return;
		}

		// Check that the survey is the public survey and exit this function if not.
		if ( ! in_array( $survey_hash, $this->getPublicSurveyHashes( $project_id ) ) ||
		     ( ! isset( $_GET['_dag'] ) && ! isset( $_GET['dag'] ) &&
		       empty( \REDCap::getGroupNames() ) ) )
		{
			return;
		}

		// Start by deeming the arm/DAG combo as valid.
		$validConfig = true;

		// Identify the set of settings for the arm.
		$armID = $this->getArmIdFromEventId( $event_id );
		$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
		if ( is_array( $listSettingArmIDs ) && in_array( $armID, $listSettingArmIDs ) )
		{
			$armSettingID = array_search( $armID, $listSettingArmIDs );
		}
		else
		{
			$validConfig = false;
		}

		// Identify the DAG and check it is valid.
		if ( $validConfig && ! empty( \REDCap::getGroupNames( false ) ) &&
		     ! isset( $_GET['_dag'] ) && ! isset( $_GET['dag'] ) )
		{
			$validConfig = false;
		}

		if ( $validConfig )
		{
			$dagID = $this->dagQueryID( $_GET['_dag'] ?? $_GET['dag'], true );
			if ( $dagID === false || $this->getGroupCode( $dagID, $armSettingID ) === false )
			{
				$validConfig = false;
			}
		}

		// If DAGs are defined for the project, do not allow the user to proceed if the DAG has
		// not been specified for the survey or if the DAG is otherwise invalid.
		if ( ! $validConfig )
		{
			echo '<script type="text/javascript">window.location = \'',
				 addslashes( APP_PATH_SURVEY_FULL ), '\'</script>';
			echo '</div></div></div></body></html>';
			$this->exitAfterHook();
			return;
		}
		$dagParam = preg_replace( '/[^0-9A-Za-z]/', '', $_GET['_dag'] ?? $_GET['dag'] );

		// Check if a user supplied component is expected, so that the user can be prompted for it
		// when submitting the survey.
		$uPrompt = null;
		if ( strpos( $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ],
		             'U' ) !== false )
		{
			$uPrompt = $this->getProjectSetting( 'scheme-prompt-user-supplied' )[ $armSettingID ];
			$uRegex = $this->getProjectSetting( 'scheme-user-supplied-format' )[ $armSettingID ];
		}

		// Check if a field lookup component is expected, so that the user can be prompted for it
		// when submitting the survey.
		$fPrompt = null;
		if ( strpos( $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ],
		             'F' ) !== false )
		{
			$fPrompt = $this->getProjectSetting( 'scheme-prompt-field-lookup' )[ $armSettingID ];
			$fList =
				$this->getFieldLookupList(
					$this->getProjectSetting( 'scheme-field-lookup-value' )[ $armSettingID ],
					$this->getProjectSetting( 'scheme-field-lookup-desc' )[ $armSettingID ],
					$this->getProjectSetting( 'scheme-field-lookup-filter' )[ $armSettingID ] );
		}

?>
<script type="text/javascript">
  $(function(){
    $('#form').attr('action', $('#form').attr('action') + '&_dag=<?php echo $dagParam; ?>' )
<?php

		if ( $uPrompt !== null || $fPrompt !== null )
		{
?>
    var vOldDataEntrySubmit = dataEntrySubmit
    dataEntrySubmit = <?php
			echo $this->makeUserPromptJS( 'el', 'vOldDataEntrySubmit(el)',
			                              '$(el).button(\'enable\')', $uPrompt, $uRegex,
			                              $fPrompt, $fList, true ); ?>

<?php
		}

?>
  })
</script>
<?php

	}



	// Check if the current user can configure the module settings for the project.
	public function canConfigure()
	{
		$user = $this->getUser();
		if ( ! is_object( $user ) )
		{
			return false;
		}
		if ( $user->isSuperUser() )
		{
			return true;
		}
		$userRights = $user->getRights();
		$specificRights = ( $this->getSystemSetting( 'config-require-user-permission' ) == 'true' );
		$moduleName = preg_replace( '/_v[0-9.]+$/', '', $this->getModuleDirectoryName() );
		if ( $specificRights && is_array( $userRights['external_module_config'] ) &&
		     in_array( $moduleName, $userRights['external_module_config'] ) )
		{
			return true;
		}
		if ( ! $specificRights && $userRights['design'] == '1' )
		{
			return true;
		}
		return false;
	}



	// Echo plain text to output (without Psalm taints).
	// Use only for e.g. JSON or CSV output.
	function echoText( $text )
	{
		$text = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XHTML );
		$chars = [ '&amp;' => 38, '&quot;' => 34, '&apos;' => 39, '&lt;' => 60, '&gt;' => 62 ];
		$text = preg_split( '/(&(?>amp|quot|apos|lt|gt);)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $text as $part )
		{
			echo isset( $chars[ $part ] ) ? chr( $chars[ $part ] ) : $part;
		}
	}



	// Escapes text for inclusion in HTML.
	function escapeHTML( $text )
	{
		return htmlspecialchars( $text, ENT_QUOTES );
	}



	// Exclude state tracking settings from settings exports.
	public function exportProjectSettings()
	{
		$this->getArmIdFromNum(1);
		$listSettings = [];
		$listFullSettings = $this->getProjectSettings();
		foreach ( $listFullSettings as $key => $value )
		{
			if ( ! in_array( $key, [ 'enabled', 'scheme-settings',
			                         'project-last-record', 'project-record-counter' ] ) )
			{
				if ( $key == 'scheme-arm' )
				{
					array_walk( $value,
					            function( &$val )
					            {
					              $val = '' . array_search( $val, $this->listArmIdNum );
					            } );
				}
				$listSettings[] = [ 'key' => $key, 'value' => $value ];
			}
		}
		return $listSettings;
	}



	// Get the arm IDs and names for the project.
	public function getArms( $projectID = null )
	{
		if ( $projectID === null )
		{
			$projectID = $this->getProjectId();
		}
		$query = $this->query( 'SELECT arm_id, arm_name FROM redcap_events_arms ' .
		                       'WHERE project_id = ? ORDER BY arm_num', [ $projectID ] );
		$result = [];
		while ( $row = $query->fetch_assoc() )
		{
			$result[ $row['arm_id'] ] = $row['arm_name'];
		}
		return $result;
	}



	// Get the list of record name types.
	public function getListRecordNameTypes()
	{
		return [ 'R' => 'Record number',
                 'G' => 'DAG',
                 'U' => 'User supplied',
                 'T' => 'Timestamp',
                 'F' => 'Field value lookup',
                 'C' => 'Check digits',
                 'Z' => 'Username',
                 '1' => 'Constant value' ];
	}



	// Validation for the module settings.
	public function validateSettings( $settings )
	{
		if ( $this->getProjectID() === null )
		{
			return null;
		}

		$errMsg = '';
		$listFieldNames = \REDCap::getFieldNames();

		// If the DAG name restriction is specified, check it is a valid regular expression.
		if ( $settings['dag-format'] != '' &&
		     preg_match( $this->makePcreString( $settings['dag-format'] ), '' ) === false )
		{
			$errMsg .= "\n- Invalid regular expression for restrict DAG name format";
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

			// Ensure that the record name type has been set, and that the numbering does not
			// include components not used in the name.
			if ( ! isset( $settings['scheme-name-type'][$i] ) ||
			     $settings['scheme-name-type'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Value required for record name type";
			}
			elseif ( ! empty( array_diff( str_split( $settings['scheme-numbering'][$i], 1 ),
			                              array_merge( ['','A'],
			                               str_split( $settings['scheme-name-type'][$i], 1 ) ) ) ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) . ": Record numbering can only use" .
				           " the selected record name types";
			}

			// Ensure that the constant value has been set if selected.
			if ( strpos( $settings['scheme-name-type'][$i], '1' ) !== false &&
			     $settings['scheme-const1'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Constant value cannot be blank if constant value used";
			}

			// Ensure that the starting number, if set, is a positive integer.
			if ( $settings['scheme-number-start'][$i] != '' &&
			     ! preg_match( '/^0|[1-9][0-9]*$/', $settings['scheme-number-start'][$i] ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": First record number must be a positive integer";
			}

			// Validate the DAG name format for the naming scheme. This is required if the record
			// name includes the DAG. If the name format is specified then the subpattern must also
			// be specified, otherwise neither can be specified. The subpattern must point to a
			// valid subpattern within the name format regular expression.
			if ( strpos( $settings['scheme-name-type'][$i], 'G' ) !== false &&
			     $settings['scheme-dag-format'][$i] == '' )
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
			         substr_count( str_replace( [ '\\\\', '\(' ], '',
			                                    $settings['scheme-dag-format'][$i] ), '(' ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Specified DAG format subpattern greater than number of subpatterns";
			}

			// Ensure that the prompt for the user supplied name is provided if the record name type
			// includes a user supplied component.
			if ( strpos( $settings['scheme-name-type'][$i], 'U' ) !== false &&
			     $settings['scheme-prompt-user-supplied'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": User supplied name prompt cannot be blank if user supplied name used";
			}

			// Validate the user supplied name format for the naming scheme.
			// This is required if the record name includes a user supplied component.
			if ( strpos( $settings['scheme-name-type'][$i], 'U' ) !== false &&
			     $settings['scheme-user-supplied-format'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": User supplied name format cannot be blank if user supplied name used";
			}
			elseif ( $settings['scheme-user-supplied-format'][$i] != '' &&
			         preg_match( $this->makePcreString(
			                                       $settings['scheme-user-supplied-format'][$i] ),
			                     '' ) === false )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Invalid regular expression for user supplied name format";
			}

			// Ensure that the timestamp format and timezone are provided if the record name type
			// includes the timestamp.
			if ( strpos( $settings['scheme-name-type'][$i], 'T' ) !== false &&
			     $settings['scheme-timestamp-format'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Timestamp format cannot be blank if timestamp used";
			}
			if ( strpos( $settings['scheme-name-type'][$i], 'T' ) !== false &&
			     $settings['scheme-timestamp-tz'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Timezone cannot be blank if timestamp used";
			}

			// Ensure that the prompt for the field lookup is provided if the record name type
			// includes a field value lookup.
			if ( strpos( $settings['scheme-name-type'][$i], 'F' ) !== false &&
			     $settings['scheme-prompt-field-lookup'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Field lookup prompt cannot be blank if field value lookup used";
			}

			// Validate the lookup value field. This is required if the record name includes a
			// field value lookup.
			if ( strpos( $settings['scheme-name-type'][$i], 'F' ) !== false &&
			     $settings['scheme-field-lookup-value'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Lookup value field cannot be blank if field value lookup used";
			}
			elseif ( $settings['scheme-field-lookup-value'][$i] != '' &&
			         ! in_array( $settings['scheme-field-lookup-value'][$i], $listFieldNames ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": The specified lookup value field does not exist";
			}

			// Validate the lookup description field. This is required if the record name includes a
			// field value lookup.
			if ( strpos( $settings['scheme-name-type'][$i], 'F' ) !== false &&
			     $settings['scheme-field-lookup-desc'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Lookup description field cannot be blank if field value lookup used";
			}
			elseif ( $settings['scheme-field-lookup-desc'][$i] != '' &&
			         ! in_array( $settings['scheme-field-lookup-desc'][$i], $listFieldNames ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": The specified lookup description field does not exist";
			}

			// Ensure that the check digit algorithm is provided if the record name type includes
			// check digits.
			if ( strpos( $settings['scheme-name-type'][$i], 'C' ) !== false &&
			     $settings['scheme-check-digit-algorithm'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Check digit algorithm cannot be blank if check digits used";
			}

		}

		if ( $errMsg != '' )
		{
			return "Your record naming configuration contains errors:$errMsg";
		}

		return null;
	}



	// Given a (server-wide) arm ID, return the arm name.
	public function getArmNameFromId( $id )
	{
		$res = $this->query( 'SELECT arm_name FROM redcap_events_arms' .
		                     ' WHERE arm_id = ?', [ $id ] );
		while ( $row = $res->fetch_row() )
		{
			return $row[0];
		}
		return null;
	}



	// Given an arm name, return the (server-wide) arm ID.
	public function getArmIdFromName( $name )
	{
		if ( defined( 'PROJECT_ID' ) )
		{
			$res = $this->query( 'SELECT arm_id FROM redcap_events_arms' .
			                     ' WHERE project_id = ? AND arm_name = ?', [ PROJECT_ID, $name ] );
			while ( $row = $res->fetch_row() )
			{
				return $row[0];
			}
		}
		return null;
	}



	public function getInstrumentEventMapping( $armID )
	{
		$projectID = $this->getProjectId();
		$result = [];
		if ( $projectID != null )
		{
			$res = $this->query( 'SELECT ef.event_id, ef.form_name FROM redcap_events_forms ef ' .
			                     'JOIN redcap_events_metadata em ON ef.event_id = em.event_id ' .
			                     'WHERE em.arm_id = ? ORDER BY em.day_offset, ( SELECT ' .
			                     'min(field_order) FROM redcap_metadata WHERE form_name = ' .
			                     'ef.form_name AND project_id = ? );', [ $armID, PROJECT_ID ] );
			while ( $row = $res->fetch_row() )
			{
				$result[] = [ 'event_id' => $row[0], 'instrument' => $row[1] ];
			}
		}
		return $result;
	}



	public function getPublicSurveyHashes( $pid )
	{
		$sql = "SELECT p.hash FROM redcap_surveys s JOIN redcap_surveys_participants p " .
		       "ON s.survey_id = p.survey_id JOIN redcap_metadata m " .
		       "ON m.project_id = s.project_id AND m.form_name = s.form_name " .
		       "WHERE p.participant_email IS NULL AND m.field_order = 1 AND s.project_id = ?";

		$listHashes = [];
		$result = $this->query( $sql, [ $pid ] );
		while( $row = $result->fetch_assoc() )
		{
			$listHashes[] = $row['hash'];
		}

		return $listHashes;
	}



	// Get a DAG value for the survey query string or check that a DAG query value is valid.
	protected function dagQueryID( $dag, $check = false )
	{
		// Determine if the supplied DAG value is valid.
		// If checking a query string DAG parameter, split the check data from the DAG ID.
		if ( $check )
		{
			if ( ! preg_match( '/^([1-9][0-9]*)?[A-Za-z]{1,4}$/', $dag ) )
			{
				return false;
			}
			$suppliedCheck = preg_replace( '/[0-9]/', '', $dag );
			$dag = preg_replace( '/[A-Za-z]/', '', $dag );
		}
		elseif ( ! preg_match( '/^([1-9][0-9]*)?$/', $dag ) )
		{
			return false;
		}
		// Check the DAG exists for the project.
		if ( $dag != '' && ! isset( \REDCap::getGroupNames()[$dag] ) )
		{
			return false;
		}
		// Generate the check data for the DAG ID.
		$generatedCheck = substr( preg_replace( '/[^A-Za-z]/', '',
		                              base64_encode( hash( 'sha256', $GLOBALS['salt'] . '-' .
		                                                   $this->getProjectId() . '-' . $dag,
		                                                   true ) ) ), 0, 4 );
		// If checking a query string parameter, compare the supplied and generated check data.
		// Return the DAG ID if the check data matches, otherwise false.
		if ( $check )
		{
			if ( $suppliedCheck == $generatedCheck )
			{
				return $dag;
			}
			return false;
		}
		// Otherwise, if generating check data to append to a DAG ID, return the DAG ID with the
		// check data appended. If DAG ID is empty string, return check data only.
		return $dag . $generatedCheck;
	}



	// Get the number of existing records.
	// Optionally with the specified record name (1 = record exists, 0 = record doesn't exist).
	protected function countRecords( $recordName = null )
	{
		return count( \REDCap::getData( 'array', $recordName, \REDCap::getRecordIdField() ) );
	}



	// Generate a new record name.
	protected function generateRecordName( $armID, $armSettingID, $groupCode,
	                                       $oldName = null, $incrementCounter = false )
	{
		// Get the scheme settings for the arm.
		$numbering = $this->getProjectSetting( 'scheme-numbering' )[ $armSettingID ];
		$nameType = $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ];
		$namePrefix = $this->getProjectSetting( 'scheme-name-prefix' )[ $armSettingID ];
		$nameSeparator = $this->getProjectSetting( 'scheme-name-separator' )[ $armSettingID ];
		$nameSuffix = $this->getProjectSetting( 'scheme-name-suffix' )[ $armSettingID ];
		$const1 = $this->getProjectSetting( 'scheme-const1' )[ $armSettingID ];
		$startNum = $this->getProjectSetting( 'scheme-number-start' )[ $armSettingID ];
		$zeroPad = $this->getProjectSetting( 'scheme-number-pad' )[ $armSettingID ];
		$timestampFormat = $this->getProjectSetting( 'scheme-timestamp-format' )[ $armSettingID ];
		$timestampTZ = $this->getProjectSetting( 'scheme-timestamp-tz' )[ $armSettingID ];
		$chkDigitAlg = $this->getProjectSetting( 'scheme-check-digit-algorithm' )[ $armSettingID ];

		// Get the user supplied component if it has been entered.
		$suppliedComponent = '';
		if ( isset( $_COOKIE[ 'redcap_custom_record_name' ] ) )
		{
			$suppliedComponent = $_COOKIE[ 'redcap_custom_record_name' ];
		}

		// Get the timestamp (UTC or server timezone) if required.
		$timestamp = '';
		if ( strpos( $nameType, 'T' ) !== false )
		{
			$timestamp = ( $timestampTZ == 'U' ) ? gmdate( $timestampFormat ) // UTC
			                                     : date( $timestampFormat );  // server
		}

		// Get the field value from the lookup if it has been entered.
		$suppliedFieldValue = '';
		if ( isset( $_COOKIE[ 'redcap_custom_record_name_fieldval' ] ) )
		{
			$suppliedFieldValue = $_COOKIE[ 'redcap_custom_record_name_fieldval' ];
		}

		// Get the user's username.
		$currentUser = ( USERID == '[survey respondent]' ? '' : USERID );

		// Determine the database locking ID.
		$lockingID = $GLOBALS['db'] . '.custom_record_naming.p' . $this->getProjectId();

		// Determine the record number using the record counter.
		// Apply the database lock so only one session can amend the record counter at a time.
		$counterID = 'project';
		if ( strpos( $numbering, 'A' ) !== false )
		{
			$counterID = "$armID";
		}
		foreach ( [ 'G' => "$groupCode", 'U' => $suppliedComponent, 'T' => $timestamp,
		            'F' => $suppliedFieldValue, 'Z' => $currentUser ]
		          as $numberingCode => $counterComponent )
		{
			if ( strpos( $numbering, $numberingCode ) !== false )
			{
				$counterID .= '/' . str_replace( ['\\','/'], ['\\\\','\\'], $counterComponent );
			}
		}
		$this->query( 'DO GET_LOCK(?,40)', [ $lockingID ] );
		$recordCounter = json_decode( $this->getProjectSetting( 'project-record-counter' ), true );

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
		}

		// Create the record name.
		// Loop until an unused record name is generated.
		while ( true )
		{
			$recordName = '';
			// Start by taking the value from the record counter.
			$recordNumber = $recordCounter[ $counterID ];
			// If applicable, left pad the record number with zeros to make it the set number
			// of digits.
			if ( $zeroPad != '' )
			{
				$recordNumber = str_pad( $recordNumber, $zeroPad, '0', STR_PAD_LEFT );
			}
			// Determine whether check digits are to be used and the number of runs required to
			// generate the record name.
			$checkDigits = '';
			$hasCheckDigits = ( strpos( $nameType, 'C' ) !== false );
			if ( $hasCheckDigits )
			{
				if ( $chkDigitAlg == 'mod97' )
				{
					$namingRuns = [1,2];
				}
			}
			else
			{
				$namingRuns = [1];
			}
			foreach ( $namingRuns as $namingRun )
			{
				// Do any check digit handling required.
				if ( $hasCheckDigits )
				{
					if ( $namingRun == 2 && $chkDigitAlg == 'mod97' )
					{
						// Convert record name to uppercase/numbers only.
						$recordName = preg_replace( '/[^A-Z0-9]/', '', strtoupper($recordName) );
						// Convert letters to numbers (A=10,B=11,C=12...).
						$recordName = implode( '', array_map( function($v)
						                                      {
						                                          if(ord($v)>64)
						                                          {
						                                              return strval(ord($v)-55);
						                                          }
						                                          return $v;
						                                      },
						                                      str_split( $recordName, 1 ) ) );
						// Append check digit placeholder.
						$recordName .= '00';
						// Calculate mod-97 of converted record name and subtract from 98.
						while ( strlen( $recordName ) > 2 )
						{
							$recordName =
								substr( '0' . ( intval( substr( $recordName, 0, 9 ) ) % 97 ), -2 ) .
								substr( $recordName, 9 );
						}
						$checkDigits = substr( '0' . ( 98 - intval( $recordName ) ), -2 );
						// Reset record name to blank.
						$recordName = '';
					}
				}
				// Build the record name from the components selected, separated by the separator
				// value (if not constant value).
				$prevConst = false;
				for ( $i = 0; $i < strlen( $nameType ); $i++ )
				{
					$thisConst = preg_match( '[1-9]', substr( $nameType, $i, 1 ) );
					if ( $i > 0 && !$thisConst && ! $prevConst )
					{
						$recordName .= $nameSeparator;
					}
					if ( substr( $nameType, $i, 1 ) == 'G' ) // DAG
					{
						$recordName .= $groupCode;
					}
					elseif ( substr( $nameType, $i, 1 ) == 'R' ) // record number
					{
						$recordName .= $recordNumber;
					}
					elseif ( substr( $nameType, $i, 1 ) == 'U' ) // user supplied
					{
						$recordName .= $suppliedComponent;
					}
					elseif ( substr( $nameType, $i, 1 ) == 'T' ) // timestamp
					{
						$recordName .= $timestamp;
					}
					elseif ( substr( $nameType, $i, 1 ) == 'F' ) // field value lookup
					{
						$recordName .= $suppliedFieldValue;
					}
					elseif ( substr( $nameType, $i, 1 ) == 'C' ) // check digits
					{
						if ( $namingRun == 2 && $chkDigitAlg == 'mod97' )
						{
							$recordName .= $checkDigits;
						}
					}
					elseif ( substr( $nameType, $i, 1 ) == 'Z' ) // username
					{
						$recordName .= $currentUser;
					}
					elseif ( substr( $nameType, $i, 1 ) == '1' ) // constant value
					{
						$recordName .= $const1;
					}
					$prevConst = $thisConst;
				}
				// Prepend the prefix and append the suffix to the record name.
				$recordName = $namePrefix . $recordName . $nameSuffix;
			}

			// Check whether recordName already exists. If it does, and the record number is used
			// in the record name, increment the record number and try again. Exit the loop if the
			// record name is unused, or if the record number is not used (in which case the user
			// will be taken to the existing record).
			if ( $this->countRecords( $recordName ) > 0 && strpos( $nameType, 'R' ) !== false &&
			     ( $oldName === null || $recordName != $oldName ) )
			{
				$recordCounter[ $counterID ]++;
			}
			else
			{
				break;
			}
		}

		// Set the new record counter and last record values.
		if ( $incrementCounter )
		{
			$recordCounter[ $counterID ]++;
		}
		$this->setProjectSetting( 'project-record-counter', json_encode( $recordCounter ) );

		// Release the database lock.
		$this->query( 'DO RELEASE_LOCK(?)', [ $lockingID ] );

		// Return the record name.
		return $recordName;

	}



	// Get the field value/description list, using the lookup value field, lookup description field
	// and the lookup filter logic.
	protected function getFieldLookupList( $valueField, $descField, $filterLogic )
	{
		// Get the record data for the project.
		try
		{
			$lookupResult = json_decode( \REDCap::getData( [ 'return_format' => 'json',
			                                                 'filterLogic' => $filterLogic,
			                                                 'exportDataAccessGroups' => true,
			                                                 'exportSurveyFields' => true,
			                                                 'exportAsLabels' => true ] ),
			                             true );
		}
		catch ( \Exception $e )
		{
			return [];
		}
		// Retrieve the lookup values/descriptions where these are not empty.
		$result = [];
		foreach ( $lookupResult as $lookupResultItem )
		{
			if ( isset( $lookupResultItem[ $descField ] ) && $lookupResultItem[ $descField ] != '' &&
			     isset( $lookupResultItem[ $valueField ] ) && $lookupResultItem[ $valueField ] != '' )
			{
				$result[ $lookupResultItem[ $valueField ] ] = $lookupResultItem[ $descField ];
			}
		}
		return $result;
	}



	// Given a DAG ID, get the DAG code for use in record names.
	protected function getGroupCode( $dagID, $armSettingID )
	{
		$listGroups = \REDCap::getGroupNames( false );
		$groupName = isset( $listGroups[ $dagID ] ) ? $listGroups[ $dagID ] : '';
		$dagFormat = $this->getProjectSetting( 'scheme-dag-format' )[ $armSettingID ];
		if ( $groupName == '' )
		{
			return ( $dagFormat == '' ? '' : false );
		}
		$dagFormat = $this->makePcreString( $dagFormat );
		if ( preg_match( $dagFormat, $groupName, $dagMatches ) )
		{
			$dagSection = $this->getProjectSetting( 'scheme-dag-section' )[ $armSettingID ];
			if ( ! isset( $dagMatches[ $dagSection ] ) )
			{
				$dagSection = 0;
			}
			return $dagMatches[ $dagSection ];
		}
		return false;
	}



	// Prompt the user for record name components.
	protected function makeUserPromptJS( $jsParams, $jsFinal, $jsCancel, $userSuppliedPrompt,
	                                     $userSuppliedRegex, $fieldValuePrompt, $listFields,
	                                     $isSurvey )
	{
		$output = "function ($jsParams) { var vDialog = $('<div></div>');";
		if ( $userSuppliedPrompt !== null )
		{
			$output .= "vDialog.append('<p>" .
			           nl2br( $this->escapeHTML( $userSuppliedPrompt ) ) . "</p>');" .
			           "var vUserSupplied = $('<input type=\"text\" style=\"width:99%\">');" .
			           "vDialog.append($('<p style=\"max-width:100%\"></p>')." .
			           "append(vUserSupplied));var vUserSuppliedErr = " .
			           "$('<p style=\"color:#c00\"></p>');vDialog.append(vUserSuppliedErr);";
		}
		if ( $userSuppliedPrompt !== null && $fieldValuePrompt !== null )
		{
			$output .= "vDialog.append('<hr>');";
		}
		if ( $fieldValuePrompt !== null )
		{
			$output .= "vDialog.append('<p>" .
			           nl2br( $this->escapeHTML( $fieldValuePrompt ) ) . "</p>');" .
			           "var vFieldValues = $('<select><option></option>";
			foreach ( $listFields as $fieldValue => $fieldDesc )
			{
				$output .= '<option value="' . $this->escapeHTML( $fieldValue ) .
				           '">' . $this->escapeHTML( $fieldDesc ) . '</option>';
			}
			$output .= "</select>');" .
			           "vDialog.append($('<p style=\"max-width:99%\"></p>').append(vFieldValues))" .
			           ";var vFieldValuesErr = $('<p style=\"color:#c00\"></p>');" .
			           "vDialog.append(vFieldValuesErr);";
		}
		$output .= 'vDialog.dialog({width:400,modal:true,buttons:{"' .
		           addslashes( $isSurvey ? $GLOBALS['lang']['survey_200']
		                                 : $GLOBALS['lang']['data_entry_46'] ) .
		           '":function(){var vValid = true;';
		if ( $userSuppliedPrompt !== null )
		{
			$output .= "vUserSuppliedErr.text('');if (vUserSupplied.val() == ''){vValid = false;" .
			           "vUserSuppliedErr.text('Sorry, this field cannot be blank.')" .
			           "}else if(!new RegExp(" . json_encode( $userSuppliedRegex ) .
			           ").test( vUserSupplied.val() ) ){vValid = false;" .
			           "vUserSuppliedErr.text('Sorry, the value you entered was not valid.')};";
		}
		if ( $fieldValuePrompt !== null )
		{
			$output .= "vFieldValuesErr.text('');if (vFieldValues.val() == ''){vValid = false;" .
			           "vFieldValuesErr.text('Sorry, this field cannot be blank.')};";
		}
		$output .= 'if (vValid){';
		if ( $userSuppliedPrompt !== null )
		{
			$output .= "document.cookie = 'redcap_custom_record_name=' + " .
			           "encodeURIComponent( vUserSupplied.val() ) + ';secure';" .
			           "vUserSupplied.prop('disabled',true);";
		}
		if ( $fieldValuePrompt !== null )
		{
			$output .= "document.cookie = 'redcap_custom_record_name_fieldval=' + " .
			           "encodeURIComponent(vFieldValues.val()) + ';secure';" .
			           "vFieldValues.prop('disabled',true);";
		}
		$output .= $jsFinal . '}}},close:function(){' . $jsCancel . '}})}';
		return $output;
	}



	// Perform a record rename for a public survey.
	protected function performSurveyRename( $oldRecordID, $eventID )
	{
		// Determine whether the project currently has records (excluding this one).
		$hasRecords = $this->countRecords() > 1;

		// If the project does not currently have any records, the module project settings which
		// keep track of the record number(s) are reset to blank values. This ensures that numbering
		// always starts from the beginning even if the project previously contained records (e.g.
		// development records which were cleared when placing the project into production status).
		if ( ! $hasRecords )
		{
			// Clear the record counter.
			$this->setProjectSetting( 'project-record-counter', '{}' );
		}

		if ( ! isset( $_GET['_dag'] ) && ! isset( $_GET['dag'] ) &&
		     isset( $_COOKIE['custom-record-naming-survey-dag'] ) )
		{
			$dagID = $_COOKIE['custom-record-naming-survey-dag'];
		}
		else
		{
			$dagID = $_GET['_dag'] ?? $_GET['dag'];
		}
		$dagID = $this->dagQueryID( $dagID, true );
		$dagID = ( $dagID === false ) ? '' : $dagID;
		$armID = $this->getArmIdFromEventId( $eventID );

		// Identify the set of settings for the arm.
		$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
		if ( ! is_array( $listSettingArmIDs ) || ! in_array( $armID, $listSettingArmIDs ) )
		{
			return false;
		}
		$armSettingID = array_search( $armID, $listSettingArmIDs );

		// Get the DAG code for the supplied DAG ID.
		$groupCode = $this->getGroupCode( $dagID, $armSettingID );
		$groupCode = ( $groupCode === false ) ? '' : $groupCode;

		$newRecordID =
			$this->generateRecordName( $armID, $armSettingID, $groupCode, $oldRecordID, true );
		if ( $dagID !== '' )
		{
			$this->setDAG( $oldRecordID, $dagID );
		}
		if ( $oldRecordID != $newRecordID )
		{
			\DataEntry::changeRecordId( $oldRecordID, $newRecordID );
		}
		return $newRecordID;
	}



	// Perform a redirect within a hook.
	// Note that it may be necessary to return from the hook after calling this function.
	protected function redirect( $url )
	{
		header( 'Location: ' . $url );
		$this->exitAfterHook();
	}



	// Convert a regular expression without delimiters to one with delimiters.
	protected function makePcreString( $str )
	{
		foreach ( str_split( '/!#$%&,-.:;@|~' ) as $chr )
		{
			if ( strpos( $str, $chr ) === false )
			{
				return $chr . $str . $chr;
			}
		}
	}



	// Given an event ID number, return the (server-wide) arm ID number.
	protected function getArmIdFromEventId( $eventID )
	{
		if ( !is_array( $this->listArmIdEvent ) )
		{
			$this->listArmIdEvent = [];
			$res = $this->query( 'SELECT arm_id, event_id FROM redcap_events_metadata', [] );
			while ( $row = $res->fetch_assoc() )
			{
				$this->listArmIdEvent[ $row['event_id'] ] = $row['arm_id'];
			}
		}
		if ( ! isset( $this->listArmIdEvent[ $eventID ] ) )
		{
			return null;
		}
		return $this->listArmIdEvent[ $eventID ];
	}



	// Given an arm number for the project, return the (server-wide) arm ID number.
	protected function getArmIdFromNum( $num )
	{
		if ( !is_array( $this->listArmIdNum ) )
		{
			$this->listArmIdNum = [];
			if ( defined( 'PROJECT_ID' ) )
			{
				$res = $this->query( 'SELECT arm_id, arm_num FROM redcap_events_arms' .
				                     ' WHERE project_id = ?', [ PROJECT_ID ] );
				while ( $row = $res->fetch_assoc() )
				{
					$this->listArmIdNum[ $row['arm_num'] ] = $row['arm_id'];
				}
			}
		}
		if ( ! isset( $this->listArmIdNum[ $num ] ) )
		{
			return null;
		}
		return intval( $this->listArmIdNum[ $num ] );
	}



	private $canAddParticipant;
	private $hasSettingsForArm;
	private $blockedBySettings;
	private $userSuppliedComponentPrompt;
	private $userSuppliedComponentRegex;
	private $fieldLookupPrompt;
	private $fieldLookupList;
	private $listArmIdNum;
	private $listArmIdEvent;
	private $userGroup;
	private $groupCode;
	private $allowNew;

}

