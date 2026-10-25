<?php
declare(strict_types=1);
// Entry point independent of Apache rewrite rules, database and installation. 301 since 2.1.48 (audit: the root was a 302).
header('Location: it/index.html' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
