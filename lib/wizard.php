<?
namespace Bitrix\Maycat\Consolejedi;

/**
 * Class CWizard
 * Класс, облегчающий написание "визардов", вносящих изменения в базу данных.
 *
 * Три Закона Волшебнотехники
 * 1. Спрашивай пользователя, уверен ли он в своих действиях.
 * 2. Повторный запуск визарда не должен ничего нарушать на проекте.
 * 3. Визарды должны быть "тонкими", вся логика должна выноситься в классы-помощники.
 *
 * Только таким образом мы можем обеспечить безопасность данных, защитить проект от человеческого фактора
 * при разворачивании изменений и сделать код изменений читаемым, то есть - поддерживаемым.
 *
 * Ниже приведён базовый класс-помощник. Класс оптимизирован под работу из консоли.
 * В случае ошибок - он бросает exception'ы.
 * О каждом успешном своём деянии он echo'ает ободряющие сообщения, заканчивающиеся \n (никаких <br>!)
 */
class Wizard
{
    /////////////////////////////////  Создание сущностей  //////////////////////////////////

    /**
     * Копировать инфоблок и его свойства
     *
     * @param $arSearchSource array массив, с помощью которого метод будет искать копируемый инфоблок
     * @param $arSearchTarget array массив, с помощью которого метод будет искать результирующий инфоблок - а то вдруг как он уже существует?
     * @param $arElementFix array описания полей, которые нужно подменить в существующем инфоблоке (напр.: скопированному инфоблоку нужно сразу вставить другое название)
     * @return bool|int false или id созданного инфоблока
     * @throws Exception в случае ошибки
     */
    static function сopyIBlockIfNotExists($arSearchSource, $arSearchTarget, $arElementFix)
    {
        // Находим старый инфоблок
        $resSourceIB = CIBlock::GetList(array(), $arSearchSource);
        $arSourceFields = $resSourceIB->GetNext();
        if (!$arSourceFields['ID'])
            throw new Exception("Не нашёл инфоблока для копирования [{$arSearchSource['ID']}");
        // Находим новый инфоблок, если есть
        $resTargetIB = CIBlock::GetList(array(), $arSearchTarget);
        $arTargetFields = $resTargetIB->GetNext();
        if ($arTargetFields['ID'])
            return $arTargetFields['ID'];
        // Вносим коррективы
        $arNewFields = array_merge($arSourceFields, $arElementFix);
        unset($arNewFields["ID"]);
        foreach ($arNewFields as $k => $v) {
            if (!is_array($v)) $arNewFields[$k] = trim($v);
            if ($k{0} == '~') unset($arNewFields[$k]);
        }
        // Копируем инфоблок
        $ib = new CIBlock();
        $new_iblock_id = $ib->Add($arNewFields);
        if (!$new_iblock_id)
            throw new Exception("Не удалось создать инфоблок. Причина: " . $ib->LAST_ERROR);
        // Работаем со свойствами //
        $ibp = new CIBlockProperty;
        $properties = CIBlockProperty::GetList(Array("sort" => "asc", "name" => "asc"), Array("ACTIVE" => "Y", "IBLOCK_ID" => $arSourceFields['IBLOCK_ID']));
        while ($prop_fields = $properties->GetNext()) {
            //// Для списков - досчитываем данные по списку /////
            if ($prop_fields["PROPERTY_TYPE"] == "L") {
                $property_enums = CIBlockPropertyEnum::GetList(Array("DEF" => "DESC", "SORT" => "ASC"), Array("IBLOCK_ID" => $arSourceFields['IBLOCK_ID'], "CODE" => $prop_fields["CODE"]));
                while ($enum_fields = $property_enums->GetNext()) {
                    $prop_fields["VALUES"][] = Array(
                        "VALUE" => $enum_fields["VALUE"],
                        "DEF" => $enum_fields["DEF"],
                        "SORT" => $enum_fields["SORT"]
                    );
                }
            }
            //// Подменяем поля ////
            $prop_fields["IBLOCK_ID"] = $new_iblock_id;
            unset($prop_fields["ID"]);
            foreach ($prop_fields as $k => $v) {
                if (!is_array($v)) $prop_fields[$k] = trim($v);
                if ($k{0} == '~') unset($prop_fields[$k]);
            }
            ///// Создаём на новом месте /////
            $PropID = $ibp->Add($prop_fields);
            if (intval($PropID) <= 0)
                throw new Exception("Не удалось скопировать свойство {$prop_fields['CODE']} ");
        }
        // Возвращаем номер инфоблока
        return $new_iblock_id;
    }


    /**
     * @param $arFields array описание создаваемой секции. Метод будет пытаться найти уже существующую секцию по id инфоблока и коду
     * @return null|int пустота или номер созданной секции
     * @throws Exception
     */
    static function сreateSectionIfNotExists($arFields)
    {
        if (!$arFields['CODE'])
            throw new Exception("Запрошено создание секции, но не указан её код. Я не могу определить, существует она или нет.");
        if (!$arFields['IBLOCK_ID'])
            throw new Exception("Запрошено создание секции, но не указан инфоблок. Куда писать-то?!");

        $arSection = CIBlockSection::GetList(null, array('IBLOCK_ID' => $arFields['IBLOCK_ID'], 'CODE' => $arFields['CODE']), null, array('ID'))->Fetch();
        if (!$arSection['ID']) {
            $rsSection = new CIBlockSection;
            if ($id = $rsSection->Add($arFields))
                return $id;
            return null; // @todo: throw exceptioin!
        }
        return $arSection["ID"];
    }









    // @todo: разобрать всё, что ниже


    /**
     * Регистрирует в базе Битрикса сайт исходя из пришедших настроек
     * @param $arFields array массив настроек создаваемого сайта. Дополняется имеющимися в функции дефолтными значениями
     */
    static function createSite($arFields)
    {
        if (!is_array($arFields))
            throw new Exception('createSite должен получить массив с данными!');

        $arFieldsDefault = Array(
            "ACTIVE" => "Y",
            "SORT" => 200,
            "DEF" => "N",
            "DIR" => "/",
            "FORMAT_DATE" => "DD.MM.YYYY",
            "FORMAT_DATETIME" => "DD.MM.YYYY HH:MI:SS",
            "CHARSET" => "UTF-8",
            "LANGUAGE_ID" => "ru",
            'CULTURE_ID' => 2
        );
        $arFields = array_merge($arFieldsDefault, $arFields);

        // проверь - может он уже есть!
        $rsSites = CSite::GetList($by = "sort", $order = "desc", Array("ID" => $arFields['LID']));
        $arSite = $rsSites->Fetch();
        if ($arSite['LID']) {
            echo "site already exists\n";
            return null;
        } // already exists

        // Созадём
        $obSite = new CSite;
        if (!$obSite->Add($arFields))
            throw new Exception("\t\t Не удалось создать сайт: " . $obSite->LAST_ERROR . "\n");

        return true;
    }

    /**
     * Создание элемента инфоблока
     * @param $arFields
     * @param array $arrFilter
     * @return bool|int
     * @throws Exception
     */
    static function createIBlockElementIfNotExists($arFields, $arrFilter = array())
    {
        $arFilter = array_merge(
            array(
                'IBLOCK_ID' => $arFields['IBLOCK_ID'],
                'NAME' => $arFields['NAME']
            ),
            $arrFilter
        );
        $arSelect = array('ID');
        $res = CIBlockElement::GetList(Array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
        $ob = $res->GetNext();
        if (!$id = $ob['ID']) {
            $el = new CIBlockElement;
            if (!$id = $el->Add($arFields))
                throw new Exception("Не смог создать элемент инфоблока с главным редактором:\n" . $el->LAST_ERROR . "\n");
        }
        return $id;
    }


    static function createIBlockIfNotExists($arSearch, $arFields)
    {
        $res = CIBlock::GetList(array(), $arSearch, true);
        if ($ar_res = $res->Fetch())
            return $ar_res['ID'];

        $ib = new CIBlock();
        $new_iblock_id = $ib->Add($arFields);
        if (intval($new_iblock_id) <= 0)
            throw new Exception("Не удалось создать инфоблок. Причина: " . $ib->LAST_ERROR);

        return $new_iblock_id;
    }


    /**
     * Создание свойства инфоблока
     * @param $iblock_id
     * @param $arFields
     * @return bool|int
     * @throws Exception
     */
    static function createPropertyIfNotExists($iblock_id, $arFields)
    {
        if (!is_array($arFields) && $arFields)
            throw new Exception("Массив с описанием создаваемого свойства пуст!");

        if (!$arFields['CODE'])
            throw new Exception("Не задан символьный код свойства!");

        if (!$iblock_id)
            throw new Exception("Не задан номер инфоблока!");

        $properties = CIBlockProperty::GetList(
            array("sort" => "asc", "name" => "asc"),
            array(
                "IBLOCK_ID" => $iblock_id,
                'CODE' => $arFields['CODE']
            )
        );
        $prop_fields = $properties->GetNext();
        if (!$prop_fields['ID']) {
            $arFields["IBLOCK_ID"] = $iblock_id;

            $ibp = new CIBlockProperty;
            if (!$PropID = $ibp->Add($arFields)) {
                throw new Exception("Что-то пошло не так при создании свойтсва: " . $ibp->LAST_ERROR);
            }
            return $PropID;
        }
        return $prop_fields['ID'];
    }


    static function createUserFieldIfNotExists($arSearch, $arFields)
    {
        if (!($arSearch['ENTITY_ID'] && $arFields['ENTITY_ID']))
            throw new Exception("В поиске или в полях не указана сущность, к которой привязано поле");
        if (!($arSearch['FIELD_NAME'] && $arFields['FIELD_NAME']))
            throw new Exception("В поиске или в полях не указано название пользовательского поля");

        // Находим, если это возможно, пользовательское поле
        $rsEntity = CUserTypeEntity::GetList(array(), $arSearch);
        $arRes = $rsEntity->Fetch();
        if ($arRes['ID'])
            return $arRes['ID'];
        // Создаём
        $arField = array_merge(array(
            'FIELD_NAME' => null,
            'XML_ID' => null,
            'ENTITY_ID' => null,
            'USER_TYPE_ID' => 'string',
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'LIST_COLUMN_LABEL' => array(
                'ru' => 'Ссылка'
            ),
            'LIST_FILTER_LABEL' => array(
                'ru' => 'Ссылка'
            ),
            'EDIT_FORM_LABEL' => array(
                'ru' => 'Ссылка'
            ),
            'SETTINGS' => array(
                'DEFAULT_VALUE' => '',
            )
        ), $arFields);
        $rsUF = new CUserTypeEntity();
        if (!$id = $rsUF->Add($arField))
            throw new Exception("Не получилось создать пользовательское свойство: " . $rsUF->LAST_ERROR);
        return $id;
    }

    /////////////////////////////////  Копирование сущностей  //////////////////////////////////


    /**
     * Копирование элемента инфоблока
     * @param $id
     * @param $arNewFields
     * @return bool|int|null
     */
    static function copyIBlockElement($id, $arNewFields)
    {
        CModule::IncludeModule('iblock');
        $resource = CIBlockElement::GetByID($id);
        if ($ob = $resource->GetNextElement()) {
            // Читаем данные
            $arFields = $ob->GetFields();
            $arFields['PROPERTIES'] = $ob->GetProperties();
            $arFieldsCopy = $arFields;
            $arFieldsCopy['PROPERTY_VALUES'] = array();

            // Чистим левые поля
            unset($arFieldsCopy['ID'], $arFieldsCopy['TMP_ID'], $arFieldsCopy['WF_LAST_HISTORY_ID'], $arFieldsCopy['SHOW_COUNTER'], $arFieldsCopy['SHOW_COUNTER_START']);

            // Обрабатываем доп. данные
            foreach ($arFields['PROPERTIES'] as $property) {
                if ($property['PROPERTY_TYPE'] == 'F') {
                    if ($property['MULTIPLE'] == 'Y') {
                        if (is_array($property['VALUE'])) {
                            foreach ($property['VALUE'] as $key => $arElEnum)
                                $arFieldsCopy['PROPERTY_VALUES'][$property['CODE']][$key] = CFile::CopyFile($arElEnum);
                        }
                    } else $arFieldsCopy['PROPERTY_VALUES'][$property['CODE']] = CFile::CopyFile($property['VALUE']);
                } elseif ($property['PROPERTY_TYPE'] == 'L') {
                    if ($property['MULTIPLE'] == 'Y') {
                        $arFieldsCopy['PROPERTY_VALUES'][$property['CODE']] = array();
                        foreach ($property['VALUE_ENUM_ID'] as $enumID) {
                            $arFieldsCopy['PROPERTY_VALUES'][$property['CODE']][] = array(
                                'VALUE' => $enumID
                            );
                        }
                    } else {
                        $arFieldsCopy['PROPERTY_VALUES'][$property['CODE']] = array(
                            'VALUE' => $property['VALUE_ENUM_ID']
                        );
                    }
                } else
                    $arFieldsCopy['PROPERTY_VALUES'][$property['CODE']] = $property['VALUE'];
            }

            // Меняем те поля, которые надо экранировать
            $arFieldsCopy = self::_array_merge_recursive_distinct($arFieldsCopy, $arNewFields);

            // Удаляем артефактные поля
            unset($arFieldsCopy['PROPERTIES']);
            unset($arFieldsCopy['XML_ID']);
            unset($arFieldsCopy['EXTERNAL_ID']);
            foreach ($arFieldsCopy as $k => $v)
                if ($k[0] == '~')
                    unset($arFieldsCopy[$k]);

            // Проверяем, может быть и не надо ничего создавать?
            $arSearchFields = $arFieldsCopy;
            unset($arSearchFields['PROPERTY_VALUES']);
            foreach ($arFieldsCopy['PROPERTY_VALUES'] as $k => $v)
                $arSearchFields['PROPERTY_' . $k] = $v;

            $res = CIBlockElement::GetList(Array('SORT' => 'ASC'), $arSearchFields, false, false, array('ID'));
            $ob = $res->GetNext();
            if ($ob['ID']) return $ob['ID'];

            $el = new CIBlockElement();
            return $el->Add($arFieldsCopy);
        }
        return null;
    }


    /////////////////////////////////  Управление сущностями  //////////////////////////////////


    static function massUpdateSection($arSearch, $arFields)
    {
        if (!is_array($arSearch) || !is_array($arFields))
            throw new Exception("Передаваемые параметры должны быть массивами");
        if (!$arSearch['IBLOCK_ID'])
            throw new Exception("Не указан номер инфоблока секции");

        $res = CIBlockSection::GetList(Array('SORT' => 'ASC'), $arSearch);
        $arSection = $res->GetNext();
        if (!$arSection['ID'])
            throw new Exception("Не найдена секция");

        $arFields['IBLOCK_ID'] = $arSearch['IBLOCK_ID'];
        $objSection = new CIBlockSection;
        $objSection->Update($arSection['ID'], $arFields);

        return true;
    }


    /**
     * @param $array1
     * @param $array2
     * @return array
     * Рекурсивно сливает массивы (в отличие от array_merge) и не создаёт дублей
     * Взято из комментариев со страницы http://php.net/manual/en/function.array-merge-recursive.php
     *
     * Больше подходит для Битрикса, нежели стандартный array_merge_recursive.
     * Применяется при мердже массивов, описывающих элемент, в том виде, как это хотят CIBlockElement:Add() и подобных
     */
    static function _array_merge_recursive_distinct ( $array1, $array2 )
    {
        if (! is_array($array1) &&   is_array($array2)) return $array2;
        if (  is_array($array1) && ! is_array($array2)) return $array1;
        if (! is_array($array1) && ! is_array($array2)) return array();

        $merged = $array1;
        foreach ( $array2 as $key => &$value )
        {
            if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
            {
                $merged [$key] = self::_array_merge_recursive_distinct ( $merged [$key], $value );
            }
            else
            {
                $merged [$key] = $value;
            }
        }

        return $merged;
    }
}
