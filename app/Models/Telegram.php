<?php

namespace App\Models;

use CodeIgniter\Model;

class Telegram extends Model
{
    protected $DBGroup = 'meters';

    public $confirmation = [
        ["Підтверджую", "confirm"],
        ["Допущена помилка", "denied"]
    ];

    public $backToMenu = [
        ["Повернутися до меню", "backMenu"]
    ];

    public $backToAdminMenu = [
        ["Повернутися до списку", "adminMenu"]
    ];

    public $backLogin =  [
        ["Повернутися назад на стадію введення логіна", "backLogin"]
    ];

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
                    $this->createTelegramMessage("<i>Підрозділ:</i> <b>" . $unit['unit'] . "</b>\n" .
                        "<i>Лічильники:</i> <u>" . str_replace(' ', '</u> <u>', $unit['counters']) . "</u>\n" .
                        "<i>Адрес:</i> <b>" . $unit['addr'] . "</b>\n" .
                        "<i>Телефон:</i> <b>" . $unit['tel'] . "</b>")
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

    public function sendMessage($chatIds, $botName, $message)
    {
        $auth = new Auth();

        $req_sendMess = curl_init();

        curl_setopt($req_sendMess, CURLOPT_URL, "http://10.10.1.14:8082/sendMessageTelegram/");
        curl_setopt($req_sendMess, CURLOPT_POST, 1);
        curl_setopt(
            $req_sendMess,
            CURLOPT_HTTPHEADER,
            [
                "Content-Type: application/json; charset=utf-8",
                "Authorization: Bearer " . $auth->getAccesToken()
            ]
        );
        curl_setopt($req_sendMess, CURLOPT_POSTFIELDS, json_encode(array(
            "telegramDispatcher" => [
                "chat_id" => $chatIds,
                "sendMessage" => true,
                "botName" => $botName
            ],
            "telegram" => [
                [
                    "text" => $message,
                    "parse_mode" => "HTML",

                    "reply_markup" => [
                        "inline_keyboard" =>  [
                            [
                                [
                                    "text" => "Повернутися до списка",
                                    "callback_data" => "backMenu"
                                ]
                            ]
                        ]
                    ]
                ]
            ]

        )));
        curl_setopt($req_sendMess, CURLOPT_RETURNTRANSFER, true);

        $response_sendMess = curl_exec($req_sendMess);

        if (curl_errno($req_sendMess)) {
            echo 'Curl error: ' . curl_error($req_sendMess);
        }

        curl_close($req_sendMess);
    }

    private function message($json, $chatId)
    {
        $userModel = new User();
        $search = new Search();
        $generator = new Generator();
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
                $this->buttonBuilder([$this->backLogin])
            )];
        }

        // Кінець розмови
        if ($textMess == '/logout') {
            if ($userModel->findUserByChatId($chatId) != null) {
                $userModel->removeTelegramDataByChatId($chatId, "");
            }
            return [$this->createTelegramMessage("Ви успішно вийшли із облікового запису.\n" .
                "Введіть новий <b>логін</b>")];
        }

        $tgUserState = $tgUser->telegramState;
        if ($tgUserState == "pass") {
            // Валідація пароля
            if (mb_strlen($textMess) > 20) return [$this->createTelegramMessage(
                "Пароль не повинен перевищувати <u>20</u> <b>символів</b>",
                $this->buttonBuilder([$this->backLogin])
            )];

            if ($textMess == $tgUser->pass) {
                $userRights = $tgUser->rights;
                if ($userRights == 3) {
                    $userModel->addUserLog($tgUser->login, ['login' => $tgUser->login, 'message' => "Увійшов в систему (telegram)"]);
                    return $this->menuMess($chatId, $tgUser->area, "<b>" . $tgUser->name . " ви успішно увійшли</b>\n" .
                        "<i>Виберіть тип лічильника</i>", true);
                } elseif ($userRights == 1 || $userRights == 2 || $userRights == 5) {
                    $userModel->insertTelegramDataByChatId($chatId, "adminMenu");
                    return $this->menuAdminMess(true);
                } else return [$this->createTelegramMessage(
                    "<b>Увага!!!</b> <i>Цей сервіс лише для співробітників</i>",
                    $this->buttonBuilder([$this->backLogin])
                )];
            }
            return [$this->createTelegramMessage(
                "<b>Увага!!!</b> <i>Ви ввели некоректний логін або пароль</i>",
                $this->buttonBuilder([$this->backLogin])
            )];
        } elseif ($tgUserState == "addPokaz" && $tgUser->rights == 3) {

            // Валідація введеного показнника лічильники
            if (str_contains($textMess, ' ') || !is_numeric($textMess) || mb_strlen($textMess) > 9) {
                return [$this->createTelegramMessage(
                    "<b>Були введені некоректні дані лічильники</b>\n" .
                        "Перевірте вказані дані та спробуйте ще раз",
                    $this->buttonBuilder([$this->backToMenu])
                )];
            }
            $last = $userModel->getCounterDate();
            $metadata = json_decode($search->getUserBySpecificData("", "", $chatId)[0]['telegramMetadata']);
            $metaCounterPK = $metadata->counterPK;

            if ($search->checkPokaz($metaCounterPK, $last['year'] . "-" . $last['month'] . "-01", $textMess)) {
                $userModel->insertTelegramDataByChatId(
                    $chatId,
                    "confirm",
                    ["typeCounter" => $metadata->typeCounter, "counterPK" => $metaCounterPK, "pokaz" => $textMess]
                );
                return [$this->createTelegramMessage("<b>Для збереження даних</b>\n" .
                    "Підтвердіть, що дані вказані правильно", $this->buttonBuilder([$this->confirmation]))];
            }
            return [$this->createTelegramMessage(
                "<b>Упс... Показник лічильника не додано</b>\n" .
                    "Перевірте вказані дані та спробуйте ще раз",
                $this->buttonBuilder([$this->backToMenu])
            )];
        } elseif ($tgUserState == "addPokazGen" && $tgUser->rights == 3) {
            try {
                if (preg_match('/[а-яА-Яa-zA-Z]/u', $textMess)) {
                    return [$this->createTelegramMessage(
                        "<b>Були введені некоректні дані генератора</b>\n" .
                            "Перевірте вказані дані та спробуйте ще раз",
                        $this->buttonBuilder([$this->backToMenu])
                    )];
                }
                $parts = explode('_', $textMess);

                list($startDate1, $startTime1) = sscanf($parts[0], "%[^\(](%[^\)])");
                $startTime = strtotime($startDate1 . ' ' . $startTime1);

                list($endDate2, $endTime2) = sscanf($parts[1], "%[^\(](%[^\)])");
                $endTime = strtotime($endDate2 . ' ' . $endTime2);

                if ($startTime >= $endTime) {
                    return [$this->createTelegramMessage(
                        "<b>Були введені некоректні дані генератора</b>\n" .
                            "Перевірте вказані дані та спробуйте ще раз",
                        $this->buttonBuilder([$this->backToMenu])
                    )];
                }

                $metadata = json_decode($search->getUserBySpecificData("", "", $chatId)[0]['telegramMetadata']);
                $metaGeneratorPK = $metadata->generatorPK;

                $genData = $generator->getSpecificGenerator($tgUser->area, '', $metaGeneratorPK, 1);
                $workingTime = number_format(($endTime - $startTime) / (60 * 60), 1, '.', '');

                if (!$genData || $genData[0]["fuel"] - floatval($workingTime * $genData[0]["coeff"]) < 0) {
                    return [$this->createTelegramMessage(
                        "<b>Були введені некоректні дані генератора</b>\n" .
                            "Перевірте вказані дані та спробуйте ще раз",
                        $this->buttonBuilder([$this->backToMenu])
                    )];
                }

                $userModel->insertTelegramDataByChatId(
                    $chatId,
                    "confirmGen",
                    ["generatorPK" => $metaGeneratorPK, "pokaz" => $textMess]
                );
                return [$this->createTelegramMessage(
                    "<b>Для збереження даних</b>\n" .
                        "Підтвердіть, що дані вказані правильно\n" .
                        "<b>Генератор працював:</b> " . $workingTime . " годин?",
                    $this->buttonBuilder([$this->confirmation])
                )];
            } catch (Exception $e) {
                return [$this->createTelegramMessage(
                    "<b>Упс... Показник генератора не додано</b>\n" .
                        "Перевірте вказані дані та спробуйте ще раз",
                    $this->buttonBuilder([$this->backToMenu])
                )];
            }
        } elseif ($tgUserState == "countCanisterOUT" && $tgUser->rights == 3) {
            try {
                $countCanister = $generator->getFuelArea($tgUser->area)[0]['sum'];
                // Валідація введенної кількості каністр
                if (str_contains($textMess, ' ') || !is_numeric($textMess) || mb_strlen($textMess) > 9 || $countCanister  == 0 || $textMess < 1 || $textMess > $countCanister) {
                    return [$this->createTelegramMessage(
                        "<b>Були введені некоректна кількість каністр</b>\n" .
                            "Перевірте кількість вказаних каністр та спробуйте ще раз",
                        $this->buttonBuilder([$this->backToMenu])
                    )];
                }

                $userModel->insertTelegramDataByChatId(
                    $chatId,
                    "confirmCountCanisterOUT",
                    ["count" => $textMess]
                );


                return [$this->createTelegramMessage(
                    "<b>Для збереження даних</b>\n" .
                        "Підтвердіть, що дані вказані правильно\n" .
                        "<b>Повертаєте каністр:</b> " . $textMess . " ?",
                    $this->buttonBuilder([$this->confirmation])
                )];
            } catch (Exception $e) {
                return [$this->createTelegramMessage(
                    "<b>Упс... Каністри не відправлені для повернення</b>\n" .
                        "Перевірте кількість каністр та спробуйте ще раз",
                    $this->buttonBuilder([$this->backToMenu])
                )];
            }
        } elseif ($tgUserState == "countFuelRefill" && $tgUser->rights == 3) {
            try {
                if (str_contains($textMess, ' ') || !is_numeric($textMess) || mb_strlen($textMess) > 9) {
                    return [$this->createTelegramMessage(
                        "<b>Були введені некоректна кількість палива</b>\n" .
                            "Перевірте кількість вказаного палива та спробуйте ще раз",
                        $this->buttonBuilder([$this->backToMenu])
                    )];
                }

                $metadata = json_decode($search->getUserBySpecificData("", "", $chatId)[0]['telegramMetadata']);
                $metaTypeGen = $metadata->typeGen;

                $userModel->insertTelegramDataByChatId(
                    $chatId,
                    "confirmCountFuelRefill",
                    [
                        "fuel" => $textMess,
                        "typeGen" => $metaTypeGen
                    ]
                );


                return [$this->createTelegramMessage(
                    "<b>Для збереження даних</b>\n" .
                        "Підтвердіть, що дані вказані правильно\n" .
                        "<b>Заправляєте палива на:</b> " . $textMess . " ?",
                    $this->buttonBuilder([$this->confirmation])
                )];
            } catch (Exception $e) {
                return [$this->createTelegramMessage(
                    "<b>Упс... Не вдалось заправити генератор</b>\n" .
                        "Перевірте кількість палива та спробуйте ще раз",
                    $this->buttonBuilder([$this->backToMenu])
                )];
            }
        }

        // Помилка користувачу по конкретній ролі
        return [$this->createTelegramMessage(
            "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
            ($tgUser->rights == 3) ? $this->buttonBuilder([$this->backToMenu]) : $this->buttonBuilder([$this->backToAdminMenu])
        )];
    }

    private function callback($json, $chatId)
    {
        $userModel = new User();
        $search = new Search();
        $generator = new Generator();
        $tgUser = $userModel->findUserByChatId($chatId);
        $callbackData = $json->callback_query->data;

        // Користувач відсутній у системі
        if ($tgUser == null) return [$this->createTelegramMessage("<b>Упс... Ви не маєте права на дану дію 🤔</b>\n" .
            "Спробуйте авторизуватися повторно\n" .
            "Введіть власний <b>логін</b>")];

        $userTgState = $tgUser->telegramState;

        // Каристувач на етапі введення пароля
        if ($userTgState == 'pass') {
            // На етапі введення пароля
            if ($callbackData == "backLogin") {
                $userModel->removeTelegramDataByChatId($chatId, "");
                return [$this->createTelegramMessage("Введіть власний <b>логін</b>")];
            }
            return [$this->createTelegramMessage(
                "<b>Упс... Ви не маєте права на дану дію 🤔</b>\n" .
                    "Ви повинні ввести свій пароль до користувача <b>" . $tgUser->login . "</b>",
                $this->buttonBuilder([$this->backLogin])
            )];
        }

        // Дії адміна
        if ($tgUser->rights == 1 || $tgUser->rights == 2 || $tgUser->rights == 5) {
            if ($userTgState == 'adminMenu' && $callbackData == 'adminMenu') return $this->menuAdminMess();
            else return [$this->createTelegramMessage(
                "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
                $this->buttonBuilder([$this->backToAdminMenu])
            )];
        }

        // Дії звичайного користувача
        if ($tgUser->rights == 3) {
            if ($callbackData == 'backMenu') {
                return $this->menuMess($chatId, $tgUser->area, "<b>" . $tgUser->name . "</b> виберіть тип лічильника");
            } elseif ($userTgState == "menu") {
                $respMessage = [];
                if ($callbackData == 'generators') {
                    $activeGenerators = $generator->getSpecificGenerator($tgUser->area, "", "", 1);
                    if (count($activeGenerators) == 0) {
                        return [$this->createTelegramMessage("Відсутні генератори в данному підрозділі 😎")];
                    }
                    $userModel->insertTelegramDataByChatId($chatId, 'chooseGenerator');
                    array_push($respMessage, $this->createTelegramMessage("<b>Список генераторів</b>"));
                    foreach ($activeGenerators as $generator) {
                        array_push(
                            $respMessage,
                            $this->createTelegramMessage(
                                "<b>Щоб вибрати генератор №</b> <u>" . $generator['serialNum'] . "</u>\n" .
                                    "натисніть на відповідну кнопку\n" .
                                    "<b>Назва:</b> <i>" . $generator['name'] . "</i>\n" .
                                    "<b>Тип:</b> <i>" . $generator['type'] . "</i>",
                                $this->buttonBuilder([[[
                                    "№ " . $generator['serialNum'],
                                    $generator['id']
                                ]]])
                            )
                        );
                    }
                } elseif ($callbackData == 'canisters_in') {
                    $specificCanister = $generator->getSpecificCanister($tgUser->area, '', 1, '');

                    if (count($specificCanister) == 0) {
                        return [$this->createTelegramMessage("Відсутні каністри для отримання 😎")];
                    }

                    $userModel->insertTelegramDataByChatId($chatId, 'chooseCanisterIN');
                    array_push($respMessage, $this->createTelegramMessage("<b>Список каністр</b>"));

                    foreach ($specificCanister as $canister) {
                        array_push(
                            $respMessage,
                            $this->createTelegramMessage(
                                "<b>Щоб отримати каністри</b>\n" .
                                    "натисніть кнопку 'Отримати'\n" .
                                    "<b>Палива:</b> <i>" . $canister['fuel'] . "</i>\n" .
                                    "<b>Кількість:</b> <i>" . $canister['canister'] . "</i>\n" .
                                    "<b>Тип:</b> <i>" . $canister['type'] . "</i>",
                                $this->buttonBuilder([[[
                                    "Отримати",
                                    $canister['id']
                                ]]])
                            )
                        );
                    }
                } elseif ($callbackData == 'canisters_out') {
                    $countCanister = $generator->getFuelArea($tgUser->area)[0]['sum'];
                    if ($countCanister == 0) {
                        return [$this->createTelegramMessage("Відсутні каністри для повернення 😎")];
                    }
                    $userModel->insertTelegramDataByChatId($chatId, 'countCanisterOUT');

                    array_push(
                        $respMessage,
                        $this->createTelegramMessage("<b>Введіть кількість каністр для повернення</b>\n" .
                            "Всього на аптеці: " . $countCanister),
                        $this->buttonBuilder([$this->backToMenu])
                    );
                } elseif ($callbackData == 'refill') {
                    if ($generator->getAreaById($tgUser->area)[0]['refill'] != 1) {
                        return [$this->createTelegramMessage(
                            "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
                            $this->buttonBuilder([$this->backToMenu])
                        )];
                    }

                    $userModel->insertTelegramDataByChatId($chatId, 'chooseTypeGenRefill');
                    $typesMenu = [];
                    foreach ($generator->getTypeGenerator() as $type) {
                        array_unshift($typesMenu, [[
                            $type['type'],
                            $type['id']
                        ]]);
                    }
                    array_push(
                        $respMessage,
                        $this->createTelegramMessage(
                            "Виберіть тип генератора",
                            $this->buttonBuilder($typesMenu)
                        )
                    );
                } elseif (is_numeric($callbackData)) {
                    $counters = $search->findCountersNotFilled($tgUser->area, $callbackData, null);

                    if (empty($counters)) return [$this->createTelegramMessage("Показники усім лічильникам були вказані\n" .
                        "<b>Ви молодці😎</b>")];

                    $userModel->insertTelegramDataByChatId(
                        $chatId,
                        'chooseCounter',
                        ["typeCounter" => $callbackData]
                    );
                    array_push($respMessage, $this->createTelegramMessage("<b>Список лічильників</b>"));
                    foreach ($counters as $counter) {
                        array_push(
                            $respMessage,
                            $this->createTelegramMessage(
                                "<b>Щоб вибрати лічильниик №</b> <u>" . $counter['counterId'] . "</u>\n" .
                                    "натисніть на відповідну кнопку\n" .
                                    "<b>Коментар:</b> <i>" . $counter['counterName'] . "</i>",
                                $this->buttonBuilder([
                                    [
                                        [
                                            "№ " . $counter['counterId'],
                                            $counter['id']
                                        ]
                                    ]
                                ])
                            )
                        );
                    }
                }
                return $respMessage;
            } elseif ($userTgState == 'chooseCounter' && is_numeric($callbackData)) {
                return $this->chooseCounter($tgUser, $chatId, $callbackData);
            } elseif ($userTgState == 'chooseGenerator' && is_numeric($callbackData)) {
                return $this->chooseGenerator($tgUser, $chatId, $callbackData);
            } elseif ($userTgState == 'chooseCanisterIN' && is_numeric($callbackData)) {
                return $this->chooseCanisterIN($tgUser, $chatId, $callbackData);
            } elseif ($userTgState == 'chooseCanisterOUT' && is_numeric($callbackData)) {
                return $this->chooseCanisterOUT($tgUser, $chatId, $callbackData);
            } elseif ($userTgState == 'chooseTypeGenRefill' && is_numeric($callbackData)) {
                return $this->chooseTypeGenRefill($tgUser, $chatId, $callbackData);
            } elseif ($userTgState == 'confirm') {
                return $this->checkConfirmActions(
                    ['type' => 'confirm', 'callbackData' => $callbackData],
                    ['tgUser' => $tgUser, 'chatId' => $chatId]
                );
            } elseif ($userTgState == 'confirmGen') {
                return $this->checkConfirmActions(
                    ['type' => 'confirmGen', 'callbackData' => $callbackData],
                    ['tgUser' => $tgUser, 'chatId' => $chatId]
                );
            } elseif ($userTgState == 'confirmCountCanisterIN') {
                return $this->checkConfirmActions(
                    ['type' => 'confirmCountCanisterIN', 'callbackData' => $callbackData],
                    ['tgUser' => $tgUser, 'chatId' => $chatId]
                );
            } elseif ($userTgState == 'confirmCountCanisterOUT') {
                return $this->checkConfirmActions(
                    ['type' => 'confirmCountCanisterOUT', 'callbackData' => $callbackData],
                    ['tgUser' => $tgUser, 'chatId' => $chatId]
                );
            } elseif ($userTgState == 'confirmCountFuelRefill') {
                return $this->checkConfirmActions(
                    ['type' => 'confirmCountFuelRefill', 'callbackData' => $callbackData],
                    ['tgUser' => $tgUser, 'chatId' => $chatId]
                );
            } else return [$this->createTelegramMessage(
                "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
                $this->buttonBuilder([$this->backToMenu])
            )];
        }
    }

    private function buttonBuilder($rowButtons = [], $layout = [], $modeCompose = false, $type = "inline_keyboard")
    {
        foreach ($rowButtons as $row) {
            $currBnt = array();
            foreach ($row as $button) {
                array_push($currBnt, array("text" => $button[0], "callback_data" => $button[1]));
            }
            array_push($layout, $currBnt);
        }

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
                return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>");
            }
            return $this->addPokazMess($chatId, $metadata, $counterPK);
        }
        return $this->addPokazMess($chatId, $metadata, $metadata->counterPK);
    }

    private function chooseGenerator($tgUser, $chatId, $generatorPK = null)
    {
        $generator = new Generator();
        $metadata = json_decode($tgUser->telegramMetadata);
        if ($generatorPK != null) {
            if (!$generator->getSpecificGenerator($tgUser->area, '', $generatorPK, 1)) {
                return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>");
            }
            return $this->addPokazGenMess($chatId, $tgUser, $generatorPK);
        }
        return $this->addPokazGenMess($chatId, $tgUser, $metadata->generatorPK);
    }

    private function chooseCanisterIN($tgUser, $chatId, $canisterPK = null)
    {
        $generator = new Generator();
        $metadata = json_decode($tgUser->telegramMetadata);
        if ($canisterPK != null) {
            if ($generator->getSpecificCanister($tgUser->area, '', 1, '') == 0) {
                return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>");
            }
            return $this->addCountCanisterMessIN($chatId, $tgUser, $canisterPK);
        }
        return $this->addCountCanisterMessIN($chatId, $tgUser, $metadata->canisterPK);
    }

    private function chooseCanisterOUT($tgUser, $chatId, $canisterPK = null)
    {
        $generator = new Generator();
        $metadata = json_decode($tgUser->telegramMetadata);
        if ($canisterPK != null) {
            if ($generator->getSpecificCanister($tgUser->area, '', 2, '') == 0) {
                return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>");
            }
            return $this->addCountCanisterMessOUT($chatId, $tgUser, $canisterPK);
        }
        return $this->addCountCanisterMessOUT($chatId, $tgUser, $metadata->canisterPK);
    }

    private function chooseTypeGenRefill($tgUser, $chatId, $typeGen = null)
    {
        $generator = new Generator();
        $metadata = json_decode($tgUser->telegramMetadata);
        if ($typeGen != null) {
            if (count($generator->getTypeGenerator($typeGen)) == 0) {
                return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>");
            }
            return $this->addRefillFuel($chatId, $tgUser, $typeGen);
        }
        return $this->addRefillFuel($chatId, $tgUser, $metadata->typeGen);
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
            "<b>Введіть показники лічильнику №</b> <u>" . $counter->counterId . "</u>\n" .
                "<b>Крайній показник лічильника становить</b> <code>" . $lastPokaz . "</code>",
            $this->buttonBuilder([$this->backToMenu])
        )];
    }

    private function addPokazGenMess($chatId, $tgUser, $generatorPK)
    {
        $userModel = new User();
        $generator = new Generator();
        $userModel->insertTelegramDataByChatId(
            $chatId,
            'addPokazGen',
            ["generatorPK" => $generatorPK]
        );
        $dataGen = $generator->getSpecificGenerator($tgUser->area, '', $generatorPK, 1);
        date_default_timezone_set('Europe/Kiev');
        $currentDateTime = date('d.m.Y(H:i)');
        $twoHoursAgo = date('d.m.Y(H:i)', strtotime('-2 hours'));
        return [$this->createTelegramMessage(
            "<b>Введіть дані роботи (початок-кінець) генератору №</b> <u>" . $dataGen[0]["serialNum"] . "</u>\n" .
                "<b>Назва:</b> <i>" . $dataGen[0]["name"] . "</i>\n" .
                "<b>Тип:</b> <i>" . $dataGen[0]["type"] . "</i>\n" .
                "<b>Палива:</b> <i>" . $dataGen[0]["fuel"] . " л.</i>\n" .
                "<b>Каністр:</b> <i>" . $dataGen[0]["canister"] . " од.</i>\n" .
                "<b>Прогнозований час роботи генератора: ≈</b> <i>" . number_format($dataGen[0]["fuel"] / $dataGen[0]["coeff"], 1, '.', '') . " годин</i>\n" .
                "<b>ПРИКЛАД:</b> <code>" . $twoHoursAgo . '_' . $currentDateTime . "</code>",
            $this->buttonBuilder([$this->backToMenu])
        )];
    }

    private function addCountCanisterMessIN($chatId, $tgUser, $canisterPK)
    {
        $userModel = new User();
        $generator = new Generator();
        $canister = $generator->getSpecificCanister($tgUser->area, '', 1, $canisterPK);
        $userModel->insertTelegramDataByChatId(
            $chatId,
            'confirmCountCanisterIN',
            ["canisterPK" => $canisterPK]
        );

        return [$this->createTelegramMessage(
            "<b>Отримані каністри</b>\n" .
                "Підтвердіть, що дані вказані правильно\n" .
                "<b>Дата відправки:</b> <i>" . $canister[0]['date'] . "</i>\n" .
                "<b>Палива:</b> <i>" . $canister[0]['fuel'] . "</i>\n" .
                "<b>Кількість:</b> <i>" . $canister[0]['canister'] . "</i>\n" .
                "<b>Тип:</b> <i>" . $canister[0]['type'] . "</i>",
            $this->buttonBuilder([$this->confirmation])
        )];
    }
    private function addCountCanisterMessOUT($chatId, $tgUser, $canisterPK)
    {
        $userModel = new User();
        $generator = new Generator();
        $canister = $generator->getSpecificCanister($tgUser->area, '', 2, $canisterPK);
        $userModel->insertTelegramDataByChatId(
            $chatId,
            'countCanisterOUT',
            ["canisterPK" => $canisterPK]
        );

        return [$this->createTelegramMessage(
            "<b>Введіть кількість каністр для повернення №</b> <u>" . $canister[0]["id"] . "</u>\n" .
                "<b>Загальна кількість:</b> <i>" . $canister[0]['canister'] . "</i>",
            $this->buttonBuilder([$this->backToMenu])
        )];
    }

    private function addRefillFuel($chatId, $tgUser, $typeGen)
    {
        $userModel = new User();
        $userModel->insertTelegramDataByChatId(
            $chatId,
            'countFuelRefill',
            ["typeGen" => $typeGen]
        );

        return [$this->createTelegramMessage("<b>Введіть кількість палива для заправлення</b>")];
    }

    private function menuMess($chatId, $areaId, $title, $justLoggined = false)
    {
        $generator = new Generator();
        $search = new Search();
        $activeGenerators = $generator->getSpecificGenerator($areaId, "", "", 1);
        $userModel = new User();
        $userModel->insertTelegramDataByChatId($chatId, "menu");

        $respMessage = [];
        if ($justLoggined) array_push($respMessage, $this->createTelegramMessage("Щоб вийти із облікового запису напишіть <code>/logout</code>"));

        $genMenu = [];
        if (count($activeGenerators) != 0) {
            array_push($genMenu, [
                "Генератори",
                "generators"
            ]);
        }
        if ($generator->getAreaById($areaId)[0]['refill'] == 1 && count($activeGenerators) != 0) {
            array_push($genMenu, [
                "Заправити",
                "refill"
            ]);
        }

        $refillMenu = [];
        if (count($generator->getSpecificCanister($areaId, '', 1, '')) > 0) {
            array_push($refillMenu, [
                "Отр. каністр",
                "canisters_in"
            ]);
        }
        if ($generator->getFuelArea($areaId)[0]['sum'] != 0) {
            array_push($refillMenu, [
                "Пов. каністр",
                "canisters_out"
            ]);
        }
        $counters = $search->getCounterByAreaId($areaId);
        $counterMenu = [];
        foreach ($counters as $counterType) {
            switch ($counterType['typeC']) {
                case 5: {
                        array_push($counterMenu, [
                            "Холодна вода",
                            5
                        ]);
                        break;
                    }
                case 6: {
                        array_push($counterMenu, [
                            "Гаряча вода",
                            6
                        ]);
                        break;
                    }
                case 7: {
                        array_push($counterMenu, [
                            "Газ",
                            7
                        ]);
                        break;
                    }
                case 8: {
                        array_push($counterMenu, [
                            "Електрика",
                            8
                        ]);
                        break;
                    }
            }
        }

        array_push(
            $respMessage,
            $this->createTelegramMessage(
                $title,
                $this->buttonBuilder([$refillMenu, $genMenu, $counterMenu])
            )
        );
        return $respMessage;
    }

    private function menuAdminMess($justLoggined = false)
    {
        $search = new Search();
        $counters = $search->findCountersNotFilled();
        $respMessage = [];

        if ($justLoggined) array_push($respMessage, $this->createTelegramMessage("Щоб вийти із облікового запису напишіть <code>/logout</code>"));
        if (empty($counters)) {
            array_push(
                $respMessage,
                $this->createTelegramMessage(
                    "Показники усім лічильникам були вказані\n" .
                        "<b>Чудово😎</b>",
                    $this->buttonBuilder([$this->backToAdminMenu])
                )
            );
            return $respMessage;
        }

        array_push($respMessage, $this->createTelegramMessage("<b>Список не вказаних показників лічильників</b>"));

        foreach ($counters as $counter) {
            array_push($respMessage, $this->createTelegramMessage("Лічильник №<u>" . $counter['counterId'] . "</u>\n" .
                "Розташування: <b>" . $counter['unit'] . "</b>\n" .
                "Адреса: <b>" . $counter['addr'] . "</b>"));
        }
        array_push(
            $respMessage,
            $this->createTelegramMessage(
                "<b>Оновити список не вказаних показників лічильників</b>\n" .
                    "На даний момент в системі налічується <code>" . count($counters) . "</code> <b>лічильників</b>",
                $this->buttonBuilder([$this->backToAdminMenu])
            )
        );

        return $respMessage;
    }

    private function checkConfirmActions($specialData, $userData)
    {
        $generator = new Generator();
        $search = new Search();
        $userModel = new User();
        $metadata = json_decode($search->getUserBySpecificData("", "", $userData['chatId'])[0]['telegramMetadata']);

        switch ($specialData['type']) {
            case 'confirmCountFuelRefill': {
                    if ($specialData['callbackData'] == 'denied') {
                        return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "<b>Упс... Виникли проблеми 🤔</b>");
                    } elseif ($specialData['callbackData'] == 'confirm') {
                        $generator->saveRefillByTrdPointANDLog(
                            ['login' => $userData['tgUser']->login, "areaId" => $userData['tgUser']->area],
                            ["fuelRefill" => $metadata->fuel, "refillType" => $metadata->typeGen],
                            true
                        );
                        return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "<b>Успішно відправлено запит на поповнення</b> " .  $userData['metadata']->fuel . " 👍\n" .
                            "Після підтвердження менеджера, паливо буде нараховано на аптеку.\n");
                    }
                    break;
                }
            case 'confirmCountCanisterOUT': {
                    if ($specialData['callbackData'] == 'denied') {
                        return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "<b>Упс... Виникли проблеми 🤔</b>");
                    } elseif ($specialData['callbackData'] == 'confirm') {
                        $countCanister = $generator->getFuelArea($userData['tgUser']->area)[0]['sum'];
                        $metaCanisterCount = $metadata->count;

                        if ($countCanister == 0 || $countCanister < $metaCanisterCount) {
                            return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "<b>Упс... Виникли проблеми 🤔</b>");
                        }

                        $generator->sendCanisterByTrdPointANDLog([
                            'date' => date('Y-m-d'),
                            'canister' => $metaCanisterCount,
                            'fuel' => 0,
                            'type' => 0,
                            'unit' => $userData['tgUser']->area,
                            'status' => 2
                        ], ["login" => $userData['tgUser']->login], true);
                        return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "Каністри <code>" . $metaCanisterCount  . "</code> були <b>успішно відправленні на повернення 👍</b>");
                    }
                    break;
                }
            case 'confirmCountCanisterIN': {
                    if ($specialData['callbackData'] == 'denied') {
                        return $this->menuMess($userData['chatId'],  $userData['tgUser']->area, "<b>Упс... Виникли проблеми 🤔</b>");
                    } elseif ($specialData['callbackData'] == 'confirm') {
                        $metaCanisterPK = $metadata->canisterPK;
                        $canisterData = $generator->getSpecificCanister('', '', 1, $metaCanisterPK);
                        if (!$canisterData) {
                            return $this->menuMess($userData['chatId'],  $userData['tgUser']->area, "<b>Упс... Виникли проблеми 🤔</b>");
                        }
                        $generator->saveCanisterByTrdPointANDLog(
                            $canisterData[0],
                            ["login" =>  $userData['tgUser']->login],
                            $metaCanisterPK,
                            true
                        );
                        $generator->saveActionGenerator(1, $canisterData[0]);
                        return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "Каністри були <b>успішно отримані 👍</b>");
                    }
                    break;
                }
            case 'confirmGen': {
                    if ($specialData['callbackData'] == 'denied') {
                        return $this->chooseGenerator($userData['tgUser'], $userData['chatId'], null);
                    } elseif ($specialData['callbackData'] == 'confirm') {
                        $metaGeneratorPK = $metadata->generatorPK;
                        $parts = explode('_', $metadata->pokaz);

                        list($startDate1, $startTime1) = sscanf($parts[0], "%[^\(](%[^\)])");
                        $startTime = strtotime($startDate1 . ' ' . $startTime1);

                        list($endDate2, $endTime2) = sscanf($parts[1], "%[^\(](%[^\)])");
                        $endTime = strtotime($endDate2 . ' ' . $endTime2);

                        $genData = $generator->getSpecificGenerator($userData['tgUser']->area, '', $metaGeneratorPK, 1);
                        $workingTime =  floatval(number_format(($endTime - $startTime) / (60 * 60), 1, '.', ''));
                        $consumedFuel = floatval(number_format($workingTime * $genData[0]["coeff"], 1, '.', ''));

                        if (!$genData || $genData[0]["fuel"] - $consumedFuel < 0) {
                            return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "<b>Упс... Виникли проблеми 🤔</b>");
                        }

                        $dataTargetGenerator = $generator->getSpecificGenerator('', '', $metaGeneratorPK);
                        $generator->saveGeneratorPokazANDLog([
                            'date' => date('Y-m-d', $startTime),
                            'year' => date('Y', $startTime),
                            'month' => date('m', $startTime),
                            'day' => date('d', $startTime),
                            'startTime' => date('H:i', $startTime),
                            'endTime' => date('H:i', $endTime),
                            'workingTime' => $workingTime,
                            'consumed' => $consumedFuel,
                            'genId' => $metaGeneratorPK
                        ], [
                            "login" => $userData['tgUser']->login
                        ], $dataTargetGenerator[0], true);
                        $generator->saveActionGenerator(
                            2,
                            [
                                "consumed" => $consumedFuel,
                                "workingTime" => $workingTime,
                                "typeId" => $dataTargetGenerator[0]['genTypeId'],
                                "areaId" => $dataTargetGenerator[0]['genAreaId'],
                            ],
                            [
                                'date' => date('Y-m-d', $startTime),
                                'year' => date('Y', $startTime),
                                'month' => date('m', $startTime),
                                'day' => date('d', $startTime),
                            ]
                        );
                        return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "Показник генератора <code>" . $metadata->pokaz . "</code> був <b>успішно доданий 👍 </b>\n" .
                            "<i>Виберіть тип лічильника</i>");
                    }
                    break;
                }
            case 'confirm': {
                    if ($specialData['callbackData'] == 'denied') {
                        return $this->chooseCounter($userData['tgUser'], $userData['chatId'], null);
                    } elseif ($specialData['callbackData'] == 'confirm') {
                        $last = $userModel->getCounterDate();
                        $metaCounterPK = $metadata->counterPK;

                        // Перевірка перед збереженням даних
                        if (!$search->checkPokaz($metaCounterPK, $last['year'] . "-" . $last['month'] . "-01", $metadata->pokaz)) {
                            return [$this->createTelegramMessage("<b>Упс... Показник лічильника не додано</b>\n" .
                                "Перевірте вказані дані та спробуйте ще раз")];
                        }

                        $savePokaz = $search->addPokazC($metaCounterPK, $metadata->pokaz, $last['year'], $last['month'], true);

                        // Перевірка після збереженням
                        if (!$savePokaz) {
                            return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "Показник лічильникa за <b>" . $last['year'] . "-" . $last['month'] . "-01</b> був <b>доданий раніше</b>\n" .
                                "<b>" . $userData['tgUser']->name . "</b> Виберіть тип лічильника");
                        }

                        $search->recalculationANDLog([
                            "counterPK" => $metaCounterPK,
                            "pokaz" => $metadata->pokaz,
                            "login" => $userData['tgUser']->login
                        ], true);
                        return $this->menuMess($userData['chatId'], $userData['tgUser']->area, "Показник лічильникa " . $metadata->pokaz . " за " . $last['month'] . "." . $last['year'] . " був <b>успішно доданий 👍 </b>\n" .
                            "<i>Виберіть тип лічильника</i>");
                    }
                    break;
                }
        }
    }
}
