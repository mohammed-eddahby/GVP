<?php
session_start();
$_SESSION['csrf_token'] = 'test';
$_POST['refresh'] = 1;
$_POST['csrf_token'] = 'test';
require 'C:\xampp\htdocs\pro_gvp\dashboard_analytics.php';
