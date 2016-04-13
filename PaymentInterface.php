<?php

namespace app\components;

/**
 * Интерфейс для подклющения поатежных систем. 
 * Необходим для использования вместе с Виджетом Payment 
 */
interface PaymentInterface
{
	/**
	 * Возвращает HTML-код формы оплаты
	 */
	public function getForm();
	
	/**
	 * Возвращает имя свойства в котором содержится сумма оплаты
	 */
	public function getSumFieldName();
	
	/**
	 * Синоним getSum()
	 */
	public function getSumFieldValue();
	
	/**
	 * Возвращает текущее значение суммы
	 */
	public function getSum();
	
	/**
	 * Возвращает уникалынй идентификатор формы оплаты
	 */
	public function getFormId();
}

