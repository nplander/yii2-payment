<?php

namespace app\components;

/**
 * Виджет подключения платежных форм
 * Идея следующая: В виджет подключается объект платёжной системы (реализующий \app\components\PaymentInterface). 
 * Если у объекта задана сумма оплаты (значение $payment->sum), то формируется форма, готовая к отправке.
 * Если сумма не задана, то отображается форма с вводом суммы, после её отправки, формируется форма в которой 
 * эта сумма указана и если autoSubmit == true, то она отправляется автоматически. 
 */
class Payment extends \yii\base\Widget
{
	/*
	 * HTML-код элементов формы
	 */
	const HTML_INPUT = '<input class="{class}" name="{name}" value="{value}">';
	const HTML_BUTTON = '<button class="{class}" name="{name}" type="submit">{value}</button>';

	/**
	 * @var \app\components\PaymentInterface 
	 */
	public $payment = null;

	/**
	 * @var boolean Автоматическая отправка формы 
	 */	
	public $autoSubmit = false;
	
	/**
	 * @var string Шаблон вывода формы для оплаты произвольной суммы. Содержит {input} и {submit} 
	 */
	public $template = '{input} {submit}';
	
	/**
	 * @var array Дополнительные параметры вывода 
	 */
	public $options = [];
	
	
	/**
	 * Возвращает форму оплаты в случае если сумма определена
	 * Возвращаетs форму ввода суммы, если та не задана
	 * 
	 * @return string HTML-код формы
	 */
	public function run()
	{
		if ($this->payment->getSum()) {
			return $this->paymentForm;
		}
		return $this->valueForm;
	}
	
	/**
	 * Форма оплаты
	 * 
	 * @return string HTML-код формы 
	 */
	public function getPaymentForm()
	{
		$formHtml = $this->payment->form;
		if ($this->autoSubmit && \Yii::$app->request->post('auto_submit', false)) {
			$formHtml .= $this->autoSubmitJs();
		}
		return $formHtml;
	}
	
	/**
	 * JS-код для автоматической отправки формы
	 * 
	 * @return string JS-код для автоматической отправки формы
	 */
	private function autoSubmitJs()
	{
		return '<script>document.getElementById("'.$this->payment->formId.'").submit();</script>';
	}
	
	/**
	 * Форма ввода произвольной суммы
	 * 
	 * @return string HTML-код формы 
	 */
	public function getValueForm()
	{
		$this->options = array_replace_recursive($this->defaultOptions, $this->options);
		return '<form method="POST">' .
					$this->csrfField() .
					$this->autoSubmitField() .
					$this->valueFormFields . 
				'</form>';
	}
	
	/**
	 * Поля формы для ввода произвольной суммы
	 * 
	 * @return string
	 */
	public function getValueFormFields()
	{
		return	str_replace(
					[
						'{input}', 
						'{submit}'
					],
					[
						str_replace(
							[
								'{value}',
								'{name}',
								'{class}'
							],
							[
								$this->options['input']['value'],
								$this->payment->sumFieldName,
								$this->options['input']['class']
							],
							self::HTML_INPUT
						), 
						str_replace(
							[
								'{value}',
								'{name}',
								'{class}'
							],
							[
								$this->options['submit']['value'],
								'submit',
								$this->options['submit']['class']
							],
							self::HTML_BUTTON
						)
					],
					$this->template
				);
	}
	
	/**
	 * @return string
	 */
	private function csrfField() 
	{
		return  '<input type="hidden" name="' .
				\Yii::$app->request->csrfParam . '" value="' .
				\Yii::$app->request->csrfToken . '" />';
	}
	
	/**
	 * @return string
	 */
	private function autoSubmitField()
	{
		return '<input type="hidden" name="auto_submit" value="'.($this->autoSubmit ? 1 : 0).'">';
	}
	
	/**
	 * @return array Настройки по-умолчанию
	 */
	public function getDefaultOptions()
	{
		return [
			'input' => [
				'class' => 'form-control',
				'value' => 200
			],
			'submit' => [
				'class' => 'btn btn-primary',
				'value' => 'Пополнить'
			]
		];
	}
	
}