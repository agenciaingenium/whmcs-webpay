<?php

use WHMCS\ClientArea;

define('CLIENTAREA', true);

require '../../../init.php';

$ca = new ClientArea();
$ca->setPageTitle('Error de pago');
$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('mypage.php', 'Error de pago');
$ca->setTemplate('webpaydirecto_error');
$ca->output();
