<?php
declare(strict_types=1);
// Entry point independent of Apache rewrite rules, database and installation.
header('Location: it/index.html' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;
