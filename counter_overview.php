<?php

namespace Nottingham\CustomRecordNaming;


if ( SUPER_USER != 1 )
{
	exit;
}

if ( ! empty( $_POST ) )
{
	if ( preg_match( '/^[1-9][0-9]*$/', $_POST['counterValue'] ) )
	{
		$listCounters = json_decode( $module->getProjectSetting( 'project-record-counter' ), true );
		if ( isset( $listCounters[ $_POST['counterID'] ] ) )
		{
			$listCounters[ $_POST['counterID'] ] = $_POST['counterValue'];
			$module->setProjectSetting( 'project-record-counter', json_encode( $listCounters ) );
		}
	}
	header( 'Location: ' . $_SERVER['REQUEST_URI'] );
	exit;
}

// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$listCounters = json_decode( $module->getProjectSetting( 'project-record-counter' ), true );

?>
<div class="projhdr"><?php echo $module->tt('counter_overview'); ?></div>
<table class="dataTable cell-border no-footer">
 <thead>
  <tr>
   <th><?php echo $module->tt('counter_id'); ?></th>
   <th><?php echo $module->tt('counter_value'); ?></th>
   <th><?php echo $module->tt('counter_new_value'); ?></th>
  </tr>
 </thead>
 <tbody>
<?php
foreach ( $listCounters as $counterID => $counterValue )
{
?>
  <tr>
   <td><?php echo $module->escape( $counterID ); ?></td>
   <td><?php echo $module->escape( $counterValue ); ?></td>
   <td>
    <form method="post" onsubmit="return confirm(&quot;<?php
	echo $module->tt( 'counter_confirm', $counterID ); ?>?&quot;)">
     <input type="hidden" name="counterID" value="<?php echo $module->escape( $counterID ); ?>">
     <input type="number" name="counterValue" value="<?php
	echo $module->escape( $counterValue ); ?>" min="1" style="width:95px">
     <input type="submit" value="<?php echo $module->tt('counter_set'); ?>">
    </form>
  </tr>
<?php
}
?>
 </tbody>
</table>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
