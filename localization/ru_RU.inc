<?php
/* Author: Sergey Khaliulov */

$labels = array();
$labels['sauserprefs']  = 'Спам Фильтр';
$labels['sauserprefssettings']  = 'Настройки спам фильтра';
$labels['spamgeneralsettings']  = 'Основные параметры';
$labels['spamthres']  = 'Порог оценки';
$labels['spamsubject']  = 'Метка темы сообщения';
$labels['spamsubjectblank']  = '(оставить пустым чтобы не менять тему сообщения)';
$labels['spamlang']  = 'Языки сообщения';
$labels['enabled']  = 'Включен';
$labels['disabled']  = 'Отключен';
$labels['headers'] = 'Заголовки сообщения';
$labels['foldheaders'] = 'Разрешить многострочные записи в заголовках';
$labels['spamlevelstars'] = 'Использовать заголовок \'Spam Level\'';
$labels['spamlevelchar'] = 'Символ уровня спама';
$labels['spamtests'] = 'Параметры проверки, основанные на Internet';
$labels['userazor1'] = 'Использовать Razor v1';
$labels['userazor2'] = 'Использовать Razor v2';
$labels['usepyzor'] = 'Использовать Pyzor';
$labels['usebayes'] = 'Использовать родной Bayesian стиль классификатор';
$labels['usedcc'] = 'Использовать DCC';
$labels['skiprblchecks'] = 'Использовать Realtime Blackhole List';
$labels['spamreportsettings']  = 'Параметры уведомления';
$labels['spamreport0']  = 'Добавить в отчет в заголовки оригинального сообщения';
$labels['spamreport1']  = 'Добавить в отчет оригинал письма как MIME вложение';
$labels['spamreport2']  = 'Добавить в отчет оригинал письма как текст';
$labels['spamaddressrules']  = 'Фильтры по адресу';
$labels['whitelist_from']  = 'Принимать письма от:';
$labels['blacklist_from']  = 'Отбрасывать письма от:';
$labels['whitelist_to']  = 'Принимать для отправки для:';
$labels['addrule']  = 'Добавить';
$labels['rule']  = 'Правило';
$labels['importfromaddressbook'] = 'Импортировать адреса из Адресной книги';
$labels['deleteall'] = 'Удалить все';
$labels['autoscore'] = 'Auto';
$labels['defaultscore'] = 'По умолчанию';
$labels['otherscore'] = 'Другой (%s)';
$labels['usedefault'] = 'Восстановить настройки по умолчанию';

$messages = array();
$messages['sauserprefchanged'] = 'Параметры фильтрации спама изменены';
$messages['sauserpreffailed'] = 'Ошибка: Невозможно изменить параметры';
$messages['spamthresexp'] = 'При превышении указанного порога - помечать как спам. Увеличение этого порога увеличит количество пропущенного спама, но уменьшит риск ложных срабатываний.';
$messages['spamlangexp'] = 'Любые полученные сообщения, которые написаны не на выбранном языке будут рассматриваться в качестве возможного спама.';
$messages['headersexp'] = 'SpamAssassin добавляет в обработанные сообщения в заголовки записи с информацией о результатах проверки. Укажите параметры для конфигурирования заголовков.';
$messages['spamtestssexp'] = 'Некоторые проверки для проверки спама используют интернет сервисы. Выберите те, которые Вы желаете использовать:';
$messages['spamreport']  = 'Если сообщение идентифицировано как спам, сообщение должно быть сформировано следующим образом:';
$messages['autowhitelist'] = 'Адреса электронной почты в Вашей Адресной книге автоматически добавлены к списку "Accept Mail From".';
$messages['whitelistexp'] = 'Звездочка (*) может использоваться как групповой символ для большей гибкости. Например: *@example.com или *@*.example.com.';
$messages['spamaddressexists'] = 'Правило уже существует для этого адреса';
$messages['spamenteraddress'] = 'Введите адрес для добавления правила';
$messages['spamaddresserror'] = 'Ошибка: Адрес указан неверно';
$messages['spamaddressdelete'] = 'Вы дествительно хотите удалить это правило?';
$messages['spamaddressdeleteall'] = 'Вы уверены что хотите удалить все правила фильтрации по адресу?';
$messages['noaddressrules'] = 'Правил фильтрации по адресу не найдено.';
$messages['importingaddresses'] = 'Импортирование адресов...';
$messages['usedefaultconfirm'] = 'Вы уверены, что Вы хотите восстановить настройки по умолчанию? Это удалит все Ваши настройки, включая правила проверки адреса.';

?>