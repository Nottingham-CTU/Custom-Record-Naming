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
		$this->userSuppliedComponentPrompt = null;
		$this->userSuppliedComponentRegex = null;
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

			$armNum = 1;
			if ( isset( $_GET['arm'] ) )
			{
				$armNum = $_GET['arm'];
			}
			elseif ( isset( $GLOBALS['ui_state'][PROJECT_ID]['record_status_dashboard']['arm'] ) &&
			         substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 37 ) ==
			           'DataEntry/record_status_dashboard.php' )
			{
				$armNum = $GLOBALS['ui_state'][PROJECT_ID]['record_status_dashboard']['arm'];
			}

			$armID = $this->getArmIdFromNum( $armNum ); // arm ID or NULL

			// If the arm ID cannot be determined, a record cannot be created.
			if ( $armID === null )
			{
				$this->canAddRecord = false;
			}

			// Check that the settings have been completed for the chosen arm. If there is no
			// naming scheme for the arm, then a record cannot be created.
			if ( $this->canAddRecord )
			{
				$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
				if ( in_array( $armID, $listSettingArmIDs ) )
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
				}
				else
				{
					$this->canAddRecord = false;
					$this->hasSettingsForArm = false;
				}
			}

			//
			if ( $this->canAddRecord )
			{
				// Get the numbering type, and check the chosen arm to see if DAG based numbering
				// is in use or the naming scheme for the arm contains the DAG.
				$numberingType = $this->getProjectSetting( 'numbering' );
				$armNeedsDAG = false;
				if ( strpos( $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ],
				             'G' ) !== false )
				{
					$armNeedsDAG = true;
				}

				// If record numbering is based on Data Access Groups (DAGs) or the naming scheme
				// contains the DAG, then the user must be in a DAG in order to create a record.
				// Get the scheme DAG format and check the current DAG matches.
				if ( $numberingType == 'G' || $numberingType == 'AG' || $armNeedsDAG )
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
		     ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 17 ) == 'DataAccessGroups/' ||
		       ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 9 ) == 'index.php' &&
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



		// On the Add/Edit Records and Record Status Dashboard pages, amend the 'add new record'
		// button if required.
		if ( ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 25 ) == 'DataEntry/record_home.php' ||
		       substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 37 ) ==
		                                                'DataEntry/record_status_dashboard.php' ) )
		{
			$addText1 = $GLOBALS['lang']['data_entry_46'];
			$addText2 = $GLOBALS['lang']['data_entry_46'] . ' ' . $GLOBALS['lang']['data_entry_442'];
			$addText3 = $GLOBALS['lang']['data_entry_46'] . $GLOBALS['lang']['data_entry_99'];

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
           vListButton[ i ].innerText.trim() == '<?php echo $addText3; ?>' )
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
			}
			// If the record name contains a user supplied component, then ensure that the user is
			// prompted for it when they click the 'add new record' button.
			elseif ( $this->userSuppliedComponentPrompt !== null )
			{
?>
<script type="text/javascript">
  $(function() {
    var vListButton = $( 'button' )
    for ( var i = 0; i < vListButton.length; i++ )
    {
      if ( vListButton[ i ].innerText.trim() == '<?php echo $addText1; ?>' ||
           vListButton[ i ].innerText.trim() == '<?php echo $addText2; ?>' ||
           vListButton[ i ].innerText.trim() == '<?php echo $addText3; ?>' )
      {
        var vOldOnclick = vListButton[ i ].onclick
        vListButton[ i ].onclick = function()
        {
          var vResponse = prompt( <?php echo json_encode( $this->userSuppliedComponentPrompt ); ?> )
          if ( vResponse === null || vResponse == '' )
          {
            return
          }
          else if ( ! new RegExp( <?php echo json_encode( $this->userSuppliedComponentRegex );
										?> ).test( vResponse ) )
          {
            alert( 'Sorry, the value you entered was not valid.' )
            return
          }
          document.cookie = 'redcap_custom_record_name=' + encodeURIComponent( vResponse ) +
                            ';secure'
          vOldOnclick()
        }
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
		// Send an ajax request to the ajax_keepalive.php file every 30 seconds, so that the
		// record ID is reserved while the form is being completed. If the user navigates away from
		// the form without saving, the record ID can be reused after 90 seconds (unless the next
		// record ID has already been used or reserved).
		if ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 19 ) == 'DataEntry/index.php' &&
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



		// Add public survey links for DAGs.
		if ( ( substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ), 31 ) ==
		                                                       'Surveys/invite_participants.php' ) )
		{
			$listDAGs = \REDCap::getGroupNames( false );
			if ( ! empty( $listDAGs ) )
			{
?>
<script type="text/javascript">
  $(function()
  {
    if ( $('#longurl').length )
    {
      var vBaseURL = $('#longurl').val()
      var vInsertAfter = $('#longurl').parent()
      var vFuncSelect = function(elem)
      {
        var vSel = window.getSelection()
        var vRange = document.createRange()
        vRange.selectNodeContents(elem)
        vSel.removeAllRanges()
        vSel.addRange(vRange)
      }
      var vURLTable = $('<table><tr><th style="border:solid #000 1px">DAG</th>' +
                        '<th style="border:solid #000 1px">Public Survey URL</th></tr></table>')
      var vURLTR = $('<tr><td style="border:solid #000 1px"><i>none</i></td>' +
                     '<td style="border:solid #000 1px">' + vBaseURL + '&amp;dag=<?php
				echo $this->dagQueryID( '' ); ?></td></tr>')
      vURLTR.find('td').eq(1).on('click',function(){vFuncSelect(this)})
      vURLTable.append(vURLTR)
<?php
				foreach ( $listDAGs as $dagID => $dagName )
				{
					$dagURL = $this->dagQueryID( $dagID );
?>
      var vURLTR = $('<tr><td style="border:solid #000 1px"><?php
					echo addslashes( htmlspecialchars( $dagName ) ); ?></td>' +
                     '<td style="border:solid #000 1px">' + vBaseURL + '&amp;dag=<?php
					echo addslashes( htmlspecialchars( $dagURL ) ); ?></td></tr>')
      vURLTR.find('td').eq(1).on('click',function(){vFuncSelect(this)})
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
		$_SESSION['module_customrecordnaming_resubmit'] = time();
		// Redirect to the survey link for the now established record.
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
		$_SESSION['module_customrecordnaming_resubmit'] = time();
	}



	public function redcap_survey_page_top( $project_id, $record, $instrument, $event_id, $group_id,
	                                        $survey_hash, $response_id, $repeat_instance )
	{
		// If a survey resubmit is required, perform this once the page has loaded.
		if ( $_SESSION['module_customrecordnaming_resubmit'] > time() - 60 )
		{
			unset( $_SESSION['module_customrecordnaming_resubmit'] );

?>
<script type="text/javascript">
  $(function(){
    $('body').css('display','none')
    $('[name="submit-btn-saverecord"]').click()
  })
</script>
<?php

			return;
		}

		// Check that the survey is the public survey and exit this function if not.
		if ( ! in_array( $survey_hash, $this->getPublicSurveyHashes( $project_id ) ) ||
		     ( ! isset( $_GET['dag'] ) && empty( \REDCap::getGroupNames() ) ) )
		{
			return;
		}

		// Start by deeming the arm/DAG combo as valid.
		$validConfig = true;

		// Identify the set of settings for the arm.
		$armID = $this->getArmIdFromEventId( $event_id );
		$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
		if ( in_array( $armID, $listSettingArmIDs ) )
		{
			$armSettingID = array_search( $armID, $listSettingArmIDs );
		}
		else
		{
			$validConfig = false;
		}

		// Identify the DAG and check it is valid.
		if ( $validConfig && ! empty( \REDCap::getGroupNames( false ) ) && ! isset( $_GET['dag'] ) )
		{
			$validConfig = false;
		}

		if ( $validConfig )
		{
			$dagID = $this->dagQueryID( $_GET['dag'], true );
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
		$dagParam = preg_replace( '/[^0-9A-Za-z]/', '', $_GET['dag'] );

		// Check if a user supplied component is expected, so that the user can be prompted for it
		// when submitting the survey.
		$uPrompt = null;
		if ( strpos( $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ],
		             'U' ) !== false )
		{
			$uPrompt = $this->getProjectSetting( 'scheme-prompt-user-supplied' )[ $armSettingID ];
			$uRegex = $this->getProjectSetting( 'scheme-user-supplied-format' )[ $armSettingID ];
		}

?>
<script type="text/javascript">
  $(function(){
    $('#form').attr('action', $('#form').attr('action') + '&dag=<?php echo $dagParam; ?>' )
<?php

		if ( $uPrompt !== null )
		{
?>
    var vOldDataEntrySubmit = dataEntrySubmit
    dataEntrySubmit = function (el)
    {
      var vResponse = prompt( <?php echo json_encode( $uPrompt ); ?> )
      if ( vResponse === null || vResponse == '' )
      {
        $(el).button('enable')
        return
      }
      else if ( ! new RegExp( <?php echo json_encode( $uRegex ); ?> ).test( vResponse ) )
      {
        alert( 'Sorry, the value you entered was not valid.' )
        $(el).button('enable')
        return
      }
      document.cookie = 'redcap_custom_record_name=' + encodeURIComponent( vResponse ) + ';secure'
      vOldDataEntrySubmit(el)
    }
<?php
		}

?>
  })
</script>
<?php

	}



	// Validation for the module settings.
	public function validateSettings( $settings )
	{
		if ( $this->getProjectID() === null )
		{
			return null;
		}

		$errMsg = '';
		$clearCounters = false;

		// If the DAG name restriction is specified, check it is a valid regular expression.
		if ( $settings['dag-format'] != '' &&
		     preg_match( $this->makePcreString( $settings['dag-format'] ), '' ) === false )
		{
			$errMsg .= "\n- Invalid regular expression for restrict DAG name format";
		}

		// Ensure that a setting is present for record numbering. This setting cannot be changed
		// once records exist (in production), as the record counter(s) would then be invalid.
		// While the project is in development status, the record numbering can always be changed
		// for convenience, even though this could cause issues. In this case, the record counters
		// will be reset to mitigate these issues.
		if ( ! isset( $settings['numbering'] ) || $settings['numbering'] == '' )
		{
			$errMsg .= "\n- Value required for record numbering";
		}
		elseif ( $settings['numbering'] != $this->getProjectSetting( 'numbering' ) &&
		         $this->countRecords() > 0 )
		{
			if ( method_exists( $this->framework, 'getProjectStatus' ) &&
			     $this->framework->getProjectStatus() == 'DEV' )
			{
				$clearCounters = true;
			}
			else
			{
				$errMsg .= "\n- Record numbering cannot be changed once records exist";
			}
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
			elseif ( in_array( $settings['numbering'], [ 'G', 'AG' ] ) &&
			         strpos( $settings['scheme-name-type'][$i], 'G' ) === false )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) . ": Record name type" .
				           " must include DAG if per DAG numbering used";
			}

			// Ensure that the prompt for the user supplied name is provided if and only if the
			// record name type includes a user supplied component.
			if ( strpos( $settings['scheme-name-type'][$i], 'U' ) !== false &&
			     $settings['scheme-prompt-user-supplied'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Prompt cannot be blank if user supplied name used";
			}
			elseif ( strpos( $settings['scheme-name-type'][$i], 'U' ) === false &&
			     $settings['scheme-prompt-user-supplied'][$i] != '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Prompt must be blank if user supplied name not used";
			}

			// Validate the user supplied name format for the naming scheme.
			// This is required if and only if the record name includes a user supplied component.
			if ( strpos( $settings['scheme-name-type'][$i], 'U' ) !== false &&
			     $settings['scheme-user-supplied-format'][$i] == '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Format cannot be blank if user supplied name used";
			}
			elseif ( strpos( $settings['scheme-name-type'][$i], 'U' ) === false &&
			     $settings['scheme-user-supplied-format'][$i] != '' )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Format must be blank if user supplied name not used";
			}
			elseif ( $settings['scheme-user-supplied-format'][$i] != '' &&
			         preg_match( $this->makePcreString(
			                                       $settings['scheme-user-supplied-format'][$i] ),
			                     '' ) === false )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Invalid regular expression for user supplied name format";
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
			         substr_count( str_replace( [ '\\\\', '\(' ], '',
			                                    $settings['scheme-dag-format'][$i] ), '(' ) )
			{
				$errMsg .= "\n- Naming scheme " . ($i + 1) .
				           ": Specified DAG format subpattern greater than number of subpatterns";
			}

		}

		if ( $errMsg != '' )
		{
			return "Your record naming configuration contains errors:$errMsg";
		}

		if ( $clearCounters )
		{
			// Clear the record counter.
			$this->setProjectSetting( 'project-record-counter', '{}' );
			// Clear the last created record.
			$this->setProjectSetting( 'project-last-record', '{}' );
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
	protected function generateRecordName( $armID, $armSettingID, $groupCode, $oldName = null )
	{
		// Get the scheme settings for the arm.
		$numbering = $this->getProjectSetting( 'numbering' );
		$nameType = $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ];
		$startNum = $this->getProjectSetting( 'scheme-number-start' )[ $armSettingID ];
		$zeroPad = $this->getProjectSetting( 'scheme-number-pad' )[ $armSettingID ];
		$namePrefix = $this->getProjectSetting( 'scheme-name-prefix' )[ $armSettingID ];
		$nameSeparator = $this->getProjectSetting( 'scheme-name-separator' )[ $armSettingID ];
		$nameSuffix = $this->getProjectSetting( 'scheme-name-suffix' )[ $armSettingID ];

		// Get the user supplied component if it has been entered.
		$suppliedComponent = '';
		if ( isset( $_COOKIE[ 'redcap_custom_record_name' ] ) )
		{
			$suppliedComponent = $_COOKIE[ 'redcap_custom_record_name' ];
			setcookie( 'redcap_custom_record_name', '', 1, '', '', true );
		}

		// Determine the record number using the record counter.
		$counterID = 'project';
		if ( $numbering == 'A' ||
		     ( $numbering == 'AG?' && strpos( $nameType, 'G' ) === false ) )
		{
			$counterID = "$armID";
		}
		elseif ( $numbering == 'G' )
		{
			$counterID = "$groupCode";
		}
		elseif ( $numbering == 'AG' ||
		     ( $numbering == 'AG?' && strpos( $nameType, 'G' ) !== false ) )
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
		       $this->countRecords( $lastRecord[ $counterID ][ 'name' ] ) > 0 ) )
		{
			$recordCounter[ $counterID ]++;
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
			// If (part of) the DAG name is to be included, prepend or append it to the
			// record number along with the separator.
			for ( $i = 0; $i < strlen( $nameType ); $i++ )
			{
				if ( $i > 0 )
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
			}
			// Prepend the prefix and append the suffix to the record name.
			$recordName = $namePrefix . $recordName . $nameSuffix;

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
		$lastRecord[ $counterID ] = [ 'name' => $recordName, 'timestamp' => time(),
		                              'user' => $currentUser ];
		$this->setProjectSetting( 'project-record-counter', json_encode( $recordCounter ) );
		$this->setProjectSetting( 'project-last-record', json_encode( $lastRecord ) );

		return $recordName;

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
			// Clear the last created record.
			$this->setProjectSetting( 'project-last-record', '{}' );
		}

		$dagID = $this->dagQueryID( $_GET['dag'], true );
		$dagID = ( $dagID === false ) ? '' : $dagID;
		$armID = $this->getArmIdFromEventId( $eventID );

		// Identify the set of settings for the arm.
		$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
		if ( ! in_array( $armID, $listSettingArmIDs ) )
		{
			return false;
		}
		$armSettingID = array_search( $armID, $listSettingArmIDs );

		// Get the DAG code for the supplied DAG ID.
		$groupCode = $this->getGroupCode( $dagID, $armSettingID );
		$groupCode = ( $groupCode === false ) ? '' : $groupCode;

		$newRecordID = $this->generateRecordName( $armID, $armSettingID, $groupCode, $oldRecordID );
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
		return $this->listArmIdNum[ $num ];
	}



	private $canAddParticipant;
	private $hasSettingsForArm;
	private $userSuppliedComponentPrompt;
	private $userSuppliedComponentRegex;
	private $listArmIdNum;
	private $listArmIdEvent;
	private $userGroup;
	private $groupCode;

}

