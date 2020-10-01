<?php

// This page receives AJAX requests for the purposes of 'keeping alive' an assigned record name
// during the period between the name being generated and data for the record being saved for the
// first time. If the record being 'kept alive' is still the most recently generated name, then the
// corresponding timestamp value is reset to the current time.

header( 'Content-Type: application/json' );

$record = $_POST['record'];
$armID = $_POST['arm'];
$groupCode = $_POST['dag'];

if ( $record == '' || !isset( $_SERVER['HTTP_X_RC_CRN_REQ'] ) )
{
	echo 'false';
	exit;
}

$numbering = $module->getProjectSetting( 'numbering' );
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

$lastRecord = json_decode( $module->getProjectSetting( 'project-last-record' ), true );
if ( $lastRecord[$counterID]['name'] == $record )
{
	$lastRecord[$counterID]['timestamp'] = time();
	$module->setProjectSetting( 'project-last-record', json_encode( $lastRecord ) );
	echo 'true';
	exit;
}

echo 'false';
