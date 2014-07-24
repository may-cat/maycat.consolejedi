<?
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (class_exists('maycat_consolejedi')) {
    return;
}

class maycat_consolejedi extends CModule
{
    public $MODULE_ID = 'maycat.consolejedi';
    public $MODULE_VERSION = '0.0.1';
    public $MODULE_VERSION_DATE = '2014-07-17 16:23:14';
    public $MODULE_NAME = 'Console Jedi';
    public $MODULE_DESCRIPTION = 'Консольные приложения';
    public $MODULE_GROUP_RIGHTS = 'N';
    public $PARTNER_NAME = "May Cat";
    public $PARTNER_URI = "http://may-cat.ru";

    public function DoInstall()
    {
        global $APPLICATION;

        // Копируем файлы на уровень выше DOCUMENT_ROOT!
        $target_path = realpath($_SERVER['DOCUMENT_ROOT']."/../");
        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT']."/bitrix/modules/maycat.consolejedi/shell/",
            $target_path,
            true,
            true
        );

        // И подменяем в демо-визарде путь к сайту, чтобы оный демо-визард мог найти Битрикс
        $lid = array_pop(explode('/',$_SERVER["DOCUMENT_ROOT"]));
        passthru("sed -i -e 's/s1/$lid/g' $target_path/_wizards/2014-05-27-t10000/run.php");

        // @todo: Также должен информировать разработчика о том, во что он вляпался и как с этим работать
        // @todo: На какой-то ман вести, что ли...
        RegisterModule($this->MODULE_ID);
    }

    public function DoUninstall()
    {
        global $APPLICATION;
        UnRegisterModule($this->MODULE_ID);
    }
}
