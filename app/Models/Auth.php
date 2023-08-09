<?php
namespace App\Models;
include_once FCPATH . '../vendor/autoload.php';

use CodeIgniter\Model;


class Auth extends Model {

    public function auth()
    {
        $response = array(
            'status' => 200,
            'timestamp' => time(),
            'error' => null
        );

        $bearer = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : (isset($_REQUEST['token']) ? (string)$_REQUEST['token'] : '');

        $bearer = str_replace('Bearer ', '', $bearer);

        if ($bearer == '') {

            $response = array(
                'status' => 400,
                'timestamp' => time(),
                'error' => 'Token not found in request'
            );

        } else {

            try {
                $key_array = json_decode(file_get_contents('https://auth.konex.com.ua/publicKey'), true);
                $public_key = isset($key_array['publicKey']) ? $key_array['publicKey'] : '';
                $key = new \Firebase\JWT\Key("-----BEGIN PUBLIC KEY-----\n" . wordwrap($public_key, 64, "\n", true) . "\n-----END PUBLIC KEY-----", 'RS256');
                $token = @\Firebase\JWT\JWT::decode($bearer, $key);
                $ex = false;

            } catch (\InvalidArgumentException $e) {
                $ex = 'InvalidArgumentException - provided key/key-array is empty or malformed.';
            } catch (\DomainException $e) {
                $ex = 'DomainException - provided algorithm is unsupported OR provided key is invalid OR unknown error thrown in openSSL or libsodium OR libsodium is required but not available.';
            } catch (\SignatureInvalidException $e) {
                $ex = 'SignatureInvalidException - provided JWT signature verification failed.';
            } catch (\BeforeValidException $e) {
                $ex = 'BeforeValidException - provided JWT is trying to be used before "nbf" claim OR provided JWT is trying to be used before "iat" claim.';
            } catch (\ExpiredException $e) {
                $ex = 'ExpiredException - provided JWT is trying to be used after "exp" claim.';
            } catch (\UnexpectedValueException $e) {
                $ex = 'UnexpectedValueException - provided JWT is malformed OR provided JWT is missing an algorithm / using an unsupported algorithm OR provided JWT algorithm does not match provided key OR provided key ID in key/key-array is empty or invalid.';
            }

            if ($ex) {
                $response = array(
                    'status' => 401,
                    'timestamp' => time(),
                    'error' => 'Token verification error: ' . $ex
                );

            } else {

                $now = new \DateTimeImmutable();

                if ($token->exp < $now->getTimestamp() ||
                    $token->iat > $now->getTimestamp()) {

                    $response = array(
                        'status' => 401,
                        'timestamp' => time(),
                        'error' => 'Token expired'
                    );
                }
            }
        }
        return (object)$response;
    }
}