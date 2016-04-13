<?php

namespace app\components;

/**
 * Компонент для работы с платежной системой Yandex.Касса
 * Реализует PaymentInterface
 */
class YandexKassa extends \yii\base\Object implements PaymentInterface
{
	/*
	 * Варианты оплаты
	 */
	const PAYMENTTYPE_YANDEXMONEY = 'PC';
	const PAYMENTTYPE_BANKCARD = 'AC';
	const PAYMENTTYPE_MOBILEPHONE = 'MC';
	const PAYMENTTYPE_TERMINALCASH = 'GP';
	const PAYMENTTYPE_WEBMONEY = 'WM';
	const PAYMENTTYPE_SBERBANKONLINE = 'SB';
	const PAYMENTTYPE_MOBILEPOS = 'MP';
	const PAYMENTTYPE_ALPHACLICK = 'AB';
	const PAYMENTTYPE_MASTERPASS = 'MA';
	const PAYMENTTYPE_PROMSVYAZBANK = 'PB';
	const PAYMENTTYPE_QIWI = 'QW';
	const PAYMENTTYPE_CREDIT = 'KV';
	const PAYMENTTYPE_CONFIDENCE = 'QP';
	
	/*
	 * Коды ошибок
	 */
	const RESULTCODE_SUCCESS = 0;
	const RESULTCODE_AUTHERROR = 1;
	const RESULTCODE_DROPREQUEST = 100;
	const RESULTCODE_PARSEERROR = 200;
	
	/*
	 * Обязательные параметры без который формирование формы невозможно
	 */
	
	/**
	 * @var integer Идентификатор магазина, выдается при подключении к Яндекс.Кассе
	 */
	public $shopId = null;

	/**
	 * @var mixed Идентификатор витрины магазина, выдается при подключении к Яндекс.Кассе
	 */
	public $scid = null;
	
	/**
	 * @var float Стоимость заказа
	 */
	public $sum = null;
	
	/**
	 * @var string  Идентификатор плательщика в системе магазина
	 */
	public $customerNumber = null;
	
	/*
	 * Необязательные параметры. Выставлются при необходимости
	 */
	
	/**
	 * @var string Уникальный номер заказа в системе магазина 
	 */
	public $orderNumber;
	
	/**
	 * @var integer Идентификатор товара, выдается при подключении к Яндекс.Кассе 
	 */
	public $shopArticleId;

	/**
	 * @var string URL, на который будет вести ссылка «Вернуться в магазин» со страницы успешного платежа 
	 */
	public $shopSuccessUrl;

	/**
	 * @var string URL, на который будет вести ссылка «Вернуться в магазин» со страницы ошибки платежа 
	 */
	public $shopFailUrl;

	/**
	 * @var string Адрес электронной почты плательщика 
	 */
	public $cps_email;

	/**
	 * @var string Номер мобильного телефона плательщика 
	 */
	public $cps_phone;

	/**
	 * @var string Способ оплаты
	 */
	public $paymentType;
	
	/*
	 * Параметры для покупки в кредит (Задаются только при paymentType == "KV")
	 */
 
	/**
	 * @var array[] Массив покупаемых товаров https://tech.yandex.ru/money/doc/payment-solution/payment-form/payment-form-http-docpage/
	 */
	public $goods = [];
	
	/**
	 * @var array[] список полей класса
	 */
	private $fieldList = [
		'required' => ['shopId','scid', 'sum', 'customerNumber'],
		'optional' => ['orderNumber', 'shopArticleId', 'shopSuccessUrl', 'shopFailUrl', 'cps_email', 'cps_phone', 'paymentType']
	];
	
	/**
	 * @var array Поля, ожидаемые в запросе от Yandex 
	 */
	private $requestFieldList = [
			'action',
            'orderSumAmount',
			'orderSumCurrencyPaycash',
            'orderSumBankPaycash', 
			'shopId',
            'invoiceId',
			'customerNumber'
		];
	
	/**
	 * @var boolean Флаг тестового режима 
	 */
	public $testMode = false;
	
	/**
	 * @var string Пароль магазина
	 */
	public $shopPassword = null;
	
	/**
	 * @var array Пользовательский набор полей формы
	 */
	public $customFields = [];
	
	/**
	 * @var array Настройки формы
	 */
	public $options = [];
	
	/**
	 * @var string Уникальный идентификатор формы 
	 */
	private $uniqueId = null;
	
	/**
	 * @var integer Код последней ошибки  
	 */
	private $lastError = 0;
	
	/**
	 * Возвращает Html-код формы для вставки в документ
	 * 
	 * @return string 
	 */
	public function getForm()
	{
		$this->options = array_merge($this->defaultOptions, $this->options);
		return	'<form action="'.$this->formAction.'" id="'.$this->formId.'" method="POST">'.
					$this->formFields.
					$this->formSubmit.
				'</form>';
	}
	
	/**
	 * Возвращает action формы в зависимости от настроек
	 * 
	 * @return string 
	 */
	public function getFormAction()
	{
		if ($this->testMode) {
			return 'https://demomoney.yandex.ru/eshop.xml';
		} else {
			return 'https://money.yandex.ru/eshop.xml';
		};
	}

	/**
	 * Возвращает Html-код полей формы
	 * В случае отсутствия обязательного параметра кидает \Exception
	 * 
	 * @return string 
	 * @throws \Exception
	 */
	public function getFormFields()
	{
		$fields = array_merge($this->fieldList['required'], $this->fieldList['optional']);
		$html = '';
		
		//Формирование обязательных и необязательных полей
		foreach ($fields as $field) {
			if (property_exists($this, $field) && !empty($this->$field)) {
				$html .= '<input name="'.$field.'" value="'.$this->$field.'" type="hidden" />';
			} elseif (array_search($field, $this->fieldList['required']) !== false) 
				throw new \Exception('Required field YandexKassa::'.$field.' is empty');
		}
		
		//Формирование полей, необходимых для оплаты в кредит
		if ($this->paymentType == self::PAYMENTTYPE_CREDIT) {
			$html .= '<input name="seller_id" value="'.$this->shopId.'" type="hidden" />';
			$idx = 0;
			foreach ($this->goods as $item) {
				$html .= '<input name="category_code_'.$idx.'" value="'.$item['code'].'" type="hidden" />'.
						 '<input name="goods_name_'.$idx.'" value="'.$item['name'].'" type="hidden" />'.
						 '<input name="goods_description_'.$idx.'" value="'.$item['descriptione'].'" type="hidden" />'.
						 '<input name="goods_quantity_'.$idx.'" value="'.$item['quantity'].'" type="hidden" />' .
						 '<input name="goods_cost_'.$idx.'" value="'.$item['cost'].'" type="hidden" />';
				$idx++;
			}
		}
		
		//Формирование набора пользовательских полей
		if (!empty($this->customFields)) {
			foreach ($this->customFields as $customName => $customVal) {
				$html .= '<input name="'.$customName.'" value="'.$customVal.'" type="hidden" />';
			}
		}
		
		return $html;
	}

	/**
	 * Html-код кнопки отправки формы
	 * 
	 * @return string 
	 */
	public function getFormSubmit()
	{
		return	'<button type="submit" class="'.$this->options['class'].'">'.
					$this->options['value'].
				'</button>';
	}
	
	/**
	 * Масссив настроек по-умолчанию
	 * 
	 * @return array 
	 */
	public function getDefaultOptions()
	{
		return [
			'class' => 'btn btn-success',
			'value' => 'Оплатить'
		];
	}
	
	/**
	 * Имя поля, содержащий оплачиваемую сумму
	 * 
	 * @return string 
	 */
	public function getSumFieldName() 
	{
		return 'sum';
	}

	/**
	 * Получение суммы для оплаты
	 * Добавлена для совместимости с getSumFieldName
	 *
	 * @return mixed
	 */
	public function getSumFieldValue() 
	{
		return $this->getSum();
	}
	
	/**
	 * Получение суммы для оплаты
	 * 
	 * @return mixed 
	 */
	public function getSum() 
	{
		return $this->sum;
	}
	
	/**
	 * Возвращает уникальный идентификатор для формы
	 * 
	 * @return string
	 */
	public function getFormId()
	{
		//Сохранение значения для множественного использования
		if (!$this->uniqueId)
			$this->uniqueId = 'yk' . uniqid();
		return $this->uniqueId;
	}
	
	/**
	 * Загрузка параметров в объект
	 * 
	 * @param array $params 
	 * @return \app\components\YandexKassa
	 */
	public function load($params = [])
	{
		$fields = array_merge($this->fieldList['required'], $this->fieldList['optional']);
		foreach ($fields as $field) {
			if (property_exists($this, $field) && isset($params[$field])) { 
				$this->$field = $params[$field];
			}
		}
		return $this;
	}
	
	/**
	 * Проверка корректности заказа. Ответ на запрос Yandex
	 * 
	 * @param array $request массив POST-параметров от Yandex
	 * @return string Строка XML для оправки
	 */
	public function checkOrder($request)
	{
		if (!$this->validateHash($request)) {
			return $this->buildResponse('checkOrder', $request['invoiceId'], self::RESULTCODE_AUTHERROR);
		}
			
		$sum = round($this->sum, 2);
		$requestSum = isset($request['orderSumAmount']) ? round($request['orderSumAmount'], 2) : null;
		if ($sum == $requestSum) {
			return $this->buildResponse('checkOrder', $request['invoiceId'], self::RESULTCODE_SUCCESS);
		}
		return $this->buildResponse('checkOrder', $invoiceId, self::RESULTCODE_DROPREQUEST, 'Неверно указана сумма платежа');	
	}

	/**
	 * Обраотчик уведомления "Средства получены"
	 * 
	 * @param array $request массив POST-параметров от Yandex
	 * @return string Строка XML для оправки
	 */	
	public function paymentAviso($request)
	{
		if (!$this->validateHash($request)) {
			return $this->buildResponse('paymentAviso', $request['invoiceId'], self::RESULTCODE_AUTHERROR);
		}		
		
		return $this->buildResponse('paymentAviso', $request['invoiceId'], self::RESULTCODE_SUCCESS);
	}

	/**
	 * Сверяет вычисленный хэш с пришедшим для проверки корректности параметров 
	 * 
	 * @param array $request массив POST-параметров от Yandex
	 * @return boolean 
	 */
	private function validateHash($request)
	{
		$str = '';
		if ($this->validateRequest($request)) {
			foreach ($this->requestFieldList as $field)
				$str .= $request[$field] . ';';
			$str .= ';' . $this->shopPassword;
			if (strtoupper($str) == strtoupper($request['md5']))
				return true;
		}
		return false;
	}
	
	/**
	 * Проверяет наличие всех необходимых полей в POST-запросе от Yandex
	 * 
	 * @param array $request массив POST-параметров от Yandex
	 * @return boolean
	 */
	private function validateRequest($request)
	{
		foreach ($this->requestFieldList as $field)
			if (!isset($request[$field]))
				return false;
		return true;
	}
	
	/**
	 * Постоение ответа сервису Yandex для получения статуса заказа
	 * 
	 * @param type checkOrder или paymentAviso
	 * @param type Номер заказа
	 * @param type Код ошибки
	 * @param type Сообщение
	 * @return type
	 */
	private function buildResponse($action, $invoiceId, $resultCode = 0, $message = null)
	{
		$this->lastError = $resultCode;
		$date = new \DateTime();
		$formatDate = $date->format("Y-m-d") . "T" . $date->format("H:i:s") . ".000" . $date->format("P"); 
		return	'<?xml version="1.0" encoding="UTF-8"?><'.$action.'Response performedDatetime="' . $performedDatetime .
				'" code="' . $resultCode . '" ' . ($message != null ? 'message="' . $message . '"' : "") . 
				' invoiceId="' . $invoiceId . '" shopId="' . $this->shopId . '"/>';
	}
	
	/**
	 * @return integer Код последней ошибки (0 - ошибок нет)
	 */
	public function getLastError()
	{
		return $this->lastError;
	}
	
	/**
	 * Конструктор для упрощенного использования
	 * 
	 * @param array $config
	 * @return \app\components\YandexKassa
	 */
	public static function create($config = [])
	{
		$component = new YandexKassa($config);
		return $component->load(\Yii::$app->request->post());
	}
}