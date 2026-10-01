<?php
// ===== ENTRY GUARD =====
// Stops the web server from ever showing a directory index for the project
// root and sends every visitor to the Brew & Co. portal hub instead.
header('Location: hub.php');
exit;
