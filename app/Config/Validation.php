<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\CreditCardRules;
use CodeIgniter\Validation\FileRules;
use CodeIgniter\Validation\FormatRules;
use CodeIgniter\Validation\Rules;
use App\ThirdParty\PasswordRules;

class Validation extends BaseConfig
{
    public $password = [
        'oldPw' => [
            'rules'  => 'required|checkPass',
            'errors' => [
                'required' => 'Увага!!! Cтарий пароль не може бути пустим',
                'checkPass' => 'Увага!!! Ви ввели некоректний старий пароль',
            ]
        ],
        'newPw'  => [
            'rules'  => 'required',
            'errors' => [
                'required' => 'Увага!!! Новий пароль не може бути пустим',
            ]
        ],
        'confPw'  => [
            'rules'  => 'required|matches[newPw]',
            'errors' => [
                'required' => 'Увага!!! Пароль для підтвердження не може бути пустим',
                'matches' => 'Увага!!! Введений пароль для підтвердження не співпав з новим',
            ],
        ],
    ];

    public $login = [
        'log' => [
            'rules'  => 'required',
            'errors' => [
                'required' => 'Увага!!! Логін не може бути пустим',
            ]
        ],
        'pw'  => [
            'rules'  => 'required|checkPass',
            'errors' => [
                'required' => 'Увага!!! Пароль не може бути пустим',
                'checkPass' => 'Увага!!! Ви ввели некоректний логін або пароль',
            ]
        ],
    ];

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var string[]
     */
    public $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
        PasswordRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------
}
