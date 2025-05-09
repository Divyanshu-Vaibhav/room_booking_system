<?php
// This script can be run manually or via a cron job
require_once 'config/database.php';
require_once 'includes/functions.php';

// Release expired blocks
$released_count = releaseExpiredBlocks($conn);

echo "Released $released_count expired room blocks.";
?>
