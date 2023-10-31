<?php

namespace App\Models;

use CodeIgniter\Model;

class Telegram extends Model
{

    public function telegram($json)
    {
        try {
            $chatId = '';
            $sendMessage = true;
            $resMessage = [];
            if (isset($json->message->text)) {
                $chatId = $json->message->chat->id;
                $resMessage = $this->message($json, $chatId);
            } elseif (isset($json->callback_query)) {
                $chatId = $json->callback_query->message->chat->id;
                $resMessage = $this->callback($json, $chatId);
            } elseif (isset($json->my_chat_member)) {
                $userModel = new User();
                if ($userModel->findUserByChatId($json->my_chat_member->chat->id) != null) {
                    $userModel->removeTelegramDataByChatId($json->my_chat_member->chat->id, "");
                }
                $sendMessage = false;
            } else $sendMessage = false;
            return [
                "code" => 200,
                "json" => [
                    "telegramDispatcher" => [
                        "chat_id" => [strval($chatId)],
                        "sendMessage" => $sendMessage
                    ],
                    "telegram" => $resMessage
                ]
            ];
        } catch (Exception $e) {
            return [
                "code" => 500,
                "error" => $e->getMessage()
            ];
        }
    }

    public function telegramUpdates($json)
    {
        $jsonAction = $json->action;
        if ($jsonAction == "userNotify") {
            $search = new Search();
            $users = $search->findUsersCountersNotFilled();
            $chats_id = array();
            foreach ($users as $user) {
                array_push($chats_id, $user['telegramChatId']);
            }
            return [
                "code" => 200,
                "json" => [
                    "telegramDispatcher" => ["chat_id" => $chats_id, "sendMessage" => !empty($users)],
                    "telegram" => [$this->createTelegramMessage("❕❕❕Потрібно вказати показники❕❕❕")]
                ]
            ];
        } elseif ($jsonAction == "managerNotify") {
            $search = new Search();
            $units = $search->findCountNotFilledCountersOfUser();
            $message = array();
            array_push($message, $this->createTelegramMessage("<b>Список не вказаних лічильників та їх розташування</b>"));
            foreach ($units as $unit) {
                array_push(
                    $message,
                    $this->createTelegramMessage("<i>Підрозділ:</i> <b>" . $unit['unit'] . "</b>\n<i>Лічильники:</i> <u>" . str_replace(' ', '</u> <u>', $unit['counters']) . "</u>\n" .
                        "<i>Адрес:</i> <b>" . $unit['addr'] . "</b>\n<i>Телефон:</i> <b>" . $unit['tel'] . "</b>")
                );
            }
            return [
                "code" => 200,
                "json" => ["telegramDispatcher" => [
                    "chat_id" => $json->chatId,
                    "sendMessage" => !empty($units)
                ], "telegram" => $message]
            ];
        }
    }


    private function message($json, $chatId)
    {
        $userModel = new User();
        $search = new Search();
        $tgUser = $userModel->findUserByChatId($chatId);
        $textMess = $json->message->text;

        // Користувач відсутній у системі
        if ($tgUser == null) {

            // Початок розмови
            if ($textMess == '/start') return [$this->createTelegramMessage("Введіть власний <b>логін</b>")];

            // Валідація логина
            if (mb_strlen($textMess) > 15) return [$this->createTelegramMessage("Логін не повинен перевищувати <u>15</u> <b>символів</b>")];

            $user = $userModel->findUserByLogin($textMess);

            if ($user == null) return [$this->createTelegramMessage("Перевірте коректність <b>логіна</b>")];

            $userModel->insertTelegramDataByLogin($user->login, $chatId, 'pass');
            return [$this->createTelegramMessage(
                "Введіть пароль для логіна <b>" . $textMess . "</b>",
                $this->buttonBuilder([["Повернутися назад", "backLogin"]])
            )];
        }

        $tgUserState = $tgUser->telegramState;
        if ($tgUserState == "pass") {
            // Валідація пароля
            if (mb_strlen($textMess) > 20) return [$this->createTelegramMessage(
                "Пароль не повинен перевищувати <u>20</u> <b>символів</b>",
                $this->buttonBuilder([["Повернутися назад", "backLogin"]])
            )];

            if ($textMess == $tgUser->pass) {
                $userRights = $tgUser->rights;
                if ($userRights == 3) return $this->menuMess(
                    $chatId,
                    "<b>" . $tgUser->name . " ви успішно увішли</b>\n<i>Виберіть тип лічильника</i>"
                );
                elseif ($userRights == 1 || $userRights == 2 || $userRights == 5) {
                    $userModel->insertTelegramDataByChatId($chatId, "adminMenu");
                    return $this->menuAdminMess();
                } else return [$this->createTelegramMessage(
                    "<b>Увага!!!</b> <i>Цей сервіс лише для співробітників</i>",
                    $this->buttonBuilder([["Повернутися назад", "backLogin"]])
                )];
            }
            return [$this->createTelegramMessage(
                "<b>Увага!!!</b> <i>Ви ввели некоректний логін або пароль</i>",
                $this->buttonBuilder([["Повернутися назад", "backLogin"]])
            )];
        } elseif ($tgUserState == "addPokaz" && $tgUser->rights == 3) {

            // Валідація введеного показнника лічильники
            if (str_contains($textMess, ' ') || !is_numeric($textMess) || mb_strlen($textMess) > 9) {
                return [$this->createTelegramMessage(
                    "<b>Були введені некоректні дані лічильники</b>\nПеревірте вказані показники та спробуйте ще раз",
                    $this->buttonBuilder([["Повернутися до меню", "backMenu"]])
                )];
            }
            $last = $userModel->getCounterDate();
            $metadata = json_decode($search->getMetadataByChatId($chatId)->telegramMetadata);
            $metaCounterPK = $metadata->counterPK;

            if ($search->checkPokaz($metaCounterPK, $last['year'] . "-" . $last['month'] . "-01", $textMess)) {
                $userModel->insertTelegramDataByChatId(
                    $chatId,
                    "confirm",
                    [
                        "typeCounter" => $metadata->typeCounter, "counterPK" => $metaCounterPK,
                        "pokaz" => $textMess
                    ]
                );
                return [$this->createTelegramMessage(
                    "<b>Для збереження даних</b>\nПідтвердіть чи вказанні привильно данні",
                    $this->buttonBuilder([
                        ["Підтвержую", "confirm"],
                        ["Допущенна помилка", "denied"]
                    ])
                )];
            }
            return [$this->createTelegramMessage(
                "<b>Упс... Показник лічильника не додано</b>\nПеревірте вказані показники та спробуйте ще раз",
                $this->buttonBuilder([["Повернутися до меню", "backMenu"]])
            )];
        }


        // Помилка користувачу по конкретній ролі
        $replyMarkup = ($tgUser->rights == 3) ? $this->buttonBuilder([[
            "Повернутися до меню",
            "backMenu"
        ]]) : $this->buttonBuilder([[
            "Повернутися до списка",
            "adminMenu"
        ]]);
        return [$this->createTelegramMessage(
            "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
            $replyMarkup
        )];
    }

    private function callback($json, $chatId)
    {
        $userModel = new User();
        $search = new Search();
        $tgUser = $userModel->findUserByChatId($chatId);
        $callbackData = $json->callback_query->data;

        // Користувач відсутній у системі
        if ($tgUser == null) return [$this->createTelegramMessage("<b>Упс... Ви не маєте права на дану дію 🤔</b>\nСпробуйте авторизуватися")];


        $userTgState = $tgUser->telegramState;

        // Каристувач на етапі введення пароля
        if ($userTgState == 'pass') {
            // На етапі введення пароля
            if ($callbackData == "backLogin") {
                $userModel->removeTelegramDataByChatId($chatId, "");
                return [$this->createTelegramMessage("Введіть власний <b>логін</b>")];
            }
            return [$this->createTelegramMessage(
                "<b>Упс... Ви не маєте права на дану дію 🤔</b>\nВи повинні ввести свій пароль до користувача <b>" . $tgUser->login . "</b>",
                $this->buttonBuilder([["Повернутися назад на стадію введеня логіна", "backLogin"]])
            )];
        }

        // Дії адміна
        if ($tgUser->rights == 1 || $tgUser->rights == 2 || $tgUser->rights == 5) {
            if ($userTgState == 'adminMenu' && $callbackData == 'adminMenu') return $this->menuAdminMess();
            else return [$this->createTelegramMessage(
                "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
                $this->buttonBuilder([[
                    "Повернутися до списка",
                    "adminMenu"
                ]])
            )];
        }

        // Дії звичайного користувача
        if ($tgUser->rights == 3) {
            if ($callbackData == 'backMenu') return $this->menuMess($chatId, "<b>" . $tgUser->name . "</b> виберіть тип лічильника");
            elseif ($userTgState == 'menu' && is_numeric($callbackData)) {
                $counters = $search->findCountersNotFilled($tgUser->area, $callbackData, null);

                if (empty($counters)) return [$this->createTelegramMessage("Показники усім лічильникам були вказані\n<b>Ви молодці😎</b>")];

                $respMessage = [];
                $userModel->insertTelegramDataByChatId(
                    $chatId,
                    'chooseCounter',
                    ["typeCounter" => $callbackData]
                );
                array_push(
                    $respMessage,
                    $this->createTelegramMessage("<b>Список лічильників</b>")
                );
                foreach ($counters as $counter) {
                    array_push(
                        $respMessage,
                        $this->createTelegramMessage(
                            "<b>Щоб вибрати лічильниик №</b> <u>" . $counter['counterId'] . "</u>\nнатисніть на відповідну кнопку\n<b>Коментар:</b> <i>" . $counter['counterName'] . "</i>",
                            $this->buttonBuilder([["№ " . $counter['counterId'], $counter['id']]])
                        )
                    );
                }
                return $respMessage;
            } elseif ($userTgState == 'chooseCounter' && is_numeric($callbackData)) return $this->chooseCounter($tgUser, $chatId, $callbackData);
            elseif ($userTgState == 'confirm') {
                $last = $userModel->getCounterDate();
                $metadata = json_decode($search->getMetadataByChatId($chatId)->telegramMetadata);

                if ($callbackData == 'denied')
                    return $this->chooseCounter($tgUser, $chatId, null);
                elseif ($callbackData == 'confirm') {
                    $metaCounterPK = $metadata->counterPK;

                    // Перевірка перед збереженням даних
                    if (!$search->checkPokaz($metaCounterPK, $last['year'] . "-" . $last['month'] . "-01", $metadata->pokaz)) {
                        return [$this->createTelegramMessage("<b>Упс... Показник лічильника не додано</b>\nПеревірте вказані показники та спробуйте ще раз")];
                    }

                    $savePokaz = $search->addPokazC($metaCounterPK, $metadata->pokaz, $last['year'], $last['month'], true);

                    // Перевірка після збереженням
                    if (!$savePokaz) {
                        return $this->menuMess($chatId, "Показник лічильникa за <b>" . $last['year'] . "-" . $last['month'] . "-01</b> був <b>доданий раніше</b>\n<b>" . $tgUser->name . "</b> Виберіть тип лічильника");
                    }

                    $search->recalculation($metaCounterPK);
                    $userModel->addUserLog($tgUser->login,
                        ['login' => $tgUser->login, 'message' => "Додав показник = " . $metadata->pokaz . " лічильнику = " . $search->getCounterByCounterPK($metaCounterPK)->counterId . " (telegram)"]
                    );
                    return $this->menuMess(
                        $chatId,
                        "Показник лічильникa " . $metadata->pokaz . " за " . $last['month'] . "." . $last['year'] . " був <b>успішно додані 👍 </b>\n<i>Виберіть тип лічильника</i>"
                    );
                }
            } else return [$this->createTelegramMessage(
                "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
                $this->buttonBuilder([[
                    "Повернутися до меню",
                    "backMenu"
                ]])
            )];
        }
    }

    private function buttonBuilder($buttons = [], $layout = [], $modeCompose = false, $type = "inline_keyboard")
    {
        $currBnt = array();
        foreach ($buttons as $button) {
            array_push($currBnt, array(
                "text" => $button[0],
                "callback_data" => $button[1]
            ));
        }
        array_push($layout, $currBnt);
        if (!$modeCompose) return array("reply_markup" => array($type => array_reverse($layout)));
        else return $layout;
    }

    private function createTelegramMessage($title, $replyMarkup = null)
    {
        $tgMess = ["text" => $title, "parse_mode" => "HTML"];
        if ($replyMarkup) $tgMess = array_merge($tgMess, $replyMarkup);
        return $tgMess;
    }

    private function chooseCounter($tgUser, $chatId, $counterPK = null)
    {
        $search = new Search();
        $metadata = json_decode($tgUser->telegramMetadata);
        if ($counterPK != null) {
            if (!$search->findCountersNotFilled($tgUser->area, $metadata->typeCounter, $counterPK)) {
                return $this->menuMess(
                    $chatId,
                    "<b>Упс... Виникли проблеми 🤔</b>\n<i>" . $tgUser->name . "</i> виберіть тип лічильника"
                );
            }
            return $this->addPokazMess($chatId, $metadata, $counterPK);
        }
        return $this->addPokazMess($chatId, $metadata, $metadata->counterPK);
    }

    private function addPokazMess($chatId, $metadata, $counterPK)
    {
        $userModel = new User();
        $search = new Search();
        $userModel->insertTelegramDataByChatId(
            $chatId,
            'addPokaz',
            ["typeCounter" => $metadata->typeCounter, "counterPK" => $counterPK]
        );
        $pokaz = $search->getLastPokazByCounter($counterPK);
        $counter = $search->getCounterByCounterPK($counterPK);
        $lastPokaz = ($pokaz) ? $pokaz->index : $counter->spokaz;
        return [$this->createTelegramMessage(
            "<b>Введіть показники лічильнику №</b> <u>" . $counter->counterId . "</u>\n<b>Крайній показник лічильника становить</b> <code>" . $lastPokaz . "</code>",
            $this->buttonBuilder([["Повернутися до меню", "backMenu"]])
        )];
    }

    private function menuMess($chatId, $title)
    {
        $userModel = new User();
        $userModel->insertTelegramDataByChatId($chatId, "menu");
        return [$this->createTelegramMessage(
            $title,
            $this->buttonBuilder([["Електрика", "8"], ["Газ", "7"], ["Гаряча вода", "6"], ["Холодна вода", "5"]])
        )];
    }

    private function menuAdminMess()
    {
        $search = new Search();
        $counters = $search->findCountersNotFilled();

        if (empty($counters)) {
            return [$this->createTelegramMessage(
                "Показники усім лічильникам були вказані\n<b>Чудово😎</b>",
                $this->buttonBuilder([["Оновити", "adminMenu"]])
            )];
        }

        $respMessage = [];
        array_push(
            $respMessage,
            $this->createTelegramMessage("<b>Список не вказаних показників лічильників</b>")
        );

        // array_push(
        //     $respMessage,
        //     $this->createTelegramMessage("Лічильник № <u>" . $counters[0]['counterId'] . "</u>\nРозташування: <b>" . $counters[0]['unit'] . "</b>\nАдреса: <b>" . $counters[0]['addr'] . "</b>")
        // );
        foreach ($counters as $counter) {
            array_push(
                $respMessage,
                $this->createTelegramMessage("Лічильник № <u>" . $counter['counterId'] . "</u>\nРозташування: <b>" . $counter['unit'] . "</b>\nАдреса: <b>" . $counter['addr'] . "</b>")
            );
        }
        array_push(
            $respMessage,
            $this->createTelegramMessage(
                "<b>Оновити список не вказних показників лічильників</b>\n<b>Всього налічується</b> <code>" . count($counters) . "</code> <b>лічильників</b>",
                $this->buttonBuilder([["Оновити", "adminMenu"]])
            )
        );

        return $respMessage;
    }
}
