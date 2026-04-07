<?php
/**
 * Force logout and redirect to signin
 */
session_start();
session_destroy();
header('Location: /Gatherly/public/pages/signin.php');
exit;