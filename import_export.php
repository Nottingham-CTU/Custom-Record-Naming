<?php
/*
 *	Import/export the custom record naming configuration from/to a JSON document.
 */


if ( ! $module->getUser()->isSuperUser() )
{
	exit;
}

// Define setting names for export/import.
$globalSettingNames = [ 'numbering', 'dag-format', 'dag-format-notice' ];
$schemeSettingNames = [ 'name-type', 'name-prefix', 'name-separator', 'name-suffix', 'number-start',
                        'number-pad', 'dag-format', 'dag-section', 'prompt-user-supplied',
                        'user-supplied-format', 'timestamp-format', 'timestamp-tz',
                        'prompt-field-lookup', 'field-lookup-value', 'field-lookup-desc',
                        'field-lookup-filter' ];


// Export the data.
if ( isset( $_GET['export'] ) )
{
	// Data is provided as JSON download.
	header( 'Content-Type: application/json' );
	header( 'Content-Disposition: attachment; filename=' .
	        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
	        '_record_naming_' . gmdate( 'Ymd-His' ) . '.json' );
	// Get global (project-wide) settings.
	$data = [ 'global' => [ 'dag-format' => $module->getProjectSetting( 'dag-format' ),
	                        'dag-format-notice' => $module->getProjectSetting( 'dag-format-notice' ),
	                        'numbering' => $module->getProjectSetting( 'numbering' ) ] ];
	// Get the arms (which are currently configured).
	$arms = $module->getProjectSetting( 'scheme-arm' );
	// Get the values for each setting.
	$settings = [];
	foreach ( $schemeSettingNames as $settingName )
	{
		$settings[$settingName] = $module->getProjectSetting( 'scheme-' . $settingName );
	}
	// Re-group the setting values by arm.
	for ( $i = 0; $i < count( $arms ); $i++ )
	{
		$armName = $module->getArmNameFromId( $arms[$i] );
		foreach ( $settings as $settingName => $settingValues )
		{
			$data['scheme'][$armName][$settingName] = $settingValues[$i];
		}
	}
	// Output the data and exit.
	echo json_encode( $data );
	exit;
}

// Import the data.
$importConfirm = false;
$importSuccess = false;
if ( isset( $_FILES['import'] ) )
{
	// Only proceed if uploaded file is valid.
	if ( is_uploaded_file( $_FILES['import']['tmp_name'] ) )
	{
		// Read the imported data, check it is valid JSON, and prepare the comparison table.
		$importData = json_decode( file_get_contents( $_FILES['import']['tmp_name'] ), true );
		if ( $importData !== null )
		{
			// Get currently configured arms.
			$arms = $module->getProjectSetting( 'scheme-arm' );
			// Get project setting metadata, and create lookup arrays of the setting labels and
			// (for multiple choice options) the choice labels.
			$projectSettings = $module->getConfig()['project-settings'];
			$settingNames = [];
			$settingValues = [];
			for ( $i = 0; $i < count($projectSettings); $i++ )
			{
				if ( isset( $projectSettings[$i]['sub_settings'] ) )
				{
					$projectSettings =
						array_merge( $projectSettings, $projectSettings[$i]['sub_settings'] );
				}
				elseif ( $projectSettings[$i]['type'] != 'descriptive' )
				{
					$settingNames[ $projectSettings[$i]['key'] ] = $projectSettings[$i]['name'];
					if ( isset( $projectSettings[$i]['choices'] ) )
					{
						foreach ( $projectSettings[$i]['choices'] as $choice )
						{
							$settingValues[ $projectSettings[$i]['key'] ][ $choice['value'] ] =
								$choice['name'];
						}
					}
				}
			}
			// Place the old and new values in an array to allow for easy display/comparison.
			$settingCompare = [];
			foreach ( $globalSettingNames as $setting )
			{
				$settingCompare[''][$setting]['old'] = $module->getProjectSetting( $setting );
				$settingCompare[''][$setting]['new'] = $importData['global'][$setting];
			}
			for ( $i = 0; $i < count( $arms ); $i++ )
			{
				$armName = $module->getArmNameFromId( $arms[$i] );
				if ( $armName == '' )
				{
					continue;
				}
				foreach ( $schemeSettingNames as $setting )
				{
					$settingCompare[$armName][$setting]['old'] =
						$module->getProjectSetting( "scheme-$setting" )[$i];
				}
			}
			foreach ( $importData['scheme'] as $armName => $armData )
			{
				if ( $module->getArmIdFromName( $armName ) === null )
				{
					$armName .= ' (arm does not exist in project)';
				}
				foreach ( $schemeSettingNames as $setting )
				{
					$settingCompare[$armName][$setting]['new'] = $armData[$setting];
				}
			}
			// Indicate success for this stage of the import.
			$importConfirm = true;
		}
	}
}
elseif ( isset( $_POST['import'] ) )
{
	// Read the imported data and check it is valid JSON.
	$importData = json_decode( $_POST['import'], true );
	if ( $importData !== null )
	{
		// Import the global (project-wide) settings.
		foreach ( $globalSettingNames as $setting )
		{
			if ( isset( $importData['global'][$setting] ) )
			{
				$module->setProjectSetting( $setting, $importData['global'][$setting] );
			}
		}
		// Prepare the new scheme settings for import (initialise to empty array).
		$newSchemeSettings = [ 'scheme-arm' => [] ];
		foreach ( $schemeSettingNames as $setting )
		{
			$newSchemeSettings["scheme-$setting"] = [];
		}
		// For each arm name in the imported file, get the arm ID (if the arm exists) and for each
		// setting, append the arm's setting to the setting array.
		foreach ( $importData['scheme'] as $newArmName => $newArmData )
		{
			$newArmID = $module->getArmIdFromName( $newArmName );
			if ( $newArmID === null )
			{
				// If the arm name does not correspond to an arm in the project, skip the arm.
				continue;
			}
			// Append 'true' to scheme-settings, to indicate the number of naming schemes.
			$newSchemeSettings['scheme-settings'][] = 'true';
			// Append the arm ID to scheme-arm.
			$newSchemeSettings['scheme-arm'][] = "$newArmID";
			foreach ( $schemeSettingNames as $setting )
			{
				$newSchemeSettings["scheme-$setting"][] = $newArmData[$setting];
			}
		}
		// Apply the new scheme settings.
		foreach ( $newSchemeSettings as $setting => $value )
		{
			$module->setProjectSetting( $setting, $value );
		}
		// Indicate success.
		$importSuccess = true;
	}
}

// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>
<div class="projhdr">Import and Export Custom Record Naming Settings</div>
<p>&nbsp;</p>
<?php
// If confirming the changes upon import, show the comparison table.
if ( $importConfirm )
{

?>
<p>
 Please check the changes to the settings and click the button at the bottom to complete the import.
</p>
<p>
 <b>Note:</b> Any imported arms which do not exist in the project will be ignored.
</p>
<table id="importCompareTable">
 <tr>
  <th>Setting</th>
  <th>Old value</th>
  <th>New value</th>
 </tr>
<?php
	foreach ( $settingCompare as $armName => $armData )
	{
		if ( $armName != '' )
		{
?>
 <tr>
  <th colspan="3"><?php echo htmlspecialchars( $armName ); ?></th>
 </tr>
<?php
		}
		foreach ( $armData as $setting => $values )
		{
			if ( $armName != '')
			{
				$setting = "scheme-$setting";
			}
?>
 <tr>
  <td><?php
			echo htmlspecialchars( $settingNames[$setting] ); ?></td>
  <td><?php
			echo htmlspecialchars( $settingValues[$setting][$values['old']] ?? $values['old'] );
?></td>
  <td><?php
			echo htmlspecialchars( $settingValues[$setting][$values['new']] ?? $values['new'] );
?></td>
 </tr>
<?php
		}
	}
?>
</table>
<form method="post">
 <p>
  <input type="submit" value="Import">
  <input type="hidden" name="import" value="<?php
	echo htmlspecialchars( json_encode( $importData ) ); ?>">
 </p>
</form>
<script type="text/javascript">
$('head').append('<style type="text/css">#importCompareTable th, #importCompareTable td ' +
                 '{border:solid 1px #000;padding:3px}</style>')
</script>
<?php

}
// Otherwise, show the export and import options.
else
{

	if ( $importSuccess )
	{

?>
<p><b>Settings imported successfully.</b></p>
<p>&nbsp;</p>
<?php

	}

?>
<p><a href="./?<?php echo htmlspecialchars( $_SERVER['QUERY_STRING'] );
?>&amp;export=1">Export Settings</a></p>
<p>&nbsp;</p>
<form method="post" enctype="multipart/form-data">
 <p>Import Settings:&nbsp; <input type="file" name="import"><input type="submit" value="Import"></p>
</form>
<?php

}

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
