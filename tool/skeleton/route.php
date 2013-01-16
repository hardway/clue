<?php 
$router=$app['router'];

$router->connect('/:controller/:action');
$router->connect('/:controller');
