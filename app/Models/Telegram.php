<?php

namespace App\Models;

use CodeIgniter\Model;

class Telegram extends Model
{

    protected $DBGroup = 'meters';

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
                $this->buttonBuilder([
                    [
                        [
                            "Повернутися назад",
                            "backLogin"
                        ]
                    ]
                ])
            )];
        }

        // Кінець розмови
        if ($textMess == '/logout') {
            if ($userModel->findUserByChatId($chatId) != null) {
                $userModel->removeTelegramDataByChatId($chatId, "");
            }
            return [$this->createTelegramMessage("Ви успішно вийшли із облікового запису\nВведіть новий <b>логін</b>")];
        }

        $tgUserState = $tgUser->telegramState;
        if ($tgUserState == "pass") {
            // Валідація пароля
            if (mb_strlen($textMess) > 20) return [$this->createTelegramMessage(
                "Пароль не повинен перевищувати <u>20</u> <b>символів</b>",
                $this->buttonBuilder([
                    [
                        [
                            "Повернутися назад",
                            "backLogin"
                        ]
                    ]
                ])
            )];

            if ($textMess == $tgUser->pass) {
                $userRights = $tgUser->rights;
                if ($userRights == 3) return $this->menuMess($chatId, $tgUser->area, "<b>" . $tgUser->name . " ви успішно увішли</b>\n<i>Виберіть тип лічильника</i>", true);
                elseif ($userRights == 1 || $userRights == 2 || $userRights == 5) {
                    $userModel->insertTelegramDataByChatId($chatId, "adminMenu");
                    return $this->menuAdminMess(true);
                } else return [$this->createTelegramMessage(
                    "<b>Увага!!!</b> <i>Цей сервіс лише для співробітників</i>",
                    $this->buttonBuilder([
                        [
                            [
                                "Повернутися назад",
                                "backLogin"
                            ]
                        ]
                    ])
                )];
            }
            return [$this->createTelegramMessage(
                "<b>Увага!!!</b> <i>Ви ввели некоректний логін або пароль</i>",
                $this->buttonBuilder([
                    [
                        [
                            "Повернутися назад",
                            "backLogin"
                        ]
                    ]
                ])
            )];
        } elseif ($tgUserState == "addPokaz" && $tgUser->rights == 3) {

            // Валідація введеного показнника лічильники
            if (str_contains($textMess, ' ') || !is_numeric($textMess) || mb_strlen($textMess) > 9) {
                return [$this->createTelegramMessage(
                    "<b>Були введені некоректні дані лічильники</b>\nПеревірте вказані показники та спробуйте ще раз",
                    $this->buttonBuilder([
                        [
                            [
                                "Повернутися до меню",
                                "backMenu"
                            ]
                        ]
                    ])
                )];
            }
            $last = $userModel->getCounterDate();
            $metadata = json_decode($search->getMetadataByChatId($chatId)->telegramMetadata);
            $metaCounterPK = $metadata->counterPK;

            if ($search->checkPokaz($metaCounterPK, $last['year'] . "-" . $last['month'] . "-01", $textMess)) {
                $userModel->insertTelegramDataByChatId(
                    $chatId,
                    "confirm",
                    ["typeCounter" => $metadata->typeCounter, "counterPK" => $metaCounterPK, "pokaz" => $textMess]
                );
                return [$this->createTelegramMessage("<b>Для збереження даних</b>\nПідтвердіть чи вказанні привильно данні", $this->buttonBuilder([[
                    ["Підтвержую", "confirm"],
                    ["Допущенна помилка", "denied"]
                ]]))];
            }
            return [$this->createTelegramMessage(
                "<b>Упс... Показник лічильника не додано</b>\nПеревірте вказані показники та спробуйте ще раз",
                $this->buttonBuilder([
                    [
                        [
                            "Повернутися до меню",
                            "backMenu"
                        ]
                    ]
                ])
            )];
        } elseif ($tgUserState == "addPokazGen" && $tgUser->rights == 3) {
            try {
                if (preg_match('/[а-яА-Яa-zA-Z]/u', $textMess)) {
                    return [$this->createTelegramMessage(
                        "<b>Були введені некоректні дані генератора</b>\nПеревірте вказані показники та спробуйте ще раз",
                        $this->buttonBuilder([
                            [
                                [
                                    "Повернутися до меню",
                                    "backMenu"
                                ]
                            ]
                        ])
                    )];
                }
                $parts = explode('_', $textMess);

                list($startDate1, $startTime1) = sscanf($parts[0], "%[^\(](%[^\)])");
                $startTime = strtotime($startDate1 . ' ' . $startTime1);

                list($endDate2, $endTime2) = sscanf($parts[1], "%[^\(](%[^\)])");
                $endTime = strtotime($endDate2 . ' ' . $endTime2);

                if ($startTime >= $endTime) {
                    return [$this->createTelegramMessage(
                        "<b>Були введені некоректні дані генератора</b>\nПеревірте вказані показники та спробуйте ще раз",
                        $this->buttonBuilder([
                            [
                                [
                                    "Повернутися до меню",
                                    "backMenu"
                                ]
                            ]
                        ])
                    )];
                }

                $metadata = json_decode($search->getMetadataByChatId($chatId)->telegramMetadata);
                $metaGeneratorPK = $metadata->generatorPK;

                $genData = $generator->findActiveGenerators($tgUser->area, $metaGeneratorPK);
                $workingTime = number_format(($endTime - $startTime) / (60 * 60), 1, '.', '');

                if (!$genData || $genData[0]["fuel"] - floatval($workingTime * $genData[0]["coeff"]) < 0) {
                    return [$this->createTelegramMessage(
                        "<b>Були введені некоректні дані генератора</b>\nПеревірте вказані показники та спробуйте ще раз",
                        $this->buttonBuilder([
                            [
                                [
                                    "Повернутися до меню",
                                    "backMenu"
                                ]
                            ]
                        ])
                    )];
                }

                $userModel->insertTelegramDataByChatId(
                    $chatId,
                    "confirmGen",
                    ["generatorPK" => $metaGeneratorPK, "pokaz" => $textMess]
                );
                return [$this->createTelegramMessage(
                    "<b>Для збереження даних</b>\nПідтвердіть чи вказанні привильно данні\n<b>Генератор працював:</b> " . $workingTime . " годин?",
                    $this->buttonBuilder([[
                        ["Підтвержую", "confirm"],
                        ["Допущенна помилка", "denied"]
                    ]])
                )];
            } catch (Exception $e) {
                return [$this->createTelegramMessage(
                    "<b>Упс... Показник генератора не додано</b>\nПеревірте вказані показники та спробуйте ще раз",
                    $this->buttonBuilder([
                        [
                            [
                                "Повернутися до меню",
                                "backMenu"
                            ]
                        ]
                    ])
                )];
            }
        }

        // Помилка користувачу по конкретній ролі
        return [$this->createTelegramMessage(
            "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
            ($tgUser->rights == 3) ? $this->buttonBuilder([
                [
                    [
                        "Повернутися до меню",
                        "backMenu"
                    ]
                ]
            ]) : $this->buttonBuilder([
                [
                    [
                        "Повернутися до списка",
                        "adminMenu"
                    ]
                ]
            ])
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
                $this->buttonBuilder([
                    [
                        [
                            "Повернутися назад на стадію введеня логіна",
                            "backLogin"
                        ]
                    ]
                ])
            )];
        }

        // Дії адміна
        if ($tgUser->rights == 1 || $tgUser->rights == 2 || $tgUser->rights == 5) {
            if ($userTgState == 'adminMenu' && $callbackData == 'adminMenu') return $this->menuAdminMess();
            else return [$this->createTelegramMessage(
                "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
                $this->buttonBuilder([
                    [
                        [
                            "Повернутися до списка",
                            "adminMenu"
                        ]
                    ]
                ])
            )];
        }

        // Дії звичайного користувача
        if ($tgUser->rights == 3) {
            if ($callbackData == 'backMenu') return $this->menuMess($chatId, $tgUser->area, "<b>" . $tgUser->name . "</b> виберіть тип лічильника");
            elseif ($userTgState == 'menu') {
                $respMessage = [];
                if ($callbackData == 'generators') {
                    $generators = $generator->findActiveGenerators($tgUser->area);

                    if (empty($generators)) return [$this->createTelegramMessage("Відсутні генератори в данному підрозділі 😎")];

                    $userModel->insertTelegramDataByChatId($chatId, 'chooseGenerator');
                    array_push($respMessage, $this->createTelegramMessage("<b>Список генераторів</b>"));
                    foreach ($generators as $generator) {
                        array_push(
                            $respMessage,
                            $this->createTelegramMessage(
                                "<b>Щоб вибрати генератор №</b> <u>" . $generator['serialNum'] . "</u>\nнатисніть на відповідну кнопку\n" .
                                    "<b>Назва:</b> <i>" . $generator['name'] . "</i>\n" .
                                    "<b>Тип:</b> <i>" . $generator['type'] . "</i>\n",
                                $this->buttonBuilder([
                                    [
                                        [
                                            "№ " . $generator['serialNum'],
                                            $generator['id']
                                        ]
                                    ]
                                ])
                            )
                        );
                    }
                } elseif ($callbackData == 'canisters') {
                    $specificCanister = $generator->getSpecificCanister($tgUser->area, '', 1, '');

                    if (count($specificCanister) == 0) {
                        return [$this->createTelegramMessage("Відсутні каністри для отримання 😎")];
                    }

                    $userModel->insertTelegramDataByChatId($chatId, 'chooseCanister');
                    array_push($respMessage, $this->createTelegramMessage("<b>Список каністр</b>"));

                    foreach ($specificCanister as $canister) {
                        array_push(
                            $respMessage,
                            $this->createTelegramMessage(
                                "<b>Щоб вибрати каністри №</b> <u>" . $canister['id'] . "</u>\nнатисніть на відповідну кнопку\n" .
                                    "<b>Палива:</b> <i>" . $canister['fuel'] . "</i>\n" .
                                    "<b>Кількість:</b> <i>" . $canister['canister'] . "</i>\n" .
                                    "<b>Тип:</b> <i>" . $canister['type'] . "</i>\n",
                                $this->buttonBuilder([
                                    [
                                        [
                                            "№ " . $canister['id'],
                                            $canister['id']
                                        ]
                                    ]
                                ])
                            )
                        );
                    }
                } elseif (is_numeric($callbackData)) {
                    $counters = $search->findCountersNotFilled($tgUser->area, $callbackData, null);

                    if (empty($counters)) return [$this->createTelegramMessage("Показники усім лічильникам були вказані\n<b>Ви молодці😎</b>")];

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
                                "<b>Щоб вибрати лічильниик №</b> <u>" . $counter['counterId'] . "</u>\nнатисніть на відповідну кнопку\n<b>Коментар:</b> <i>" . $counter['counterName'] . "</i>",
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
            } elseif ($userTgState == 'chooseCanister' && is_numeric($callbackData)) {
                return $this->chooseCanister($tgUser, $chatId, $callbackData);
            } elseif ($userTgState == 'confirm') {
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
                        return $this->menuMess($chatId, $tgUser->area, "Показник лічильникa за <b>" . $last['year'] . "-" . $last['month'] . "-01</b> був <b>доданий раніше</b>\n<b>" . $tgUser->name . "</b> Виберіть тип лічильника");
                    }

                    $search->recalculation($metaCounterPK);
                    $userModel->addUserLog(
                        $tgUser->login,
                        ['login' => $tgUser->login, 'message' => "Додав показник = " . $metadata->pokaz . " лічильнику = " . $search->getCounterByCounterPK($metaCounterPK)->counterId . " (telegram)"]
                    );
                    return $this->menuMess($chatId, $tgUser->area, "Показник лічильникa " . $metadata->pokaz . " за " . $last['month'] . "." . $last['year'] . " був <b>успішно додані 👍 </b>\n<i>Виберіть тип лічильника</i>");
                }
            } elseif ($userTgState == 'confirmGen') {
                $metadata = json_decode($search->getMetadataByChatId($chatId)->telegramMetadata);

                if ($callbackData == 'denied') return $this->chooseGenerator($tgUser, $chatId, null);
                elseif ($callbackData == 'confirm') {
                    $metaGeneratorPK = $metadata->generatorPK;
                    $parts = explode('_', $metadata->pokaz);

                    list($startDate1, $startTime1) = sscanf($parts[0], "%[^\(](%[^\)])");
                    $startTime = strtotime($startDate1 . ' ' . $startTime1);

                    list($endDate2, $endTime2) = sscanf($parts[1], "%[^\(](%[^\)])");
                    $endTime = strtotime($endDate2 . ' ' . $endTime2);

                    $genData = $generator->findActiveGenerators($tgUser->area, $metaGeneratorPK);
                    $workingTime =  floatval(number_format(($endTime - $startTime) / (60 * 60), 1, '.', ''));
                    $consumedFuel = floatval(number_format($workingTime * $genData[0]["coeff"], 1, '.', ''));

                    if (!$genData || $genData[0]["fuel"] - $consumedFuel < 0) {
                        return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>\n<i>" . $tgUser->name . "</i> виберіть генератор");
                    }

                    $dataTargetGenerator = $generator->getSpecificGenerator('', '', $metaGeneratorPK);
                    $consumed = $dataTargetGenerator[0]['fuel'] - $consumedFuel;

                    $userModel->addUserLog(
                        $tgUser->login,
                        [
                            'login' => $tgUser->login,
                            'message' => "Подав час роботи = " . $workingTime . " годин, генератору = " . $dataTargetGenerator[0]['serialNum'] . " (telegram)"
                        ]
                    );
                    $this->db->table('genaratorPokaz')->insert([
                        'date' => date('Y-m-d', $startTime),
                        'year' => date('Y', $startTime),
                        'month' => date('m', $startTime),
                        'day' => date('d', $startTime),
                        'startTime' => date('H:i', $startTime),
                        'endTime' => date('H:i', $endTime),
                        'workingTime' => $workingTime,
                        'consumed' => $consumedFuel,
                        'genId' => $metaGeneratorPK
                    ]);
                    $this->db->table('fuelArea')->update(
                        ['fuel' => $consumed],
                        ['areaId' => $dataTargetGenerator[0]['genAreaId'], 'type' => $dataTargetGenerator[0]['genTypeId']]
                    );
                    return $this->menuMess($chatId, $tgUser->area, "Показник генератора <code>" . $metadata->pokaz . "</code> був <b>успішно додані 👍 </b>\n<i>Виберіть тип лічильника</i>");
                }
            } elseif ($userTgState == 'confirmCountCanister') {
                $metadata = json_decode($search->getMetadataByChatId($chatId)->telegramMetadata);

                if ($callbackData == 'denied') return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>\n<i>" . $tgUser->name . "</i> виберіть каністри");
                elseif ($callbackData == 'confirm') {
                    $metaCanisterPK = $metadata->canisterPK;
                    $canisterData = $generator->getSpecificCanister('', '', '', $metaCanisterPK);
                    if (!$canisterData || $canisterData == 0) {
                        return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>\n<i>" . $tgUser->name . "</i> виберіть каністри");
                    }
                    $this->db->query('UPDATE trackingCanister SET status = 2 WHERE id = ' . $metaCanisterPK);
                    $fuelArea = $this->db->query('SELECT * FROM fuelArea WHERE areaId = ' . $canisterData[0]['areaId'] . ' AND type = ' . $canisterData[0]['typeId'])->getResultArray();
                    if ($fuelArea) {
                        $this->db->query('UPDATE fuelArea SET fuel = ROUND(fuel + ' . $canisterData[0]['fuel'] . ', 2), canister = canister + ' . $canisterData[0]['canister'] . '
                                            WHERE type = ' . $canisterData[0]['typeId'] . ' AND areaId = ' . $canisterData[0]['areaId']);
                    } else {
                        $this->db->query('INSERT INTO fuelArea(fuel, canister, areaId, type) 
                                            VALUES (' . $canisterData[0]['fuel'] . ', ' . $canisterData[0]['canister'] . ', ' . $canisterData[0]['areaId'] . ', ' . $canisterData[0]['typeId'] . ')');
                    }
                    $userModel->addUserLog(
                        $tgUser->login,
                        [
                            'login' => $tgUser->login,
                            'message' => "Отриманно каністри №" . $metaCanisterPK . ", палива = " . $canisterData[0]['fuel'] . ", каністр = " . $canisterData[0]['canister'] . " (telegram)"
                        ]
                    );
                    return $this->menuMess($chatId, $tgUser->area, "Каністри №<code>" . $metaCanisterPK . "</code> були <b>успішно отримані 👍 </b>\n<i>Виберіть тип лічильника</i>");
                }
            } else return [$this->createTelegramMessage(
                "<b>Упс... Ви не маєте права на дану дію 🤔</b>",
                $this->buttonBuilder([
                    [
                        [
                            "Повернутися до меню",
                            "backMenu"
                        ]
                    ]
                ])
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
                return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>\n<i>" . $tgUser->name . "</i> виберіть тип лічильника");
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
            if (!$generator->findActiveGenerators($tgUser->area, $generatorPK)) {
                return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>\n<i>" . $tgUser->name . "</i> виберіть генератор");
            }
            return $this->addPokazGenMess($chatId, $tgUser, $generatorPK);
        }
        return $this->addPokazGenMess($chatId, $tgUser, $metadata->generatorPK);
    }

    private function chooseCanister($tgUser, $chatId, $canisterPK = null)
    {
        $generator = new Generator();
        $metadata = json_decode($tgUser->telegramMetadata);
        if ($canisterPK != null) {
            if ($generator->getSpecificCanister($tgUser->area, '', 1, '') == 0) {
                return $this->menuMess($chatId, $tgUser->area, "<b>Упс... Виникли проблеми 🤔</b>\n<i>" . $tgUser->name . "</i> виберіть каністри");
            }
            return $this->addCountСanisterMess($chatId, $tgUser, $canisterPK);
        }
        return $this->addCountСanisterMess($chatId, $tgUser, $metadata->canisterPK);
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
            $this->buttonBuilder([
                [
                    [
                        "Повернутися до меню",
                        "backMenu"
                    ]
                ]
            ])
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
        $dataGen = $generator->findActiveGenerators($tgUser->area, $generatorPK);
        date_default_timezone_set('Europe/Kiev');
        $currentDateTime = date('d.m.Y(H:i)');
        $twoHoursAgo = date('d.m.Y(H:i)', strtotime('-2 hours'));
        return [$this->createTelegramMessage(
            "<b>Введіть дані роботи (початок-кінець) генератору №</b> <u>" . $dataGen[0]["serialNum"] . "</u>\n" .
                "<b>Назва:</b> <i>" . $dataGen[0]["name"] . "</i>\n" .
                "<b>Тип:</b> <i>" . $dataGen[0]["type"] . "</i>\n" .
                "<b>Палива:</b> <i>" . $dataGen[0]["fuel"] . " л.</i>\n" .
                "<b>Каністр:</b> <i>" . $dataGen[0]["canister"] . " од.</i>\n" .
                "<b>Прогнозований час роботи генератора: ≈ </b> <i>" . number_format($dataGen[0]["fuel"] / $dataGen[0]["coeff"], 1, '.', '') . " годин</i>\n" .
                "<b>ПРИКЛАД:</b> <code>" . $twoHoursAgo . '_' . $currentDateTime . "</code>\n",
            $this->buttonBuilder([
                [
                    [
                        "Повернутися до меню",
                        "backMenu"
                    ]
                ]
            ])
        )];
    }

    private function addCountСanisterMess($chatId, $tgUser, $canisterPK)
    {
        $userModel = new User();
        $generator = new Generator();
        $canister = $generator->getSpecificCanister($tgUser->area, '', 1, $canisterPK);
        $userModel->insertTelegramDataByChatId(
            $chatId,
            'confirmCountCanister',
            ["canisterPK" => $canisterPK]
        );

        return [$this->createTelegramMessage(
            "<b>Отримані каністри №</b> <u>" . $canister[0]["id"] . "</u>\nПідтвердіть чи вказанні привильно данні\n" .
                "<b>Дата відправки:</b> <i>" . $canister[0]['date'] . "</i>\n" .
                "<b>Палива:</b> <i>" . $canister[0]['fuel'] . "</i>\n" .
                "<b>Кількість:</b> <i>" . $canister[0]['canister'] . "</i>\n" .
                "<b>Тип:</b> <i>" . $canister[0]['type'] . "</i>\n",
            $this->buttonBuilder([[
                ["Підтвержую", "confirm"],
                ["Допущенна помилка", "denied"]
            ]])
        )];
    }


    private function menuMess($chatId, $areaId, $title, $justLoggined = false)
    {
        $generator = new Generator();
        $specificCanister = $generator->getSpecificCanister($areaId, '', 1, '');
        $activeGenerators = $generator->findActiveGenerators($areaId);
        $userModel = new User();
        $userModel->insertTelegramDataByChatId($chatId, "menu");

        $respMessage = [];
        if ($justLoggined) array_push($respMessage, $this->createTelegramMessage("Щоб вийти із облікового запису напишіть <code>/logout</code>"));

        $genMenu = [];
        if (count($activeGenerators) > 0) {
            array_push($genMenu, [
                "Генератори",
                "generators"
            ]);
        }
        if (count($specificCanister) > 0) {
            array_push($genMenu, [
                "Каністри",
                "canisters"
            ]);
        }

        array_push(
            $respMessage,
            $this->createTelegramMessage(
                $title,
                $this->buttonBuilder([
                    $genMenu,
                    [
                        ["Електрика", "8"],
                        ["Газ", "7"],
                        ["Гаряча вода", "6"],
                        ["Холодна вода", "5"]
                    ]
                ])
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
                    "Показники усім лічильникам були вказані\n<b>Чудово😎</b>",
                    $this->buttonBuilder([
                        [
                            [
                                "Оновити",
                                "adminMenu"
                            ]
                        ]
                    ])
                )
            );
            return $respMessage;
        }

        array_push($respMessage, $this->createTelegramMessage("<b>Список не вказаних показників лічильників</b>"));

        foreach ($counters as $counter) {
            array_push($respMessage, $this->createTelegramMessage("Лічильник № <u>" . $counter['counterId'] . "</u>\nРозташування: <b>" . $counter['unit'] . "</b>\nАдреса: <b>" . $counter['addr'] . "</b>"));
        }
        array_push(
            $respMessage,
            $this->createTelegramMessage(
                "<b>Оновити список не вказних показників лічильників</b>\n<b>Всього налічується</b> <code>" . count($counters) . "</code> <b>лічильників</b>",
                $this->buttonBuilder([
                    [
                        [
                            "Оновити",
                            "adminMenu"
                        ]
                    ]
                ])
            )
        );

        return $respMessage;
    }
}
