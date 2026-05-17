<?php
// Root entry point — forward to the public homepage.
require_once __DIR__ . '/config/db.php';
redirect('public/index.php');
