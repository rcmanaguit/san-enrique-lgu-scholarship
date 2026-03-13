<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login('../login.php');
require_admin('../index.php');

redirect('application-periods.php#requirements');
