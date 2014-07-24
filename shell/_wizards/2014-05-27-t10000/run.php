<?
// Настройки, чтобы скрипт добрался до Битрикса
$_SERVER['DOCUMENT_ROOT']=realpath(__DIR__."/../../s1");

// Задаём константы
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

// Подключаем Битрикс
require $_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('maycat.consolejedi');
\Bitrix\Maycat\Consolejedi\Consoleapp::checkIfUserIsAdequateOrDie('Вы точно хотите запустить визард?','Y');