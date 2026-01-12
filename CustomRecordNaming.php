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



	// Intercept API requests from the mobile app and rename any new records created.

	function redcap_module_api_before( $project_id, $post )
	{
		// Determine if this is an API request from the mobile app to add data to the project.
		if ( ! isset( $_POST['customrecordnaming'] ) && $post['mobile_app'] == '1' &&
		     $post['uuid'] != '' && $post['content'] == 'record' && $post['action'] == 'import' &&
		     $post['format'] == 'json' && $post['forceAutoNumber'] == '1' &&
		     $post['returnContent'] == 'auto_ids' )
		{
			// Get a list of record IDs which already exist in the project.
			// If these IDs appear in the response, it's an existing record, not a new one.
			// This should not happen as forceAutoNumber is used.
			$listRecordIDs = [];
			$queryRecordIDs = $this->query( 'SELECT DISTINCT `record` FROM ' .
			                                \REDCap::getDataTable( $project_id ) .
			                                ' WHERE project_id = ?', [ $project_id ] );
			while ( $recordID = $queryRecordIDs->fetch_assoc() )
			{
				$listRecordIDs[] = $recordID['record'];
			}
			// Get one event ID for the project. If it's not longitudinal, use this.
			$queryEventIDs = $this->query( 'SELECT `event_id` FROM redcap_events_metadata em ' .
			                               'JOIN redcap_events_arms ea ON em.arm_id = ea.arm_id ' .
			                               'WHERE ea.project_id = ?', [ $project_id ] );
			$singleEventID = $queryEventIDs->fetch_assoc()['event_id'];
			$isLongitudinal = ( $queryEventIDs->fetch_assoc() !== null );
			// Get the event IDs to use for each new record for longitudinal projects.
			$obProj = new \Project( $project_id );
			$listRecordEventIDs = [];
			$recordIDField = $this->getRecordIdField( $project_id );
			$listImportedData = json_decode( $post['data'], true );
			if ( is_array( $listImportedData ) )
			{
				foreach ( $listImportedData as $infoImportedData )
				{
					if ( isset( $infoImportedData[ $recordIDField ] ) &&
					     ! isset( $listRecordEventIDs[ $infoImportedData[ $recordIDField ] ] ) )
					{
						if ( isset( $infoImportedData['redcap_event_name'] ) )
						{
							$listRecordEventIDs[ $infoImportedData[ $recordIDField ] ] =
								$obProj->getEventIdUsingUniqueEventName(
								                           $infoImportedData['redcap_event_name'] );
						}
						elseif ( ! $isLongitudinal )
						{
							$listRecordEventIDs[ $infoImportedData[ $recordIDField ] ] =
								$singleEventID;
						}
					}
				}
			}
			// Get the setting arm IDs.
			$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
			// Make the actual API request to add the data.
			$curl = curl_init( APP_PATH_WEBROOT_FULL . 'api/' );
			curl_setopt( $curl, CURLOPT_CAINFO, APP_PATH_DOCROOT . '/Resources/misc/cacert.pem' );
			curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, true );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, [ 'customrecordnaming' => '1' ] + $post );
			$result = curl_exec( $curl );
			// Check the response code and pass it through.
			$responseCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
			header( 'HTTP/1.1 ' . $responseCode );
			header( 'Content-type: ' . curl_getinfo( $curl, CURLINFO_CONTENT_TYPE ) );
			if ( $responseCode == 200 )
			{
				// Check the response for new record names.
				$result = json_decode( $result, true );
				$newResult = [];
				foreach ( $result as $recordIDs )
				{
					// Identify the new record name.
					$recordIDs = explode( ',', $recordIDs, 2 );
					if ( ! in_array( $recordIDs[0], $listRecordIDs ) )
					{
						// The record name is new, so replace it with one generated by this module.
						$eventID = $listRecordEventIDs[ $recordIDs[1] ];
						$armID = $this->getArmIdFromEventId( $eventID );
						$queryDAG = $this->query( 'SELECT `value` FROM ' .
						                          \REDCap::getDataTable( $project_id ) .
						                          ' WHERE project_id = ? AND record = ? AND ' .
						                          'field_name = \'__GROUPID__\'',
						                          [ $project_id, $recordIDs[0] ] );
						$dagID = $queryDAG->fetch_assoc();
						$dagID = ( $dagID === null ) ? '' : $dagID['value'];
						if ( is_array( $listSettingArmIDs ) &&
							 in_array( $armID, $listSettingArmIDs ) )
						{
							$armSettingID = array_search( $armID, $listSettingArmIDs );
							$groupCode = $this->getGroupCode( $dagID, $armSettingID );
							$groupCode = ( $groupCode === false ) ? '' : $groupCode;
							$newRecordID =
									$this->generateRecordName( $armID, $armSettingID, $groupCode,
									                           $recordIDs[0], true, $eventID );
							\REDCap::renameRecord( $project_id, $recordIDs[0], $newRecordID );
							// Use the generated record name in the API response.
							$recordIDs[0] = $newRecordID;
						}
					}
					$newResult[] = implode( ',', $recordIDs );
				}
				$result = json_encode( $newResult );
			}
			// Send the amended API response back to the app and exit.
			$this->echoText( $result );
			flush();
			$this->exitAfterHook();
			return '';
		}
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
		if ( $this->isSurveyPage() && ( isset( $_GET['_dag'] ) || isset( $_GET['dag'] ) ) )
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
		$this->promptDAG = false;

		$pagePath = substr( PAGE_FULL, strlen( APP_PATH_WEBROOT ) );

		// Perform a redirect when a new record is created to use the appropriate participant ID.
		if ( defined( 'PROJECT_ID' ) && defined( 'USERID' ) &&
			 ( substr( $pagePath, 0, 11 ) == 'DataEntry/?' ||
			   substr( $pagePath, 0, 19 ) == 'DataEntry/index.php' ||
			   substr( $pagePath, 0, 25 ) == 'DataEntry/record_home.php' ||
			   substr( $pagePath, 0, 37 ) == 'DataEntry/record_status_dashboard.php' ) )
		{
			// Determine the current DAG and arm.
			$userRights = $this->getUser()->getRights();
			$userGroup = $userRights['group_id']; // group ID or NULL
			if ( $userGroup == null && isset( $_COOKIE['redcap_custom_record_name_selecteddag'] ) )
			{
				$listValidDAGs = array_keys( \REDCap::getGroupNames() );
				if ( in_array( $_COOKIE['redcap_custom_record_name_selecteddag'], $listValidDAGs ) )
				{
					$userGroup = intval( $_COOKIE['redcap_custom_record_name_selecteddag'] );
					$_SESSION['module_customrecordnaming_selecteddag'] = $userGroup;
					setcookie( 'redcap_custom_record_name_selecteddag', '', 0, '', '', true );
				}
			}
			$this->userGroup = $userGroup;

			$armNum = 1;
			$armID = null;
			if ( isset( $_GET['arm'] ) && is_numeric( $_GET['arm'] ) )
			{
				$armNum = $_GET['arm'];
			}
			elseif ( substr( $pagePath, 0, 37 ) == 'DataEntry/record_status_dashboard.php' )
			{
				// On the record status dashboard, the arm ID can be saved from when the user
				// previously viewed it, so check this value.
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

			// If the arm ID cannot be determined or there are no settings for the arm, a record
			// cannot be created.
			if ( $armID === null )
			{
				$this->canAddRecord = false;
				$this->hasSettingsForArm = false;
			}
			else
			{
				$listSettingArmIDs = $this->getProjectSetting( 'scheme-arm' );
				if ( is_array( $listSettingArmIDs ) && in_array( $armID, $listSettingArmIDs ) )
				{
					$armSettingID = array_search( $armID, $listSettingArmIDs );
				}
				else
				{
					$this->canAddRecord = false;
					$this->hasSettingsForArm = false;
				}
			}

			// Check that the logic is satisfied to allow access to the arm and for a new record
			// to be created.
			$blockedArmRedirect = false;
			if ( $this->canAddRecord )
			{
				$listAccessArmLogic = $this->getProjectSetting('scheme-allow-access-logic');
				$accessArmLogic = ( is_array( $listAccessArmLogic ) &&
				                    isset( $listAccessArmLogic[ $armSettingID ] ) )
				                  ? $listAccessArmLogic[ $armSettingID ] : '';
				$accessArmLogic = $this->evaluateLogic( $accessArmLogic );
				$createRecordLogic = $this->getProjectSetting('scheme-allow-new-logic');
				$createRecordLogic = ( is_array( $createRecordLogic ) &&
				                       isset( $createRecordLogic[ $armSettingID ] ) )
				                     ? $createRecordLogic[ $armSettingID ] : '';
				$createRecordLogic = $this->evaluateLogic( $createRecordLogic );
				if ( ! $accessArmLogic || ! $createRecordLogic )
				{
					$this->canAddRecord = false;
					$this->blockedBySettings = true;
					if ( ! $accessArmLogic )
					{
						// If access arm logic is not satisfied, identify an arm we can redirect to.
						$blockedArmRedirect = 0;
						foreach ( $listAccessArmLogic as $accessArmLogicID => $accessArmLogic2 )
						{
							if ( $this->evaluateLogic( $accessArmLogic2 ) )
							{
								$blockedArmRedirect =
										array_search( $listSettingArmIDs[ $accessArmLogicID ],
										              $this->listArmIdNum );
								break;
							}
						}
					}
				}
			}

			// If we need to redirect away from a blocked arm, do this here.
			if ( $blockedArmRedirect !== false )
			{
				// If there are no accessible arms, redirect to the project home.
				if ( $blockedArmRedirect === 0 )
				{
					$this->redirect( APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION .
					                 '/index.php?pid=' . $this->getProjectId() );
					return;
				}
				// Otherwise redirect to the add/edit records page or the record status dashboard
				// page with the first accessible arm selected.
				if ( substr( $pagePath, 0, 25 ) == 'DataEntry/record_home.php' )
				{
					$this->redirect( APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION .
					                 '/DataEntry/record_home.php?pid=' . $this->getProjectId() .
					                 '&arm=' . $blockedArmRedirect );
					return;
				}
				$this->redirect( APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION .
				                 '/DataEntry/record_status_dashboard.php?pid=' .
				                 $this->getProjectId() . '&arm=' . $blockedArmRedirect );
				return;
			}

			// Check that the settings have been completed for the chosen arm. If there is no
			// naming scheme for the arm, then a record cannot be created.
			if ( $this->canAddRecord )
			{
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
				// If the user is not assigned to any DAG, prompt them for which DAG to use.
				// Get the scheme DAG format and check the current DAG matches.
				if ( strpos( $numberingType, 'G' ) !== false || $armNeedsDAG )
				{
					if ( $userGroup == null )
					{
						$this->promptDAG = true;
						if ( isset( $_GET['auto'] ) )
						{
							$this->canAddRecord = false;
						}
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
				 isset( $_POST['module-custom-record-naming-new-record'] ) )
			{
				unset( $_POST['module-custom-record-naming-new-record'],
				       $_SESSION['module_customrecordnaming_selecteddag'] );
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
			$dagFormatErrorText = $this->tt('dag_fmt_error1');
			if ( $dagFormatNotice != '' )
			{
				$dagFormatErrorText .= '\n\n' . $this->tt('dag_fmt_error2');
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
      if ( $( '#new_group' ).val() != '<?php echo addslashes( $GLOBALS['lang']['rights_179'] ); ?>' )
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
				$cantAddMsg = $this->tt('addrecerr_dag');
				if ( ! $this->hasSettingsForArm )
				{
					$cantAddMsg = $this->tt('addrecerr_setup');
				}
				elseif ( $this->blockedBySettings )
				{
					$cantAddMsg = $this->tt('addrecerr_prohibit');
				}
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
        $( '<i><?php echo $this->escape( $cantAddMsg ); ?></i>' ).insertBefore( vListButton[ i ] )
        break
      }
    }
  })
</script>
<?php
			}
			// If the record name contains a user supplied component, then ensure that the user is
			// prompted for it when they click the 'add new record' button.
			elseif ( $this->promptDAG || $this->userSuppliedComponentPrompt !== null ||
			         $this->fieldLookupPrompt !== null )
			{
				$promptDAG = $this->promptDAG;
				if ( substr( $pagePath, 0, 37 ) == 'DataEntry/record_status_dashboard.php' )
				{
					$selectedDAG =
						\UIState::getUIStateValue( PROJECT_ID, 'record_status_dashboard', 'dag' );
					if ( preg_match( '/^[1-9][0-9]*$/', $selectedDAG ) )
					{
						$promptDAG = $selectedDAG;
					}
				}
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
				                              $this->fieldLookupList, false, $promptDAG ); ?>

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

			if ( $this->userGroup !== null ||
			     isset( $_SESSION['module_customrecordnaming_selecteddag'] ) )
			{
				$userGroup = $this->userGroup;
				if ( $userGroup == null )
				{
					$userGroup = $_SESSION['module_customrecordnaming_selecteddag'];
				}

?>
    var vDAGSelect = $('select[name=__GROUPID__]')
    if ( vDAGSelect.length == 1 && vDAGSelect[0].value == '' )
    {
      vDAGSelect[0].value = '<?php echo $userGroup; ?>'
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
    $('select[name="<?php echo $this->escape( $_GET['page'] );
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
      var vURLTable = $('<table><tr><th style="border:solid #000 1px;padding:3px">' +
                        '<?php echo addslashes( $this->tt('pubsurv_dag') ); ?></th>' +
                        '<th style="border:solid #000 1px;padding:3px">' +
                        '<?php echo addslashes( $this->tt('pubsurv_url') ); ?></th>' +
                        '<th style="border:solid #000 1px;padding:3px"><img ' +
                        'src="<?php echo APP_PATH_WEBROOT; ?>Resources/images/qrcode.png" ' +
                        'style="vertical-align:middle"> ' +
                        '<?php echo addslashes( $this->tt('pubsurv_qr') ); ?></th></tr></table>')
<?php
				$dagURL = $this->dagQueryID( '' );
?>
      var vURLTR = $('<tr><td style="border:solid #000 1px;padding:3px"><i>none</i></td>' +
                     '<td style="border:solid #000 1px;padding:3px">' + vBaseURL + '&amp;_dag=' +
                     '<?php echo $this->escape( $dagURL ); ?></td>' +
                     '<td style="border:solid #000 1px;padding:3px;text-align:center">' +
                     '<a href="#" data-qr="' + vURLCode + '%26_dag%3D' +
                     '<?php echo $this->escape( $dagURL ); ?>">View</a></td></tr>')
      vURLTR.find('td').eq(1).on('click',function(){vFuncSelect(this)})
      vURLTR.find('a[data-qr]').eq(0).on('click',function(e){vFuncQRClick(this);e.preventDefault()})
      vURLTable.append(vURLTR)
<?php
				foreach ( $listDAGs as $dagID => $dagName )
				{
					$dagURL = $this->dagQueryID( $dagID );
?>
      var vURLTR = $('<tr><td style="border:solid #000 1px;padding:3px">' +
                     '<?php echo $this->escape( $dagName ); ?></td>' +
                     '<td style="border:solid #000 1px;padding:3px">' + vBaseURL + '&amp;_dag=' +
                     '<?php echo $this->escape( $dagURL ); ?></td>' +
                     '<td style="border:solid #000 1px;padding:3px;text-align:center">' +
                     '<a href="#" data-qr="' + vURLCode + '%26_dag%3D' +
                     '<?php echo $this->escape( $dagURL ); ?>">View</a></td></tr>')
      vURLTR.find('td').eq(1).on('click',function(){vFuncSelect(this)})
      vURLTR.find('a[data-qr]').eq(0).on('click',function(e){vFuncQRClick(this);e.preventDefault()})
      vURLTable.append(vURLTR)
<?php
				}
?>
      vURLTable.insertAfter( vInsertAfter )
      vInsertAfter.css('display','none')
      vURLTable.before('<p><?php echo addslashes( $this->tt('pubsurv_link_note') ); ?></p>')
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
    var vDialog = $('<div><p><?php
			echo $this->tt( 'addrec_name_exists1',
			                $_SESSION['module_customrecordnaming_amended'][0] ),
			     '<br>',
			     $this->tt( 'addrec_name_exists2',
			                $_SESSION['module_customrecordnaming_amended'][1] );
?></p></div>')
    vDialog.dialog(
    {
      autoOpen:true,
      modal:true,
      resizable:false,
      title:'<?php echo addslashes( $this->tt('addrec_name_exists') ); ?>',
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

		// Get the public survey logic mode and apply it if applicable.
		$nameType = $this->getProjectSetting( 'scheme-name-type' )[ $armSettingID ];
		$pubSvLogicMode =
				$this->getProjectSetting( 'scheme-public-survey-logic-mode' )[ $armSettingID ];
		if ( strpos( $nameType, 'S' ) !== false )
		{
			if ( substr( $pubSvLogicMode, 0, 1 ) == 'F' ) // replace following type
			{
				for ( $i = 0; $i < intval( substr( $pubSvLogicMode, 1, 1 ) ); $i++ )
				{
					$nameType = preg_replace( '/S./', 'S', $nameType );
				}
			}
			elseif ( $pubSvLogicMode == 'A' ) // replace all types
			{
				$nameType = 'S';
			}
		}

		// Check if a user supplied component is expected, so that the user can be prompted for it
		// when submitting the survey.
		$uPrompt = null;
		if ( strpos( $nameType, 'U' ) !== false )
		{
			$uPrompt = $this->getProjectSetting( 'scheme-prompt-user-supplied' )[ $armSettingID ];
			$uRegex = $this->getProjectSetting( 'scheme-user-supplied-format' )[ $armSettingID ];
		}

		// Check if a field lookup component is expected, so that the user can be prompted for it
		// when submitting the survey.
		$fPrompt = null;
		if ( strpos( $nameType, 'F' ) !== false )
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



	// Evaluates logic for arm access / creating records.
	function evaluateLogic( $logic )
	{
		// Empty logic always evaluates as true.
		if ( $logic == '' )
		{
			return true;
		}
		// If the logic is not syntactically valid, return false.
		if ( ! \LogicTester::isValid( $logic) )
		{
			return false;
		}
		$logic = \Piping::pipeSpecialTags( $logic, $this->getProjectId(), null, null, null, null,
		                                   true, null, null, false, false, false, true, false,
		                                   false, true );
		return \LogicTester::apply( $logic );
	}



	// Exclude state tracking settings from settings exports.
	public function exportProjectSettings()
	{
		$fnGetConfigFields = function ( $listConfig ) use ( &$fnGetConfigFields )
		{
			$listFields = [];
			foreach ( $listConfig as $infoConfig )
			{
				if ( $infoConfig['type'] == 'sub_settings' )
				{
					$listFields += $fnGetConfigFields( $infoConfig['sub_settings'] );
				}
				else
				{
					$listFields[ $infoConfig['key'] ] = $infoConfig['key'];
				}
			}
			return $listFields;
		};
		$this->getArmIdFromNum(1);
		$listSettings = [];
		$listSettingFields = $fnGetConfigFields( $this->getConfig()['project-settings'] );
		$listFullSettings = $this->getProjectSettings();
		foreach ( $listSettingFields as $key )
		{
			if ( ! in_array( $key, [ 'enabled', 'scheme-settings',
			                         'project-last-record', 'project-record-counter' ] ) &&
			     array_key_exists( $key, $listFullSettings ) && $listFullSettings[ $key ] !== null )
			{
				$value = $listFullSettings[ $key ];
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
		$listTypes = [];
		foreach ( [ 'R', 'G', 'U', 'T', 'S', 'F', 'C', 'Z', '1' ] as $code )
		{
			$listTypes[ $code ] = $this->tt( 'nametype_' . $code );
		}
		return $listTypes;
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
			$errMsg .= "\n- " . $this->tt('validate_setting_dag_format');
		}

		// Validate the settings for each custom naming scheme.
		// There should be a 1-1 mapping  between naming schemes and arms.
		$definedArms = [];
		for ( $i = 0; $i < count( $settings['scheme-settings'] ); $i++ )
		{
			// Check the arm is specified and has not already had a scheme defined for it.
			if ( $settings['scheme-arm'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_arm_req', ($i + 1) );
			}
			elseif ( in_array( $settings['scheme-arm'][$i], $definedArms ) )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_arm_def', ($i + 1) );
			}
			$definedArms[] = $settings['scheme-arm'][$i];

			// Ensure that the record name type has been set, and that the numbering does not
			// include components not used in the name.
			if ( ! isset( $settings['scheme-name-type'][$i] ) ||
			     $settings['scheme-name-type'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_name_type', ($i + 1) );
			}
			elseif ( ! empty( array_diff( str_split( $settings['scheme-numbering'][$i], 1 ),
			                              array_merge( ['','A'],
			                               str_split( $settings['scheme-name-type'][$i], 1 ) ) ) ) )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_numbering', ($i + 1) );
			}

			// Ensure that the constant value has been set if selected.
			if ( strpos( $settings['scheme-name-type'][$i], '1' ) !== false &&
			     $settings['scheme-const1'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_const1', ($i + 1) );
			}

			// Ensure that the starting number, if set, is a positive integer.
			if ( $settings['scheme-number-start'][$i] != '' &&
			     ! preg_match( '/^0|[1-9][0-9]*$/', $settings['scheme-number-start'][$i] ) )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_number_start', ($i + 1) );
			}

			// Validate the DAG name format for the naming scheme. This is required if the record
			// name includes the DAG. If the name format is specified then the subpattern must also
			// be specified, otherwise neither can be specified. The subpattern must point to a
			// valid subpattern within the name format regular expression.
			if ( strpos( $settings['scheme-name-type'][$i], 'G' ) !== false &&
			     $settings['scheme-dag-format'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_dag_format_1', ($i + 1) );
			}
			elseif ( $settings['scheme-dag-format'][$i] != '' &&
			         preg_match( $this->makePcreString( $settings['scheme-dag-format'][$i] ),
			                     '' ) === false )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_dag_format_2', ($i + 1) );
			}
			elseif ( $settings['scheme-dag-format'][$i] == '' &&
			         $settings['scheme-dag-section'][$i] != '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_dag_section_1', ($i + 1) );
			}
			elseif ( $settings['scheme-dag-format'][$i] != '' &&
			         $settings['scheme-dag-section'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_dag_section_2', ($i + 1) );
			}
			elseif ( $settings['scheme-dag-section'][$i] != '' &&
			         ! preg_match( '/^0|[1-9][0-9]*$/', $settings['scheme-dag-section'][$i] ) )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_dag_section_3', ($i + 1) );
			}
			elseif ( $settings['scheme-dag-section'][$i] >
			         substr_count( str_replace( [ '\\\\', '\(' ], '',
			                                    $settings['scheme-dag-format'][$i] ), '(' ) )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_dag_section_4', ($i + 1) );
			}

			// Ensure that the prompt for the user supplied name is provided if the record name type
			// includes a user supplied component.
			if ( strpos( $settings['scheme-name-type'][$i], 'U' ) !== false &&
			     $settings['scheme-prompt-user-supplied'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_usrsuppl', ($i + 1) );
			}

			// Validate the user supplied name format for the naming scheme.
			// This is required if the record name includes a user supplied component.
			if ( strpos( $settings['scheme-name-type'][$i], 'U' ) !== false &&
			     $settings['scheme-user-supplied-format'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_usrsuppl_fmt_1', ($i + 1) );
			}
			elseif ( $settings['scheme-user-supplied-format'][$i] != '' &&
			         preg_match( $this->makePcreString(
			                                       $settings['scheme-user-supplied-format'][$i] ),
			                     '' ) === false )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_usrsuppl_fmt_2', ($i + 1) );
			}

			// Ensure that the timestamp format and timezone are provided if the record name type
			// includes the timestamp.
			if ( strpos( $settings['scheme-name-type'][$i], 'T' ) !== false &&
			     $settings['scheme-timestamp-format'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_timestamp_fmt', ($i + 1) );
			}
			if ( strpos( $settings['scheme-name-type'][$i], 'T' ) !== false &&
			     $settings['scheme-timestamp-tz'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_timestamp_tz', ($i + 1) );
			}

			// Ensure that the prompt for the field lookup is provided if the record name type
			// includes a field value lookup.
			if ( strpos( $settings['scheme-name-type'][$i], 'F' ) !== false &&
			     $settings['scheme-prompt-field-lookup'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_field_lookup_1', ($i + 1) );
			}

			// Validate the lookup value field. This is required if the record name includes a
			// field value lookup.
			if ( strpos( $settings['scheme-name-type'][$i], 'F' ) !== false &&
			     $settings['scheme-field-lookup-value'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_field_lookup_2', ($i + 1) );
			}
			elseif ( $settings['scheme-field-lookup-value'][$i] != '' &&
			         ! in_array( $settings['scheme-field-lookup-value'][$i], $listFieldNames ) )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_field_lookup_3', ($i + 1) );
			}

			// Validate the lookup description field. This is required if the record name includes a
			// field value lookup.
			if ( strpos( $settings['scheme-name-type'][$i], 'F' ) !== false &&
			     $settings['scheme-field-lookup-desc'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_field_lookup_4', ($i + 1) );
			}
			elseif ( $settings['scheme-field-lookup-desc'][$i] != '' &&
			         ! in_array( $settings['scheme-field-lookup-desc'][$i], $listFieldNames ) )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_field_lookup_5', ($i + 1) );
			}

			// Ensure that the check digit algorithm is provided if the record name type includes
			// check digits.
			if ( strpos( $settings['scheme-name-type'][$i], 'C' ) !== false &&
			     $settings['scheme-check-digit-algorithm'][$i] == '' )
			{
				$errMsg .= "\n- " . $this->tt( 'validate_setting_scheme_check_digit', ($i + 1) );
			}

		}

		if ( $errMsg != '' )
		{
			return $this->tt('validate_setting') . $errMsg;
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
	protected function generateRecordName( $armID, $armSettingID, $groupCode, $oldName = null,
	                                       $incrementCounter = false, $eventID = null )
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
		$pubSvLogic = $this->getProjectSetting( 'scheme-public-survey-logic' )[ $armSettingID ];
		$pubSvLogicMode =
				$this->getProjectSetting( 'scheme-public-survey-logic-mode' )[ $armSettingID ];
		$chkDigitAlg = $this->getProjectSetting( 'scheme-check-digit-algorithm' )[ $armSettingID ];

		// Amend the name type based on whether this is a public survey / mobile app submission
		// (i.e. whether an old record name is present).
		if ( $oldName === null )
		{
			// Not a public survey / mobile app submission, ignore the public survey logic.
			$nameType = str_replace( 'S', '', $nameType );
		}
		elseif ( strpos( $nameType, 'S' ) !== false )
		{
			// This is a public survey / mobile app submission, apply the logic mode if not
			// standard.
			if ( substr( $pubSvLogicMode, 0, 1 ) == 'F' ) // replace following type
			{
				for ( $i = 0; $i < intval( substr( $pubSvLogicMode, 1, 1 ) ); $i++ )
				{
					$nameType = preg_replace( '/S./', 'S', $nameType );
				}
			}
			elseif ( $pubSvLogicMode == 'A' ) // replace all types
			{
				$nameType = 'S';
			}
		}

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

		// Get the public survey logic component if it has been entered
		// (public surveys / mobile app only).
		$pubSurveyComponent = '';
		if ( strpos( $nameType, 'S' ) !== false )
		{
			$pubSurveyInstrument = array_keys( \REDCap::getInstrumentNames() )[0];
			$pubSurveyComponent = \REDCap::evaluateLogic( $pubSvLogic, $this->getProjectId(),
			                                              $oldName, $eventID, 1,
			                                              $pubSurveyInstrument,
			                                              $pubSurveyInstrument, null, true );
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
		            'F' => $suppliedFieldValue, 'Z' => $currentUser, 'S' => $pubSurveyComponent ]
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
		// If the record counter is less than the starting number, set to the starting number.
		if ( ! isset( $recordCounter[ $counterID ] ) ||
		     ( $startNum != '' && intval( $startNum ) > $recordCounter[ $counterID ] ) )
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
				if ( in_array( $chkDigitAlg, [ 'mod97', 'mod11' ] ) )
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
					if ( $namingRun == 2 && $chkDigitAlg == 'mod11' )
					{
						// Consider numbers only.
						$recordName = preg_replace( '/[^0-9]/', '', strtoupper($recordName) );
						// Calculate mod-11 of converted record name.
						$checkDigits = 0;
						while ( strlen( $recordName ) > 0 )
						{
							$checkDigits += intval( substr( $recordName, 0, 1 ) ) *
							                ( strlen( $recordName ) + 1 );
							$recordName = substr( $recordName, 1 );
						}
						$checkDigits = ( 11 - ( $checkDigits % 11 ) ) % 11;
						$checkDigits = strval( $checkDigits == 10 ? 'X' : $checkDigits );
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
					elseif ( substr( $nameType, $i, 1 ) == 'S' ) // public survey component
					{
						$recordName .= $pubSurveyComponent;
					}
					elseif ( substr( $nameType, $i, 1 ) == 'F' ) // field value lookup
					{
						$recordName .= $suppliedFieldValue;
					}
					elseif ( substr( $nameType, $i, 1 ) == 'C' ) // check digits
					{
						if ( $namingRun == 2 && in_array( $chkDigitAlg, [ 'mod97', 'mod11' ] ) )
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
	                                     $isSurvey, $dag = false )
	{
		$fnFormatPromptText = function( $text )
		{
			$text = $this->escape( $text );
			$text = nl2br( $text, false );
			$text = str_replace( ["\r", "\n"], '', $text );
			$fnParse = function( $m )
			{
				return '<' . $m[2] . $m[4] .
				       ( $m[2] == '' ? '' : ( ' href="' . $m[3] . '" target="_blank"' ) ) .
				       '>' . $m[5] . '</' . $m[6] . '>';
			};
			return preg_replace_callback( '/&lt;((?<t1>a) href=&quot;((?(?=&quot;)|.)*)&quot;|' .
			                              '(?<t2>b|i))&gt;(.*?)&lt;\/((?P=t1)|(?P=t2))&gt;/',
			                              $fnParse, $text );
		};
		$output = "function ($jsParams) { var vDialog = $('<div></div>');";
		if ( $dag !== false )
		{
			$output .= "vDialog.append('<p>" . addslashes( $this->tt('prompt_dag') ) . "</p>');" .
			           "var vDAGList = $('<select><option></option>";
			foreach ( \REDCap::getGroupNames() as $dagID => $dagName )
			{
				$output .= '<option';
				if ( is_integer( $dag ) )
				{
					$output .= ' selected';
				}
				$output .= ' value="' . $this->escape( $dagID ) . '">' .
				           $this->escape( $dagName ) . '</option>';
			}
			$output .= "');vDialog.append($('<p style=\"max-width:99%\"></p>').append(vDAGList));" .
			           "var vDAGListErr = $('<p style=\"color:#c00\"></p>');" .
			           "vDialog.append(vDAGListErr);";
		}
		if ( $dag !== false && ( $userSuppliedPrompt !== null || $fieldValuePrompt !== null ) )
		{
			$output .= "vDialog.append('<hr>');";
		}
		if ( $userSuppliedPrompt !== null )
		{
			$output .= "vDialog.append('<p>" .
			           $fnFormatPromptText( $userSuppliedPrompt ) . "</p>');" .
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
			           $fnFormatPromptText( $fieldValuePrompt ) . "</p>');" .
			           "var vFieldValues = $('<select><option></option>";
			foreach ( $listFields as $fieldValue => $fieldDesc )
			{
				$output .= '<option value="' . $this->escape( $fieldValue ) .
				           '">' . $this->escape( $fieldDesc ) . '</option>';
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
		if ( $dag !== false )
		{
			$output .= "vDAGListErr.text('');if (vDAGList.val() == ''){vValid = false;" .
			           "vDAGListErr.text('" . addslashes( $this->tt('prompt_err_blank') ) . "')};";
		}
		if ( $userSuppliedPrompt !== null )
		{
			$output .= "vUserSuppliedErr.text('');if (vUserSupplied.val() == ''){vValid = false;" .
			           "vUserSuppliedErr.text('" . addslashes( $this->tt('prompt_err_blank') ) .
			           "')}else if(!new RegExp(" . json_encode( $userSuppliedRegex ) .
			           ").test( vUserSupplied.val() ) ){vValid = false;" .
			           "vUserSuppliedErr.text('" . addslashes( $this->tt('prompt_err_invalid') ) .
			           "')};";
		}
		if ( $fieldValuePrompt !== null )
		{
			$output .= "vFieldValuesErr.text('');if (vFieldValues.val() == ''){vValid = false;" .
			           "vFieldValuesErr.text('" . addslashes( $this->tt('prompt_err_blank') ) .
			           "')};";
		}
		$output .= 'if (vValid){';
		if ( $dag !== false )
		{
			$output .= "document.cookie = 'redcap_custom_record_name_selecteddag=' + " .
			           "encodeURIComponent( vDAGList.val() ) + ';secure';" .
			           "vDAGList.prop('disabled',true);";
		}
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

		$newRecordID = $this->generateRecordName( $armID, $armSettingID, $groupCode,
		                                          $oldRecordID, true, $eventID );
		if ( $dagID !== '' )
		{
			$this->setDAG( $oldRecordID, $dagID );
		}
		if ( $oldRecordID != $newRecordID )
		{
			$newRecordIDFinal = $newRecordID;
			$newRecordIDCtr = 0;
			while ( $this->countRecords( $newRecordIDFinal ) == 1 )
			{
				$newRecordIDCtr++;
				$newRecordIDFinal = $newRecordID . '--' . $newRecordIDCtr;
			}
			\DataEntry::changeRecordId( $oldRecordID, $newRecordIDFinal );
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
	private $promptDAG;

}

