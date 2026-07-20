<?php
session_start();
require 'C:\xampp\htdocs\pro_gvp\includes\auth.php';
$token1 = csrfToken();
$_POST['refresh'] = 1;
$_POST['csrf_token'] = $token1;
var_dump('check1', csrfCheck());
$_POST['csrf_token'] = 'bad';
var_dump('check2', csrfCheck());
$_SESSION['csrf_token'] = null;
$token2 = csrfToken();
$_POST['csrf_token'] = $token2;
var_dump('check3', csrfCheck());
