<?php

namespace App\Models;

use CodeIgniter\Model;

class User extends Model
{
    protected $DBGroup = 'meters';

    public function auth()
    {

        if (isset($_COOKIE['k_meters_login']) && isset($_COOKIE['k_meters_pw'])) {
            $res = $this->db->query("SELECT * FROM user WHERE (login = '" . $_COOKIE['k_meters_login'] . "') AND (pass = '" . $_COOKIE['k_meters_pw'] . "')")->getResultObject();
            
            if (empty($res)) return false;

            session()->set([
                'isLoggedIn' => true,
                'mLogin' => $res[0]->login,
                'mPass' => $res[0]->pass,
                'usId' => $res[0]->id,
                'usName' => $res[0]->name,
                'usRights' => $res[0]->rights,
                'usArea' => $res[0]->area,
            ]);

            return true;
        }

        return false;
    }

    public function checkUser($login, $password)
    {
        $res = $this->db->query("SELECT * FROM user WHERE login='" . $login . "'")->getResultObject();

        if (empty($res))  return false;

        if ($password == $res[0]->pass) {
            session()->set([
                'mLogin' => $login,
                'mPass' => $res[0]->pass,
                'usId' => $res[0]->id,
                'usName' => $res[0]->name,
                'usRights' => $res[0]->rights,
                'usArea' => $res[0]->area,
            ]);

            if (!isset($_COOKIE['k_meters_login'])) {
                $this->setCookies('k_meters_login', $login);
                $this->setCookies('k_meters_pw', $res[0]->pass);
            }

            return true;
        }

        return false;
    }

    public function changePassword($password)
    {
        try {
            (new Update)->changePW(session('usId'), $password);
            $this->setCookies('k_meters_pw', $password);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function setCookies($key, $value, $domain = '/')
    {
        setcookie($key, $value, time() + 3600 * 24 * 30 * 12, $domain, false, true);
    }

    public function deleteCookies($key = ['k_meters_login', 'k_meters_pw'], $domain = '/')
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $this->deleteCookies($k);
            }
        } else {
            if (isset($_COOKIE[$key])) {
                unset($_COOKIE[$key]);
                setcookie($key, '', time() - 3600, $domain);
            }
        }
    }

    function checkPass($user, $pass)
    {
        return $this->db->query("SELECT login, pass, id, rights FROM user where login='" . $user . "' and pass='" . $pass . "' and rights<>4")->getResultArray();
    }
    function addUserLog($login, $data)
    {
        if ($login != 'meters') $this->db->table('userlog')->insert($data);
        $this->db->query("DELETE FROM userlog WHERE date < '" . date('Y-m-d', strtotime('-40 days')) . "'");
    }
    function getNameById($id)
    {
        return $this->db->query("SELECT login, name, pass, id, area FROM user where id='" . $id . "'")->getRowArray();
    }

    function getCounterDate()
    {
        $last = mktime(0, 0, 0, date('m'), 1, date('Y'));
        return [
            'month' => date('m', $last),
            'year' => date('Y', $last)
        ];
    }

    function getPrevLastCounterDate()
    {
        $prevLast = mktime(0, 0, 0, date('m'), 0, date('Y'));
        return [
            'month' => date('m', $prevLast),
            'year' => date('Y', $prevLast)
        ];
    }

    function findUserByChatId($chatId)
    {
        return $this->db->query("SELECT * FROM user WHERE telegramChatId = '" . $chatId . "'")->getFirstRow();
    }

    function findUserByLogin($login)
    {
        return $this->db->query("SELECT * FROM user WHERE login = '" . $login . "'")->getFirstRow();
    }

    function insertTelegramDataByLogin($login, $telegramChatId, $telegramState)
    {
        $this->db->query("UPDATE user SET telegramChatId = '$telegramChatId', telegramState = '$telegramState' WHERE login = '$login'");
    }

    function removeTelegramDataByChatId($chatId, $state)
    {
        $this->db->query("UPDATE user SET telegramState = '" . $state . "', telegramChatId = '', telegramMetadata = '' WHERE telegramChatId = '$chatId'");
    }

    function insertTelegramDataByChatId($chatId, $telegramState, $metadata = null)
    {
        if ($metadata != null) $this->db->query("UPDATE user SET telegramState = '$telegramState', telegramMetadata = '" . json_encode($metadata) . "' WHERE telegramChatId = '$chatId'");
        else $this->db->query("UPDATE user SET telegramState = '" . $telegramState . "' WHERE telegramChatId = '" . $chatId . "'");
    }
}
