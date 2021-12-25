<?php

namespace PaySystem;

use Bitrix\Main\SystemException;
use Bitrix\Sale\Order;

class Mobile
{
    private const LOGIN_MERCHANT = '*****';

    private const TEST_LOGIN = '*****';
    private const TEST_PASSWORD = '*****';
    private const TEST_RETURN_URL = 'https://site.test.ru';

    private const PROD_LOGIN = '*****';
    private const PROD_PASSWORD = '*****';
    private const PROD_RETURN_URL = 'https://site.ru';

    private const DEV_URL = 'https://3dsec.sberbank.ru';
    private const PROD_URL = 'https://securepayments.sberbank.ru';

    private $Authorization,
            $login,
            $password,
            $sberUrl,
            $returnUrl;

    public function __construct()
    {
        $this->Authorization = new Authorization();
        // Здесь напишите свою проверку для текущей площадки
        $isDev = true;
        $this->login = $isDev ? self::TEST_LOGIN : self::PROD_LOGIN;
        $this->password = $isDev ? self::TEST_PASSWORD : self::PROD_PASSWORD;
        $this->sberUrl = $isDev ? self::DEV_URL : self::PROD_URL;
        $this->returnUrl = $isDev ? self::TEST_RETURN_URL : self::PROD_RETURN_URL;
    }

    public function google_pay($params)
    {

        if (!empty($params['token'])) {
            // Метод, который возвращает идентификатор пользователя по его token
            // и сохраняет в переменную $userId 
        } else {
            throw new SystemException('Не введен токен пользователя.');
        }

        if (!empty($params['paymentToken'])) {
            // Принимаем paymentData.paymentMethodData.tokenizationData.token;
            $paymentToken = $params['paymentToken'];
        } else {
            throw new SystemException('Не введен токен платежного сервиса.');
        }

        if (!empty($params['orderNumber'])) {
            $order = Order::load(trim($params['orderNumber']));
            $orderNumber = $order->getId();
            $orderPrice = $order->getPrice();

            if (empty($order) || $order->getUserId() != $userId) {
                throw new SystemException('Заказ с указанном номером не найден.');
            }

            if ($order->isPaid()) {
                throw new SystemException('Заказ уже оплачен.');
            }

            if ($order->isCanceled()) {
                throw new SystemException('Заказ отменен и не может быть оплачен.');
            }
        } else {
            throw new SystemException('Не введен номер заказа.');
        }

        $json = json_encode([
            'merchant' => self::LOGIN_MERCHANT,
            'orderNumber' => $orderNumber . rand(),
            'paymentToken' => $paymentToken,
            'language' => 'RU',
            'preAuth' => false,
            'amount' => (int)($orderPrice * 100),
            'returnUrl' => $this->returnUrl
        ]);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->sberUrl . '/payment/google/payment.do',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json;charset=UTF-8"],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response);

        if ($response->success == 'true') {
            $status = $this->get_order_status($orderNumber, $response->data->orderId);
            if ($status['actionCode'] == 0) {
                $paymentCollection = $order->getPaymentCollection();
                $payment = $paymentCollection[0];
                $payment->setPaid('Y');

                $order->setField('STATUS_ID', 'RZ');
                $order->save();
            } else {
                throw new SystemException($status['actionCodeDescription']);
            }
        } else {
            throw new SystemException($response->error->message);
        }

        return json_encode([
            'result' => 'success',
        ]);
    }

    public function apple_pay($params)
    {

        if (!empty($params['token'])) {
            // Метод, который возвращает идентификатор пользователя по его token
            // и сохраняет в переменную $userId 
        } else {
            throw new SystemException('Не введен токен пользователя.');
        }

        if (!empty($params['paymentToken'])) {
            // Принимаем paymentData
            $paymentToken = $params['paymentToken'];
        } else {
            throw new SystemException('Не введен токен платежного сервиса.');
        }

        if (!empty($params['orderNumber'])) {
            $order = Order::load(trim($params['orderNumber']));
            $orderNumber = $order->getId();

            if (empty($order) || $order->getUserId() != $userId) {
                throw new SystemException('Заказ с указанном номером не найден.');
            }

            if ($order->isPaid()) {
                throw new SystemException('Заказ уже оплачен.');
            }

            if ($order->isCanceled()) {
                throw new SystemException('Заказ отменен и не может быть оплачен.');
            }
        } else {
            throw new SystemException('Не введен номер заказа.');
        }

        $json = json_encode(
            [
                'merchant' => self::LOGIN_MERCHANT,
                'orderNumber' => $orderNumber . rand(),
                'paymentToken' => $paymentToken,
                'language' => 'RU',
                'preAuth' => false,
            ]
            , JSON_UNESCAPED_UNICODE);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->sberUrl . '/payment/applepay/payment.do',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json;charset=UTF-8"],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response);

        if ($response->success == 'true') {
            $status = $this->get_order_status($orderNumber, $response->data->orderId);
            if ($status['actionCode'] == 0) {
                $paymentCollection = $order->getPaymentCollection();
                $payment = $paymentCollection[0];
                $payment->setPaid('Y');
                $order->save();
            } else {
                throw new SystemException($status['actionCodeDescription']);
            }
        } else {
            throw new SystemException($response->error->message);
        }

        return json_encode([
            'result' => 'success',
        ]);
    }

    private function get_order_status($params)
    {

        $fields = 'userName=' . $this->login . '&password=' . $this->password;
        $fields .= '&orderNumber=' . $params['orderNumber'];
        if (!empty($params['orderId'])) {
            $fields .= '&orderId=' . $params['orderId'];
        }
        $fields .= '&language=ru';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->sberUrl . '/payment/rest/getOrderStatusExtended.do',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded;charset=UTF-8"],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response, true);
    }

}