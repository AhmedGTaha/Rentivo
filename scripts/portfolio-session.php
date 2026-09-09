<?php

declare(strict_types=1);

session_name('rentivo_session');
session_id('rentivoportfolioadmin');
session_start();

$_SESSION['_auth_user_id'] = 1;

session_write_close();

echo "Created local demo session for user 1.\n";
