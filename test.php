<?php
include './vendor/autoload.php';
$method = 'handle'.studly_case(str_replace('.', '_', 'subscription.create'));
echo $method;