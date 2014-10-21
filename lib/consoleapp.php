<?
/**
 * Общий класс для упрощения работы с пользователем через консоль
 */
namespace Maycat\Consolejedi;

class Consoleapp
{

    static function checkIfIsConsoleOrDie()
    {
        global $_SERVER;
        if ($_SERVER['DOCUMENT_ROOT'][0]!='.') // Если путь не относительный - то скорее всего это апачом сгенерированный путь и лучше не продолжать
            die('you shall not pass!');
    }


    static function checkIfUserIsAdequateOrDie($message,$confirm_message)
    {
        while(ob_get_level()) ob_end_clean();
        echo "$message\nВы уверены?\n(Если уверены - напечатайте фразу \"$confirm_message\")\n";
        $handle = fopen ("php://stdin","r");
        $line = fgets($handle);
        if(trim($line) != $confirm_message)
            die("Вы ошиблись в написании или предпочли отменить операцию\n");
        echo "\n";
    }
}