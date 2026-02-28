<?php
require_once __DIR__ . '/../config/bootstrap.php';
logout_user();
redirect('/admin/login.php');
