<?php
if (WP_UNINSTALL_PLUGIN === "snapshot/snapshot.php") {

	if (!isset($snapshot))
	{
		include dirname(__FILE__) . "/snapshot.php";
		$snapshot = new DBSnapshot();
	}
	$snapshot->uninstall_snapshot();
}
